<?php

use Livewire\Volt\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use App\Exceptions\PosException;
use App\Models\Transaction;
use App\Models\Outlet;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\CheckoutService;

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    public $search = '';

    // Menangkap ID outlet dari parameter URL (?outlet=...)
    #[Url]
    public $outlet = '';

    #[Url]
    public $status = '';

    #[Url]
    public $cashier = '';

    // Void
    public $voidId = null;
    public $voidReason = '';

    public function updating($property)
    {
        if (in_array($property, ['search', 'outlet', 'status', 'cashier'])) {
            $this->resetPage(); // Reset halaman ke 1 saat filter berubah
        }
    }

    public function openVoid($id)
    {
        $this->voidId = $id;
        $this->voidReason = '';
        $this->resetValidation();
    }

    public function closeVoid()
    {
        $this->reset(['voidId', 'voidReason']);
    }

    public function confirmVoid(CheckoutService $checkout)
    {
        $transaction = Transaction::with('items')->find($this->voidId);

        if (!$transaction) {
            $this->closeVoid();
            return;
        }

        try {
            $checkout->void($transaction, $this->voidReason);
        } catch (PosException $e) {
            $this->dispatch('notify', $e->toNotify());
            return;
        }

        $this->closeVoid();
        $this->dispatch('notify', [
            'title' => 'Transaksi Dibatalkan',
            'text' => "{$transaction->order_id} berhasil di-void. Stok barang sudah dikembalikan.",
            'icon' => 'success',
        ]);
    }

    public function with(): array
    {
        $transactions = Transaction::with(['items', 'outlet', 'user', 'voidedBy'])
            ->when($this->search, fn($q) => $q->where('order_id', 'like', '%' . $this->search . '%'))
            ->when($this->outlet !== '', fn($q) => $q->where('outlet_id', $this->outlet))
            ->when($this->status !== '', fn($q) => $q->where('status', $this->status))
            ->when($this->cashier !== '', fn($q) => $q->where('user_id', $this->cashier))
            ->latest()
            ->paginate(15);

        return [
            'transactions' => $transactions,
            'outlets' => Outlet::where('is_active', true)->orderBy('name')->get(),
            'cashiers' => User::orderBy('name')->get(['id', 'name']),
            'voidTarget' => $this->voidId ? Transaction::find($this->voidId) : null,
        ];
    }
};
?>

@php $rp = fn($n) => 'Rp ' . number_format($n, 0, ',', '.'); @endphp

<div class="space-y-4">
    <x-ui.page-header title="Transaksi" subtitle="Daftar lengkap transaksi penjualan. Setiap void tercatat beserta alasan dan siapa yang melakukannya." />

    <div class="tile !p-3 md:!p-4">
        <div class="grid grid-cols-2 lg:grid-cols-[1fr_11rem_11rem_10rem] gap-2 md:gap-3">
            <div class="relative col-span-2 lg:col-span-1">
                <x-icon name="search" class="w-4 h-4 absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400" />
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari Order ID..." class="input !pl-11">
            </div>
            <select wire:model.live="outlet" class="select">
                <option value="">Semua Outlet</option>
                @foreach ($outlets as $o) <option value="{{ $o->id }}">{{ $o->name }}</option> @endforeach
            </select>
            <select wire:model.live="cashier" class="select">
                <option value="">Semua Kasir</option>
                @foreach ($cashiers as $c) <option value="{{ $c->id }}">{{ $c->name }}</option> @endforeach
            </select>
            <select wire:model.live="status" class="select col-span-2 lg:col-span-1">
                <option value="">Semua Status</option>
                <option value="completed">Selesai</option>
                <option value="void">Void</option>
            </select>
        </div>
    </div>

    <div class="tile-flush">
        <div class="divide-y divide-zinc-100">
            @forelse ($transactions as $tx)
                <div class="flex flex-col sm:flex-row sm:items-center gap-3 px-4 md:px-5 py-3.5 {{ $tx->isVoid() ? 'bg-red-50/40' : '' }}" wire:key="tx-{{ $tx->id }}">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="w-10 h-10 rounded-2xl flex items-center justify-center flex-shrink-0 {{ $tx->isVoid() ? 'bg-red-100 text-red-600' : 'bg-zinc-100' }}">
                            <x-icon :name="$tx->isVoid() ? 'ban' : 'receipt'" class="w-4 h-4" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold flex items-center gap-2 flex-wrap">
                                {{ $tx->order_id }}
                                @if ($tx->isVoid()) <span class="badge-danger">VOID</span> @endif
                                <span class="{{ \App\Models\PaymentMethod::allByCode()->get($tx->payment_method)?->is_cash ? 'badge-light' : 'badge-dark' }}">{{ \App\Models\PaymentMethod::labelFor($tx->payment_method) }}</span>
                            </p>
                            <p class="text-xs text-zinc-400 mt-0.5">
                                {{ $tx->created_at->format('d M Y, H:i') }} · {{ $tx->outlet->name ?? '-' }} · {{ $tx->user->name ?? '-' }} · {{ $tx->items->sum('quantity') }} item
                                @if ($tx->totalDiscount() > 0) · diskon {{ $rp($tx->totalDiscount()) }} @endif
                            </p>
                            @if ($tx->isVoid())
                                <p class="text-xs text-red-600 mt-0.5">{{ $tx->void_reason }} — {{ $tx->voidedBy->name ?? '-' }}, {{ $tx->voided_at?->format('d/m H:i') }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center justify-between sm:justify-end gap-3 pl-[3.25rem] sm:pl-0">
                        <p class="text-sm font-bold whitespace-nowrap {{ $tx->isVoid() ? 'line-through text-zinc-400' : '' }}">{{ $rp($tx->total) }}</p>
                        <div class="flex gap-1">
                            <a href="/pos/receipt/{{ $tx->id }}" wire:navigate class="btn-secondary btn-sm"><x-icon name="printer" class="w-3.5 h-3.5" /> Struk</a>
                            @unless ($tx->isVoid())
                                <button type="button" wire:click="openVoid({{ $tx->id }})" class="btn-danger btn-sm">Void</button>
                            @endunless
                        </div>
                    </div>
                </div>
            @empty
                <x-ui.empty icon="receipt" title="Belum ada transaksi" />
            @endforelse
        </div>
        @if ($transactions->hasPages())
            <div class="px-5 py-4 border-t border-zinc-100">{{ $transactions->links() }}</div>
        @endif
    </div>

    @if ($voidTarget)
        <x-ui.modal :title="'Void transaksi ' . $voidTarget->order_id . '?'" close="closeVoid">
            <form wire:submit="confirmVoid" class="space-y-4">
                <div class="rounded-2xl bg-red-50 border border-red-100 p-4 text-sm text-red-700">
                    Total {{ $rp($voidTarget->total) }}. Stok barang dikembalikan ke outlet dan transaksi tidak dihitung di laporan. Tindakan ini tidak bisa dibatalkan.
                </div>
                <div>
                    <label class="label">Alasan void</label>
                    <textarea wire:model="voidReason" rows="3" placeholder="mis. Pelanggan membatalkan pembelian / salah input" class="input"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <button type="button" wire:click="closeVoid" class="btn-secondary">Batal</button>
                    <button type="submit" class="btn bg-red-600 text-snow hover:bg-red-700">Ya, Void</button>
                </div>
            </form>
        </x-ui.modal>
    @endif
</div>
