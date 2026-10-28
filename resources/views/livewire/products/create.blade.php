<?php

use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use App\Exceptions\PosException;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Outlet;
use App\Services\ProductService;

new #[Layout('components.layouts.app')] class extends Component {
    use WithFileUploads;

    #[Rule('required|min:3', message: ['name.required' => 'Nama produk wajib diisi.', 'name.min' => 'Nama produk minimal 3 karakter.'])]
    public $name = '';

    #[Rule('required', message: 'Kategori wajib dipilih.')]
    public $category_id = '';

    #[Rule('nullable|exists:brands,id', message: 'Brand tidak valid.')]
    public $brand_id = '';

    public $cost_price = null;

    #[Rule('required|numeric|min:0', message: ['sell_price.required' => 'Harga jual wajib diisi.', 'sell_price.numeric' => 'Harga jual harus berupa angka.', 'sell_price.min' => 'Harga jual tidak boleh minus.'])]
    public $sell_price = null;

    public $sku = '';
    public $barcode = '';
    public $hasVariants = false;
    public $is_active = 1;

    #[Rule('nullable|image|max:5120', message: ['photo.image' => 'Foto harus berupa gambar (JPG/PNG).', 'photo.max' => 'Ukuran foto maksimal 5MB.'])]
    public $photo = null;

    // ==== Varian ====
    public $availableSizes = [];
    public $newSize = '';
    public $variantPrices = [];
    public $selectedSizes = [];
    public $availableColors = [];
    public $newColor = '';
    public $variantStocks = [];

    // ==== Stok non-varian ====
    public $simpleStocks = [];

    public function mount()
    {
        // Pilihan ukuran diatur owner di halaman Pengaturan
        $this->availableSizes = \App\Models\Setting::sizeOptions();
    }

    // Ukuran di luar pilihan standar (hanya untuk produk ini)
    public function addSize()
    {
        $size = strtoupper(trim($this->newSize));
        if ($size !== '' && !in_array($size, $this->availableSizes)) {
            $this->availableSizes[] = $size;
            $this->selectedSizes[] = $size;
        }
        $this->newSize = '';
    }

    public function addColor()
    {
        $color = trim($this->newColor);
        if ($color !== '' && !in_array($color, $this->availableColors)) {
            $this->availableColors[] = $color;
        }
        $this->newColor = '';
    }

    public function removeColor($color)
    {
        $this->availableColors = array_values(array_diff($this->availableColors, [$color]));
    }

    public function getVariantCombinationsProperty()
    {
        if (empty($this->selectedSizes) || empty($this->availableColors)) {
            return [];
        }

        $combinations = [];
        foreach ($this->selectedSizes as $size) {
            foreach ($this->availableColors as $color) {
                $combinations[] = ['size' => $size, 'color' => $color];
            }
        }

        return $combinations;
    }

    public function getActiveOutletProperty()
    {
        return Outlet::find(session('current_outlet_id'));
    }

    public function with(): array
    {
        return [
            'categories' => Category::orderBy('name')->get(),
            'brands' => Brand::orderBy('name')->get(),
        ];
    }

    public function generateSku(ProductService $products)
    {
        if (empty(trim($this->name)) || empty($this->category_id)) {
            $this->dispatch('notify', [
                'title' => 'Data Belum Lengkap!',
                'text' => 'Isi Nama Produk dan pilih Kategori terlebih dahulu untuk men-generate SKU Auto.',
                'icon' => 'warning'
            ]);
            return;
        }

        $this->sku = $products->generateSku(Category::find($this->category_id), $this->name);
    }

    public function handleBarcodeScan()
    {
        if (!empty($this->barcode) && empty($this->sku)) {
            $this->sku = $this->barcode;
        }
    }

    public function removePhoto()
    {
        $this->photo = null;
    }

    public function save(ProductService $products)
    {
        $this->validate();

        $activeOutletId = session('current_outlet_id');

        // Stok awal yang diisi user hanya untuk outlet aktif
        $variants = collect($this->variantCombinations)->map(fn($combo) => $combo + [
            'stock' => $this->variantStocks[$combo['size'] . '|' . $combo['color']][$activeOutletId] ?? 0,
            'sell_price' => $this->variantPrices[$combo['size'] . '|' . $combo['color']] ?? null,
        ])->all();

        try {
            $products->create([
                'name' => $this->name,
                'category_id' => $this->category_id,
                'brand_id' => $this->brand_id,
                'cost_price' => $this->cost_price,
                'sell_price' => $this->sell_price,
                'sku' => $this->sku,
                'barcode' => $this->barcode,
                'has_variants' => $this->hasVariants,
                'is_active' => $this->is_active,
                'stock' => $this->simpleStocks[$activeOutletId] ?? 0,
                'variants' => $variants,
            ], $this->photo, $activeOutletId);
        } catch (PosException $e) {
            $this->dispatch('notify', $e->toNotify());
            return;
        }

        session()->flash('notify', ['title' => 'Produk Dibuat', 'text' => 'Data produk baru berhasil disimpan.', 'icon' => 'success']);
        $this->redirect('/products', navigate: true);
    }
};
?>

