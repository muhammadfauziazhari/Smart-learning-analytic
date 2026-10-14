{{-- JavaScript tab switcher, dipakai bersama oleh halaman Admin, Dosen, dan Kaprodi --}}
<script>
    function switchTab(tabId) {
        // Sembunyikan semua konten
        document.querySelectorAll('.tab-content').forEach(el => {
            el.classList.add('hidden');
        });
        // Tampilkan konten yang dipilih
        document.getElementById('content-' + tabId).classList.remove('hidden');

        // Reset style semua tombol navigasi
        document.querySelectorAll('.nav-item').forEach(btn => {
            btn.classList.remove('bg-indigo-600', 'text-white', 'shadow-lg', 'shadow-indigo-600/20');
            btn.classList.add('text-slate-400', 'hover:text-white', 'hover:bg-slate-800/60');
        });

        // Aktifkan style tombol yang diklik
        const activeBtn = document.querySelector(`[data-target="${tabId}"]`);
        activeBtn.classList.add('bg-indigo-600', 'text-white', 'shadow-lg', 'shadow-indigo-600/20');
        activeBtn.classList.remove('text-slate-400', 'hover:text-white', 'hover:bg-slate-800/60');

        // Update judul di header (mengambil label dari component sidebar)
        document.getElementById('page-title').innerText = activeBtn.dataset.title;
    }
</script>