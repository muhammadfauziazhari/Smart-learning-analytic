{{--
    Status layanan analytics untuk dashboard analitik (FR-12a).

    Dipakai dashboard Dosen (FR-20) dan Kaprodi (FR-21).

    Data yang dibutuhkan:
      $layananAktif bool
      $peringatan    string|null
--}}
@if (! empty($layananAktif))
    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs font-medium text-emerald-700 flex items-center gap-2">
        <i class="fa-solid fa-circle-check text-emerald-500"></i>
        Layanan analytics aktif — engagement, klaster, dan risiko dihitung Python (kontrak v3.1).
    </div>
@elseif (! empty($peringatan))
    <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-xs text-amber-800 flex items-start gap-2">
        <i class="fa-solid fa-triangle-exclamation mt-0.5 text-amber-500"></i>
        <span>{{ $peringatan }}</span>
    </div>
@endif
