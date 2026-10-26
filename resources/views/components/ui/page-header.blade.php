{{-- Header halaman: judul, subjudul, tombol aksi (slot), tautan kembali opsional --}}
@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => 'Kembali'])
<div {{ $attributes->merge(['class' => 'flex flex-col gap-4 md:flex-row md:items-end md:justify-between']) }}>
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" wire:navigate class="inline-flex items-center gap-1 text-xs font-semibold text-zinc-500 hover:text-zinc-900 mb-2">
                <x-icon name="chevron-left" class="w-4 h-4" /> {{ $backLabel }}
            </a>
        @endif
        <h1 class="page-title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="page-sub">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
