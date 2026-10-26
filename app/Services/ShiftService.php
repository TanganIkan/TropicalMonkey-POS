<?php

namespace App\Services;

use App\Exceptions\PosException;
use App\Models\PaymentMethod;
use App\Models\Shift;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Shift kasir. Shift dibuka otomatis (staff saat login, owner saat memakai POS)
 * dan ditutup saat logout. Hitung kas saat tutup shift bersifat opsional.
 * Setiap user hanya boleh punya satu shift terbuka.
 */
class ShiftService
{
    public function __construct(private ActivityLogger $logger) {}

    public function current(User|int $user): ?Shift
    {
        $userId = $user instanceof User ? $user->id : $user;

        return Shift::with('outlet')->where('user_id', $userId)->whereNull('closed_at')->latest('opened_at')->first();
    }

    /**
     * Pastikan user punya shift terbuka di outlet ini. Shift di outlet lain
     * ditutup otomatis; shift di outlet yang sama dilanjutkan.
     */
    public function ensureOpen(User $user, int $outletId): Shift
    {
        $current = $this->current($user);

        if ($current && $current->outlet_id == $outletId) {
            return $current;
        }

        if ($current) {
            $this->close($current, null, 'Ditutup otomatis: login di outlet lain');
        }

        return $this->open($user, $outletId);
    }

    /**
     * @throws PosException
     */
    public function open(User $user, int $outletId, $openingCash = 0): Shift
    {
        if (!is_numeric($openingCash) || $openingCash < 0) {
            throw new PosException('Modal awal kas harus berupa angka dan tidak boleh minus.', 'Modal Tidak Valid');
        }

        if ($open = $this->current($user)) {
            throw new PosException("Anda masih punya shift terbuka di {$open->outlet->name} sejak {$open->opened_at->format('d/m H:i')}. Tutup dulu shift tersebut.", 'Shift Masih Terbuka');
        }

        $shift = Shift::create([
            'user_id' => $user->id,
            'outlet_id' => $outletId,
            'opened_at' => now(),
            'opening_cash' => $openingCash,
        ]);

        $description = $openingCash > 0 ? 'Mulai shift dengan modal Rp ' . number_format($openingCash, 0, ',', '.') : 'Mulai shift';
        $this->logger->log('shift.open', $description, $shift, ['opening_cash' => (float) $openingCash], $user->id, $outletId);

        return $shift;
    }

    /**
     * Rekap shift: jumlah transaksi, penjualan, dan kas yang seharusnya ada di laci.
     */
    public function summary(Shift $shift): array
    {
        $completed = Transaction::where('shift_id', $shift->id)->completed();
        $cashCodes = PaymentMethod::allByCode()->where('is_cash', true)->keys();

        $payments = TransactionPayment::query()
            ->whereIn('transaction_id', (clone $completed)->select('id'))
            ->select('payment_method', DB::raw('SUM(amount + tax_amount) as total'))
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method');

        $cashSales = (float) $payments->only($cashCodes->all())->sum();

        return [
            'transactions' => (clone $completed)->count(),
            'net_sales' => (float) (clone $completed)->sum(DB::raw('subtotal - discount_amount')),
            'discount' => (float) (clone $completed)->sum('discount_amount')
                + (float) DB::table('transaction_items')->whereIn('transaction_id', (clone $completed)->select('id'))->sum('discount_amount'),
            'tax' => (float) (clone $completed)->sum('tax_amount'),
            'voids' => Transaction::where('shift_id', $shift->id)->where('status', Transaction::STATUS_VOID)->count(),
            'by_method' => $payments->map(fn($v) => (float) $v)->all(),
            'cash_sales' => $cashSales,
            'expected_cash' => (float) $shift->opening_cash + $cashSales,
        ];
    }

    /**
     * Tutup shift. $closingCash kosong = ditutup tanpa hitung kas (mis. saat logout).
     *
     * @throws PosException
     */
    public function close(Shift $shift, $closingCash = null, ?string $note = null): Shift
    {
        if (!$shift->isOpen()) {
            throw new PosException('Shift ini sudah ditutup.', 'Shift Sudah Ditutup');
        }

        $counted = $closingCash !== null && $closingCash !== '';

        if ($counted && (!is_numeric($closingCash) || $closingCash < 0)) {
            throw new PosException('Jumlah kas fisik harus berupa angka dan tidak boleh minus.', 'Kas Tidak Valid');
        }

        $expected = $this->summary($shift)['expected_cash'];

        $shift->update([
            'closed_at' => now(),
            'expected_cash' => $expected,
            'closing_cash' => $counted ? $closingCash : null,
            'cash_difference' => $counted ? $closingCash - $expected : null,
            'closing_note' => trim((string) $note) ?: null,
        ]);

        $diff = $shift->cash_difference;
        $diffText = !$counted ? 'tanpa hitung kas' : ($diff == 0 ? 'kas sesuai' : ('selisih Rp ' . number_format($diff, 0, ',', '.')));
        $this->logger->log('shift.close', "Menutup shift ({$diffText})", $shift, [
            'expected_cash' => $expected,
            'closing_cash' => $counted ? (float) $closingCash : null,
            'cash_difference' => $diff,
        ], auth()->id() ?? $shift->user_id, $shift->outlet_id);

        return $shift;
    }
}
