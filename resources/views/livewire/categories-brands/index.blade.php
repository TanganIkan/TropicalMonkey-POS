<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Illuminate\Validation\Rule;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Stock;

new #[Layout('components.layouts.app')] class extends Component {
    public $categorySearch = '';
    public $brandSearch = '';

    // Daftar panjang dipotong; sisanya lewat tombol "Tampilkan semua" atau pencarian
    public const VISIBLE = 8;
    public $expanded = ['category' => false, 'brand' => false];

    // Form tambah/edit (satu form aktif di satu waktu): type = category|brand, id = null untuk data baru
    public $formType = null;
    public $formId = null;
    public $formName = '';
    public $formDescription = '';

    public function startCreate($type)
    {
        $this->openForm($type);
    }

    public function startEdit($type, $id)
    {
        $model = $type === 'brand' ? Brand::findOrFail($id) : Category::findOrFail($id);
        $this->openForm($type, $model->id, $model->name, $model->description ?? '');
    }

    protected function openForm($type, $id = null, $name = '', $description = '')
    {
        $this->resetValidation();
        $this->formType = $type === 'brand' ? 'brand' : 'category';
        $this->formId = $id;
        $this->formName = $name;
        $this->formDescription = $description;
    }

    public function cancelForm()
    {
        $this->reset(['formType', 'formId', 'formName', 'formDescription']);
        $this->resetValidation();
    }

    public function saveForm()
    {
        $isBrand = $this->formType === 'brand';
        $label = $isBrand ? 'Brand' : 'Kategori';

        $this->formName = trim($this->formName);
        $this->validate(
            [
                'formName' => ['required', 'min:2', Rule::unique($isBrand ? 'brands' : 'categories', 'name')->ignore($this->formId)],
                'formDescription' => 'nullable|string|max:255',
            ],
            [
                'formName.required' => "Nama " . strtolower($label) . " wajib diisi.",
                'formName.min' => "Nama " . strtolower($label) . " minimal 2 karakter.",
                'formName.unique' => "{$label} ini sudah ada.",
            ],
        );

        $data = ['name' => $this->formName];
        if ($isBrand) {
            $data['description'] = trim($this->formDescription);
        }

        $model = $isBrand ? Brand::class : Category::class;
        if ($this->formId) {
            $model::findOrFail($this->formId)->update($data);
        } else {
            $model::create($data);
            // Data baru belum punya produk sehingga ada di urutan bawah; buka daftar agar langsung terlihat
            $this->expanded[$this->formType] = true;
        }

        $this->dispatch('notify', [
            'title' => 'Berhasil!',
            'text' => $this->formId ? "{$label} berhasil diperbarui." : "{$label} baru berhasil ditambahkan.",
            'icon' => 'success',
        ]);
        $this->cancelForm();
    }

    public function deleteCategory($id)
    {
        $category = Category::findOrFail($id);
        if ($category->products()->count() > 0) {
            $this->dispatch('notify', [
                'title' => 'Akses Ditolak!',
                'text' => 'Kategori ini tidak bisa dihapus karena masih menampung produk.',
                'icon' => 'error'
            ]);
            return;
        }
        $category->delete();

        $this->dispatch('notify', [
            'title' => 'Dihapus!',
            'text' => 'Kategori berhasil dihapus dari sistem.',
            'icon' => 'success'
        ]);
    }

    public function deleteBrand($id)
    {
        $brand = Brand::findOrFail($id);
        if ($brand->products()->count() > 0) {
            $this->dispatch('notify', [
                'title' => 'Akses Ditolak!',
                'text' => 'Brand ini tidak bisa dihapus karena masih digunakan oleh produk.',
                'icon' => 'error'
            ]);
            return;
        }
        $brand->delete();

        $this->dispatch('notify', [
            'title' => 'Dihapus!',
            'text' => 'Brand berhasil dihapus dari sistem.',
            'icon' => 'success'
        ]);
    }

    // Total stok di outlet aktif per kategori/brand (varian nonaktif tidak dihitung, sama seperti daftar produk)
    protected function stockBy(string $column)
    {
        return Stock::query()
            ->join('products', 'products.id', '=', 'stocks.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'stocks.product_variant_id')
            ->where('stocks.outlet_id', session('current_outlet_id'))
            ->where(fn($q) => $q->whereNull('stocks.product_variant_id')->orWhere('product_variants.is_active', true))
            ->selectRaw("products.{$column} as group_id, SUM(stocks.quantity) as qty")
            ->groupBy("products.{$column}")
            ->pluck('qty', 'group_id');
    }

    public function with(): array
    {
        $search = fn($q, $term) => $q->when(trim($term) !== '', fn($q) => $q->where('name', 'like', '%' . trim($term) . '%'));

        return [
            // Yang paling banyak produknya di atas, supaya yang sering dipakai tidak tersembunyi
            'categories' => $search(Category::withCount(['products', 'variants']), $this->categorySearch)->orderByDesc('products_count')->orderBy('name')->get(),
            'brands' => $search(Brand::withCount(['products', 'variants']), $this->brandSearch)->orderByDesc('products_count')->orderBy('name')->get(),
            'categoryTotal' => Category::count(),
            'brandTotal' => Brand::count(),
            'categoryStock' => $this->stockBy('category_id'),
            'brandStock' => $this->stockBy('brand_id'),
        ];
    }
};
?>

