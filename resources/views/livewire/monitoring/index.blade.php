<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;
use App\Exceptions\PosException;
use App\Models\ActivityLog;
use App\Models\Outlet;
use App\Models\Shift;
use App\Models\User;
use App\Services\ReportService;
use App\Services\ShiftService;
use Carbon\Carbon;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public $date = '';
    public $filterUser = '';
    public $filterOutlet = '';
    public $filterAction = '';

    // Tutup shift karyawan dari halaman monitoring (mis. lupa tutup shift)
    public $closingShiftId = null;
    public $closingCash = null;
    public $closingNote = '';

    public function mount()
    {
        $this->date = now()->toDateString();
    }

    public function updating($property)
    {
        if (in_array($property, ['filterUser', 'filterOutlet', 'filterAction', 'date'])) {
            $this->resetPage();
        }
    }

    public function openCloseShift($id)
    {
        $this->closingShiftId = $id;
        $this->reset(['closingCash', 'closingNote']);
    }

    public function closeShift(ShiftService $shifts)
    {
        $shift = Shift::find($this->closingShiftId);

        if (!$shift) {
            return;
        }

        try {
            $shifts->close($shift, $this->closingCash, trim('Ditutup oleh owner. ' . $this->closingNote));
        } catch (PosException $e) {
            $this->dispatch('notify', $e->toNotify());
            return;
        }

        $this->reset(['closingShiftId', 'closingCash', 'closingNote']);
        $this->dispatch('notify', ['title' => 'Shift Ditutup', 'text' => 'Shift karyawan berhasil ditutup.', 'icon' => 'success']);
    }

    public function with(): array
    {
        $shifts = app(ShiftService::class);
        $reports = app(ReportService::class);
        $day = Carbon::parse($this->date ?: now());

        $openShifts = Shift::with(['user', 'outlet'])->whereNull('closed_at')->orderBy('opened_at')->get()
            ->map(fn($shift) => ['shift' => $shift, 'summary' => $shifts->summary($shift)]);

        $logs = ActivityLog::with(['user', 'outlet'])
            ->whereDate('created_at', $day)
            ->when($this->filterUser !== '', fn($q) => $q->where('user_id', $this->filterUser))
            ->when($this->filterOutlet !== '', fn($q) => $q->where('outlet_id', $this->filterOutlet))
            ->when($this->filterAction !== '', fn($q) => $q->where('action', $this->filterAction))
            ->latest('id')
            ->paginate(20);

        return [
            'openShifts' => $openShifts,
            'cashiers' => $reports->cashiers($day->copy()->startOfDay(), $day->copy()->endOfDay(), $this->filterOutlet ?: null),
            'recentShifts' => Shift::with(['user', 'outlet'])->whereNotNull('closed_at')->whereDate('closed_at', $day)->latest('closed_at')->get(),
            'logs' => $logs,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'outlets' => Outlet::orderBy('name')->get(['id', 'name']),
            'closingShift' => $this->closingShiftId ? Shift::with(['user', 'outlet'])->find($this->closingShiftId) : null,
        ];
    }
};
?>

@php
    $rp = fn($n) => 'Rp ' . number_format($n, 0, ',', '.');
    $dayLabel = \Carbon\Carbon::parse($date)->translatedFormat('d M Y');
@endphp

