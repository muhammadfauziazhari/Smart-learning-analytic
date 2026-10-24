"""FR-15: Skor risiko prediktif.

Risiko dihitung sebagai kebalikan dari engagement, ditambah faktor-faktor
penyebab yang biasanya berkaitan dengan mahasiswa yang berisiko:
konsistensi rendah dan tugas yang belum dikerjakan.

Nilai `risk_level` sengaja TIDAK dihitung di sini. Penetapan status EWS
adalah FR-16 dan memakai threshold milik Kaprodi/Dosen yang disimpan di
Laravel (tabel `ews_thresholds`). Layanan ini hanya menghasilkan skor;
membuat keputusannya dengan angka tetap di sini akan mengabaikan
konfigurasi yang dibuat pengguna.
"""

from __future__ import annotations

# Bobot komponen risiko, total = 1.0
RISK_WEIGHTS = {
    "engagement_rendah": 0.40,
    "konsistensi_rendah": 0.25,
    "tugas_menumpuk": 0.20,
    "interaksi_rendah": 0.15,
}

# Komponen dengan nilai di atas ambang ini dianggap aman, tidak menambah risiko
BATAS_AMAN = 0.40


def hitung_risk(
    engagement_rate: float,
    *,
    konsistensi: float,
    tugas: float,
    interaksi: float,
    bobot: dict[str, float] | None = None,
) -> dict[str, float]:
    """Kembalikan risk_score (0..1) beserta rincian komponennya."""
    w = {**RISK_WEIGHTS, **(bobot or {})}
    total_bobot = sum(w.values()) or 1.0

    # Risiko utama: engagement serendah mungkin
    risiko_engagement = 1.0 - engagement_rate

    # Konsistensi rendah = hadir tidak teratur
    risiko_konsistensi = 0.0 if konsistensi >= BATAS_AMAN else (BATAS_AMAN - konsistensi) / BATAS_AMAN

    # Tugas menumpuk = tugas belum dikerjakan
    risiko_tugas = 0.0 if tugas >= BATAS_AMAN else (BATAS_AMAN - tugas) / BATAS_AMAN

    # Interaksi rendah = tidak pernah berdiskusi
    risiko_interaksi = 0.0 if interaksi >= BATAS_AMAN else (BATAS_AMAN - interaksi) / BATAS_AMAN

    komponen = {
        "engagement_rendah": risiko_engagement,
        "konsistensi_rendah": risiko_konsistensi,
        "tugas_menumpuk": risiko_tugas,
        "interaksi_rendah": risiko_interaksi,
    }

    skor = sum(komponen[key] * w[key] for key in komponen) / total_bobot

    rincian: dict[str, float] = {key: round(value, 4) for key, value in komponen.items()}
    rincian["risk_score"] = round(max(0.0, min(1.0, skor)), 4)

    return rincian


def confidence_dari_silhouette(silhouette: float | None, jumlah_cluster: int) -> float:
    """Ubah kualitas pengelompokan menjadi confidence 0..1.

    Confidence ini menggambarkan keyakinan terhadap PENGELOMPOKAN,
    bukan keyakinan terhadap prediksi risiko seorang mahasiswa.
    """
    if silhouette is None or jumlah_cluster < 2:
        return 0.0

    # silhouette berada di rentang -1..1, dipetakan ke 0..1
    return round(max(0.0, min(1.0, (silhouette + 1.0) / 2.0)), 4)