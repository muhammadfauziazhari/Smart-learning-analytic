"""FR-15: Skor risiko prediktif.

Risiko pada dasarnya adalah kebalikan dari engagement, ditambah penalti bagi
mahasiswa yang akses kuisnya DITOLAK Moodle (event "Quiz access was
prevented").

PENTING: penalti kuis dihitung RELATIF terhadap median batch, bukan mutlak.
Pada data IF209, 241 dari 264 mahasiswa punya setidaknya satu akses kuis
yang ditolak (rata-rata 4,69). Kalau penalti-nya mutlak, hampir semua
mahasiswa mendapat penalti yang sama besar, sehingga tidak ada yang
terdiferensiasi: yang benar-benar diblokir sering dan yang hanya satu kali
sama-sama terc startups. Dengan cara relativ, hanya yang jauh melewati
median kelas yang dikenai penalti.

Nilai `risk_level` tidak dipakai sebagai keputusan EWS. Penetapan status EWS
adalah FR-16 dan dihitung Laravel berdasarkan threshold milik Kaprodi/Dosen
(tabel `ews_thresholds`). Angka di sini hanya untuk tampilan.
"""

from __future__ import annotations

import statistics

# Setiap kejadian "Quiz access was prevented" di atas median batch
# menambah risiko sebesar ini, dibatasi maksimal PENALTI_MAKS.
PENALTI_PER_KUIS_DITOLAK = 0.02
PENALTI_MAKS = 0.15


def median_kuis_ditolak(daftar: list[int]) -> float:
    """Median accesses blocked across satu batch, dipakai sebagai patokan."""
    if not daftar:
        return 0.0
    return float(statistics.median(daftar))


def hitung_risk(
    engagement_rate: float,
    *,
    kuis_ditolak: int = 0,
    median_kuis: float = 0.0,
) -> dict:
    """Kembalikan risk_score (0..1), level, dan rincian komponennya."""
    dasar = max(0.0, min(1.0, 1.0 - engagement_rate))

    # Hanya yangpenalty-nya di atas median batch yang dihitung
    kelebihan = max(0.0, float(kuis_ditolak) - median_kuis)
    penalti = min(PENALTI_MAKS, kelebihan * PENALTI_PER_KUIS_DITOLAK)

    skor = max(0.0, min(1.0, dasar + penalti))

    if skor >= 0.65:
        level = "high"
    elif skor >= 0.40:
        level = "medium"
    else:
        level = "low"

    return {
        "risk_score": round(skor, 4),
        "risk_level": level,
        "median_kuis_ditolak": round(median_kuis, 2),
        "komponen_risk": {
            "engagement_rendah": round(dasar, 4),
            "kuis_ditolak_lebih": round(penalti, 4),
        },
    }


def confidence_dari_silhouette(silhouette: float | None, jumlah_cluster: int) -> float:
    """Ubah kualitas pengelompokan menjadi confidence 0..1.

    Confidence menggambarkan keyakinan terhadap PENGELOMPOKAN,
    bukan keyakinan terhadap prediksi risiko seorang mahasiswa.
    """
    if silhouette is None or jumlah_cluster < 2:
        return 0.0

    # silhouette berada di rentang -1..1, dipetakan ke 0..1
    return round(max(0.0, min(1.0, (silhouette + 1.0) / 2.0)), 4)