{{-- Notifikasi flash: session('success'), session('error'), session('status') --}}

@if (session('success'))
    <div role="alert"
        class="js-flash-alert mb-4 flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3">
        <i class="fa-solid fa-circle-check mt-0.5 text-emerald-500"></i>
        <p class="text-sm text-emerald-800">{{ session('success') }}</p>
        <button type="button" onclick="this.closest('.js-flash-alert').remove()"
            class="ml-auto text-emerald-400 transition-colors hover:text-emerald-600" aria-label="Tutup">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
@endif

@if (session('error'))
    <div role="alert"
        class="js-flash-alert mb-4 flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3">
        <i class="fa-solid fa-circle-exclamation mt-0.5 text-red-500"></i>
        <p class="text-sm text-red-800">{{ session('error') }}</p>
        <button type="button" onclick="this.closest('.js-flash-alert').remove()"
            class="ml-auto text-red-400 transition-colors hover:text-red-600" aria-label="Tutup">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
@endif

@if (session('status'))
    <div role="alert"
        class="js-flash-alert mb-4 flex items-start gap-3 rounded-xl border border-amber/40 bg-cream px-4 py-3">
        <i class="fa-solid fa-circle-info mt-0.5 text-royal"></i>
        <p class="text-sm text-navy">{{ session('status') }}</p>
        <button type="button" onclick="this.closest('.js-flash-alert').remove()"
            class="ml-auto text-royal/70 transition-colors hover:text-royal" aria-label="Tutup">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
@endif

@once
    @push('flash-alert-script')
        <script>
            // Hilangkan alert otomatis setelah 8 detik
            document.querySelectorAll('.js-flash-alert').forEach(el => {
                setTimeout(() => el.remove(), 8000);
            });
        </script>
    @endpush
@endonce