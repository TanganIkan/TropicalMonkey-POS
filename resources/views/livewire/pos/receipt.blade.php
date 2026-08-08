<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Transaction;

new #[Layout('components.layouts.app')] class extends Component {
    public Transaction $transaction;

    public function mount(Transaction $transaction)
    {
        $this->transaction = $transaction->load('items', 'outlet', 'user');
    }

    public function paymentLabel($method)
    {
        return match ($method) {
            'tunai' => 'Tunai',
            'qris' => 'QRIS',
            'kartu_lokal' => 'Kartu Lokal',
            'kartu_non_lokal' => 'Kartu Non-Lokal',
            default => $method,
        };
    }
};
?>

<div class="p-4 md:p-6 max-w-md mx-auto">
    <div class="text-center mb-6 print:hidden">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 text-green-500 mb-3">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>
        <h1 class="text-xl font-bold text-gray-900">Transaksi Berhasil</h1>
    </div>

    <!-- Struk -->
    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm font-mono text-sm">
        <div class="text-center mb-4 pb-4 border-b border-dashed border-gray-300">
            <p class="font-bold text-base">{{ $transaction->outlet->name ?? '-' }}</p>
            <p class="text-xs text-gray-500">{{ $transaction->outlet->address ?? '' }}</p>
        </div>

        <div class="mb-4 pb-4 border-b border-dashed border-gray-300 text-xs space-y-1">
            <div class="flex justify-between"><span>No. Order</span><span>{{ $transaction->order_id }}</span></div>
            <div class="flex justify-between">
                <span>Tanggal</span><span>{{ $transaction->created_at->format('d/m/Y H:i') }}</span>
            </div>
            <div class="flex justify-between"><span>Kasir</span><span>{{ $transaction->user->name ?? '-' }}</span></div>
        </div>

        <div class="mb-4 pb-4 border-b border-dashed border-gray-300 space-y-2">
            @foreach($transaction->items as $item)
                <div>
                    <div class="flex justify-between">
                        <span>{{ $item->product_name }}</span>
                        <span>Rp {{ number_format($item->subtotal, 0, ',', '.') }}</span>
                    </div>
                    <div class="text-xs text-gray-500">{{ $item->quantity }} x Rp
                        {{ number_format($item->price, 0, ',', '.') }}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="space-y-1 text-xs">
            <div class="flex justify-between"><span>Subtotal</span><span>Rp
                    {{ number_format($transaction->subtotal, 0, ',', '.') }}</span></div>
            @if($transaction->tax_amount > 0)
                <div class="flex justify-between"><span>Pajak (3%)</span><span>Rp
                        {{ number_format($transaction->tax_amount, 0, ',', '.') }}</span></div>
            @endif
            <div class="flex justify-between font-bold text-sm pt-2 border-t border-dashed border-gray-300">
                <span>Total</span><span>Rp {{ number_format($transaction->total, 0, ',', '.') }}</span>
            </div>
            <div class="flex justify-between pt-2">
                <span>Metode</span><span>{{ $this->paymentLabel($transaction->payment_method) }}</span>
            </div>

            @if($transaction->payment_method === 'tunai')
                <div class="flex justify-between"><span>Uang Dibayar</span><span>Rp
                        {{ number_format($transaction->cash_received, 0, ',', '.') }}</span></div>
                <div class="flex justify-between font-bold"><span>Kembalian</span><span>Rp
                        {{ number_format($transaction->change_amount, 0, ',', '.') }}</span></div>
            @endif
        </div>
    </div>

    <div class="flex gap-3 mt-6 print:hidden">
        <button onclick="window.print()"
            class="flex-1 py-3 bg-gray-100 text-gray-700 rounded-xl font-medium hover:bg-gray-200 transition">
            Cetak Struk
        </button>
        <a href="/pos" wire:navigate
            class="flex-1 text-center py-3 bg-primary text-white rounded-xl font-medium hover:bg-primary/90 transition">
            Transaksi Baru
        </a>
    </div>
</div>