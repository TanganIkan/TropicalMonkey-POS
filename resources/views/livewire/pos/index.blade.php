<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Exceptions\PosException;
use App\Services\CartService;
use App\Services\ShiftService;
use Illuminate\Support\Facades\Auth;

new #[Layout('components.layouts.app')]
    class extends Component {
    public $search = '';
    public $cart = [];

    // Tutup shift (hitung kas opsional)
    public $closingCash = null;
    public $closingNote = '';
    public $showCloseShift = false;

    public function mount(CartService $carts, ShiftService $shifts)
    {
        // Shift dibuka otomatis: tidak perlu input modal
        $shifts->ensureOpen(auth()->user(), (int) $this->outletId);

        // Samakan harga dengan data terbaru (bisa berubah sejak barang masuk keranjang)
        [$this->cart, $changes] = $carts->refresh(session()->get('pos_active_cart', []));
        $this->saveCartToSession();

        if ($changes) {
            $this->dispatch('notify', ['title' => 'Keranjang Diperbarui', 'text' => implode(' ', $changes), 'icon' => 'info']);
        }
    }

    protected function saveCartToSession()
    {
        session(['pos_active_cart' => $this->cart]);
    }

    public function getOutletIdProperty()
    {
        return session('current_outlet_id');
    }

    public function scanOrSearch(CartService $carts)
    {
        $term = trim($this->search);
        $this->search = '';

        if (empty($term)) {
            return;
        }

        try {
            [$product, $variant] = $carts->resolveCode($term);
            $this->cart = $carts->add($this->cart, $product, $variant, $this->outletId);
            $this->saveCartToSession();
        } catch (PosException $e) {
            $this->dispatch('notify', $e->toNotify());
        }
    }

    public function increaseQty(CartService $carts, $cartKey)
    {
        try {
            $this->cart = $carts->increase($this->cart, $cartKey);
            $this->saveCartToSession();
        } catch (PosException $e) {
            $this->dispatch('notify', $e->toNotify());
        }
    }

    public function decreaseQty(CartService $carts, $cartKey)
    {
        $this->cart = $carts->decrease($this->cart, $cartKey);
        $this->saveCartToSession();
    }

    public function removeItem($cartKey)
    {
        unset($this->cart[$cartKey]);
        $this->saveCartToSession();
    }

    public function clearCart()
    {
        $this->cart = [];
        session()->forget('pos_active_cart');

        $this->dispatch('notify', [
            'title' => 'Dikosongkan!',
            'text' => 'Keranjang belanja telah dibersihkan.',
            'icon' => 'success',
        ]);
    }

    public function getTotalItemsProperty()
    {
        return app(CartService::class)->totalItems($this->cart);
    }

    public function getSubtotalProperty()
    {
        return app(CartService::class)->subtotal($this->cart);
    }

    public function proceedToPayment()
    {
        if (count($this->cart) === 0) {
            return;
        }
        $this->redirect('/pos/checkout', navigate: true);
    }

    // Shift terbuka milik user ini (bisa di outlet lain)
    public function getShiftProperty()
    {
        return app(ShiftService::class)->current(auth()->user());
    }

    public function getShiftSummaryProperty()
    {
        return $this->shift ? app(ShiftService::class)->summary($this->shift) : null;
    }

    public function closeShift(ShiftService $shifts)
    {
        if (!$this->shift) {
            return;
        }

        try {
            $shift = $shifts->close($this->shift, $this->closingCash, $this->closingNote);
        } catch (PosException $e) {
            $this->dispatch('notify', $e->toNotify());
            return;
        }

        $diff = $shift->cash_difference;
        $message = match (true) {
            $diff === null => 'Shift ditutup. Terima kasih!',
            $diff == 0 => 'Shift ditutup, kas sesuai. Terima kasih!',
            default => 'Shift ditutup. Kas ' . ($diff > 0 ? 'lebih' : 'kurang') . ' Rp ' . number_format(abs($diff), 0, ',', '.') . ' dari seharusnya.',
        };

        // Tutup shift = selesai bertugas
        app(\App\Services\ActivityLogger::class)->log('logout', 'Logout');
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        session()->flash('status', $message);

        return $this->redirect('/login');
    }
};
?>

@php
    $shift = $this->shift;
    $rp = fn($n) => 'Rp ' . number_format($n, 0, ',', '.');
@endphp

