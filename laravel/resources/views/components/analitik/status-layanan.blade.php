{{--
    Status layanan analytics untuk dashboard analitik (FR-12a).

    dippedakai dashboard Dosen (FR-20) dan Kaprodi (FR-21).

    Data yang dibutuhkan:
      $layananAktif bool
      $peringatan    string|null
--}}
@if (! empty($layananAktif))
    <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/5 px-4 py-2.5 text-xs text-emerald-400 flex items-center gap-2">
        <i class="fa-solid fa-circle-check"></i>
        Layanan analytics aktif — engagement, klaster, dan risiko dihitung Python (kontrak v3.1).
    </div>
@elseif (! empty($peringatan))
    <div class="rounded-xl border border-amber-500/40 bg-amber-500/5 px-4 py-3 text-xs text-amber-400 flex items-start gap-2">
        <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
        <span>{{ $peringatan }}</span>
    </div>
@endif