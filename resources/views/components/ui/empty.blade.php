@props(['icon' => 'box', 'title' => 'Belum ada data', 'text' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center text-center py-10 px-4']) }}>
    <div class="w-12 h-12 rounded-2xl bg-zinc-100 text-zinc-400 flex items-center justify-center mb-3">
        <x-icon :name="$icon" />
    </div>
    <p class="text-sm font-semibold text-zinc-700">{{ $title }}</p>
    @if ($text)
        <p class="text-xs text-zinc-400 mt-1 max-w-xs">{{ $text }}</p>
    @endif
    {{ $slot }}
</div>