<form wire:submit="save" class="space-y-4">
    <x-ui.page-header title="Tambah Produk" back="/products" back-label="Data produk">
        <a href="/products" wire:navigate class="btn-secondary">Batal</a>
        <button type="submit" class="btn-primary">
            <span wire:loading.remove wire:target="save">Simpan Produk</span>
            <span wire:loading wire:target="save">Menyimpan...</span>
        </button>
    </x-ui.page-header>

    <div class="bento grid-cols-1 lg:grid-cols-3 items-start">
        <div class="lg:col-span-2 space-y-3 md:space-y-4">
            <!-- Informasi dasar -->
            <div class="tile">
                <h2 class="tile-title mb-4">Informasi Dasar</h2>
                <div class="space-y-4">
                    <div>
                        <label class="label">Nama produk <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="name" placeholder="mis. Kaos Polos Hitam" class="input">
                        @error('name') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="label">Kategori <span class="text-red-500">*</span></label>
                            <select wire:model="category_id" class="select">
                                <option value="">Pilih kategori</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="label">Brand</label>
                            <select wire:model="brand_id" class="select">
                                <option value="">Pilih brand</option>
                                @foreach ($brands as $brand)
                                    <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                                @endforeach
                            </select>
                            @error('brand_id') <span class="field-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>
            </div>

            <!-- Harga & SKU -->
            <div class="tile">
                <h2 class="tile-title mb-4">Harga & Kode</h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4">
                    <div>
                        <label class="label">Harga modal</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-zinc-400">Rp</span>
                            <input type="number" wire:model.live.debounce.500ms="cost_price" placeholder="0" class="input !pl-11">
                        </div>
                    </div>
                    <div>
                        <label class="label">Harga jual <span class="text-red-500">*</span></label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-zinc-400">Rp</span>
                            <input type="number" wire:model.live.debounce.500ms="sell_price" placeholder="0" class="input !pl-11 font-semibold">
                        </div>
                        @error('sell_price') <span class="field-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="col-span-2 sm:col-span-1 rounded-2xl bg-ink text-white px-4 py-3 flex sm:flex-col items-center sm:items-start justify-between">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-white/50">Est. margin</span>
                        <span class="text-xl font-bold">
                            @if ($cost_price > 0 && $sell_price > $cost_price)
                                {{ round((($sell_price - $cost_price) / $cost_price) * 100) }}%
                            @elseif ($sell_price > 0 && empty($cost_price))
                                100%
                            @else
                                --%
                            @endif
                        </span>
                    </div>
                </div>

                @unless ($hasVariants)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="label">Kode SKU</label>
                            <div class="flex gap-2">
                                <input type="text" wire:model="sku" placeholder="mis. KOS-HTM-M" class="input font-mono">
                                <button type="button" wire:click="generateSku" class="btn-secondary !px-3 text-xs">Auto</button>
                            </div>
                        </div>
                        <div>
                            <label class="label">Barcode (opsional)</label>
                            <input type="text" wire:model="barcode" wire:keydown.enter.prevent="handleBarcodeScan" placeholder="Klik lalu tembak scanner" class="input font-mono">
                        </div>
                    </div>
                @endunless
            </div>

            <!-- Varian -->
            <div class="tile">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="tile-title">Varian Produk</h2>
                        <p class="tile-sub">Aktifkan bila produk punya ukuran/warna.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" wire:model.live="hasVariants" class="sr-only peer">
                        <span class="w-11 h-6 rounded-full bg-zinc-200 peer-checked:bg-ink transition"></span>
                        <span class="absolute left-1 top-1 w-4 h-4 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                    </label>
                </div>
                @error('hasVariants') <span class="field-error">{{ $message }}</span> @enderror

                @if ($hasVariants)
                    <div class="mt-5 pt-5 border-t border-zinc-100 space-y-5" wire:key="variant-section">
                        <div wire:key="size-picker">
                            <label class="label">Ukuran</label>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($availableSizes as $size)
                                    <label for="ukuran-{{ $size }}" class="cursor-pointer" wire:key="size-{{ $size }}">
                                        <input type="checkbox" id="ukuran-{{ $size }}" wire:model.live="selectedSizes" value="{{ $size }}" class="sr-only peer">
                                        <span class="chip peer-checked:border-ink peer-checked:bg-ink peer-checked:text-white min-w-[3rem]">{{ $size }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="flex gap-2 mt-3 max-w-xs">
                                <input type="text" wire:model="newSize" wire:keydown.enter.prevent="addSize" placeholder="Ukuran lain (3XL / 42)" class="input !py-2">
                                <button type="button" wire:click="addSize" class="btn-secondary !py-2 whitespace-nowrap">+ Ukuran</button>
                            </div>
                        </div>

                        <div>
                            <label class="label">Warna</label>
                            <div class="flex gap-2">
                                <input type="text" wire:model="newColor" wire:keydown.enter.prevent="addColor" placeholder="Ketik warna lalu Enter (mis. Hitam)" class="input">
                                <button type="button" wire:click="addColor" class="btn-primary">Tambah</button>
                            </div>
                            <div class="flex flex-wrap gap-2 mt-3">
                                @foreach ($availableColors as $color)
                                    <span wire:key="color-{{ $color }}" class="chip !cursor-default">
                                        {{ $color }}
                                        <button type="button" wire:click="removeColor(@js($color))" class="text-zinc-400 hover:text-red-600" aria-label="Hapus warna"><x-icon name="x" class="w-3.5 h-3.5" /></button>
                                    </span>
                                @endforeach
                            </div>
                        </div>

                        @if (count($this->variantCombinations) > 0)
                            <div class="rounded-2xl border border-zinc-200 overflow-x-auto" wire:key="combo-table">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Varian</th>
                                            <th class="text-center">Harga jual <span class="normal-case font-normal">(kosong = harga utama)</span></th>
                                            <th class="text-center">Stok awal · {{ $this->activeOutlet->name ?? 'Outlet aktif' }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($this->variantCombinations as $combo)
                                            @php $key = $combo['size'] . '|' . $combo['color']; @endphp
                                            <tr wire:key="combo-{{ $key }}">
                                                <td class="font-semibold whitespace-nowrap">{{ $combo['size'] }} · {{ $combo['color'] }}</td>
                                                <td class="text-center">
                                                    <input type="number" min="0" wire:model="variantPrices.{{ $key }}" placeholder="{{ $sell_price ?: '-' }}" class="input !py-1.5 !w-32 text-center mx-auto">
                                                </td>
                                                <td class="text-center">
                                                    <input type="number" min="0" wire:model="variantStocks.{{ $key }}.{{ session('current_outlet_id') }}" placeholder="0" class="input !py-1.5 !w-24 text-center mx-auto font-semibold">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <x-ui.empty icon="layers" title="Belum ada kombinasi" text="Pilih minimal 1 ukuran dan tambahkan 1 warna." class="!py-6 rounded-2xl border-2 border-dashed border-zinc-200" />
                        @endif
                    </div>
                @else
                    <div class="mt-5 pt-5 border-t border-zinc-100">
                        <label class="label flex items-center justify-between">Stok awal <span class="badge-light">{{ $this->activeOutlet->name ?? 'Outlet aktif' }}</span></label>
                        <input type="number" min="0" wire:model="simpleStocks.{{ session('current_outlet_id') }}" placeholder="0" class="input-lg">
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-3 md:space-y-4 lg:sticky lg:top-4">
            <!-- Foto -->
            <div class="tile">
                <h2 class="tile-title mb-4">Foto Produk</h2>
                @if ($photo)
                    <div class="relative rounded-2xl overflow-hidden">
                        <img src="{{ $photo->temporaryUrl() }}" class="w-full h-56 object-cover">
                        <button type="button" wire:click="removePhoto" class="absolute top-3 right-3 w-9 h-9 rounded-xl bg-white shadow flex items-center justify-center hover:text-red-600" aria-label="Hapus foto">
                            <x-icon name="x" class="w-4 h-4" />
                        </button>
                    </div>
                @else
                    <label class="block rounded-2xl border-2 border-dashed border-zinc-200 bg-zinc-50 p-8 text-center cursor-pointer hover:border-zinc-400 transition">
                        <div class="w-12 h-12 rounded-2xl bg-white mx-auto mb-3 flex items-center justify-center"><x-icon name="image" class="text-zinc-400" /></div>
                        <p class="text-sm font-semibold">Pilih gambar</p>
                        <p class="text-xs text-zinc-400 mt-1">PNG / JPG, maks. 5MB</p>
                        <input type="file" wire:model="photo" accept="image/*" class="hidden">
                    </label>
                @endif
                <p wire:loading wire:target="photo" class="text-xs text-zinc-500 mt-3">Mengunggah...</p>
                @error('photo') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <!-- Status -->
            <div class="tile">
                <h2 class="tile-title mb-3">Status</h2>
                <select wire:model="is_active" class="select">
                    <option value="1">Aktif & ditampilkan</option>
                    <option value="0">Draf (disembunyikan)</option>
                </select>
                <p class="text-xs text-zinc-500 mt-2">Hanya produk aktif yang bisa di-scan di kasir.</p>
            </div>
        </div>
    </div>
</form>
