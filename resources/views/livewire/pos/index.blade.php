<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;

new #[Layout('components.layouts.app')] class extends Component {
    public $search = '';
    public $cart = [];
    public $notFoundMessage = '';

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
        $this->notFoundMessage = '';
        $term = trim($this->search);

        if (empty($term)) {
            return;
        }

        // 1. CARI DI TABEL VARIAN DULU (Karena yang biasa discan adalah barcode varian fisik)
        $variant = ProductVariant::with('product')
            ->where('barcode', $term)
            ->orWhere('sku', $term)
            ->first();

        if ($variant) {
            $product = $variant->product;

            if ($product->is_active == 0) {
                $this->notFoundMessage = "Produk '{$product->name}' masih bersifat draf dan tidak bisa dipesan.";
                $this->search = '';
                return;
            }

            // Jika ketemu, masukkan Induk dan Variannya ke keranjang
            $this->addToCart($product, $variant);
            $this->search = '';
            return;
        }

        // 2. JIKA TIDAK KETEMU DI VARIAN, CARI DI TABEL INDUK (Untuk Produk Tunggal seperti Topi/Stiker)
        $product = Product::where('barcode', $term)->orWhere('sku', $term)->first();

        if (!$product) {
            $this->notFoundMessage = 'Produk tidak ditemukan: ' . $term;
            $this->search = '';
            return;
        }

        if ($product->is_active == 0) {
            $this->notFoundMessage = "Produk '{$product->name}' masih bersifat draf dan tidak bisa dipesan.";
            $this->search = '';
            return;
        }

        // Cegah kasir memasukkan Induk produk bervarian tanpa memilih ukurannya
        if ($product->has_variants) {
            $this->notFoundMessage = "Ini produk bervarian. Harap scan barcode/SKU ukurannya secara spesifik.";
            $this->search = '';
            return;
        }

        // Masukkan Produk Tunggal ke keranjang
        $this->addToCart($product, null);
        $this->search = '';
    }

    // Fungsi addToCart sekarang menerima 2 parameter (Induk dan Anak)
    public function addToCart(Product $product, ?ProductVariant $variant = null)
    {
        $variantId = $variant ? $variant->id : null;

        // Buat ID unik untuk keranjang (contoh: "12-single" atau "12-5") supaya Kaos M & Kaos L tidak menyatu
        $cartKey = $product->id . '-' . ($variantId ?? 'single');

        // Cari stok dengan mempertimbangkan variannya
        $stock = Stock::where('product_id', $product->id)
            ->where('outlet_id', $this->outletId)
            ->where('product_variant_id', $variantId)
            ->first();

        $availableStock = $stock ? $stock->quantity : 0;

        // Nama untuk di struk (Contoh: "Bikini (S - Kuning)")
        $itemName = $variant ? "{$product->name} ({$variant->size} - {$variant->color})" : $product->name;

        if ($availableStock <= 0) {
            $this->notFoundMessage = "Stok {$itemName} habis di outlet ini.";
            return;
        }

        if (isset($this->cart[$cartKey])) {
            $newQty = $this->cart[$cartKey]['quantity'] + 1;

            if ($newQty > $availableStock) {
                $this->notFoundMessage = "Stok {$itemName} hanya tersisa {$availableStock}.";
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

    // Fungsi Plus/Minus sekarang mencari berdasarkan $cartKey, bukan $productId lagi
    public function increaseQty($cartKey)
    {
        if (!isset($this->cart[$cartKey]))
            return;

        $item = $this->cart[$cartKey];
        if ($item['quantity'] + 1 > $item['stock']) {
            $this->notFoundMessage = "Stok {$item['name']} hanya tersisa {$item['stock']}.";
            return;
        }
        $this->cart[$cartKey]['quantity']++;
        $this->saveCartToSession();
    }

    public function decreaseQty($cartKey)
    {
        if (!isset($this->cart[$cartKey]))
            return;

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
        if (count($this->cart) === 0)
            return;
        $this->redirect('/pos/checkout', navigate: true);
    }
};
?>

<div class="p-4 md:p-6 lg:h-[calc(100vh-4rem)] flex flex-col min-h-screen lg:min-h-0">
    <!-- Header -->
    <div class="mb-4 md:mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-2 flex-shrink-0">
        <div>
            <h1 class="text-xl md:text-2xl font-bold text-gray-900 flex items-center gap-2">
                <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z">
                    </path>
                </svg>
                Mesin Kasir
            </h1>
            <p class="text-sm text-gray-500 mt-1">Kelola transaksi penjualan</p>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="flex-1 grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6 lg:min-h-0 pb-6 lg:pb-0">

        <!-- Bagian Kiri: Pencarian & Tabel Keranjang -->
        <div class="lg:col-span-2 flex flex-col space-y-4 lg:min-h-0">
            <div class="bg-white border border-gray-200 rounded-xl p-3 md:p-4 shadow-sm flex-shrink-0">
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 md:pl-4 flex items-center pointer-events-none">
                        <svg class="w-5 h-5 md:w-6 md:h-6 text-gray-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                    <input type="text" wire:model="search" wire:keydown.enter.prevent="scanOrSearch" autofocus
                        id="scan-input" placeholder="Scan barcode atau ketik SKU..."
                        class="w-full pl-10 md:pl-12 pr-4 py-2 md:py-3 bg-gray-50 border-2 border-transparent focus:border-primary focus:bg-white rounded-lg text-base md:text-lg outline-none transition">
                </div>
                @if ($notFoundMessage)
                    <div class="mt-3 flex items-center text-red-600 bg-red-50 p-2 rounded-lg text-sm font-medium">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        {{ $notFoundMessage }}
                    </div>
                @endif
            </div>

            <div class="bg-white border border-gray-200 rounded-xl shadow-sm flex flex-col overflow-hidden">
                <div class="overflow-x-auto overflow-y-auto flex-1 p-0">
                    <table class="w-full text-left min-w-[700px]">
                        <thead class="bg-gray-50/80 backdrop-blur sticky top-0 z-10 border-b border-gray-200">
                            <tr class="text-xs uppercase text-gray-500 font-semibold tracking-wider">
                                <th class="px-4 md:px-6 py-3 md:py-4">Informasi Produk</th>
                                <th class="px-4 py-3 md:py-4 text-right">Harga</th>
                                <th class="px-4 py-3 md:py-4 text-center w-32">Kuantitas</th>
                                <th class="px-4 py-3 md:py-4 text-right">Subtotal</th>
                                <th class="px-4 md:px-6 py-3 md:py-4"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($cart as $cartKey => $item)
                                <tr class="hover:bg-gray-50/50 transition">
                                    <td class="px-4 md:px-6 py-3 md:py-4">
                                        <div class="flex items-center gap-3 md:gap-4">
                                            <div
                                                class="w-10 h-10 md:w-12 md:h-12 rounded-lg bg-gray-100 border border-gray-200 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                                @if(!empty($item['image']))
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
                                                <p class="text-xs text-gray-500 mt-1">SKU: <span
                                                        class="font-mono bg-gray-100 px-1 py-0.5 rounded">{{ $item['sku'] ?? 'N/A' }}</span>
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td
                                        class="px-4 py-3 md:py-4 text-sm text-gray-600 text-right font-medium whitespace-nowrap">
                                        Rp {{ number_format($item['price'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 md:py-4">
                                        <div
                                            class="flex items-center justify-center gap-1 bg-gray-50 border border-gray-200 rounded-lg p-1 w-fit mx-auto">
                                            <!-- PERBAIKAN: Gunakan '{{ $cartKey }}' dengan tanda kutip -->
                                            <button wire:click="decreaseQty('{{ $cartKey }}')"
                                                class="w-7 h-7 md:w-8 md:h-8 rounded-md bg-white hover:bg-gray-100 shadow-sm text-gray-600 font-bold transition flex items-center justify-center">-</button>
                                            <span
                                                class="w-8 md:w-10 text-center font-bold text-gray-900 text-sm">{{ $item['quantity'] }}</span>
                                            <!-- PERBAIKAN: Gunakan '{{ $cartKey }}' dengan tanda kutip -->
                                            <button wire:click="increaseQty('{{ $cartKey }}')"
                                                class="w-7 h-7 md:w-8 md:h-8 rounded-md bg-white hover:bg-gray-100 shadow-sm text-gray-600 font-bold transition flex items-center justify-center">+</button>
                                        </div>
                                    </td>
                                    <td
                                        class="px-4 py-3 md:py-4 text-right text-sm font-bold text-gray-900 whitespace-nowrap">
                                        Rp {{ number_format($item['price'] * $item['quantity'], 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 md:px-6 py-3 md:py-4 text-right">
                                        <!-- PERBAIKAN: Gunakan '{{ $cartKey }}' dengan tanda kutip -->
                                        <button wire:click="removeItem('{{ $cartKey }}')"
                                            class="p-1.5 md:p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition"
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
                                    <td colspan="5" class="px-6 py-12 md:py-16 text-center">
                                        <div class="flex flex-col items-center justify-center text-gray-400">
                                            <svg class="w-12 h-12 md:w-16 md:h-16 mb-3 md:mb-4 text-gray-200" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z">
                                                </path>
                                            </svg>
                                            <p class="text-sm md:text-base font-medium text-gray-500">Keranjang masih kosong
                                            </p>
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
            <h2 class="text-base md:text-lg font-bold text-gray-900 mb-4 md:mb-6 flex items-center">
                <svg class="w-5 h-5 mr-2 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z">
                    </path>
                </svg>
                Detail Belanja
            </h2>

            <div class="space-y-3 md:space-y-4 mb-6">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Total Item</span>
                    <span class="font-medium text-gray-900">{{ $this->totalItems }} Barang</span>
                </div>

                <div class="border-t border-dashed border-gray-200 pt-3 md:pt-4 flex justify-between items-center">
                    <span class="font-bold text-gray-900 text-base md:text-lg">Subtotal</span>
                    <span class="font-bold text-xl md:text-2xl text-primary">Rp
                        {{ number_format($this->subtotal, 0, ',', '.') }}</span>
                </div>
            </div>

            <button wire:click="proceedToPayment" {{ count($cart) === 0 ? 'disabled' : '' }}
                class="w-full py-3 md:py-4 rounded-xl font-bold text-base md:text-lg flex justify-center items-center gap-2 transition 
                {{ count($cart) > 0 ? 'bg-primary text-white shadow-lg shadow-primary/30 hover:bg-primary/90' : 'bg-gray-200 text-gray-400 cursor-not-allowed' }}">
                Lanjut ke Pembayaran
                <svg class="w-5 h-5 md:w-6 md:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3">
                    </path>
                </svg>
            </button>
        </div>
    </div>
</div>