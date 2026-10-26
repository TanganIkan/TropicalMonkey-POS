<?php

namespace App\Services;

use App\Exceptions\CartChangedException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\PosException;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Perhitungan & penyimpanan transaksi POS.
 *
 * Urutan hitung:
 *   item: bruto = harga x qty, dikurangi diskon item  -> subtotal item
 *   subtotal  = jumlah subtotal item
 *   net       = subtotal - diskon total belanja        (pendapatan toko)
 *   pajak     = dihitung per bagian pembayaran sesuai tarif metodenya
 *   total     = net + pajak
 *
 * Diskon: ['type' => 'percent'|'amount', 'value' => angka]
 * Pembayaran: list of ['method' => code, 'amount' => bagian net, 'cash_received' => ?angka]
 */
class CheckoutService
{
    public function __construct(
        private CartService $carts,
        private StockService $stocks,
        private ShiftService $shifts,
        private ActivityLogger $logger,
    ) {}

    /**
     * Hitung seluruh rincian transaksi tanpa menyimpan apa pun.
     *
     * @throws PosException
     */
    public function calculate(array $cart, ?array $orderDiscount, array $payments): array
    {
        $summary = $this->summarize($cart, $orderDiscount);
        $paymentRows = $this->calculatePayments($payments, $summary['net']);
        $tax = array_sum(array_column($paymentRows, 'tax_amount'));

        return $summary + [
            'payments' => $paymentRows,
            'tax' => $tax,
            'total' => $summary['net'] + $tax,
            'paid' => array_sum(array_column($paymentRows, 'amount')),
            'change' => array_sum(array_column($paymentRows, 'change_amount')),
        ];
    }

    /**
     * Rincian belanja & diskon (tanpa pembayaran).
     *
     * @throws PosException bila diskon tidak valid
     */
    public function summarize(array $cart, ?array $orderDiscount): array
    {
        $lines = [];
        foreach ($cart as $key => $item) {
            $gross = $item['price'] * $item['quantity'];
            $discount = $this->discountAmount($item['discount'] ?? null, $gross, "Diskon {$item['name']}");
            $lines[$key] = ['gross' => $gross, 'discount' => $discount, 'subtotal' => $gross - $discount, 'share' => 0];
        }

        $subtotal = array_sum(array_column($lines, 'subtotal'));
        $orderDiscountAmount = $this->discountAmount($orderDiscount, $subtotal, 'Diskon total belanja');
        $net = $subtotal - $orderDiscountAmount;

        // Bagi diskon total belanja ke tiap item secara proporsional (untuk laporan per brand & laba)
        if ($orderDiscountAmount > 0 && $subtotal > 0) {
            $remaining = $orderDiscountAmount;
            $keys = array_keys($lines);
            foreach ($keys as $i => $key) {
                $share = $i === count($keys) - 1
                    ? $remaining
                    : round($orderDiscountAmount * $lines[$key]['subtotal'] / $subtotal);
                $lines[$key]['share'] = $share;
                $remaining -= $share;
            }
        }

        return [
            'lines' => $lines,
            'gross' => array_sum(array_column($lines, 'gross')),
            'item_discount' => array_sum(array_column($lines, 'discount')),
            'subtotal' => $subtotal,
            'order_discount' => $orderDiscountAmount,
            'total_discount' => array_sum(array_column($lines, 'discount')) + $orderDiscountAmount,
            'net' => $net,
        ];
    }