<div class="space-y-4 {{ count($cart) > 0 ? 'pb-20 lg:pb-0' : '' }}">
    <x-ui.page-header title="Kasir" subtitle="Scan barcode atau ketik SKU untuk menambahkan barang.">
        @if ($shift)
            <span class="badge-success !py-1.5 !px-3"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Shift sejak {{ $shift->opened_at->format('H:i') }}</span>
            <button type="button" wire:click="$set('showCloseShift', true)" class="btn-secondary !py-2">
                <x-icon name="logout" class="w-4 h-4" /> Tutup Shift
            </button>
        @endif
    </x-ui.page-header>

    <div class="bento grid-cols-1 lg:grid-cols-3 items-start">
        <!-- Kiri: scan + keranjang -->
        <div class="lg:col-span-2 space-y-3 md:space-y-4 min-w-0">
            <div class="tile !p-3 md:!p-4">
                <div class="relative">
                    <x-icon name="scan" class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400 pointer-events-none" />
                    <input type="text" wire:model="search" wire:keydown.enter.prevent="scanOrSearch" autofocus id="scan-input"
                        placeholder="Scan barcode atau ketik SKU, lalu Enter" autocomplete="off" spellcheck="false"
                        class="input-lg !pl-12 !bg-white">
                </div>
            </div>

            <div class="tile !p-0 overflow-hidden">
                <div class="flex items-center justify-between px-4 md:px-5 py-3.5 border-b border-zinc-100">
                    <h2 class="tile-title">Keranjang <span class="text-zinc-400 font-normal">({{ $this->totalItems }})</span></h2>
                    @if (count($cart) > 0)
                        <button type="button" x-data @click="confirmDeletion(() => $wire.clearCart(), 'Semua item di keranjang')" class="btn-ghost btn-sm !text-red-600">
                            <x-icon name="trash" class="w-4 h-4" /> Kosongkan
                        </button>
                    @endif
                </div>

                <div class="divide-y divide-zinc-100">
                    @forelse ($cart as $cartKey => $item)
                        <div class="grid grid-cols-[3rem_1fr_auto] sm:grid-cols-[3rem_1fr_auto_7rem_2.25rem] gap-x-3 gap-y-2 items-center px-4 md:px-5 py-3" wire:key="cart-{{ $cartKey }}">
                            <div class="w-12 h-12 rounded-2xl bg-zinc-100 flex items-center justify-center overflow-hidden row-span-2 sm:row-span-1 self-start sm:self-center">
                                @if (!empty($item['image']))
                                    <img src="{{ asset('storage/' . $item['image']) }}" alt="{{ $item['name'] }}" class="w-full h-full object-cover">
                                @else
                                    <x-icon name="image" class="w-5 h-5 text-zinc-300" />
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold leading-snug">{{ $item['name'] }}</p>
                                <p class="text-xs text-zinc-400 mt-0.5 truncate"><span class="font-mono">{{ $item['sku'] ?? 'N/A' }}</span> · {{ $rp($item['price']) }}</p>
                            </div>
                            <div class="col-start-2 row-start-2 sm:col-start-3 sm:row-start-1 justify-self-start flex items-center gap-1 rounded-2xl bg-zinc-100 p-1">
                                <button wire:click="decreaseQty('{{ $cartKey }}')" class="w-8 h-8 rounded-xl bg-white flex items-center justify-center hover:bg-ink hover:text-white transition" aria-label="Kurangi">
                                    <x-icon name="minus" class="w-4 h-4" />
                                </button>
                                <span class="w-8 text-center text-sm font-bold">{{ $item['quantity'] }}</span>
                                <button wire:click="increaseQty('{{ $cartKey }}')" class="w-8 h-8 rounded-xl bg-white flex items-center justify-center hover:bg-ink hover:text-white transition" aria-label="Tambah">
                                    <x-icon name="plus" class="w-4 h-4" />
                                </button>
                            </div>
                            <p class="col-start-3 row-start-2 sm:col-start-4 sm:row-start-1 text-right text-sm font-bold whitespace-nowrap">{{ $rp($item['price'] * $item['quantity']) }}</p>
                            <button wire:click="removeItem('{{ $cartKey }}')" class="col-start-3 row-start-1 sm:col-start-5 justify-self-end btn-icon !w-8 !h-8 hover:!text-red-600" title="Hapus item">
                                <x-icon name="x" class="w-4 h-4" />
                            </button>
                        </div>
                    @empty
                        <x-ui.empty icon="bag" title="Keranjang masih kosong" text="Gunakan scanner atau ketik SKU produk di kolom pencarian." class="py-14" />
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Kanan: ringkasan -->
        <div class="space-y-3 md:space-y-4 lg:sticky lg:top-4">
            <div class="tile-dark">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/50">Subtotal</p>
                <p class="text-3xl md:text-4xl font-bold tracking-tight mt-1 truncate">{{ $rp($this->subtotal) }}</p>
                <div class="flex items-center justify-between text-sm text-white/60 mt-4 pt-4 border-t border-white/10">
                    <span>Total item</span>
                    <span class="font-semibold text-white">{{ $this->totalItems }} barang</span>
                </div>
                <button wire:click="proceedToPayment" @disabled(count($cart) === 0)
                    class="btn btn-lg w-full mt-5 bg-white text-ink hover:bg-zinc-100">
                    Lanjut Pembayaran <x-icon name="arrow-right" class="w-4 h-4" />
                </button>
            </div>

            <div class="tile !p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-zinc-100 flex items-center justify-center flex-shrink-0"><x-icon name="percent" class="w-4 h-4" /></div>
                <p class="text-xs text-zinc-500">Diskon, split payment, dan pajak diatur di halaman pembayaran.</p>
            </div>
        </div>
    </div>

    @if (count($cart) > 0)
        <div class="lg:hidden fixed inset-x-3 bottom-[5.75rem] z-20">
            <div class="bg-white rounded-3xl border border-zinc-200 shadow-lift p-2 pl-5 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] text-zinc-500">{{ $this->totalItems }} barang</p>
                    <p class="text-lg font-bold truncate">{{ $rp($this->subtotal) }}</p>
                </div>
                <button wire:click="proceedToPayment" class="btn-primary">Bayar <x-icon name="arrow-right" class="w-4 h-4" /></button>
            </div>
        </div>
    @endif

    @if ($showCloseShift && $shift)
        @php $sum = $this->shiftSummary; @endphp
        <x-ui.modal title="Tutup Shift" :subtitle="$shift->outlet->name . ' · sejak ' . $shift->opened_at->format('d/m/Y H:i')" close="$set('showCloseShift', false)">
            <div class="grid grid-cols-2 gap-2">
                <div class="tile-muted">
                    <p class="stat-label">Transaksi</p>
                    <p class="text-xl font-bold mt-1">{{ $sum['transactions'] }}</p>
                </div>
                <div class="tile-muted">
                    <p class="stat-label">Penjualan</p>
                    <p class="text-xl font-bold mt-1 truncate">{{ $rp($sum['net_sales']) }}</p>
                </div>
            </div>

            <div class="mt-4 space-y-2 text-sm">
                @if ($sum['discount'] > 0)
                    <div class="flex justify-between"><span class="text-zinc-500">Total diskon</span><span>{{ $rp($sum['discount']) }}</span></div>
                @endif
                @if ($sum['tax'] > 0)
                    <div class="flex justify-between"><span class="text-zinc-500">Pajak</span><span>{{ $rp($sum['tax']) }}</span></div>
                @endif
                @foreach ($sum['by_method'] as $code => $amount)
                    <div class="flex justify-between text-xs"><span class="text-zinc-400">{{ \App\Models\PaymentMethod::labelFor($code) }}</span><span class="text-zinc-600">{{ $rp($amount) }}</span></div>
                @endforeach
                @if ($sum['voids'] > 0)
                    <div class="flex justify-between text-red-600"><span>Transaksi void</span><span>{{ $sum['voids'] }}</span></div>
                @endif
                @if ($shift->opening_cash > 0)
                    <div class="flex justify-between pt-3 border-t border-dashed border-zinc-200"><span class="text-zinc-500">Modal awal</span><span>{{ $rp($shift->opening_cash) }}</span></div>
                @endif
                <div class="flex justify-between text-base font-bold {{ $shift->opening_cash > 0 ? '' : 'pt-3 border-t border-dashed border-zinc-200' }}">
                    <span>Penjualan Tunai</span><span>{{ $rp($sum['cash_sales']) }}</span>
                </div>
            </div>

            <form wire:submit="closeShift" class="mt-5 space-y-3">
                <div>
                    <label class="label">Kas tunai di laci <span class="font-normal text-zinc-400">(opsional)</span></label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-zinc-400">Rp</span>
                        <input type="number" min="0" wire:model.live.debounce.300ms="closingCash" placeholder="0" class="input-lg !pl-11">
                    </div>
                    @if (is_numeric($closingCash) && $closingCash !== '')
                        @php $diff = $closingCash - $sum['expected_cash']; @endphp
                        <p class="text-xs font-semibold mt-1.5 {{ $diff == 0 ? 'text-emerald-600' : 'text-amber-600' }}">
                            {{ $diff == 0 ? 'Kas sesuai' : ($diff > 0 ? 'Lebih ' : 'Kurang ') . $rp(abs($diff)) }}
                        </p>
                    @endif
                </div>
                <textarea wire:model="closingNote" rows="2" placeholder="Catatan (opsional), mis. alasan selisih kas" class="input"></textarea>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" wire:click="$set('showCloseShift', false)" class="btn-secondary">Batal</button>
                    <button type="submit" class="btn-primary">Tutup Shift & Logout</button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
