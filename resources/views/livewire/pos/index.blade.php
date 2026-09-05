<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;

new #[Layout('components.layouts.app')]
    class extends Component {
    public $search = '';
    public $cart = [];

    public function mount()
    {
        $this->cart = session()->get('pos_active_cart', []);
    }

    protected function saveCartToSession()
    {
        session(['pos_active_cart' => $this->cart]);
    }

    public function getOutletIdProperty()
    {
        return session('current_outlet_id');
    }

    public function scanOrSearch()
    {
        $term = trim($this->search);

        if (empty($term)) {
            return;
        }

        $variant = ProductVariant::with('product')->where('barcode', $term)->orWhere('sku', $term)->first();

        if ($variant) {
            $product = $variant->product;

            if ($product->is_active == 0) {
                $this->dispatch('swal', [
                    'title' => 'Produk Nonaktif',
                    'text' => "Produk '{$product->name}' masih bersifat draf dan tidak bisa dipesan.",
                    'icon' => 'error',
                ]);
                $this->search = '';
                return;
            }

            $this->addToCart($product, $variant);
            $this->search = '';
            return;
        }

        $product = Product::where('barcode', $term)->orWhere('sku', $term)->first();

        if (!$product) {
            $this->dispatch('swal', [
                'title' => 'Tidak Ditemukan!',
                'text' => "Barcode atau SKU '{$term}' tidak terdaftar di sistem.",
                'icon' => 'error',
            ]);
            $this->search = '';
            return;
        }

        if ($product->is_active == 0) {
            $this->dispatch('swal', [
                'title' => 'Produk Nonaktif',
                'text' => "Produk '{$product->name}' masih bersifat draf dan tidak bisa dipesan.",
                'icon' => 'error',
            ]);
            $this->search = '';
            return;
        }

        if ($product->has_variants) {
            $this->dispatch('swal', [
                'title' => 'Pilih Varian!',
                'text' => 'Ini produk bervarian. Harap scan barcode/SKU ukurannya secara spesifik.',
                'icon' => 'info',
            ]);
            $this->search = '';
            return;
        }

        $this->addToCart($product, null);
        $this->search = '';
    }

    public function addToCart(Product $product, ?ProductVariant $variant = null)
    {
        $variantId = $variant ? $variant->id : null;
        $cartKey = $product->id . '-' . ($variantId ?? 'single');

        $stock = Stock::where('product_id', $product->id)->where('outlet_id', $this->outletId)->where('product_variant_id', $variantId)->first();

        $availableStock = $stock ? $stock->quantity : 0;
        $itemName = $variant ? "{$product->name} ({$variant->size} - {$variant->color})" : $product->name;

        if ($availableStock <= 0) {
            $this->dispatch('swal', [
                'title' => 'Stok Habis!',
                'text' => "Stok {$itemName} kosong di outlet ini.",
                'icon' => 'error',
            ]);
            return;
        }

        if (isset($this->cart[$cartKey])) {
            $newQty = $this->cart[$cartKey]['quantity'] + 1;

            if ($newQty > $availableStock) {
                $this->dispatch('swal', [
                    'title' => 'Stok Tidak Cukup!',
                    'text' => "Stok {$itemName} hanya tersisa {$availableStock} unit.",
                    'icon' => 'warning',
                ]);
                return;
            }

            $this->cart[$cartKey]['quantity'] = $newQty;
        } else {
            $this->cart[$cartKey] = [
                'name' => $itemName,
                'sku' => $variant ? $variant->sku : $product->sku,
                'image' => $product->image ?? null,
                'price' => $product->sell_price,
                'quantity' => 1,
                'stock' => $availableStock,
            ];
        }

        $this->saveCartToSession();
    }

    public function increaseQty($cartKey)
    {
        if (!isset($this->cart[$cartKey])) {
            return;
        }

        $item = $this->cart[$cartKey];
        if ($item['quantity'] + 1 > $item['stock']) {
            $this->dispatch('swal', [
                'title' => 'Batas Stok!',
                'text' => "Stok {$item['name']} hanya tersisa {$item['stock']} unit.",
                'icon' => 'warning',
            ]);
            return;
        }
        $this->cart[$cartKey]['quantity']++;
        $this->saveCartToSession();
    }

    public function decreaseQty($cartKey)
    {
        if (!isset($this->cart[$cartKey])) {
            return;
        }

        if ($this->cart[$cartKey]['quantity'] > 1) {
            $this->cart[$cartKey]['quantity']--;
        } else {
            unset($this->cart[$cartKey]);
        }
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

        $this->dispatch('swal', [
            'title' => 'Dikosongkan!',
            'text' => 'Keranjang belanja telah dibersihkan.',
            'icon' => 'success',
        ]);
    }

    public function getTotalItemsProperty()
    {
        return collect($this->cart)->sum('quantity');
    }

    public function getSubtotalProperty()
    {
        return collect($this->cart)->sum(fn($item) => $item['price'] * $item['quantity']);
    }

    public function proceedToPayment()
    {
        if (count($this->cart) === 0) {
            return;
        }
        $this->redirect('/pos/checkout', navigate: true);
    }
};
?>

