<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use App\Models\Transaction;
use App\Models\Outlet;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';

    // Menangkap ID outlet dari parameter URL (?outlet=...)
    #[Url]
    public $outlet = '';

    public function mount()
    {
        if (auth()->user()->role !== 'owner') {
            $this->redirect('/pos', navigate: true);
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingOutlet()
    {
        $this->resetPage(); // Reset halaman ke 1 saat ganti outlet
    }

    public function with(): array
    {
        // Filter Transaksi berdasarkan pencarian dan outlet
        $transactions = Transaction::with(['items', 'outlet'])
            ->when($this->search, function ($query) {
                $query->where('order_id', 'like', '%' . $this->search . '%');
            })
            ->when($this->outlet !== '', function ($query) {
                $query->where('outlet_id', $this->outlet);
            })
            ->latest()
            ->paginate(15);

        // Ambil data outlet untuk dropdown
        $outlets = Outlet::where('is_active', true)->orderBy('name')->get();

        return compact('transactions', 'outlets');
    }
};
?>

<div class="p-4 md:p-8 bg-gray-50/50 min-h-screen">

    <div class="mb-4">
        <a href="/dashboard" wire:navigate
            class="inline-flex items-center text-sm font-semibold text-gray-600 hover:text-gray-800 transition">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Kembali
        </a>
    </div>

    <!-- Header & Filter -->
    <div class="mb-6 flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 tracking-tight">Riwayat Transaksi</h1>
            <p class="text-sm text-gray-500 mt-1">Daftar lengkap transaksi penjualan.</p>
        </div>

        <!-- Dropdown Outlet & Search Bar (Berdampingan) -->
        <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">

            <select wire:model.live="outlet"
                class="w-full sm:w-48 px-4 py-2 border border-gray-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-600 outline-none transition shadow-sm">
                <option value="">Semua Outlet</option>
                @foreach($outlets as $o)
                    <option value="{{ $o->id }}">{{ $o->name }}</option>
                @endforeach
            </select>

            <div class="relative w-full sm:w-64 flex-shrink-0">
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari Order ID..."
                    class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-600 outline-none transition shadow-sm">
                <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" fill="none"
                    stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>

        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-4 font-bold text-gray-600">Tanggal</th>
                        <th class="px-6 py-4 font-bold text-gray-600">Order ID</th>
                        <th class="px-6 py-4 font-bold text-gray-600">Outlet</th>
                        <th class="px-6 py-4 font-bold text-gray-600">Metode</th>
                        <th class="px-6 py-4 font-bold text-gray-600 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($transactions as $tx)
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="px-6 py-4 text-gray-500">
                                {{ $tx->created_at->format('d M Y, H:i') }}
                            </td>
                            <td class="px-6 py-4 font-bold text-gray-900">
                                {{ $tx->order_id }}
                                <span class="block text-xs font-normal text-gray-400">{{ $tx->items->sum('quantity') }}
                                    item</span>
                            </td>
                            <td class="px-6 py-4 text-gray-600">
                                {{ $tx->outlet->name ?? 'Semua' }}
                            </td>
                            <td class="px-6 py-4">
                                @php
                                    $methodLabel = match ($tx->payment_method) {
                                        'qris' => 'QRIS',
                                        'kartu_lokal', 'kartu_non_lokal' => 'KARTU',
                                        default => 'TUNAI'
                                    };
                                    $methodClass = $methodLabel === 'TUNAI'
                                        ? 'bg-blue-100 text-blue-700'
                                        : 'bg-indigo-100 text-indigo-700';
                                @endphp
                                <span
                                    class="px-2.5 py-1 rounded-md text-[10px] font-extrabold tracking-widest {{ $methodClass }}">
                                    {{ $methodLabel }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right font-bold text-gray-900">
                                Rp {{ number_format($tx->total, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-400">Belum ada data transaksi yang
                                ditemukan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">
            {{ $transactions->links() }}
        </div>
    </div>
</div>