<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Exceptions\CartChangedException;
use App\Exceptions\PosException;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Services\CartService;
use App\Services\CheckoutService;

new #[Layout('components.layouts.app')] class extends Component {
    public $cart = [];

    // Diskon per item: [cartKey => ['type' => 'amount'|'percent', 'value' => angka]]
    public $itemDiscounts = [];

    // Diskon total belanja
    public $orderDiscountType = 'amount';
    public $orderDiscountValue = null;

    // Pembayaran tunggal
    public $paymentMethod = 'tunai';
    public $cashReceived = null;

    // Split payment: list of ['method', 'amount', 'cash_received']
    public $splitMode = false;
    public $payments = [];

    public function mount(CartService $carts)
    {
        [$this->cart, $changes] = $carts->refresh(session()->get('pos_active_cart', []));
        session(['pos_active_cart' => $this->cart]);

        if (empty($this->cart)) {
            $this->redirect('/pos', navigate: true);
            return;
        }

        foreach ($this->cart as $key => $item) {
            $this->itemDiscounts[$key] = [
                'type' => $item['discount']['type'] ?? 'amount',
                'value' => $item['discount']['value'] ?? null,
            ];
        }

        $this->paymentMethod = $this->methods->first()?->code ?? 'tunai';

        if ($changes) {
            $this->dispatch('notify', ['title' => 'Keranjang Diperbarui', 'text' => implode(' ', $changes), 'icon' => 'info']);
        }
    }

    public function getOutletProperty()
    {
        return Outlet::find(session('current_outlet_id'));
    }

    public function getMethodsProperty()
    {
        return PaymentMethod::active();
    }

    // Simpan diskon item ke keranjang (ikut tersimpan bila kembali ke halaman POS)
    public function updatedItemDiscounts(CartService $carts)
    {
        foreach ($this->itemDiscounts as $key => $discount) {
            $this->cart = $carts->setDiscount($this->cart, $key, $discount['type'] ?? 'amount', $discount['value'] ?? 0);
        }
        session(['pos_active_cart' => $this->cart]);
    }

    protected function orderDiscount(): ?array
    {
        return $this->orderDiscountValue ? ['type' => $this->orderDiscountType, 'value' => $this->orderDiscountValue] : null;
    }

    /**
     * Rincian untuk ditampilkan. Error diskon ditampilkan di layar, bukan popup.
     */
    public function getSummaryProperty()
    {
        try {
            return app(CheckoutService::class)->summarize($this->cart, $this->orderDiscount()) + ['error' => null];
        } catch (PosException $e) {
            $plain = array_map(fn($item) => array_diff_key($item, ['discount' => 1]), $this->cart);

            return app(CheckoutService::class)->summarize($plain, null) + ['error' => $e->getMessage()];
        }
    }

    protected function paymentInput(): array
    {
        return $this->splitMode
            ? $this->payments
            : [['method' => $this->paymentMethod, 'cash_received' => $this->cashReceived]];
    }

    public function getPaymentPreviewProperty()
    {
        try {
            $rows = app(CheckoutService::class)->calculatePayments($this->paymentInput(), $this->summary['net'], strict: false);
        } catch (PosException $e) {
            $rows = [];
        }

        $tax = array_sum(array_column($rows, 'tax_amount'));

        return [
            'rows' => $rows,
            'tax' => $tax,
            'total' => $this->summary['net'] + $tax,
            'allocated' => array_sum(array_column($rows, 'amount')),
            'remaining' => $this->summary['net'] - array_sum(array_column($rows, 'amount')),
        ];
    }

    public function selectPayment($method)
    {
        $this->paymentMethod = $method;
        $this->cashReceived = null;
    }

    public function toggleSplit()
    {
        $this->splitMode = !$this->splitMode;
        $this->payments = $this->splitMode
            ? [['method' => $this->paymentMethod, 'amount' => null, 'cash_received' => null]]
            : [];
    }

    public function addPaymentRow()
    {
        $used = collect($this->payments)->pluck('method');
        $next = $this->methods->first(fn($m) => !$used->contains($m->code)) ?? $this->methods->first();

        $this->payments[] = ['method' => $next->code, 'amount' => null, 'cash_received' => null];
    }

    public function removePaymentRow($index)
    {
        unset($this->payments[$index]);
        $this->payments = array_values($this->payments);
    }

    // Isi baris ini dengan sisa yang belum dibagi
    public function fillRemaining($index)
    {
        $others = collect($this->payments)->except($index)->sum(fn($p) => (float) ($p['amount'] ?? 0));
        $this->payments[$index]['amount'] = max(0, $this->summary['net'] - $others);
    }

    public function processPayment(CheckoutService $checkout)
    {
        // Cegah double checkout (tombol Back browser): keranjang di session sudah dikosongkan
        if (empty(session()->get('pos_active_cart'))) {
            $this->redirect('/pos', navigate: true);
            return;
        }

        try {
            $transaction = $checkout->process(
                $this->cart,
                session('current_outlet_id'),
                auth()->id(),
                $this->paymentInput(),
                $this->orderDiscount(),
            );
        } catch (CartChangedException $e) {
            // Tampilkan total yang sudah diperbarui, kasir cek ulang sebelum bayar
            $this->cart = $e->cart;
            $this->cashReceived = null;
            session(['pos_active_cart' => $this->cart]);
            $this->dispatch('notify', $e->toNotify());

            if (empty($this->cart)) {
                $this->redirect('/pos', navigate: true);
            }
            return;
        } catch (PosException $e) {
            $this->dispatch('notify', $e->toNotify());
            return;
        }

        session()->forget('pos_active_cart');
        $this->cart = [];

        // Struk hanya dicetak otomatis sekali, tepat setelah pembayaran (bukan saat refresh)
        session()->flash('print_receipt', true);
        $this->redirect('/pos/receipt/' . $transaction->id, navigate: true);
    }
};
?>

