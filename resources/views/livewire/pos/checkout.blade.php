<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Stock;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

new #[Layout('components.layouts.app')] class extends Component {
    public $cart = [];
    public $paymentMethod = 'tunai'; // tunai | qris | kartu_lokal | kartu_non_lokal
    public $cashReceived = null;

    public function mount()
    {
        $this->cart = session()->get('pos_active_cart', []);

        if (empty($this->cart)) {
            $this->redirect('/pos', navigate: true);
        }
    }

    public function getOutletProperty()
    {
        return Outlet::find(session('current_outlet_id'));
    }

    public function getSubtotalProperty()
    {
        return collect($this->cart)->sum(fn($item) => $item['price'] * $item['quantity']);
    }

    public function getTaxAmountProperty()
    {
        return $this->paymentMethod === 'kartu_non_lokal'
            ? round($this->subtotal * 0.03)
            : 0;
    }

    public function getTotalProperty()
    {
        return $this->subtotal + $this->taxAmount;
    }

    public function getChangeProperty()
    {
        if ($this->paymentMethod !== 'tunai' || !$this->cashReceived) {
            return 0;
        }
        return max(0, $this->cashReceived - $this->total);
    }

    public function selectPayment($method)
    {
        $this->paymentMethod = $method;
        $this->cashReceived = null;
    }

    public function processPayment()
    {
        if (empty($this->cart)) {
            return;
        }

        if ($this->paymentMethod === 'tunai') {
            if (!$this->cashReceived || $this->cashReceived < $this->total) {
                $this->addError('cashReceived', 'Nominal yang dibayar kurang dari total belanja.');
                return;
            }
        }

        $outletId = session('current_outlet_id');
        $subtotal = $this->subtotal;
        $taxAmount = $this->taxAmount;
        $total = $this->total;

        $transaction = DB::transaction(function () use ($outletId, $subtotal, $taxAmount, $total) {
            $transaction = Transaction::create([
                'order_id' => 'TRX-' . date('ymd') . '-' . strtoupper(Str::random(5)),
                'outlet_id' => $outletId,
                'user_id' => auth()->id(),
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'payment_method' => $this->paymentMethod,
                'cash_received' => $this->paymentMethod === 'tunai' ? $this->cashReceived : null,
                'change_amount' => $this->paymentMethod === 'tunai' ? $this->change : null,
            ]);

            foreach ($this->cart as $cartKey => $item) {
                [$productId, $variantPart] = explode('-', $cartKey, 2);
                $variantId = $variantPart === 'single' ? null : $variantPart;

                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'product_name' => $item['name'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'subtotal' => $item['price'] * $item['quantity'],
                ]);

                // Potong stok di outlet aktif
                $stock = Stock::where('product_id', $productId)
                    ->where('outlet_id', $outletId)
                    ->where('product_variant_id', $variantId)
                    ->first();

                if ($stock) {
                    $stock->decrement('quantity', $item['quantity']);
                }
            }

            return $transaction;
        });

        // Kosongkan keranjang
        session()->forget('pos_active_cart');

        $this->redirect('/pos/receipt/' . $transaction->id, navigate: true);
    }
};
?>