<div class="p-4 md:p-6 lg:h-[calc(100vh-4rem)] flex flex-col min-h-screen lg:min-h-0 bg-gray-50/30">
    <!-- Header -->
    <div class="mb-4 md:mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-2 flex-shrink-0">
        <div>
            <h1 class="text-xl md:text-3xl font-bold text-gray-900 flex items-center gap-2">
                Mesin Kasir
            </h1>
            <p class="text-sm text-gray-500 mt-1">Kelola transaksi penjualan</p>
        </div>

        @if (count($cart) > 0)
            <button type="button" x-data @click="confirmDeletion(() => $wire.clearCart(), 'Semua item di keranjang')"
                class="inline-flex items-center text-sm font-semibold text-gray-500 hover:text-red-600 transition">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                    </path>
                </svg>
                Kosongkan Keranjang
            </button>
        @endif
    </div>

    <!-- Main Content Grid -->
    <div class="flex-1 grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6 lg:min-h-0 pb-6 lg:pb-0">

        <!-- Bagian Kiri: Pencarian & Tabel Keranjang -->
        <div class="lg:col-span-2 flex flex-col space-y-4 lg:min-h-0">
            <div class="bg-white border border-gray-200 rounded-xl p-3 md:p-4 shadow-sm flex-shrink-0 relative">
                <div class="absolute inset-y-0 left-0 pl-3 md:pl-5 flex items-center pointer-events-none">
                    <svg class="w-5 h-5 md:w-6 md:h-6 text-gray-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z">
                        </path>

                    </svg>
                </div>
                <input type="text" wire:model="search" wire:keydown.enter.prevent="scanOrSearch" autofocus
                    id="scan-input" placeholder="Scan barcode atau ketik SKU..." autocomplete="off" spellcheck="false"
                    class="w-full pl-10 md:pl-14 pr-4 py-2 md:py-3 bg-gray-50/50 border border-gray-200 focus:border-gray-900 focus:ring-1 focus:ring-gray-900 focus:bg-white rounded-lg text-base md:text-lg font-medium outline-none transition-none placeholder:font-normal">
            </div>

            <div class="bg-white border border-gray-200 rounded-xl shadow-sm flex flex-col overflow-hidden">
                <div class="overflow-x-auto overflow-y-auto flex-1 p-0">
                    <table class="w-full text-left min-w-[700px]">
                        <thead class="bg-gray-50/90 backdrop-blur sticky top-0 z-10 border-b border-gray-100">
                            <tr class="text-[11px] uppercase text-gray-500 font-bold tracking-wider">
                                <th class="px-4 md:px-6 py-3 md:py-4">Informasi Produk</th>
                                <th class="px-4 py-3 md:py-4 text-right">Harga</th>
                                <th class="px-4 py-3 md:py-4 text-center w-32">Kuantitas</th>
                                <th class="px-4 py-3 md:py-4 text-right">Subtotal</th>
                                <th class="px-4 md:px-6 py-3 md:py-4"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($cart as $cartKey => $item)
                                <tr class="hover:bg-gray-50/50 transition group">
                                    <td class="px-4 md:px-6 py-3 md:py-4">
                                        <div class="flex items-center gap-3 md:gap-4">
                                            <div
                                                class="w-10 h-10 md:w-12 md:h-12 rounded-lg bg-gray-50 border border-gray-200 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                                @if (!empty($item['image']))
                                                    <img src="{{ asset('storage/' . $item['image']) }}"
                                                        alt="{{ $item['name'] }}" class="w-full h-full object-cover">
                                                @else
                                                    <svg class="w-5 h-5 md:w-6 md:h-6 text-gray-300" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                                                        </path>
                                                    </svg>
                                                @endif
                                            </div>
                                            <div>
                                                <p class="text-sm font-bold text-gray-900 leading-tight whitespace-normal">
                                                    {{ $item['name'] }}
                                                </p>
                                                <p
                                                    class="text-xs text-gray-500 mt-1 font-mono bg-gray-100/80 inline-block px-1.5 py-0.5 rounded border border-gray-200">
                                                    {{ $item['sku'] ?? 'N/A' }}
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td
                                        class="px-4 py-3 md:py-4 text-sm text-gray-600 text-right font-semibold whitespace-nowrap">
                                        Rp {{ number_format($item['price'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 md:py-4">
                                        <div
                                            class="flex items-center justify-center gap-1 bg-gray-50 border border-gray-200 rounded-lg p-1 w-fit mx-auto shadow-sm">
                                            <button wire:click="decreaseQty('{{ $cartKey }}')"
                                                class="w-7 h-7 md:w-8 md:h-8 rounded-md bg-white hover:bg-gray-900 hover:text-white border border-gray-100 shadow-sm text-gray-600 font-bold transition flex items-center justify-center">-</button>
                                            <span
                                                class="w-8 md:w-10 text-center font-extrabold text-gray-900 text-sm">{{ $item['quantity'] }}</span>
                                            <button wire:click="increaseQty('{{ $cartKey }}')"
                                                class="w-7 h-7 md:w-8 md:h-8 rounded-md bg-white hover:bg-gray-900 hover:text-white border border-gray-100 shadow-sm text-gray-600 font-bold transition flex items-center justify-center">+</button>
                                        </div>
                                    </td>
                                    <td
                                        class="px-4 py-3 md:py-4 text-right text-sm font-extrabold text-gray-900 whitespace-nowrap">
                                        Rp {{ number_format($item['price'] * $item['quantity'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 md:px-6 py-3 md:py-4 text-right">
                                        <button wire:click="removeItem('{{ $cartKey }}')"
                                            class="p-1.5 md:p-2 text-gray-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition opacity-0 group-hover:opacity-100"
                                            title="Hapus Item">
                                            <svg class="w-4 h-4 md:w-5 md:h-5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16">
                                                </path>
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 md:py-20 text-center">
                                        <div class="flex flex-col items-center justify-center text-gray-400">
                                            <div
                                                class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                                                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z">
                                                    </path>
                                                </svg>
                                            </div>
                                            <p class="text-sm md:text-base font-bold text-gray-500">Keranjang masih
                                                kosong</p>
                                            <p class="text-xs md:text-sm mt-1">Gunakan scanner atau ketik SKU produk di
                                                atas.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Bagian Kanan: Ringkasan Sederhana -->
        <div
            class="bg-white border border-gray-200 rounded-xl p-4 md:p-6 shadow-sm flex flex-col h-fit lg:sticky lg:top-6">
            <h2
                class="text-base md:text-lg font-bold text-gray-900 mb-4 md:mb-6 flex items-center pb-4 border-b border-gray-100">
                Ringkasan Transaksi
            </h2>

            <div class="space-y-4 mb-6">
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-500 font-medium">Total Item</span>
                    <span class="font-bold text-gray-900 px-2 py-1 bg-gray-100 rounded-md">{{ $this->totalItems }}
                        Barang</span>
                </div>

                <div class="pt-4 flex justify-between items-center">
                    <span class="font-bold text-gray-900 text-base md:text-lg">Subtotal</span>
                    <span class="font-black text-2xl md:text-3xl text-gray-900 tracking-tight">Rp
                        {{ number_format($this->subtotal, 0, ',', '.') }}</span>
                </div>
            </div>

            <button wire:click="proceedToPayment" {{ count($cart) === 0 ? 'disabled' : '' }}
                class="w-full py-3.5 md:py-4 rounded-xl font-bold text-base md:text-lg flex justify-center items-center gap-2 transition 
                {{ count($cart) > 0 ? 'bg-gray-900 text-white shadow-lg hover:bg-gray-800' : 'bg-gray-100 text-gray-400 cursor-not-allowed border border-gray-200' }}">
                Lanjut Pembayaran
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3">
                    </path>
                </svg>
            </button>
        </div>
    </div>
</div>