@php
    $summary = $this->summary;
    $preview = $this->paymentPreview;
    $rp = fn($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $pct = fn($n) => rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
@endphp

<div class="space-y-4">
    <x-ui.page-header title="Pembayaran" back="/pos" back-label="Kembali ke kasir" />

    <div class="bento grid-cols-1 lg:grid-cols-5 items-start">
        <!-- Ringkasan & diskon -->
        <div class="tile lg:col-span-3 min-w-0">
            <div class="flex items-center justify-between mb-2">
                <h2 class="tile-title">Ringkasan & Diskon</h2>
                <span class="badge-light">{{ count($cart) }} jenis barang</span>
            </div>

            <div class="divide-y divide-zinc-100 max-h-[30rem] overflow-y-auto -mx-1 px-1">
                @foreach ($cart as $key => $item)
                    @php $line = $summary['lines'][$key] ?? null; @endphp
                    <div class="py-3.5" wire:key="line-{{ $key }}">
                        <div class="flex justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold">{{ $item['name'] }}</p>
                                <p class="text-xs text-zinc-400">{{ $rp($item['price']) }} × {{ $item['quantity'] }}</p>
                            </div>
                            <div class="text-right flex-shrink-0">
                                @if ($line && $line['discount'] > 0)
                                    <p class="text-xs text-zinc-400 line-through">{{ $rp($line['gross']) }}</p>
                                @endif
                                <p class="text-sm font-bold">{{ $rp($line['subtotal'] ?? $item['price'] * $item['quantity']) }}</p>
                            </div>
                        </div>
                        <div class="mt-2.5 flex items-center gap-2">
                            <span class="text-xs text-zinc-500 font-medium w-12">Diskon</span>
                            <select wire:model.live="itemDiscounts.{{ $key }}.type" class="select !w-20 !py-1.5 !px-3 !text-xs !rounded-xl">
                                <option value="amount">Rp</option>
                                <option value="percent">%</option>
                            </select>
                            <input type="number" min="0" wire:model.live.debounce.400ms="itemDiscounts.{{ $key }}.value" placeholder="0"
                                class="input !w-28 !py-1.5 !px-3 !text-xs !rounded-xl">
                            @if ($line && $line['discount'] > 0)
                                <span class="badge-dark">-{{ $rp($line['discount']) }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-3 pt-4 border-t border-zinc-100">
                <label class="label">Diskon total belanja</label>
                <div class="flex items-center gap-2">
                    <select wire:model.live="orderDiscountType" class="select !w-24">
                        <option value="amount">Rp</option>
                        <option value="percent">%</option>
                    </select>
                    <input type="number" min="0" wire:model.live.debounce.400ms="orderDiscountValue" placeholder="0" class="input flex-1">
                </div>
            </div>

            @if ($summary['error'])
                <div class="mt-3 rounded-2xl bg-red-50 border border-red-100 text-red-700 text-xs font-medium px-4 py-3">
                    {{ $summary['error'] }} Diskon belum diterapkan.
                </div>
            @endif
        </div>

        <!-- Total & pembayaran -->
        <div class="lg:col-span-2 space-y-3 md:space-y-4 lg:sticky lg:top-4">
            <div class="tile-dark">
                <div class="space-y-2 text-sm text-white/60">
                    <div class="flex justify-between"><span>Subtotal</span><span class="text-white">{{ $rp($summary['gross']) }}</span></div>
                    @if ($summary['item_discount'] > 0)
                        <div class="flex justify-between"><span>Diskon item</span><span class="text-white">-{{ $rp($summary['item_discount']) }}</span></div>
                    @endif
                    @if ($summary['order_discount'] > 0)
                        <div class="flex justify-between"><span>Diskon total belanja</span><span class="text-white">-{{ $rp($summary['order_discount']) }}</span></div>
                    @endif
                    @foreach ($preview['rows'] as $row)
                        @if ($row['tax_amount'] > 0)
                            <div class="flex justify-between"><span>Pajak {{ \App\Models\PaymentMethod::labelFor($row['payment_method']) }} ({{ $pct($row['tax_rate']) }}%)</span><span class="text-white">{{ $rp($row['tax_amount']) }}</span></div>
                        @endif
                    @endforeach
                </div>
                <div class="mt-4 pt-4 border-t border-white/10">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-white/50">Total Bayar</p>
                    <p class="text-3xl md:text-4xl font-bold tracking-tight mt-1 truncate">{{ $rp($preview['total']) }}</p>
                </div>
            </div>

            <div class="tile">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="tile-title">Metode Bayar</h2>
                    <button type="button" wire:click="toggleSplit" class="{{ $splitMode ? 'chip-active' : 'chip' }} !py-1.5 !text-xs">
                        <x-icon name="layers" class="w-3.5 h-3.5" /> Split
                    </button>
                </div>

                @if (!$splitMode)
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($this->methods as $method)
                            <button type="button" wire:click="selectPayment('{{ $method->code }}')"
                                class="rounded-2xl border-2 px-3 py-3 text-left transition {{ $paymentMethod === $method->code ? 'border-ink bg-ink text-white' : 'border-zinc-200 hover:border-zinc-400' }}">
                                <x-icon :name="$method->is_cash ? 'cash' : 'card'" class="w-4 h-4 {{ $paymentMethod === $method->code ? 'text-white' : 'text-zinc-400' }}" />
                                <p class="text-sm font-semibold mt-2 leading-tight">{{ $method->name }}</p>
                                @if ($method->tax_rate > 0)
                                    <p class="text-[10px] mt-0.5 {{ $paymentMethod === $method->code ? 'text-white/60' : 'text-zinc-400' }}">+{{ $pct($method->tax_rate) }}% pajak</p>
                                @endif
                            </button>
                        @endforeach
                    </div>

                    @if ($this->methods->firstWhere('code', $paymentMethod)?->is_cash)
                        <div class="mt-4">
                            <label class="label">Uang diterima</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-zinc-400">Rp</span>
                                <input type="number" wire:model.live.debounce.300ms="cashReceived" placeholder="0" class="input-lg !pl-11">
                            </div>
                            @if ($cashReceived && $cashReceived >= $preview['total'])
                                <div class="mt-3 flex justify-between items-center rounded-2xl bg-zinc-100 px-4 py-3">
                                    <span class="text-sm font-medium text-zinc-500">Kembalian</span>
                                    <span class="text-xl font-bold">{{ $rp($cashReceived - $preview['total']) }}</span>
                                </div>
                            @endif
                        </div>
                    @endif
                @else
                    <div class="space-y-2.5">
                        @foreach ($payments as $i => $payment)
                            @php
                                $method = $this->methods->firstWhere('code', $payment['method']);
                                $amount = (float) ($payment['amount'] ?? 0);
                                $tax = $method ? round($amount * $method->tax_rate / 100) : 0;
                                $cash = (float) ($payment['cash_received'] ?? 0);
                            @endphp
                            <div class="tile-muted space-y-2" wire:key="pay-{{ $i }}">
                                <div class="flex items-center gap-2">
                                    <select wire:model.live="payments.{{ $i }}.method" class="select !py-2 flex-1 !bg-white">
                                        @foreach ($this->methods as $m)
                                            <option value="{{ $m->code }}">{{ $m->name }}{{ $m->tax_rate > 0 ? ' (+' . $pct($m->tax_rate) . '% pajak)' : '' }}</option>
                                        @endforeach
                                    </select>
                                    @if (count($payments) > 1)
                                        <button type="button" wire:click="removePaymentRow({{ $i }})" class="btn-icon" title="Hapus"><x-icon name="x" class="w-4 h-4" /></button>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="number" min="0" wire:model.live.debounce.400ms="payments.{{ $i }}.amount" placeholder="Nominal belanja" class="input !py-2 flex-1 !bg-white">
                                    <button type="button" wire:click="fillRemaining({{ $i }})" class="btn-secondary btn-sm whitespace-nowrap">Isi sisa</button>
                                </div>
                                @if ($method?->is_cash)
                                    <input type="number" min="0" wire:model.live.debounce.400ms="payments.{{ $i }}.cash_received" placeholder="Uang diterima" class="input !py-2 !bg-white">
                                @endif
                                @if ($amount > 0)
                                    <p class="text-xs text-zinc-500">
                                        Ditagih {{ $rp($amount + $tax) }}{{ $tax > 0 ? ' (termasuk pajak ' . $rp($tax) . ')' : '' }}
                                        @if ($method?->is_cash && $cash >= $amount + $tax)
                                            · Kembalian <span class="font-semibold text-zinc-900">{{ $rp($cash - $amount - $tax) }}</span>
                                        @endif
                                    </p>
                                @endif
                            </div>
                        @endforeach

                        <button type="button" wire:click="addPaymentRow" class="w-full rounded-2xl border-2 border-dashed border-zinc-200 py-2.5 text-sm font-semibold text-zinc-500 hover:border-zinc-400 hover:text-zinc-800 transition">
                            + Tambah metode
                        </button>

                        <div class="flex justify-between text-sm font-semibold {{ abs($preview['remaining']) < 0.01 ? 'text-emerald-600' : 'text-amber-600' }}">
                            <span>{{ $preview['remaining'] >= 0 ? 'Sisa belum dibagi' : 'Kelebihan' }}</span>
                            <span>{{ $rp(abs($preview['remaining'])) }}</span>
                        </div>
                    </div>
                @endif
            </div>

            <button type="button" x-data @click="confirmCheckout(() => $wire.processPayment())" class="btn-primary btn-lg w-full">
                <span wire:loading.remove wire:target="processPayment">Selesaikan Transaksi</span>
                <span wire:loading wire:target="processPayment">Memproses...</span>
                <x-icon name="check" class="w-4 h-4" wire:loading.remove wire:target="processPayment" />
            </button>
        </div>
    </div>
</div>
