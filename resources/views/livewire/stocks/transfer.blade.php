<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Exceptions\PosException;
use App\Models\Outlet;
use App\Models\StockTransfer;
use App\Services\ActivityLogger;
use App\Services\ProductService;
use App\Services\StockService;

new #[Layout('components.layouts.app')] class extends Component {
    public $toOutletId = '';
    public $search = '';
    public $note = '';

    // ["{product_id}-{variant_id|single}" => ['product_id', 'variant_id', 'name', 'sku', 'available', 'quantity']]
    public $items = [];

    public function mount()
    {
        $this->toOutletId = $this->destinations->first()?->id ?? '';
    }

    public function getOutletProperty()
    {
        return Outlet::find(session('current_outlet_id'));
    }

    public function getDestinationsProperty()
    {
        return Outlet::where('is_active', true)->whereKeyNot(session('current_outlet_id'))->orderBy('name')->get();
    }

    public function addItem(ProductService $products, StockService $stocks)
    {
        $code = trim($this->search);
        $this->search = '';

        $found = $code !== '' ? $products->findByCode($code) : null;

        if (!$found) {
            $this->dispatch('notify', ['title' => 'Tidak Ditemukan!', 'text' => "Barcode atau SKU '{$code}' tidak terdaftar.", 'icon' => 'error']);
            return;
        }

        [$product, $variant] = $found;

        if (!$variant && $product->has_variants) {
            $this->dispatch('notify', ['title' => 'Pilih Varian!', 'text' => 'Ini produk bervarian. Scan barcode/SKU variannya.', 'icon' => 'info']);
            return;
        }

        $key = $product->id . '-' . ($variant?->id ?? 'single');
        $available = $stocks->available($product->id, $variant?->id, $this->outlet->id);

        if (isset($this->items[$key])) {
            $this->items[$key]['quantity']++;
        } else {
            $this->items[$key] = [
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'name' => $variant ? "{$product->name} ({$variant->label()})" : $product->name,
                'sku' => $variant?->sku ?? $product->sku,
                'available' => $available,
                'quantity' => 1,
            ];
        }
    }

    public function removeItem($key)
    {
        unset($this->items[$key]);
    }

    public function submit(StockService $stocks, ActivityLogger $logger)
    {
        try {
            $transfer = $stocks->transfer($this->outlet->id, (int) $this->toOutletId, array_values($this->items), $this->note);
        } catch (PosException $e) {
            $this->dispatch('notify', $e->toNotify());
            return;
        }

        $transfer->load('toOutlet');
        $qty = collect($this->items)->sum('quantity');
        $logger->log('stock.transfer', "Transfer {$transfer->reference}: {$qty} barang ke {$transfer->toOutlet->name}", $transfer, ['quantity' => $qty]);

        $this->reset(['items', 'note']);
        $this->dispatch('notify', [
            'title' => 'Transfer Berhasil',
            'text' => "{$transfer->reference}: {$qty} barang dipindahkan ke {$transfer->toOutlet->name}.",
            'icon' => 'success',
        ]);
    }

    public function with(): array
    {
        $outletId = session('current_outlet_id');

        return [
            'recent' => StockTransfer::with(['fromOutlet', 'toOutlet', 'user', 'items'])
                ->where(fn($q) => $q->where('from_outlet_id', $outletId)->orWhere('to_outlet_id', $outletId))
                ->latest()
                ->limit(10)
                ->get(),
        ];
    }
};
?>