    /**
     * Simpan transaksi dan potong stok. Seluruh proses dibatalkan bila ada
     * satu saja item yang stoknya tidak cukup.
     *
     * @throws PosException
     * @throws InsufficientStockException
     * @throws CartChangedException
     */
    public function process(array $cart, ?int $outletId, int $userId, array $payments, ?array $orderDiscount = null): Transaction
    {
        if (empty($cart)) {
            throw new PosException('Keranjang belanja kosong.', 'Keranjang Kosong');
        }

        if (!$outletId) {
            throw new PosException('Outlet aktif tidak ditemukan. Silakan login ulang dan pilih outlet.', 'Outlet Tidak Valid');
        }

        // Shift dibuka otomatis (mis. owner yang langsung bertransaksi)
        $shift = $this->shifts->ensureOpen(User::findOrFail($userId), $outletId);

        // Harga yang ditagih harus sama dengan harga terbaru yang dilihat kasir
        [$freshCart, $changes] = $this->carts->refresh($cart);
        if ($changes) {
            throw new CartChangedException($freshCart, $changes);
        }

        $calc = $this->calculate($cart, $orderDiscount, $payments);

        $transaction = DB::transaction(function () use ($cart, $calc, $outletId, $userId, $shift) {
            $cashRows = array_filter($calc['payments'], fn($p) => $p['cash_received'] !== null);

            $transaction = Transaction::create([
                'order_id' => $this->nextOrderId(),
                'outlet_id' => $outletId,
                'user_id' => $userId,
                'shift_id' => $shift->id,
                'subtotal' => $calc['subtotal'],
                'discount_amount' => $calc['order_discount'],
                'tax_amount' => $calc['tax'],
                'total' => $calc['total'],
                'payment_method' => count($calc['payments']) === 1 ? $calc['payments'][0]['payment_method'] : 'split',
                'cash_received' => $cashRows ? array_sum(array_column($cashRows, 'cash_received')) : null,
                'change_amount' => $cashRows ? array_sum(array_column($cashRows, 'change_amount')) : null,
            ]);

            foreach ($calc['payments'] as $payment) {
                TransactionPayment::create(['transaction_id' => $transaction->id] + $payment);
            }

            foreach ($cart as $key => $item) {
                [$productId, $variantId] = $this->carts->parseKey($key);
                $line = $calc['lines'][$key];

                $this->stocks->decrease($productId, $variantId, $outletId, $item['quantity'], $transaction->order_id, $item['name']);

                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'product_name' => $item['name'],
                    'price' => $item['price'],
                    'cost_price' => $this->costPrice($productId, $variantId),
                    'quantity' => $item['quantity'],
                    'discount_amount' => $line['discount'],
                    'subtotal' => $line['subtotal'],
                    'order_discount_share' => $line['share'],
                ]);
            }

            return $transaction;
        });

        $discountText = $calc['total_discount'] > 0 ? ' (diskon Rp ' . number_format($calc['total_discount'], 0, ',', '.') . ')' : '';
        $this->logger->log('transaction.create', "Transaksi {$transaction->order_id} Rp " . number_format($transaction->total, 0, ',', '.') . $discountText, $transaction, [
            'total' => (float) $transaction->total,
            'discount' => $calc['total_discount'],
            'items' => array_sum(array_column($cart, 'quantity')),
            'payment_method' => $transaction->payment_method,
        ], $userId, $outletId);

        return $transaction;
    }

    /**
     * Batalkan transaksi: stok dikembalikan, transaksi tidak dihitung di laporan.
     *
     * @throws PosException
     */
    public function void(Transaction $transaction, string $reason): Transaction
    {
        $reason = trim($reason);

        if ($transaction->isVoid()) {
            throw new PosException('Transaksi ini sudah dibatalkan sebelumnya.', 'Sudah Void');
        }

        if ($reason === '') {
            throw new PosException('Alasan void wajib diisi.', 'Alasan Kosong', 'warning');
        }

        DB::transaction(function () use ($transaction, $reason) {
            foreach ($transaction->items as $item) {
                $this->stocks->increase($item->product_id, $item->product_variant_id, $transaction->outlet_id, $item->quantity, $transaction->order_id, 'void', $reason);
            }

            $transaction->update([
                'status' => Transaction::STATUS_VOID,
                'voided_at' => now(),
                'voided_by' => auth()->id(),
                'void_reason' => $reason,
            ]);
        });

        $this->logger->log('transaction.void', "Void transaksi {$transaction->order_id} Rp " . number_format($transaction->total, 0, ',', '.') . " — {$reason}", $transaction, [
            'total' => (float) $transaction->total,
            'reason' => $reason,
        ], outletId: $transaction->outlet_id);

        return $transaction;
    }

    /**
     * Rincian per bagian pembayaran (pajak, kembalian). $strict = false untuk
     * pratinjau di layar: nominal kosong / uang kurang / pembagian belum pas
     * tidak dianggap error.
     *
     * @throws PosException
     */
    public function calculatePayments(array $payments, float $net, bool $strict = true): array
    {
        $methods = PaymentMethod::active()->keyBy('code');

        if (empty($payments)) {
            throw new PosException('Pilih metode pembayaran.', 'Metode Kosong', 'warning');
        }

        // Satu metode: otomatis membayar seluruh belanja
        if (count($payments) === 1) {
            $payments[0]['amount'] = $net;
        }

        $rows = [];
        foreach ($payments as $payment) {
            $method = $methods->get($payment['method'] ?? '');
            if (!$method) {
                throw new PosException('Metode pembayaran tidak dikenali atau sudah nonaktif.', 'Metode Tidak Valid');
            }

            $amount = (float) ($payment['amount'] ?? 0);
            if ($amount <= 0 && !$strict) {
                continue;
            }
            if ($amount <= 0) {
                throw new PosException("Nominal untuk {$method->name} harus lebih dari 0.", 'Nominal Tidak Valid', 'warning');
            }

            $tax = round($amount * $method->tax_rate / 100);
            $due = $amount + $tax;
            $cashReceived = null;
            $change = null;

            if ($method->is_cash) {
                $cashReceived = $payment['cash_received'] ?? null;
                if ($strict && (!is_numeric($cashReceived) || $cashReceived < $due)) {
                    throw new PosException("Nominal uang {$method->name} yang diterima masih kurang dari Rp " . number_format($due, 0, ',', '.') . '.', 'Uang Tidak Cukup!');
                }
                $cashReceived = is_numeric($cashReceived) ? (float) $cashReceived : null;
                $change = $cashReceived !== null ? max(0, $cashReceived - $due) : null;
            }

            $rows[] = [
                'payment_method' => $method->code,
                'amount' => $amount,
                'tax_rate' => $method->tax_rate,
                'tax_amount' => $tax,
                'cash_received' => $cashReceived,
                'change_amount' => $change,
            ];
        }

        $paid = array_sum(array_column($rows, 'amount'));
        if ($strict && abs($paid - $net) > 0.009) {
            throw new PosException(
                'Jumlah pembagian pembayaran (Rp ' . number_format($paid, 0, ',', '.') . ') harus sama dengan total belanja setelah diskon (Rp ' . number_format($net, 0, ',', '.') . ').',
                'Pembayaran Tidak Sesuai',
                'warning',
            );
        }

        return $rows;
    }

    /**
     * @throws PosException
     */
    protected function discountAmount(?array $discount, float $base, string $label): float
    {
        $value = (float) ($discount['value'] ?? 0);

        if (!$discount || $value == 0) {
            return 0;
        }

        if ($value < 0) {
            throw new PosException("{$label} tidak boleh minus.", 'Diskon Tidak Valid', 'warning');
        }

        if (($discount['type'] ?? 'amount') === 'percent') {
            if ($value > 100) {
                throw new PosException("{$label} tidak boleh lebih dari 100%.", 'Diskon Tidak Valid', 'warning');
            }

            return round($base * $value / 100);
        }

        if ($value > $base) {
            throw new PosException("{$label} (Rp " . number_format($value, 0, ',', '.') . ') melebihi harga (Rp ' . number_format($base, 0, ',', '.') . ').', 'Diskon Tidak Valid', 'warning');
        }

        return $value;
    }

    protected function costPrice(int $productId, ?int $variantId): float
    {
        if ($variantId) {
            return ProductVariant::with('product')->find($variantId)?->effectiveCostPrice() ?? 0;
        }

        return (float) Product::whereKey($productId)->value('cost_price');
    }

    /**
     * Nomor order berurutan per hari: TRX-261026-0001, TRX-261026-0002, ...
     * Dipanggil di dalam DB::transaction; baris order hari ini dikunci agar
     * dua kasir tidak mendapat nomor yang sama.
     */
    protected function nextOrderId(): string
    {
        $prefix = 'TRX-' . now()->format('ymd') . '-';

        $last = Transaction::where('order_id', 'like', $prefix . '%')
            ->lockForUpdate()
            ->pluck('order_id')
            ->map(fn($id) => substr($id, strlen($prefix)))
            ->filter(fn($suffix) => ctype_digit($suffix)) // abaikan format lama (acak)
            ->map(fn($suffix) => (int) $suffix)
            ->max() ?? 0;

        return $prefix . str_pad($last + 1, 4, '0', STR_PAD_LEFT);
    }
}
