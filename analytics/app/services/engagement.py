"""FR-11: Perhitungan Engagement Rate.

Empat komponen yang SINGKAPNYA bisa dihitung dari log Moodle. Komponen
yang tidak punya dasar data sengaja tidak dipakai; lihat catatan di bawah.

CATATAN KALIBRASI
-----------------
Bobot dan batas atas komponen diambil dari 131.767 baris log nyata
(Mata Kuliah IF209 Jaringan Komputer, 264 mahasiswa, Feb-Agustus 2026).
Statistik yang dipakai:

    total_event          median 446   p75 591   maks 1.554
    hari_aktif           median  29   p75  42   maks  118
    tugas_dikumpulkan    median  12   p75  14   maks   24
    jenis_event          median  21   p75  23   maks   31

Batas atas dipilih sedikit di bawah p75 supaya mahasiswa pada umumnya
tidak otomatis mendapat nilai maximum, dan yang benar-benar Exceptional
saja yang menyentuh angka 1.0. Kalau data semester berikutnya berbeda,
nilai BATAS ini harus dihitung ulang dari data itu.
"""

from __future__ import annotations

from ..schemas import StudentMetrics

WEIGHTS = {
    "frekuensi": 0.35,
    "konsistensi": 0.30,
    "tugas": 0.20,
    "variasi": 0.15,
}

# Nilai yang dianggap "penuh" untuk tiap komponen.
#
# Ditentukan supaya mahasiswa pada percentile-50 mendapat sekitar 0,7 pada
# tiap komponen, sehingga rentang 0..1 benar-benar terpakai dan
#-component tidak mentok di angka 1,0.
BATAS_KALIBRASI = {
    "total_event": 600,          # median 446 -> 0,74
    "hari_aktif": 45,            # median  29 -> 0,64
    "tugas_dikumpulkan": 18,     # median  12 -> 0,67
    "jenis_event": 28,           # median  21 -> 0,75
}


def _clamp(value: float, low: float = 0.0, high: float = 1.0) -> float:
    return max(low, min(high, value))


def hitung_engagement(
    m: StudentMetrics,
    batas: dict[str, float] | None = None,
) -> dict[str, float]:
    """Kembalikan engagement_rate (0..1), engagement_score (0..100), dan rincian komponen.

    `batas` overriding `BATAS_KALIBRASI` dipakai untuk mata kuliah yang
    intensitas aktivitasnya berbeda jauh. Tanpa itu, kelas dengan aktivitas
    lebih rendah otomatis terbaca pasif semua.
    """
    ambang = {**BATAS_KALIBRASI, **(batas or {})}

    frekuensi = _clamp(m.total_event / ambang["total_event"])
    konsistensi = _clamp(m.hari_aktif / ambang["hari_aktif"])
    tugas = _clamp(m.tugas_dikumpulkan / ambang["tugas_dikumpulkan"])
    variasi = _clamp(m.jenis_event / ambang["jenis_event"])

    rate = (
        frekuensi * WEIGHTS["frekuensi"]
        + konsistensi * WEIGHTS["konsistensi"]
        + tugas * WEIGHTS["tugas"]
        + variasi * WEIGHTS["variasi"]
    )
    rate = _clamp(rate)

    return {
        "frekuensi": round(frekuensi, 4),
        "konsistensi": round(konsistensi, 4),
        "tugas": round(tugas, 4),
        "variasi": round(variasi, 4),
        "engagement_rate": round(rate, 4),
        "engagement_score": round(rate * 100, 2),
    }


# KOMPONEN YANG TIDAK DIPAKAI, dan alasannya. Dicatat supaya tidak
# diam-diam dipakai lagi di revisi berikutnya.
#
# - interaksi / diskusi (bobot 0.20 pada rancangan v1)
#     Event Forum di log contoh hanya 104 baris, dan tidak ada satu pun
#     event yang berisi 'post'. Tidak ada dasar data sama sekali.
#
# - durasi sesi / rata-rata menit per sesi (bobot 0.15 pada rancangan v1)
#     Rata-rata hanya 5,7 menit dengan simpangan 3,01. Tidak cukup
#     variasi untuk membedakan mahasiswa.
#
# - nilai atau angkankhir tugas
#     Log report Moodle tidak mencatat nilai. Semua angka pada kolom
#     Description adalah ID internal Moodle, bukan skor. Nilainya hanya
#     ada di gradebook, yang harus diekspor lewat laporan terpisah.