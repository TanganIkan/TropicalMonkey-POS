{{-- Pasang tema sebelum halaman tampil (mencegah kilatan putih di mode gelap) --}}
<script>
    (() => {
        let mode = 'system';
        try { mode = localStorage.getItem('theme') || 'system'; } catch (e) {}
        if (mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    })();
</script>
