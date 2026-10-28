{{-- Form tambah/edit di halaman Kategori & Brand (livewire/categories-brands). Butuh: $type, $title, $formId --}}
<form wire:submit="saveForm" class="px-5 py-4 bg-zinc-50/70 space-y-3" wire:key="form-{{ $type }}-{{ $formId ?? 'new' }}" @keydown.escape="$wire.cancelForm()">
    <p class="text-xs font-semibold uppercase tracking-wider text-zinc-500">{{ $formId ? 'Edit' : 'Tambah' }} {{ strtolower($title) }}</p>
    <div>
        <input type="text" wire:model="formName" x-init="$el.focus()" placeholder="{{ $type === 'brand' ? 'Nama brand, mis. Tropical Monkey' : 'Nama kategori, mis. T-Shirt' }}" class="input">
        @error('formName') <span class="field-error">{{ $message }}</span> @enderror
    </div>
    @if ($type === 'brand')
        <div>
            <input type="text" wire:model="formDescription" placeholder="Deskripsi (opsional)" class="input">
            @error('formDescription') <span class="field-error">{{ $message }}</span> @enderror
        </div>
    @endif
    <div class="flex justify-end gap-2">
        <button type="button" wire:click="cancelForm" class="btn-secondary btn-sm">Batal</button>
        <button type="submit" class="btn-primary btn-sm">
            <span wire:loading.remove wire:target="saveForm">Simpan</span>
            <span wire:loading wire:target="saveForm">Menyimpan...</span>
        </button>
    </div>
</form>
