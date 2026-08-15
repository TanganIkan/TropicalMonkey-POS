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
            'tunai' => 'TUNAI',
            'qris' => 'QRIS',
            'kartu_lokal' => 'KARTU DEBIT/KREDIT',
            'kartu_non_lokal' => 'KARTU LUAR NEGERI',
            default => strtoupper($method),
        };
    }
};
?>

<div class="p-4 md:p-8 max-w-sm mx-auto bg-gray-50/50 min-h-screen" x-data
    x-init="setTimeout(() => window.print(), 500)">

    <div class="text-center mb-6 print:hidden">
        <div
            class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-900 text-white mb-4 shadow-lg shadow-gray-900/20">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>
        <h1 class="text-2xl font-black text-gray-900 tracking-tight">Transaksi Berhasil</h1>
        <p class="text-sm text-gray-500 mt-1">Struk akan otomatis dicetak.</p>
    </div>

    <!-- Struk Fisik -->
    <div id="print-area" class="bg-white p-6 shadow-sm font-mono text-sm relative border border-gray-200">
        <!-- Efek Kertas Sobek (Zigzag) di atas dan bawah khusus untuk tampilan web -->
        <div
            class="absolute -top-1.5 left-0 right-0 h-3 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4IiBoZWlnaHQ9IjgiPjxwb2x5Z29uIHBvaW50cz0iMCwwIDQsOCA4LDAiIGZpbGw9IiNmOWZhZmIiLz48L3N2Zz4=')] bg-repeat-x print:hidden">
        </div>

        <div class="text-center mb-5 pb-5 border-b-2 border-dashed border-gray-300">
            <h2 class="font-black text-lg tracking-widest uppercase">{{ $transaction->outlet->name ?? 'Toko Anda' }}
            </h2>
            <p class="text-xs text-gray-500 mt-1 uppercase">{{ $transaction->outlet->address ?? 'Alamat Outlet' }}</p>
        </div>

        <div class="mb-5 pb-5 border-b-2 border-dashed border-gray-300 text-xs space-y-2">
            <div class="flex justify-between">
                <span class="text-gray-500">NO. ORDER</span>
                <span class="font-bold text-gray-900">{{ $transaction->order_id }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">WAKTU</span>
                <span class="font-bold text-gray-900">{{ $transaction->created_at->format('d/m/Y H:i') }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">KASIR</span>
                <span class="font-bold text-gray-900 uppercase">{{ $transaction->user->name ?? '-' }}</span>
            </div>
        </div>

        <div class="mb-5 pb-5 border-b-2 border-dashed border-gray-300 space-y-3">
            @foreach($transaction->items as $item)
                <div>
                    <div class="flex justify-between font-bold text-gray-900">
                        <span class="truncate pr-2">{{ $item->product_name }}</span>
                        <span>{{ number_format($item->subtotal, 0, ',', '.') }}</span>
                    </div>
                    <div class="text-xs text-gray-500 font-medium">
                        {{ $item->quantity }} x {{ number_format($item->price, 0, ',', '.') }}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="space-y-1.5 text-xs">
            <div class="flex justify-between text-gray-600 font-bold">
                <span>SUBTOTAL</span>
                <span>{{ number_format($transaction->subtotal, 0, ',', '.') }}</span>
            </div>

            @if($transaction->tax_amount > 0)
                <div class="flex justify-between text-gray-600 font-bold">
                    <span>PAJAK (3%)</span>
                    <span>{{ number_format($transaction->tax_amount, 0, ',', '.') }}</span>
                </div>
            @endif

            <div
                class="flex justify-between font-black text-base pt-3 mt-2 border-t-2 border-dashed border-gray-300 text-gray-900 tracking-tight">
                <span>TOTAL</span>
                <span>Rp {{ number_format($transaction->total, 0, ',', '.') }}</span>
            </div>

            <div class="flex justify-between pt-3 text-gray-500 font-bold">
                <span>METODE</span>
                <span class="text-gray-900">{{ $this->paymentLabel($transaction->payment_method) }}</span>
            </div>

            @if($transaction->payment_method === 'tunai')
                <div class="flex justify-between text-gray-500 font-bold">
                    <span>TUNAI</span>
                    <span class="text-gray-900">{{ number_format($transaction->cash_received, 0, ',', '.') }}</span>
                </div>
                <div class="flex justify-between font-bold text-gray-900">
                    <span>KEMBALI</span>
                    <span>{{ number_format($transaction->change_amount, 0, ',', '.') }}</span>
                </div>
            @endif
        </div>

        <div class="mt-8 text-center text-xs text-gray-500 font-medium space-y-1">
            <p>Terima kasih atas kunjungan Anda!</p>
            <p>Barang yang sudah dibeli tidak dapat ditukar/dikembalikan.</p>
        </div>

        <div
            class="absolute -bottom-1.5 left-0 right-0 h-3 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4IiBoZWlnaHQ9IjgiPjxwb2x5Z29uIHBvaW50cz0iMCw4IDQsMCA4LDgiIGZpbGw9IiNmOWZhZmIiLz48L3N2Zz4=')] bg-repeat-x print:hidden">
        </div>
    </div>

    <div class="flex flex-col gap-3 mt-8 print:hidden">
        <button onclick="window.print()"
            class="w-full py-3.5 bg-white border-2 border-gray-200 text-gray-900 rounded-xl font-bold hover:bg-gray-50 hover:border-gray-300 transition shadow-sm">
            Cetak Ulang Struk
        </button>
        <a href="/pos" wire:navigate
            class="w-full text-center py-3.5 bg-gray-900 text-white rounded-xl font-bold hover:bg-black transition shadow-lg shadow-gray-900/20">
            Transaksi Baru (Kembali ke POS)
        </a>
    </div>

    <!-- Gaya CSS khusus untuk mode pencetakan -->
    <style>
        @media print {

            /* Atur ukuran kertas struk kasir (Thermal 80mm) */
            @page {
                margin: 0;
                size: 80mm auto;
            }

            /* Sembunyikan SEMUA elemen di layar secara default */
            body * {
                visibility: hidden !important;
            }

            /* Tampilkan hanya area struk dan elemen di dalamnya */
            #print-area,
            #print-area * {
                visibility: visible !important;
                color: #000 !important;
                /* Paksa tinta hitam agar jelas */
            }

            /* Atur ulang posisi dan ukuran struk saat dicetak */
            #print-area {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 80mm !important;
                max-width: 80mm !important;
                margin: 0 !important;
                padding: 5mm !important;
                /* Beri jarak aman dari tepi kertas */
                border: none !important;
                box-shadow: none !important;
            }

            /* Hapus background agar lebih bersih di printer */
            * {
                background: transparent !important;
            }
        }
    </style>
</div>