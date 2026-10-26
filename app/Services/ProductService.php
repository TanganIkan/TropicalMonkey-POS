<?php

namespace App\Services;

use App\Exceptions\PosException;
use App\Models\Category;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Stock;
use App\Models\StockHistory;
use App\Models\TransactionItem;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductService
{
    public function __construct(
        private StockService $stocks,
        private ActivityLogger $logger,
    ) {}

    /**
     * Cari barang dari kode hasil scan/ketik. SKU dan barcode dicek di produk
     * maupun varian, dengan urutan yang sama untuk POS dan semua import.
     *
     * @return array{0: Product, 1: ?ProductVariant}|null
     */
    public function findByCode(string $code): ?array
    {
        $code = trim($code);

        if ($code === '') {
            return null;
        }

        $variant = ProductVariant::with('product')
            ->where(fn($q) => $q->where('barcode', $code)->orWhere('sku', $code))
            ->first();

        if ($variant) {
            return [$variant->product, $variant];
        }

        $product = Product::where('barcode', $code)->orWhere('sku', $code)->first();

        return $product ? [$product, null] : null;
    }

    /**
     * Apakah kode (SKU/barcode) sudah dipakai produk atau varian lain.
     * Kode harus unik di keempat kolom agar hasil scan tidak ambigu.
     */
    public function codeTaken(?string $code, ?int $ignoreProductId = null, ?int $ignoreVariantId = null): bool
    {
        $code = trim((string) $code);

        if ($code === '') {
            return false;
        }

        $inProducts = Product::where(fn($q) => $q->where('sku', $code)->orWhere('barcode', $code))
            ->when($ignoreProductId, fn($q) => $q->whereKeyNot($ignoreProductId))
            ->exists();

        return $inProducts || ProductVariant::where(fn($q) => $q->where('sku', $code)->orWhere('barcode', $code))
            ->when($ignoreVariantId, fn($q) => $q->whereKeyNot($ignoreVariantId))
            ->exists();
    }

    public function generateSku(Category $category, string $productName): string
    {
        $categoryCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $category->name), 0, 3));
        $nameCode = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $productName), 0, 3));

        do {
            $sku = $categoryCode . '-' . $nameCode . '-' . mt_rand(1000, 9999);
        } while ($this->codeTaken($sku));

        return $sku;
    }

    /**
     * SKU varian otomatis: NAMA-UKURAN-WARNA, diberi akhiran -2, -3, ... bila sudah terpakai.
     */
    public function variantSku(string $productName, string $size, string $color): string
    {
        $base = strtoupper(Str::slug($productName, '-')) . '-' . strtoupper($size) . '-' . strtoupper(Str::slug($color, '-'));
        $sku = $base;

        for ($i = 2; $this->codeTaken($sku); $i++) {
            $sku = "{$base}-{$i}";
        }

        return $sku;
    }

    /**
     * Buat produk baru, atau tambahkan varian ke produk lama dengan nama yang sama.
     * Stok awal hanya diisi untuk outlet aktif; outlet lain dibuatkan baris stok 0.
     *
     * $data: name, category_id, brand_id, cost_price, sell_price, sku, barcode,
     *        has_variants, is_active, stock (non-varian),
     *        variants (list of ['size', 'color', 'stock'])
     *
     * @throws PosException
     */
    public function create(array $data, ?UploadedFile $photo, ?int $activeOutletId): Product
    {
        $hasVariants = (bool) $data['has_variants'];
        $variants = $data['variants'] ?? [];
        $productName = trim($data['name']);
        $brandId = $data['brand_id'] ?: null;
        $sku = trim((string) ($data['sku'] ?? ''));
        $barcode = trim((string) ($data['barcode'] ?? ''));

        if ($hasVariants && empty($variants)) {
            throw new PosException('Pilih minimal 1 ukuran dan 1 warna untuk membuat varian.', 'Varian Kosong!', 'warning');
        }

        if (!$hasVariants) {
            foreach (['SKU' => $sku, 'Barcode' => $barcode] as $label => $code) {
                if ($this->codeTaken($code)) {
                    throw new PosException("{$label} '{$code}' sudah dipakai produk/varian lain.", "{$label} Sudah Terpakai!");
                }
            }
        }

        // Produk dengan nama yang sama tidak digabung otomatis; varian baru ditambahkan
        // secara eksplisit lewat halaman Kelola Stok & Varian produk tersebut.
        if (Product::where('name', $productName)->exists()) {
            throw new PosException(
                "Produk '{$productName}' sudah terdaftar. Untuk menambah varian baru, buka menu Kelola Stok & Varian pada produk tersebut, atau gunakan nama lain.",
                'Produk Sudah Terdaftar!',
            );
        }

        $product = DB::transaction(function () use ($data, $photo, $activeOutletId, $productName, $brandId, $hasVariants, $variants, $sku, $barcode) {
            $imagePath = $photo ? $photo->store('products', 'public') : null;

            $product = Product::create([
                'name' => $productName,
                'category_id' => $data['category_id'],
                'brand_id' => $brandId,
                'cost_price' => $data['cost_price'] ?: 0,
                'sell_price' => $data['sell_price'],
                'sku' => $hasVariants ? null : ($sku ?: null),
                'barcode' => $hasVariants ? null : ($barcode ?: null),
                'image' => $imagePath,
                'has_variants' => $hasVariants,
                'is_active' => $data['is_active'],
            ]);

            $outlets = Outlet::where('is_active', true)->get();

            if ($hasVariants) {
                foreach ($variants as $combo) {
                    $variant = ProductVariant::create([
                        'product_id' => $product->id,
                        'sku' => $this->variantSku($product->name, $combo['size'], $combo['color']),
                        'size' => $combo['size'],
                        'color' => $combo['color'],
                        'sell_price' => $this->priceOrNull($combo['sell_price'] ?? null),
                    ]);

                    $this->initStock($product->id, $variant->id, $outlets, $activeOutletId, (int) ($combo['stock'] ?? 0));
                }
            } else {
                $this->initStock($product->id, null, $outlets, $activeOutletId, (int) ($data['stock'] ?? 0));
            }

            return $product;
        });

        $this->logger->log('product.create', "Menambah produk {$product->name}", $product);

        return $product;
    }

    /**
     * $data: name, category_id, brand_id, cost_price, sell_price, is_active,
     *        sku & barcode (hanya untuk produk tanpa varian)
     *
     * @throws PosException
     */
    public function update(Product $product, array $data, ?UploadedFile $photo, bool $removePhoto): Product
    {
        $name = trim($data['name']);
        if (Product::where('name', $name)->whereKeyNot($product->id)->exists()) {
            throw new PosException("Nama '{$name}' sudah dipakai produk lain.", 'Nama Sudah Dipakai!');
        }

        $codes = [];
        if (!$product->has_variants) {
            foreach (['sku' => 'SKU', 'barcode' => 'Barcode'] as $field => $label) {
                $code = trim((string) ($data[$field] ?? ''));
                if ($this->codeTaken($code, ignoreProductId: $product->id)) {
                    throw new PosException("{$label} '{$code}' sudah dipakai produk/varian lain.", "{$label} Sudah Terpakai!");
                }
                $codes[$field] = $code ?: null;
            }
        }

        $oldImage = $product->image;
        $imagePath = $oldImage;

        if ($photo) {
            $imagePath = $photo->store('products', 'public');
        } elseif ($removePhoto) {
            $imagePath = null;
        }

        $product->update($codes + [
            'name' => $name,
            'category_id' => $data['category_id'],
            'brand_id' => $data['brand_id'] ?: null,
            'cost_price' => $data['cost_price'] ?: 0,
            'sell_price' => $data['sell_price'],
            'image' => $imagePath,
            'is_active' => $data['is_active'],
        ]);

        // Foto lama baru dihapus setelah data produk berhasil disimpan
        if ($oldImage && $oldImage !== $imagePath) {
            Storage::disk('public')->delete($oldImage);
        }

        $changes = array_keys($product->getChanges());
        $this->logger->log('product.update', "Mengubah produk {$product->name}", $product, ['fields' => array_values(array_diff($changes, ['updated_at']))]);

        return $product;
    }

    /**
     * @throws PosException
     */
    public function delete(Product $product): void
    {
        try {
            DB::transaction(function () use ($product) {
                Stock::where('product_id', $product->id)->delete();
                ProductVariant::where('product_id', $product->id)->delete();
                $product->delete();
            });
        } catch (QueryException $e) {
            if ($e->getCode() == '23000') {
                throw new PosException('Produk ini gagal dihapus karena sudah memiliki riwayat penjualan/transaksi. Silakan Edit dan ubah statusnya menjadi "Draf (Disembunyikan)".', 'Tidak Bisa Dihapus!');
            }

            throw new PosException('Gagal menghapus produk: ' . $e->getMessage(), 'Terjadi Kesalahan!');
        }

        // Foto baru dihapus setelah produk benar-benar terhapus dari database
        if ($product->image && Storage::disk('public')->exists($product->image)) {
            Storage::disk('public')->delete($product->image);
        }

        $this->logger->log('product.delete', "Menghapus produk {$product->name}", properties: ['product_id' => $product->id]);
    }

    /**
     * @throws PosException
     */
    public function addVariant(Product $product, string $size, string $color, string $sku, ?string $barcode): ProductVariant
    {
        foreach (['SKU' => $sku, 'Barcode' => $barcode] as $label => $code) {
            if ($this->codeTaken($code)) {
                throw new PosException("{$label} '{$code}' sudah dipakai produk/varian lain.", "{$label} Sudah Terpakai!");
            }
        }

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'size' => trim($size),
            'color' => trim($color),
            'sku' => trim($sku),
            'barcode' => trim((string) $barcode) ?: null,
        ]);

        $product->refresh();

        return $variant;
    }

    /**
     * Varian tanpa riwayat apa pun dihapus permanen. Varian yang sudah punya
     * riwayat stok/penjualan hanya dinonaktifkan agar jejak auditnya tetap utuh.
     *
     * @return string 'deleted' | 'deactivated'
     */
    public function deleteVariant(int $variantId): string
    {
        $variant = ProductVariant::findOrFail($variantId);

        $hasHistory = StockHistory::where('product_variant_id', $variant->id)->exists()
            || TransactionItem::where('product_variant_id', $variant->id)->exists();

        $label = "{$variant->product->name} ({$variant->label()})";

        if ($hasHistory) {
            $variant->update(['is_active' => false]);
            $this->logger->log('variant.update', "Menonaktifkan varian {$label}", $variant);

            return 'deactivated';
        }

        DB::transaction(function () use ($variant) {
            Stock::where('product_variant_id', $variant->id)->delete();
            $variant->delete();
        });
        $this->logger->log('variant.update', "Menghapus varian {$label}", properties: ['variant_id' => $variant->id]);

        return 'deleted';
    }

    public function activateVariant(int $variantId): void
    {
        $variant = ProductVariant::with('product')->findOrFail($variantId);
        $variant->update(['is_active' => true]);
        $this->logger->log('variant.update', "Mengaktifkan varian {$variant->product->name} ({$variant->label()})", $variant);
    }

    /**
     * Ubah data varian: ukuran, warna, SKU, barcode, harga (kosong = ikut produk induk).
     *
     * @throws PosException
     */
    public function updateVariant(ProductVariant $variant, array $data): ProductVariant
    {
        $size = trim((string) $data['size']);
        $color = trim((string) $data['color']);
        $sku = trim((string) $data['sku']);
        $barcode = trim((string) ($data['barcode'] ?? ''));

        if ($size === '' || $color === '' || $sku === '') {
            throw new PosException('Ukuran, warna, dan SKU varian wajib diisi.', 'Data Belum Lengkap', 'warning');
        }

        foreach (['SKU' => $sku, 'Barcode' => $barcode] as $label => $code) {
            if ($this->codeTaken($code, ignoreVariantId: $variant->id)) {
                throw new PosException("{$label} '{$code}' sudah dipakai produk/varian lain.", "{$label} Sudah Terpakai!");
            }
        }

        $duplicate = ProductVariant::where('product_id', $variant->product_id)
            ->whereKeyNot($variant->id)
            ->where('size', $size)
            ->where('color', $color)
            ->exists();

        if ($duplicate) {
            throw new PosException("Varian {$size} - {$color} sudah ada di produk ini.", 'Varian Sudah Ada!', 'warning');
        }

        $variant->update([
            'size' => $size,
            'color' => $color,
            'sku' => $sku,
            'barcode' => $barcode ?: null,
            'sell_price' => $this->priceOrNull($data['sell_price'] ?? null),
            'cost_price' => $this->priceOrNull($data['cost_price'] ?? null),
        ]);

        $this->logger->log('variant.update', "Mengubah varian {$variant->product->name} ({$variant->label()})", $variant);

        return $variant;
    }

    protected function priceOrNull($value): ?float
    {
        return is_numeric($value) && $value >= 0 ? (float) $value : null;
    }

    protected function initStock(int $productId, ?int $variantId, $outlets, ?int $activeOutletId, int $quantity): void
    {
        foreach ($outlets as $outlet) {
            $qty = $outlet->id == $activeOutletId ? $quantity : 0;
            $this->stocks->increase($productId, $variantId, $outlet->id, $qty, null, 'initial');
        }
    }
}
