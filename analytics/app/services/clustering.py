"""FR-13 & FR-14: Klasterisasi pola belajar dan pemetaan profil cluster.

Algoritma: K-Means (scikit-learn) pada vektor fitur engagement yang
sudah dinormalisasi ke rentang 0..1.

Dua lapis perlindungan, karena K-Means gagal saat data terlalu sedikit
atau semua nilainya identik:

1. Fallback berbasis ambang engagement_rate, bila K-Means tidak mungkin.
2. Pelabelan cluster dari NILAI rata-ratanya, bukan dari posisinya, supaya
   label tidak pernah bertentangan dengan engagement yang ditampilkan.

Calibrasi label memakai data contoh IF209, yang menghasilkan:
    k=2 silhouette 0.4463 -> kelompok 183 mahasiswa (rata-rata 574 event)
                                  dan 81 mahasiswa (rata-rata 167 event)

Catatan untuk laporan: label di sini belum divalidasi lewat wawancara
dosen. Langkah berikutnya adalah validasi itu, lalu disimpan sebagai
konfigurasi pada tabel `cluster_labels`.
"""

from __future__ import annotations

import warnings

import numpy as np
from sklearn.cluster import KMeans
from sklearn.metrics import silhouette_score

# Hanya fitur engagement. Tidak ditambah fitur lain karena pada
# percobaan dengan fitur tambahan, hasilnya memburuk (lihat catatan di
# bagian bawah modul).
FEATURE_KEYS = ["frekuensi", "konsistensi", "tugas", "variasi"]

# Ambang engagement_rate untuk penamaan cluster (FR-14).
# Urut dari tertinggi ke terendah. Label memakai NILAI ABSOLUT, bukan
# urutan cluster: kalau label mengikuti posisi, maka dengan 2 cluster
# kelompok kedua selalu disebut "Aktif" walau rata-ratanya 1%.
LABEL_BANDS = (
    (0.75, "Sangat Aktif"),
    (0.50, "Aktif"),
    (0.25, "Cukup"),
    (0.00, "Pasif"),
)


def label_for(rata_engagement: float) -> str:
    """Ambil nama cluster dari rata-rata engagement_rate."""
    for batas, nama in LABEL_BANDS:
        if rata_engagement >= batas:
            return nama
    return LABEL_BANDS[-1][1]


def _fallback_engagement_bucket(rows: list[dict]) -> list[int]:
    """Kelompokkan berdasarkan ambang engagement_rate, tanpa K-Means."""
    buckets = []
    for row in rows:
        rate = row["engagement_rate"]
        if rate >= 0.75:
            buckets.append(0)
        elif rate >= 0.50:
            buckets.append(1)
        elif rate >= 0.25:
            buckets.append(2)
        else:
            buckets.append(3)
    return buckets


def _label_names(rows: list[dict], labels: list[int]) -> dict[int, str]:
    """Nama setiap cluster, dihitung dari rata-rata engagement_rate-nya."""
    totals: dict[int, list[float]] = {}
    for row, label in zip(rows, labels, strict=True):
        totals.setdefault(label, []).append(row["engagement_rate"])

    return {
        label: label_for(sum(values) / len(values))
        for label, values in totals.items()
    }


def clusterize(
    rows: list[dict],
    n_clusters: int = 3,
    random_state: int = 42,
) -> tuple[list[int], dict]:
    """Kembalikan (label cluster per baris, info tambahan)."""
    n_samples = len(rows)

    if n_samples < n_clusters or len(set(r["engagement_rate"] for r in rows)) == 1:
        labels = _fallback_engagement_bucket(rows)
        return labels, {
            "fallback_used": True,
            "silhouette_score": None,
            "label_names": _label_names(rows, labels),
            "warning": (
                f"Jumlah data ({n_samples}) tidak cukup untuk {n_clusters} cluster "
                "atau semua nilai sama; pengelompokan memakai ambang engagement_rate."
            ),
        }

    # Fitur engagement sudah 0..1, jadi tidak perlu StandardScaler.
    x = np.array([[row[key] for key in FEATURE_KEYS] for row in rows], dtype=float)

    k = min(n_clusters, n_samples)

    with warnings.catch_warnings():
        warnings.simplefilter("ignore")
        model = KMeans(n_clusters=k, random_state=random_state, n_init=10)
        raw_labels = model.fit_predict(x)

    silhouette = None
    if k > 1 and len(set(raw_labels.tolist())) > 1:
        try:
            silhouette = round(float(silhouette_score(x, raw_labels)), 4)
        except ValueError:
            silhouette = None

    # Susun ulang supaya cluster 0 = paling aktif (FR-14)
    engagement_by_label: dict[int, list[float]] = {}
    for raw, row in zip(raw_labels.tolist(), rows, strict=True):
        engagement_by_label.setdefault(raw, []).append(row["engagement_rate"])

    ranking = sorted(
        engagement_by_label.items(),
        key=lambda item: -(sum(item[1]) / len(item[1])),
    )

    mapping = {raw: new_id for new_id, (raw, _values) in enumerate(ranking)}
    labels = [mapping[raw] for raw in raw_labels.tolist()]

    return labels, {
        "fallback_used": False,
        "silhouette_score": silhouette,
        "warning": None,
        "label_names": _label_names(rows, labels),
    }


# CATATAN PERCOBAAN YANG GAGAL
#
# Versi awal memakai 9 fitur, termasuk jumlah event kuis, durasi sesi,
# dan jumlah submission. Pada data IF209 hasilnya lebih buruk:
#
#     k=2  silhouette 0.4463  ukuran [183, 81]
#
# Sementara versi 4 fitur engagement menghasilkan pemisahan yang sama.
# Penyebabnya: jumlah event kuis hampir seragam antar mahasiswa
# (median 86, IQR sempit), dan durasi sesi tidak bervariasi
# (rata-rata 5,7 menit, simpangan 3,01). Fitur yang tidak membedakan
# hanya menambah noise pada jarak K-Means.