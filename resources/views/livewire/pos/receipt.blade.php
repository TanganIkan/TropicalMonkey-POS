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
            'tunai' => 'CASH',
            'qris' => 'QRIS',
            'kartu_lokal' => 'DEBIT/CREDIT CARD',
            'kartu_non_lokal' => 'INTERNATIONAL CARD',
            default => strtoupper($method),
        };
    }
};
?>

<div class="p-4 md:p-8 w-full flex justify-center bg-gray-50/50 min-h-screen" x-data
    x-init="setTimeout(() => window.print(), 500)">

    <div class="w-full max-w-[80mm]">

        <!-- Pesan Sukses Web -->
        <div class="text-center mb-6 print:hidden w-full">
            <div
                class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-900 text-white mb-4 shadow-lg shadow-gray-900/20">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h1 class="text-xl font-black text-gray-900 tracking-tight">Transaction Successful</h1>
            <p class="text-sm text-gray-500 mt-1">Receipt will be printed automatically.</p>
        </div>

        <!-- AREA STRUK FISIK -->
        <div id="print-area"
            class="bg-white p-4 sm:p-6 shadow-sm font-mono text-sm relative border border-gray-200 print:border-none print:shadow-none print:p-0 w-full text-black">

            <!-- Efek Kertas Sobek Atas -->
            <div
                class="absolute -top-1.5 left-0 right-0 h-3 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4IiBoZWlnaHQ9IjgiPjxwb2x5Z29uIHBvaW50cz0iMCwwIDQsOCA4LDAiIGZpbGw9IiNmOWZhZmIiLz48L3N2Zz4=')] bg-repeat-x print:hidden">
            </div>

            <!-- Header -->
            <div class="text-center mb-4 pb-4 border-b border-dashed border-gray-300 print:border-black">
                <h2 class="font-black text-xl tracking-widest uppercase">
                    {{ $transaction->outlet->name ?? 'YOUR STORE' }}
                </h2>
                <p class="text-xs mt-1 uppercase">{{ $transaction->outlet->address ?? 'Outlet Address' }}</p>
            </div>

            <!-- Meta Data -->
            <div class="mb-4 pb-4 border-b border-dashed border-gray-300 print:border-black text-xs space-y-1.5">
                <div class="flex justify-between">
                    <span class="font-semibold">ORDER NO.</span>
                    <span class="font-bold">{{ $transaction->order_id }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-semibold">DATE</span>
                    <span class="font-bold">{{ $transaction->created_at->format('d/m/Y H:i') }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="font-semibold">CASHIER</span>
                    <span class="font-bold uppercase">{{ $transaction->user->name ?? '-' }}</span>
                </div>
            </div>

            <!-- Items -->
            <div class="mb-4 border-b border-dashed border-gray-300 print:border-black">
                <div
                    class="flex justify-between font-bold mb-2 text-xs border-b border-gray-200 print:border-black pb-2">
                    <span>ITEM</span>
                    <span>AMOUNT</span>
                </div>
                <div class="space-y-3 pb-3 pt-1">
                    @foreach($transaction->items as $item)
                        <div class="text-xs">
                            <div class="flex justify-between font-bold">
                                <span class="truncate pr-2">{{ $item->product_name }}</span>
                                <span>{{ number_format($item->subtotal, 0, ',', '.') }}</span>
                            </div>
                            <div class="font-medium mt-0.5">
                                {{ $item->quantity }} x {{ number_format($item->price, 0, ',', '.') }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Totals -->
            <div class="space-y-1.5 text-xs pb-4 border-b border-dashed border-gray-300 print:border-black">
                <div class="flex justify-between font-bold">
                    <span>SUBTOTAL</span>
                    <span>{{ number_format($transaction->subtotal, 0, ',', '.') }}</span>
                </div>

                @if($transaction->tax_amount > 0)
                    <div class="flex justify-between font-bold">
                        <span>TAX (3%)</span>
                        <span>{{ number_format($transaction->tax_amount, 0, ',', '.') }}</span>
                    </div>
                @endif

                <div class="flex justify-between font-black text-sm pt-2 mt-2 tracking-tight">
                    <span>TOTAL</span>
                    <span>Rp {{ number_format($transaction->total, 0, ',', '.') }}</span>
                </div>
            </div>

            <!-- Payment Details -->
            <div class="space-y-1.5 text-xs pt-3">
                <div class="flex justify-between font-bold">
                    <span>METHOD</span>
                    <span>{{ $this->paymentLabel($transaction->payment_method) }}</span>
                </div>

                @if($transaction->payment_method === 'tunai')
                    <div class="flex justify-between font-bold">
                        <span>CASH</span>
                        <span>{{ number_format($transaction->cash_received, 0, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between font-bold mt-1">
                        <span>CHANGE</span>
                        <span>{{ number_format($transaction->change_amount, 0, ',', '.') }}</span>
                    </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="mt-8 text-center text-xs font-bold space-y-1">
                <p>Thank you for your visit!</p>
                <p class="font-medium text-[10px]">Items purchased cannot be returned or exchanged.</p>
            </div>

            <div
                class="absolute -bottom-1.5 left-0 right-0 h-3 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4IiBoZWlnaHQ9IjgiPjxwb2x5Z29uIHBvaW50cz0iMCw4IDQsMCA4LDgiIGZpbGw9IiNmOWZhZmIiLz48L3N2Zz4=')] bg-repeat-x print:hidden">
            </div>
        </div>

        <div class="flex flex-col gap-3 mt-8 print:hidden">
            <button onclick="window.print()"
                class="w-full py-3 bg-white border-2 border-gray-200 text-gray-900 rounded-xl font-bold hover:bg-gray-50 transition shadow-sm">
                Reprint Receipt
            </button>
            <a href="/pos" wire:navigate
                class="w-full text-center py-3 bg-gray-900 text-white rounded-xl font-bold hover:bg-black transition shadow-lg">
                New Transaction
            </a>
        </div>

    </div>

    <!-- CLEAN CSS PRINT -->
    <style>
        @media print {
            @page {
                margin: 0;
                size: 80mm auto;
                /* Memaksa browser sadar ini kertas 80mm */
            }

            body * {
                visibility: hidden;
            }

            #print-area,
            #print-area * {
                visibility: visible;
                color: #000 !important;
            }

            #print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 80mm;
                padding: 4mm;
            }
        }
    </style>
</div>