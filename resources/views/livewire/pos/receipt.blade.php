<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use App\Models\Transaction;

new #[Layout('components.layouts.app')]
    class extends Component {
    public Transaction $transaction;
    public $autoPrint = false;

    public function mount(Transaction $transaction)
    {
        $this->transaction = $transaction->load('items', 'outlet', 'user', 'payments');
        $this->autoPrint = (bool) session('print_receipt', false);
    }

    public function paymentLabel($method)
    {
        return \App\Models\PaymentMethod::allByCode()->get($method)?->receipt_label ?? strtoupper($method);
    }

    public function rate($rate)
    {
        return rtrim(rtrim(number_format($rate, 2, ',', '.'), '0'), ',');
    }
};
?>

<div class="space-y-4" x-data x-init="@if ($autoPrint) setTimeout(() => window.print(), 500) @endif">
    <div class="bento grid-cols-1 lg:grid-cols-5 items-start max-w-5xl mx-auto">
        <!-- Info & aksi (tidak ikut tercetak) -->
        <div class="lg:col-span-2 space-y-3 md:space-y-4 print:hidden">
            <div class="tile-dark">
                <div class="w-12 h-12 rounded-2xl bg-white text-ink flex items-center justify-center mb-5">
                    <x-icon name="check" />
                </div>
                @if ($transaction->isVoid())
                    <h1 class="text-2xl font-bold tracking-tight">Transaksi Dibatalkan</h1>
                    <p class="text-sm text-white/60 mt-1">{{ $transaction->void_reason }}</p>
                @else
                    <h1 class="text-2xl font-bold tracking-tight">Transaction Successful</h1>
                    @if ($autoPrint)
                        <p class="text-sm text-white/60 mt-1">Receipt will be printed automatically.</p>
                    @endif
                @endif
                <div class="mt-5 pt-5 border-t border-white/10">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-white/50">Total</p>
                    <p class="text-3xl font-bold tracking-tight mt-1">Rp {{ number_format($transaction->total, 0, ',', '.') }}</p>
                    <p class="text-xs text-white/50 mt-1">{{ $transaction->order_id }} · {{ $transaction->created_at->format('d/m/Y H:i') }}</p>
                </div>
            </div>

            <div class="tile !p-3 grid grid-cols-2 gap-2">
                <button onclick="window.print()" class="btn-secondary">
                    <x-icon name="printer" class="w-4 h-4" /> Reprint Receipt
                </button>
                <a href="/pos" wire:navigate class="btn-primary">
                    <x-icon name="plus" class="w-4 h-4" /> New Transaction
                </a>
            </div>
        </div>

        <!-- Struk -->
        <div class="lg:col-span-3 flex justify-center">
            {{-- theme-light: pratinjau struk selalu terang seperti kertas --}}
            <div class="w-full max-w-[80mm] theme-light">
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
                                <span>{{ number_format($item->price * $item->quantity, 0, ',', '.') }}</span>
                            </div>
                            <div class="font-medium mt-0.5">
                                {{ $item->quantity }} x {{ number_format($item->price, 0, ',', '.') }}
                            </div>
                            @if ($item->discount_amount > 0)
                                <div class="flex justify-between font-medium mt-0.5">
                                    <span>DISC</span>
                                    <span>-{{ number_format($item->discount_amount, 0, ',', '.') }}</span>
                                </div>
                            @endif
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

                @if($transaction->discount_amount > 0)
                    <div class="flex justify-between font-bold">
                        <span>DISCOUNT</span>
                        <span>-{{ number_format($transaction->discount_amount, 0, ',', '.') }}</span>
                    </div>
                @endif

                {{-- Tarif pajak diambil dari data transaksi, bukan tarif yang berlaku sekarang --}}
                @foreach($transaction->payments->where('tax_amount', '>', 0) as $payment)
                    <div class="flex justify-between font-bold">
                        <span>TAX ({{ $this->rate($payment->tax_rate) }}%)</span>
                        <span>{{ number_format($payment->tax_amount, 0, ',', '.') }}</span>
                    </div>
                @endforeach

                <div class="flex justify-between font-black text-sm pt-2 mt-2 tracking-tight">
                    <span>TOTAL</span>
                    <span>Rp {{ number_format($transaction->total, 0, ',', '.') }}</span>
                </div>
            </div>

            <!-- Payment Details -->
            <div class="space-y-1.5 text-xs pt-3">
                @foreach($transaction->payments as $payment)
                    <div class="flex justify-between font-bold">
                        <span>{{ $transaction->payments->count() > 1 ? $this->paymentLabel($payment->payment_method) : 'METHOD' }}</span>
                        <span>{{ $transaction->payments->count() > 1 ? number_format($payment->amount + $payment->tax_amount, 0, ',', '.') : $this->paymentLabel($payment->payment_method) }}</span>
                    </div>

                    @if($payment->cash_received !== null)
                        <div class="flex justify-between font-bold">
                            <span>CASH</span>
                            <span>{{ number_format($payment->cash_received, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex justify-between font-bold mt-1">
                            <span>CHANGE</span>
                            <span>{{ number_format($payment->change_amount, 0, ',', '.') }}</span>
                        </div>
                    @endif
                @endforeach

                @if($transaction->isVoid())
                    <div class="text-center font-black text-sm mt-3 border-2 border-black py-1">*** VOID ***</div>
                @endif
            </div>

            <!-- Footer -->
            <div class="mt-8 text-center text-xs font-bold space-y-1">
                @foreach (preg_split('/\r?\n/', (string) \App\Models\Setting::get('receipt_footer')) as $i => $line)
                    <p class="{{ $i > 0 ? 'font-medium text-[10px]' : '' }}">{{ $line }}</p>
                @endforeach
            </div>

            <div
                class="absolute -bottom-1.5 left-0 right-0 h-3 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4IiBoZWlnaHQ9IjgiPjxwb2x5Z29uIHBvaW50cz0iMCw4IDQsMCA4LDgiIGZpbGw9IiNmOWZhZmIiLz48L3N2Zz4=')] bg-repeat-x print:hidden">
            </div>
        </div>
            </div>
        </div>
    </div>

    <!-- CLEAN CSS PRINT (Dengan Font Diperbesar & Garis Ditebalkan) -->
    <style>
        @media print {
            @page {
                margin: 0;
                size: 80mm auto;
            }

            body * {
                visibility: hidden;
            }

            #print-area,
            #print-area * {
                visibility: visible;
                color: #000 !important;
                /* --- FONT DIPERBESAR --- */
                font-size: 16px !important;
            }

            /* Memperbesar Judul Toko */
            #print-area h2 {
                font-size: 24px !important;
            }

            /* Memaksa elemen teks yang tadinya kecil ikut membesar */
            #print-area .text-xs,
            #print-area .text-sm {
                font-size: 15px !important;
            }

            /* Memperbesar Total Harga agar lebih menonjol */
            #print-area .text-xl,
            #print-area .text-lg {
                font-size: 20px !important;
            }

            #print-area {
                position: absolute;
                left: 0;
                top: 0;
                width: 80mm;
                padding: 4mm;
            }

            /* Untuk garis putus-putus (dijadikan 2px agar seimbang dengan teks besar) */
            #print-area .border-dashed {
                border-bottom: 2px dashed #000 !important;
                border-top: none !important;
                border-left: none !important;
                border-right: none !important;
            }

            /* Untuk garis lurus biasa (header tabel, juga dijadikan 2px) */
            #print-area .border-b:not(.border-dashed) {
                border-bottom: 2px solid #000 !important;
            }
        }
    </style>
</div>
