{{-- Pilihan tema: terang / gelap / ikuti sistem. Di HP berupa satu tombol yang berganti mode. --}}
@php
    $modes = [['light', 'sun', 'Terang'], ['dark', 'moon', 'Gelap'], ['system', 'monitor', 'Ikuti sistem']];
@endphp
<div {{ $attributes }} x-data="{
    mode: (() => { try { return localStorage.getItem('theme') || 'system' } catch (e) { return 'system' } })(),
    set(m) { this.mode = m; window.setTheme(m); },
    next() { this.set({ light: 'dark', dark: 'system', system: 'light' }[this.mode]); },
}">
    <div class="hidden sm:flex items-center gap-0.5 rounded-2xl border border-zinc-200 p-0.5">
        @foreach ($modes as [$mode, $icon, $label])
            <button type="button" @click="set('{{ $mode }}')" title="{{ $label }}" aria-label="Tema {{ $label }}"
                :class="mode === '{{ $mode }}' ? 'bg-ink text-white' : 'text-zinc-400 hover:text-zinc-900'"
                class="w-8 h-8 rounded-xl flex items-center justify-center transition">
                <x-icon :name="$icon" class="w-4 h-4" />
            </button>
        @endforeach
    </div>
    <button type="button" @click="next()" class="sm:hidden btn-icon" :title="'Tema: ' + { light: 'Terang', dark: 'Gelap', system: 'Ikuti sistem' }[mode]" aria-label="Ganti tema">
        @foreach ($modes as [$mode, $icon])
            <x-icon :name="$icon" class="w-5 h-5" x-show="mode === '{{ $mode }}'" />
        @endforeach
    </button>
</div>