<div class="space-y-4">
    <x-ui.page-header title="Transfer Stok" :subtitle="'Pindahkan stok dari ' . $this->outlet->name . ' ke outlet lain. Stok langsung berpindah saat disimpan.'" back="/stocks/history" back-label="Stok" />

    @if ($this->destinations->isEmpty())
        <div class="tile"><x-ui.empty icon="store" title="Belum ada outlet tujuan yang aktif" text="Tambahkan outlet lain di menu Outlet." /></div>
    @else
        <div class="bento grid-cols-1 lg:grid-cols-3 items-start">
            <div class="lg:col-span-2 space-y-3 md:space-y-4">
                <div class="tile !p-3 md:!p-4">
                    <div class="relative">
                        <x-icon name="scan" class="w-5 h-5 absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400" />
                        <input type="text" wire:model="search" wire:keydown.enter.prevent="addItem" autofocus placeholder="Scan barcode atau ketik SKU, lalu Enter" class="input-lg !pl-12 !bg-white">
                    </div>
                </div>
                <div class="tile !p-0 overflow-hidden">
                    <div class="px-4 md:px-5 py-3.5 border-b border-zinc-100"><h2 class="tile-title">Barang yang ditransfer</h2></div>
                    <div class="divide-y divide-zinc-100">
                        @forelse ($items as $key => $item)
                            <div class="flex items-center gap-3 px-4 md:px-5 py-3" wire:key="tr-{{ $key }}">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold truncate">{{ $item['name'] }}</p>
                                    <p class="text-xs text-zinc-400"><span class="font-mono">{{ $item['sku'] ?? '-' }}</span> · stok {{ $item['available'] }}</p>
                                </div>
                                <input type="number" min="1" wire:model.live.debounce.300ms="items.{{ $key }}.quantity"
                                    class="input !w-24 !py-1.5 text-center font-semibold {{ $item['quantity'] > $item['available'] ? '!border-red-400 !text-red-600' : '' }}">
                                <button type="button" wire:click="removeItem('{{ $key }}')" class="btn-icon hover:!text-red-600" aria-label="Hapus"><x-icon name="x" class="w-4 h-4" /></button>
                            </div>
                        @empty
                            <x-ui.empty icon="swap" title="Belum ada barang" text="Scan barcode untuk menambahkan barang." />
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="space-y-3 md:space-y-4 lg:sticky lg:top-4">
                <div class="tile space-y-4">
                    <div>
                        <label class="label">Dari</label>
                        <div class="input !bg-zinc-100 flex items-center gap-2"><x-icon name="store" class="w-4 h-4 text-zinc-400" /> {{ $this->outlet->name }}</div>
                    </div>
                    <div>
                        <label class="label">Ke outlet</label>
                        <select wire:model="toOutletId" class="select">
                            @foreach ($this->destinations as $o)
                                <option value="{{ $o->id }}">{{ $o->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Catatan</label>
                        <input type="text" wire:model="note" placeholder="Opsional" class="input">
                    </div>
                </div>
                <div class="tile-dark">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-white/50">Total barang</p>
                    <p class="text-3xl font-bold mt-1">{{ collect($items)->sum('quantity') }}</p>
                    <button type="button" x-data @disabled(empty($items))
                        @click="confirmAction(() => $wire.submit(), 'Transfer stok?', 'Stok akan langsung dipindahkan ke outlet tujuan.', 'Ya, Transfer')"
                        class="btn btn-lg w-full mt-4 bg-white text-ink hover:bg-zinc-100">Transfer Sekarang</button>
                </div>
            </div>
        </div>
    @endif

    <div class="tile !p-0 overflow-hidden">
        <h2 class="tile-title px-4 md:px-6 py-4 border-b border-zinc-100">Riwayat Transfer</h2>
        <div class="divide-y divide-zinc-100">
            @forelse ($recent as $t)
                <div class="px-4 md:px-6 py-3.5 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="font-semibold">{{ $t->reference }} <span class="font-normal text-zinc-500">· {{ $t->fromOutlet->name }} → {{ $t->toOutlet->name }}</span></p>
                        <p class="text-xs text-zinc-400">{{ $t->created_at->format('d M Y H:i') }} · {{ $t->user->name ?? '-' }}</p>
                    </div>
                    <p class="text-xs text-zinc-500 mt-1">
                        {{ $t->items->map(fn($i) => "{$i->item_name} ×{$i->quantity}")->implode(', ') }}
                        @if ($t->note) <span class="italic">— {{ $t->note }}</span> @endif
                    </p>
                </div>
            @empty
                <x-ui.empty icon="swap" title="Belum ada transfer" />
            @endforelse
        </div>
    </div>
</div>
