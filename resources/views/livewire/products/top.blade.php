<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use App\Models\Outlet;
use App\Services\ReportService;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    #[Url]
    public $outlet = '';

    // Kosong = sepanjang waktu
    #[Url]
    public $startDate = '';

    #[Url]
    public $endDate = '';

    public function mount()
    {
        if (auth()->user()->role !== 'owner') {
            $this->redirect('/pos', navigate: true);
        }
    }

    public function updating($property)
    {
        if (in_array($property, ['outlet', 'startDate', 'endDate'])) {
            $this->resetPage();
        }
    }

    public function with(): array
    {
        $topProducts = app(ReportService::class)->topProducts(
            $this->outlet,
            15,
            $this->startDate ? \Carbon\Carbon::parse($this->startDate)->startOfDay() : null,
            $this->endDate ? \Carbon\Carbon::parse($this->endDate)->endOfDay() : null,
        );

        $outlets = Outlet::where('is_active', true)->orderBy('name')->get();

        return compact('topProducts', 'outlets');
    }
};
?>

<div class="space-y-4">
    <x-ui.page-header title="Produk Terlaris" subtitle="Peringkat produk berdasarkan jumlah terjual (semua varian digabung)." back="/dashboard" back-label="Dashboard" />

    <div class="tile !p-3 md:!p-4">
        <div class="grid grid-cols-2 lg:grid-cols-[1fr_1fr_16rem] gap-2 md:gap-3">
            <div>
                <label class="label">Dari tanggal</label>
                <input type="date" wire:model.live="startDate" class="input">
            </div>
            <div>
                <label class="label">Sampai tanggal</label>
                <input type="date" wire:model.live="endDate" class="input">
            </div>
            <div class="col-span-2 lg:col-span-1">
                <label class="label">Outlet</label>
                <select wire:model.live="outlet" class="select">
                    <option value="">Semua Cabang / Outlet</option>
                    @foreach ($outlets as $o)
                        <option value="{{ $o->id }}">{{ $o->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    <div class="tile-flush" wire:loading.class="opacity-60">
        @php $max = $topProducts->max('total_sold') ?: 1; @endphp
        <div class="divide-y divide-zinc-100">
            @forelse ($topProducts as $index => $item)
                @php $rank = $topProducts->firstItem() + $index; @endphp
                <div class="flex items-center gap-4 px-4 md:px-6 py-3.5">
                    <span class="w-9 h-9 rounded-xl flex items-center justify-center text-sm font-bold flex-shrink-0 {{ $rank <= 3 ? 'bg-ink text-white' : 'bg-zinc-100 text-zinc-500' }}">{{ $rank }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold truncate">{{ $item->product_name }}</p>
                        <div class="w-full bg-zinc-100 rounded-full h-1.5 mt-2">
                            <div class="bg-ink h-1.5 rounded-full" style="width: {{ ($item->total_sold / $max) * 100 }}%"></div>
                        </div>
                    </div>
                    <p class="text-sm font-bold whitespace-nowrap">{{ number_format($item->total_sold, 0, ',', '.') }} <span class="text-xs font-medium text-zinc-400">unit</span></p>
                </div>
            @empty
                <x-ui.empty icon="star" title="Belum ada data penjualan" text="Lakukan transaksi di halaman kasir untuk melihat peringkat." />
            @endforelse
        </div>
        @if ($topProducts->hasPages())
            <div class="px-5 py-4 border-t border-zinc-100">{{ $topProducts->links() }}</div>
        @endif
    </div>
</div>
