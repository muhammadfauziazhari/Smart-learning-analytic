"""FR-13 & FR-14: Klasterisasi pola belajar dan pemetaan profil cluster.

Algoritma: K-Means (scikit-learn) pada vektor fitur yang dinormalisasi.

Dua lapis perlindungan, karena K-Means bisa gagal ketika data terlalu sedikit
atau semua nilai Engagement Rate-nya identik (fewer clusters than distinct
samples):

1. Fallback berbasis ambang engagement, bila K-Means tidak bisa dijalankan.
2. Pelabelan cluster otomatis, sehingga label tidak hardcode terhadap data.

Catatan untuk laporan: label di sini belum divalidasi lewat wawancara dosen.
Langkah berikutnya adalah validasi oleh dosen, lalu disimpan sebagai
konfigurasi pada tabel `cluster_labels`.
"""

from __future__ import annotations

import warnings

import numpy as np
from sklearn.cluster import KMeans
from sklearn.metrics import silhouette_score
from sklearn.preprocessing import StandardScaler

# Ambang batas engagement_rate untuk penamaan cluster (FR-14).
# Urut dari tertinggi ke terendah, dan label TIDAK bergantung pada urutan
# cluster yang ditemukan K-Means, hanya pada nilai rata-ratanya.
#
# Penting: label memakai nilai absolut, bukan posisi. Kalau label mengikuti
# posisi, maka dengan 2 cluster, kelompok kedua akan selalu disebut "Aktif"
# walau engagement rata-ratanya hanya 1%.
LABEL_BANDS = (
    (0.75, "Sangat Aktif"),
    (0.50, "Aktif"),
    (0.25, "Cukup"),
    (0.00, "Pasif"),
)

FEATURE_KEYS = [
    "frekuensi",
    "konsistensi",
    "tugas",
    "interaksi",
    "avg_session_minutes",
]


def label_for(rata_engagement: float) -> str:
    """Ambil nama cluster dari rata-rata engagement_rate."""
    for batas, nama in LABEL_BANDS:
        if rata_engagement >= batas:
            return nama
    return LABEL_BANDS[-1][1]


def _vectorize(rows: list[dict[str, float]]) -> np.ndarray:
    return np.array(
        [[row[key] for key in FEATURE_KEYS] for row in rows],
        dtype=float,
    )


def _fallback_engagement_bucket(rows: list[dict[str, float]]) -> list[int]:
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


def clusterize(
    rows: list[dict[str, float]],
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

    x = _vectorize(rows)
    x_scaled = StandardScaler().fit_transform(x)

    k = min(n_clusters, n_samples)

    with warnings.catch_warnings():
        warnings.simplefilter("ignore")
        model = KMeans(n_clusters=k, random_state=random_state, n_init=10)
        raw_labels = model.fit_predict(x_scaled)

    silhouette = None
    if k > 1 and len(set(raw_labels.tolist())) > 1:
        try:
            silhouette = round(float(silhouette_score(x_scaled, raw_labels)), 4)
        except ValueError:
            silhouette = None

    # Susun ulang label supaya cluster 0 = paling aktif (FR-14)
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


def _label_names(rows: list[dict[str, float]], labels: list[int]) -> dict[int, str]:
    """Nama setiap cluster, dihitung dari rata-rata engagement_rate-nya."""
    totals: dict[int, list[float]] = {}
    for row, label in zip(rows, labels, strict=True):
        totals.setdefault(label, []).append(row["engagement_rate"])

    return {
        label: label_for(sum(values) / len(values))
        for label, values in totals.items()
    }