<div class="space-y-4" wire:poll.30s>
    <x-ui.page-header title="Monitoring Karyawan" subtitle="Siapa yang sedang bertugas dan apa saja aktivitasnya. Diperbarui otomatis setiap 30 detik.">
        <input type="date" wire:model.live="date" class="input !w-auto">
    </x-ui.page-header>

    <!-- Sedang bertugas -->
    <div class="flex items-center gap-2 px-1">
        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
        <h2 class="tile-title">Sedang Bertugas <span class="text-zinc-400 font-normal">({{ $openShifts->count() }})</span></h2>
    </div>
    @if ($openShifts->isEmpty())
        <div class="tile"><x-ui.empty icon="users" title="Tidak ada karyawan yang sedang bertugas" /></div>
    @else
        <div class="bento grid-cols-1 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($openShifts as ['shift' => $shift, 'summary' => $sum])
                <div class="tile" wire:key="open-{{ $shift->id }}">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-2xl bg-ink text-white flex items-center justify-center font-bold flex-shrink-0">{{ strtoupper(mb_substr($shift->user->name, 0, 1)) }}</div>
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold truncate">{{ $shift->user->name }}</p>
                            <p class="text-xs text-zinc-400 truncate">{{ $shift->outlet->name }} · sejak {{ $shift->opened_at->format('H:i') }} ({{ $shift->opened_at->diffForHumans(null, true) }})</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-[auto_1fr_1fr] gap-2 mt-4">
                        <div class="tile-muted !p-2.5"><p class="stat-label">Trx</p><p class="font-bold mt-0.5">{{ $sum['transactions'] }}</p></div>
                        <div class="tile-muted !p-2.5 min-w-0"><p class="stat-label">Penjualan</p><p class="font-bold mt-0.5 text-sm break-words">{{ $rp($sum['net_sales']) }}</p></div>
                        <div class="tile-muted !p-2.5 min-w-0"><p class="stat-label">Tunai</p><p class="font-bold mt-0.5 text-sm break-words">{{ $rp($sum['cash_sales']) }}</p></div>
                    </div>
                    @if ($sum['discount'] > 0 || $sum['voids'] > 0)
                        <p class="text-xs text-zinc-500 mt-3">
                            @if ($sum['discount'] > 0) Diskon diberikan {{ $rp($sum['discount']) }} @endif
                            @if ($sum['voids'] > 0) · <span class="text-red-600 font-semibold">{{ $sum['voids'] }} void</span> @endif
                        </p>
                    @endif
                    <button type="button" wire:click="openCloseShift({{ $shift->id }})" class="btn-secondary btn-sm w-full mt-4">Tutup Shift</button>
                </div>
            @endforeach
        </div>
    @endif

    <div class="bento grid-cols-1 xl:grid-cols-2 items-start">
        <!-- Rekap karyawan -->
        <div class="tile-flush">
            <h2 class="tile-title px-4 md:px-5 py-3.5 border-b border-zinc-100">Rekap Karyawan · {{ $dayLabel }}</h2>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Karyawan</th><th class="text-right">Transaksi</th><th class="text-right">Penjualan</th><th class="text-right">Diskon</th><th class="text-right">Void</th></tr></thead>
                    <tbody>
                        @forelse ($cashiers as $row)
                            <tr>
                                <td class="font-semibold whitespace-nowrap">{{ $row['user']->name ?? '-' }}</td>
                                <td class="text-right">{{ $row['transactions'] }}</td>
                                <td class="text-right font-semibold whitespace-nowrap">{{ $rp($row['net_sales']) }}</td>
                                <td class="text-right text-zinc-500 whitespace-nowrap">{{ $rp($row['discount']) }}</td>
                                <td class="text-right {{ $row['voids'] ? 'text-red-600 font-semibold' : 'text-zinc-300' }}">{{ $row['voids'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-ui.empty icon="receipt" title="Belum ada transaksi" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Shift ditutup -->
        <div class="tile-flush">
            <h2 class="tile-title px-4 md:px-5 py-3.5 border-b border-zinc-100">Shift Ditutup · {{ $dayLabel }}</h2>
            <div class="divide-y divide-zinc-100">
                @forelse ($recentShifts as $shift)
                    <div class="px-4 md:px-5 py-3 flex items-start justify-between gap-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $shift->user->name }} <span class="font-normal text-zinc-500">· {{ $shift->outlet->name }}</span></p>
                            <p class="text-xs text-zinc-400">
                                {{ $shift->opened_at->format('H:i') }} – {{ $shift->closed_at->format('H:i') }} ·
                                @if ($shift->closing_cash !== null)
                                    kas {{ $rp($shift->closing_cash) }} dari seharusnya {{ $rp($shift->expected_cash) }}
                                @else
                                    penjualan tunai {{ $rp($shift->expected_cash) }}
                                @endif
                            </p>
                            @if ($shift->closing_note) <p class="text-xs text-zinc-400 italic truncate">{{ $shift->closing_note }}</p> @endif
                        </div>
                        @if ($shift->cash_difference === null)
                            <span class="badge-light">Kas tidak dihitung</span>
                        @else
                            <span class="{{ $shift->cash_difference == 0 ? 'badge-success' : 'badge-warning' }}">
                                {{ $shift->cash_difference == 0 ? 'Sesuai' : ($shift->cash_difference > 0 ? '+' : '-') . $rp(abs($shift->cash_difference)) }}
                            </span>
                        @endif
                    </div>
                @empty
                    <x-ui.empty icon="clock" title="Belum ada shift yang ditutup" />
                @endforelse
            </div>
        </div>
    </div>

    <!-- Log aktivitas -->
    <div class="tile-flush">
        <div class="px-4 md:px-5 py-3.5 border-b border-zinc-100 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            <h2 class="tile-title">Log Aktivitas</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <select wire:model.live="filterUser" class="select !py-2">
                    <option value="">Semua Karyawan</option>
                    @foreach ($users as $u) <option value="{{ $u->id }}">{{ $u->name }}</option> @endforeach
                </select>
                <select wire:model.live="filterOutlet" class="select !py-2">
                    <option value="">Semua Outlet</option>
                    @foreach ($outlets as $o) <option value="{{ $o->id }}">{{ $o->name }}</option> @endforeach
                </select>
                <select wire:model.live="filterAction" class="select !py-2">
                    <option value="">Semua Aktivitas</option>
                    @foreach (\App\Models\ActivityLog::ACTIONS as $code => $label) <option value="{{ $code }}">{{ $label }}</option> @endforeach
                </select>
            </div>
        </div>
        <div class="divide-y divide-zinc-100">
            @forelse ($logs as $log)
                <div class="px-4 md:px-5 py-3 flex gap-3 text-sm" wire:key="log-{{ $log->id }}">
                    <p class="text-xs text-zinc-400 w-11 flex-shrink-0 pt-0.5 font-mono">{{ $log->created_at->format('H:i') }}</p>
                    <div class="min-w-0 flex-1">
                        <p><span class="font-semibold">{{ $log->user->name ?? 'Sistem' }}</span> <span class="text-zinc-600">{{ $log->description }}</span></p>
                        <p class="text-xs text-zinc-400 mt-0.5">{{ $log->actionLabel() }}{{ $log->outlet ? ' · ' . $log->outlet->name : '' }}</p>
                    </div>
                </div>
            @empty
                <x-ui.empty icon="activity" title="Belum ada aktivitas" />
            @endforelse
        </div>
        <div class="px-5 py-4 border-t border-zinc-100">{{ $logs->links() }}</div>
    </div>

    @if ($closingShift)
        <x-ui.modal :title="'Tutup shift ' . $closingShift->user->name" :subtitle="$closingShift->outlet->name . ' · sejak ' . $closingShift->opened_at->format('d/m H:i')" close="$set('closingShiftId', null)">
            <form wire:submit="closeShift" class="space-y-4">
                <div>
                    <label class="label">Kas tunai di laci <span class="font-normal text-zinc-400">(opsional)</span></label>
                    <input type="number" min="0" wire:model="closingCash" class="input">
                </div>
                <textarea wire:model="closingNote" rows="2" placeholder="Catatan (opsional)" class="input"></textarea>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" wire:click="$set('closingShiftId', null)" class="btn-secondary">Batal</button>
                    <button type="submit" class="btn-primary">Tutup Shift</button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
