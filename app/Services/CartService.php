<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\PosException;
use App\Models\Product;
use App\Models\ProductVariant;

/**
 * Logika keranjang POS. Keranjang berupa array biasa (disimpan di session
 * oleh komponen) dengan key "{product_id}-{variant_id|single}".
 */
class CartService
{
    public function __construct(
        private StockService $stocks,
        private ProductService $products,
    ) {}

    /**
     * Cari produk/varian dari hasil scan barcode atau ketikan SKU.
     *
     * @return array{0: Product, 1: ?ProductVariant}
     * @throws PosException
     */
    public function resolveCode(string $term): array
    {
        $found = $this->products->findByCode($term);

        if (!$found) {
            throw new PosException("Barcode atau SKU '{$term}' tidak terdaftar di sistem.", 'Tidak Ditemukan!');
        }

        [$product, $variant] = $found;

        $this->ensureSellable($product, $variant);

        if (!$variant && $product->has_variants) {
            throw new PosException('Ini produk bervarian. Harap scan barcode/SKU ukurannya secara spesifik.', 'Pilih Varian!', 'info');
        }

        return [$product, $variant];
    }

    /**
     * @throws InsufficientStockException
     */
    public function add(array $cart, Product $product, ?ProductVariant $variant, int $outletId): array
    {
        $variantId = $variant?->id;
        $key = $this->makeKey($product->id, $variantId);
        $name = $this->itemName($product, $variant);
        $available = $this->stocks->available($product->id, $variantId, $outletId);
        $newQty = ($cart[$key]['quantity'] ?? 0) + 1;

        if ($newQty > $available) {
            throw new InsufficientStockException($name, $available, $newQty);
        }

        if (isset($cart[$key])) {
            $cart[$key]['quantity'] = $newQty;
            $cart[$key]['stock'] = $available;
        } else {
            $cart[$key] = [
                'name' => $name,
                'sku' => $variant ? $variant->sku : $product->sku,
                'image' => $product->image ?? null,
                'price' => $this->priceOf($product, $variant),
                'quantity' => 1,
                'stock' => $available,
            ];
        }

        return $cart;
    }

    /**
     * @throws InsufficientStockException
     */
    public function increase(array $cart, string $key): array
    {
        if (!isset($cart[$key])) {
            return $cart;
        }

        $item = $cart[$key];
        if ($item['quantity'] + 1 > $item['stock']) {
            throw new InsufficientStockException($item['name'], $item['stock'], $item['quantity'] + 1);
        }

        $cart[$key]['quantity']++;

        return $cart;
    }

    public function decrease(array $cart, string $key): array
    {
        if (!isset($cart[$key])) {
            return $cart;
        }

        if ($cart[$key]['quantity'] > 1) {
            $cart[$key]['quantity']--;
        } else {
            unset($cart[$key]);
        }

        return $cart;
    }

    /**
     * Samakan keranjang dengan data terbaru: harga jual ikut diperbarui, barang
     * yang sudah dihapus/nonaktif dikeluarkan.
     *
     * @return array{0: array, 1: list<string>} [keranjang baru, daftar perubahan]
     */
    public function refresh(array $cart): array
    {
        $changes = [];

        foreach ($cart as $key => $item) {
            [$productId, $variantId] = $this->parseKey($key);
            $product = Product::find($productId);
            $variant = $variantId ? ProductVariant::where('product_id', $productId)->find($variantId) : null;

            try {
                if (!$product || ($variantId && !$variant)) {
                    throw new PosException("{$item['name']} sudah dihapus dari sistem.");
                }
                $this->ensureSellable($product, $variant);
            } catch (PosException $e) {
                unset($cart[$key]);
                $changes[] = "{$item['name']} dikeluarkan dari keranjang: " . $e->getMessage();
                continue;
            }

            $price = $this->priceOf($product, $variant);
            if ((float) $item['price'] !== $price) {
                $changes[] = "Harga {$item['name']} berubah: Rp " . number_format($item['price'], 0, ',', '.')
                    . ' → Rp ' . number_format($price, 0, ',', '.') . '.';
                $cart[$key]['price'] = $price;
            }
        }

        return [$cart, $changes];
    }

    public function totalItems(array $cart): int
    {
        return collect($cart)->sum('quantity');
    }

    public function subtotal(array $cart): float
    {
        return collect($cart)->sum(fn($item) => $item['price'] * $item['quantity']);
    }

    public function makeKey(int $productId, ?int $variantId): string
    {
        return $productId . '-' . ($variantId ?? 'single');
    }

    /**
     * @return array{0: int, 1: ?int}
     */
    public function parseKey(string $key): array
    {
        [$productId, $variantPart] = explode('-', $key, 2);

        return [(int) $productId, $variantPart === 'single' ? null : (int) $variantPart];
    }

    /**
     * Atur diskon satu item. $type: 'percent' | 'amount'; nilai 0 = hapus diskon.
     */
    public function setDiscount(array $cart, string $key, string $type, $value): array
    {
        if (!isset($cart[$key])) {
            return $cart;
        }

        $value = (float) $value;
        if ($value <= 0) {
            unset($cart[$key]['discount']);
        } else {
            $cart[$key]['discount'] = ['type' => $type === 'percent' ? 'percent' : 'amount', 'value' => $value];
        }

        return $cart;
    }

    public function priceOf(Product $product, ?ProductVariant $variant): float
    {
        return $variant ? $variant->setRelation('product', $product)->effectiveSellPrice() : (float) $product->sell_price;
    }

    protected function itemName(Product $product, ?ProductVariant $variant): string
    {
        return $variant ? "{$product->name} ({$variant->size} - {$variant->color})" : $product->name;
    }

    /**
     * @throws PosException
     */
    protected function ensureSellable(Product $product, ?ProductVariant $variant): void
    {
        if (!$product->is_active) {
            throw new PosException("Produk '{$product->name}' masih bersifat draf dan tidak bisa dipesan.", 'Produk Nonaktif');
        }

        if ($variant && !$variant->is_active) {
            throw new PosException("Varian {$variant->label()} dari '{$product->name}' sudah dinonaktifkan.", 'Varian Nonaktif');
        }
    }
}
