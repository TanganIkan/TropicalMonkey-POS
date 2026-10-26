{{-- Modal bento. Tampilkan dengan @if di halaman; $close = aksi Livewire saat klik latar/tombol tutup --}}
@props(['title' => null, 'subtitle' => null, 'close' => null, 'size' => 'md'])
@php
    $width = ['sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-2xl'][$size] ?? 'sm:max-w-md';
@endphp
{{-- !m-0: modal ada di dalam kontainer space-y-*, yang memberi margin-top dan menggeser lapisan fixed ke bawah --}}
<div class="fixed inset-0 z-50 !m-0 flex items-end sm:items-center justify-center p-0 sm:p-4 bg-black/40 backdrop-blur-sm"
    @if ($close) wire:click.self="{{ $close }}" @endif>
    <div {{ $attributes->merge(['class' => "w-full {$width} bg-white rounded-t-4xl sm:rounded-3xl shadow-lift max-h-[92vh] overflow-y-auto p-6"]) }}>
        @if ($title)
            <div class="flex items-start justify-between gap-3 mb-5">
                <div class="min-w-0">
                    <h2 class="text-lg font-semibold text-zinc-900">{{ $title }}</h2>
                    @if ($subtitle)
                        <p class="text-xs text-zinc-500 mt-0.5">{{ $subtitle }}</p>
                    @endif
                </div>
                @if ($close)
                    <button type="button" wire:click="{{ $close }}" class="btn-icon -mr-2 -mt-1" aria-label="Tutup"><x-icon name="x" /></button>
                @endif
            </div>
        @endif
        {{ $slot }}
    </div>
</div>
