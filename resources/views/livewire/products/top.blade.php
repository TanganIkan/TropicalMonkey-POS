<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    #[Url]
    public $outlet = '';

    public function mount()
    {
        if (auth()->user()->role !== 'owner') {
            $this->redirect('/pos', navigate: true);
        }
    }

    public function updatingOutlet()
    {
        $this->resetPage();
    }

    public function with(): array
    {
        $trxIds = Transaction::when($this->outlet !== '', function ($query) {
            $query->where('outlet_id', $this->outlet);
        })->pluck('id');

        $topProducts = TransactionItem::select('product_name', DB::raw('SUM(quantity) as total_sold'))
            ->whereIn('transaction_id', $trxIds)
            ->groupBy('product_name')
            ->orderByDesc('total_sold')
            ->paginate(15);

        $outlets = Outlet::where('is_active', true)->orderBy('name')->get();

        return compact('topProducts', 'outlets');
    }
};
?>

<div class="p-4 md:p-8 bg-gray-50/30 min-h-screen">

    <div class="mb-5">
        <a href="/dashboard" wire:navigate
            class="inline-flex items-center text-sm font-bold text-gray-500 hover:text-gray-900 transition-all duration-300 ease-in-out">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Kembali ke Dashboard
        </a>
    </div>

    <div class="mb-8 flex flex-col md:flex-row md:items-end justify-between gap-5">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 tracking-tight flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-gray-900 text-white flex items-center justify-center shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z">
                        </path>
                    </svg>
                </div>
                Semua Produk Terlaris
            </h1>
            <p class="text-sm text-gray-500 mt-2">Daftar lengkap peringkat produk dengan penjualan terbanyak.</p>
        </div>

        <!-- Filter Outlet -->
        <div class="w-full md:w-auto relative">
            <select wire:model.live="outlet"
                class="w-full sm:w-64 px-4 py-2.5 pl-10 border border-gray-200 rounded-xl text-sm font-medium text-gray-800 bg-white focus:ring-2 focus:ring-gray-900 outline-none transition-all duration-300 ease-in-out shadow-sm appearance-none cursor-pointer">
                <option value="">Semua Cabang / Outlet</option>
                @foreach($outlets as $o)
                    <option value="{{ $o->id }}">{{ $o->name }}</option>
                @endforeach
            </select>
            <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                    </path>
                </svg>
            </div>
            <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- Tabel dengan Transisi Loading Bawaan Livewire (Wire:Loading.class) -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden transition-all duration-300 ease-in-out"
        wire:loading.class="opacity-50 pointer-events-none">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-5 font-bold text-gray-500 uppercase tracking-wider text-xs">Peringkat</th>
                        <th class="px-6 py-5 font-bold text-gray-500 uppercase tracking-wider text-xs">Nama Produk</th>
                        <th class="px-6 py-5 font-bold text-gray-500 uppercase tracking-wider text-xs text-right">Total
                            Terjual</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($topProducts as $index => $item)
                        @php
                            $rank = $topProducts->firstItem() + $index;
                            $rankBg = $rank === 1 ? 'bg-yellow-100 text-yellow-700 border-yellow-200' :
                                ($rank === 2 ? 'bg-gray-100 text-gray-600 border-gray-200' :
                                    ($rank === 3 ? 'bg-orange-100 text-orange-700 border-orange-200' : 'bg-gray-50 text-gray-500 border-gray-100'));
                        @endphp
                        <tr class="hover:bg-gray-50/80 transition-all duration-300 ease-in-out">
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-full border text-xs font-bold {{ $rankBg }} shadow-sm">
                                    {{ $rank }}
                                </span>
                            </td>
                            <td class="px-6 py-4 font-bold text-gray-900">
                                {{ $item->product_name }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span
                                    class="font-black text-gray-900 bg-gray-100 px-3 py-1.5 rounded-lg border border-gray-200">
                                    {{ number_format($item->total_sold, 0, ',', '.') }} <span
                                        class="text-xs text-gray-500 font-bold ml-0.5">unit</span>
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-20 text-center">
                                <div class="flex flex-col items-center justify-center text-gray-400">
                                    <div
                                        class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mb-4 border border-gray-100">
                                        <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                        </svg>
                                    </div>
                                    <p class="font-bold text-gray-500">Belum ada data penjualan.</p>
                                    <p class="text-sm mt-1">Lakukan transaksi di halaman kasir untuk melihat peringkat.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($topProducts->hasPages())
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/50">
                {{ $topProducts->links() }}
            </div>
        @endif
    </div>
</div>