<div class="p-4 md:p-6 max-w-8xl mx-auto">
    <div class="mb-6">
        <a href="/pos" wire:navigate class="text-sm text-gray-500 hover:text-gray-700 flex items-center mb-2">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18">
                </path>
            </svg>
            Kembali ke POS
        </a>
        <h1 class="text-2xl font-bold text-gray-900">Pembayaran</h1>
    </div>

    <!-- Ringkasan Belanja -->
    <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-6 shadow-sm mb-4">
        <h2 class="text-lg font-bold mb-4">Ringkasan</h2>
        <div class="space-y-2 max-h-64 overflow-y-auto mb-4">
            @foreach($cart as $item)
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">{{ $item['name'] }} <span
                            class="text-gray-400">x{{ $item['quantity'] }}</span></span>
                    <span class="font-medium">Rp {{ number_format($item['price'] * $item['quantity'], 0, ',', '.') }}</span>
                </div>
            @endforeach
        </div>
        <div class="border-t border-gray-200 pt-3 space-y-2">
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Subtotal</span>
                <span>Rp {{ number_format($this->subtotal, 0, ',', '.') }}</span>
            </div>
            @if($this->taxAmount > 0)
                <div class="flex justify-between text-sm">
                    <span class="text-gray-500">Pajak Kartu Non-Lokal (3%)</span>
                    <span>Rp {{ number_format($this->taxAmount, 0, ',', '.') }}</span>
                </div>
            @endif
            <div class="flex justify-between text-lg font-bold pt-2 border-t border-dashed border-gray-200">
                <span>Total</span>
                <span class="text-primary">Rp {{ number_format($this->total, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>

    <!-- Metode Pembayaran -->
    <div class="bg-white border border-gray-200 rounded-xl p-4 sm:p-6 shadow-sm mb-4">
        <h2 class="text-lg font-bold mb-4">Metode Pembayaran</h2>

        <!-- Grid diubah menjadi 2 kolom (atau 4 kolom di layar besar) agar tombolnya rapi -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <button type="button" wire:click="selectPayment('tunai')"
                class="py-3 rounded-lg border-2 font-medium text-sm transition {{ $paymentMethod === 'tunai' ? 'border-primary text-primary bg-primary/5' : 'border-gray-200 text-gray-500' }}">
                Tunai
            </button>
            <button type="button" wire:click="selectPayment('qris')"
                class="py-3 rounded-lg border-2 font-medium text-sm transition flex flex-col items-center justify-center {{ $paymentMethod === 'qris' ? 'border-primary text-primary bg-primary/5' : 'border-gray-200 text-gray-500' }}">
                <span>QRIS</span>
            </button>
            <button type="button" wire:click="selectPayment('kartu_lokal')"
                class="py-3 rounded-lg border-2 font-medium text-sm transition {{ $paymentMethod === 'kartu_lokal' ? 'border-primary text-primary bg-primary/5' : 'border-gray-200 text-gray-500' }}">
                Kartu Lokal
            </button>
            <button type="button" wire:click="selectPayment('kartu_non_lokal')"
                class="py-3 rounded-lg border-2 font-medium text-sm transition flex flex-col items-center justify-center {{ $paymentMethod === 'kartu_non_lokal' ? 'border-primary text-primary bg-primary/5' : 'border-gray-200 text-gray-500' }}">
                <span>Kartu Luar</span>
                <span class="text-[10px] opacity-70 leading-none mt-1">+3% tax</span>
            </button>
        </div>

        @if($paymentMethod === 'tunai')
            <div class="mt-5">
                <label class="block text-sm font-medium text-gray-700 mb-1">Uang Diterima</label>
                <div class="relative">
                    <span class="absolute left-4 top-3 text-gray-500">Rp</span>
                    <input type="number" wire:model.live="cashReceived" placeholder="0"
                        class="w-full pl-10 pr-4 py-3 border-2 border-gray-200 rounded-lg text-lg font-bold outline-none focus:border-primary">
                </div>
                @error('cashReceived') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror

                @if($cashReceived && $cashReceived >= $this->total)
                    <div class="mt-3 flex justify-between items-center bg-green-50 border border-green-200 rounded-lg p-3">
                        <span class="text-sm font-medium text-green-700">Kembalian</span>
                        <span class="text-lg font-bold text-green-700">Rp {{ number_format($this->change, 0, ',', '.') }}</span>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <button wire:click="processPayment" wire:loading.attr="disabled"
        class="w-full py-4 bg-primary text-white rounded-xl font-bold text-lg shadow-lg shadow-primary/30 hover:bg-primary/90 transition">
        <span wire:loading.remove wire:target="processPayment">Selesaikan Transaksi</span>
        <span wire:loading wire:target="processPayment">Memproses...</span>
    </button>
</div>