@php
    $columns = [
        'category' => [
            'title' => 'Kategori',
            'sub' => 'Jenis barang, mis. T-Shirt, Topi',
            'icon' => 'tag',
            'items' => $categories,
            'total' => $categoryTotal,
            'stock' => $categoryStock,
            'search' => 'categorySearch',
            'param' => 'category',
            'delete' => 'deleteCategory',
        ],
        'brand' => [
            'title' => 'Brand',
            'sub' => 'Merek produk yang dijual',
            'icon' => 'star',
            'items' => $brands,
            'total' => $brandTotal,
            'stock' => $brandStock,
            'search' => 'brandSearch',
            'param' => 'brand',
            'delete' => 'deleteBrand',
        ],
    ];
@endphp

<div class="space-y-4">
    <x-ui.page-header title="Kategori & Brand" subtitle="Kelompokkan produk berdasarkan kategori dan brand. Klik salah satu untuk melihat produknya." />

    <div class="bento grid-cols-1 lg:grid-cols-2 items-start">
        @foreach ($columns as $type => $col)
            @php
                $maxProducts = max(1, $col['items']->max('products_count') ?? 0);
                $searching = trim($this->{$col['search']}) !== '';
                $showAll = $searching || $expanded[$type];
                $hidden = $col['items']->count() - $this::VISIBLE;
            @endphp
            <section class="tile-flush" wire:key="col-{{ $type }}">
                <!-- Header -->
                <div class="px-5 pt-5 pb-4 flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="tile-title flex items-center gap-2">{{ $col['title'] }} <span class="badge-light">{{ $col['total'] }}</span></h2>
                        <p class="tile-sub">{{ $col['sub'] }}</p>
                    </div>
                    <button type="button" wire:click="startCreate('{{ $type }}')" class="btn-primary btn-sm flex-shrink-0">
                        <x-icon name="plus" class="w-3.5 h-3.5" /> Tambah
                    </button>
                </div>

                <!-- Cari -->
                <div class="px-5 pb-4">
                    <div class="relative">
                        <x-icon name="search" class="w-4 h-4 absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400" />
                        <input type="text" wire:model.live.debounce.300ms="{{ $col['search'] }}" placeholder="Cari {{ strtolower($col['title']) }}..." class="input !pl-11">
                    </div>
                </div>

                <div class="border-t border-zinc-100 divide-y divide-zinc-100">
                    <!-- Form tambah -->
                    @if ($formType === $type && !$formId)
                        @include('partials.category-brand-form', ['type' => $type, 'title' => $col['title']])
                    @endif

                    @forelse ($showAll ? $col['items'] : $col['items']->take($this::VISIBLE) as $item)
                        @if ($formType === $type && (int) $formId === $item->id)
                            @include('partials.category-brand-form', ['type' => $type, 'title' => $col['title']])
                        @else
                            @php $qty = (int) ($col['stock'][$item->id] ?? 0); @endphp
                            <div class="group flex items-center gap-3 px-5 py-3.5 hover:bg-zinc-50/70 transition" wire:key="{{ $type }}-{{ $item->id }}">
                                <a href="/products?{{ $col['param'] }}={{ $item->id }}" wire:navigate class="flex items-center gap-3 min-w-0 flex-1" title="Lihat produk {{ $item->name }}">
                                    <div class="w-10 h-10 rounded-2xl bg-ink text-white flex items-center justify-center text-sm font-bold flex-shrink-0">{{ strtoupper(mb_substr($item->name, 0, 1)) }}</div>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold truncate group-hover:underline underline-offset-2">{{ $item->name }}</p>
                                        @if ($type === 'brand' && $item->description)
                                            <p class="text-xs text-zinc-400 truncate">{{ $item->description }}</p>
                                        @endif
                                        <p class="text-xs text-zinc-500 mt-0.5">
                                            {{ $item->products_count }} produk
                                            @if ($item->variants_count > 0) · {{ $item->variants_count }} varian @endif
                                            · <span class="{{ $qty <= 0 ? 'text-red-600' : '' }}">{{ number_format($qty, 0, ',', '.') }} stok</span>
                                        </p>
                                        <div class="h-1 rounded-full bg-zinc-100 mt-2 overflow-hidden max-w-[14rem]">
                                            <div class="h-full rounded-full bg-ink" style="width: {{ round($item->products_count / $maxProducts * 100) }}%"></div>
                                        </div>
                                    </div>
                                </a>
                                <div class="flex gap-1 flex-shrink-0">
                                    <button type="button" wire:click="startEdit('{{ $type }}', {{ $item->id }})" class="btn-icon" title="Edit"><x-icon name="pencil" class="w-4 h-4" /></button>
                                    <button type="button" x-data @click="confirmDeletion(() => $wire.{{ $col['delete'] }}({{ $item->id }}), @js($col['title'] . ' ' . $item->name))"
                                        class="btn-icon hover:!text-red-600 hover:!bg-red-50" title="Hapus"><x-icon name="trash" class="w-4 h-4" /></button>
                                </div>
                            </div>
                        @endif
                    @empty
                        <x-ui.empty :icon="$col['icon']" :title="trim($this->{$col['search']}) !== '' ? 'Tidak ditemukan' : 'Belum ada ' . strtolower($col['title'])"
                            :text="trim($this->{$col['search']}) !== '' ? 'Coba kata kunci lain.' : 'Klik Tambah untuk membuat yang pertama.'" />
                    @endforelse
                </div>
                @if (!$searching && $hidden > 0)
                    <button type="button" wire:click="$set('expanded.{{ $type }}', {{ $expanded[$type] ? 'false' : 'true' }})"
                        class="w-full border-t border-zinc-100 px-5 py-3 text-sm font-semibold text-zinc-600 hover:bg-zinc-50/70 hover:text-zinc-900 transition flex items-center justify-center gap-1.5">
                        {{ $expanded[$type] ? 'Tampilkan lebih sedikit' : 'Tampilkan semua (' . $hidden . ' lainnya)' }}
                        <x-icon name="chevron-right" class="w-4 h-4 transition {{ $expanded[$type] ? '-rotate-90' : 'rotate-90' }}" />
                    </button>
                @endif
                <p class="px-5 py-3 border-t border-zinc-100 text-[11px] text-zinc-400">Stok dihitung di outlet aktif. Yang masih punya produk tidak bisa dihapus.</p>
            </section>
        @endforeach
    </div>
</div>
