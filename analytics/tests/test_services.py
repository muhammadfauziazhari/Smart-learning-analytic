"""Test logika perhitungan (FR-11, FR-13, FR-14, FR-15).

Angka dalam fixture diambil dari distribusi nyata log IF209 (264 mahasiswa),
supaya test tidak menguji angka yang tidak pernah terjadi di dunia nyata.
"""

from __future__ import annotations

import sys
from pathlib import Path

import pytest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from app.schemas import StudentMetrics  # noqa: E402
from app.services.clustering import clusterize, label_for  # noqa: E402
from app.services.engagement import (  # noqa: E402
    BATAS_KALIBRASI,
    WEIGHTS,
    hitung_engagement,
)
from app.services.risk import (  # noqa: E402
    confidence_dari_silhouette,
    hitung_risk,
    median_kuis_ditolak,
)


def metrik(**overrides) -> StudentMetrics:
    """Mahasiswa di median dataset IF209.

    Median nyata: total_event 446, hari_aktif 29, tugas 12, jenis_event 21.
    Expected: engagement_rate sekitar 0,71.
    """
    dasar = {
        "total_event": 446,
        "hari_aktif": 29,
        "tugas_dikumpulkan": 12,
        "jenis_event": 21,
        "kuis_ditolak": 4,
    }
    dasar.update(overrides)
    return StudentMetrics(**dasar)


# ------------------------------------------------------------- FR-11


def test_bobot_komponen_total_satu():
    assert sum(WEIGHTS.values()) == pytest.approx(1.0)


def test_mahasiswa_median_realistis_mendapat_engagement_sekitar_070():
    """Angka median dari data nyata, bukan angka tebakan.

    Fixture ini memakai tiap komponen pada nilai mediannya masing-masing,
    jadi hasilnya sedikit berbeda dari median gabungan 264 mahasiswa.
    """
    hasil = hitung_engagement(metrik())
    assert hasil["engagement_rate"] == pytest.approx(0.70, abs=0.01)


def test_mahasiswa_maksimal_mendapat_engagement_sempurna():
    hasil = hitung_engagement(
        StudentMetrics(
            total_event=2000, hari_aktif=300, tugas_dikumpulkan=99, jenis_event=99
        )
    )
    assert hasil["engagement_rate"] == 1.0
    assert hasil["engagement_score"] == 100.0


def test_mahasiswa_nol_aktivitas_dapat_engagement_nol():
    hasil = hitung_engagement(
        StudentMetrics(total_event=0, hari_aktif=0, tugas_dikumpulkan=0, jenis_event=0)
    )
    assert hasil["engagement_rate"] == 0.0
    assert hasil["engagement_score"] == 0.0


def test_engagement_selalu_dalam_rentang_0_sampai_1():
    """Nilai ekstrem tidak boleh menembus batas."""
    for m in [
        StudentMetrics(total_event=10**9, hari_aktif=10**6, tugas_dikumpulkan=10**6, jenis_event=10**6),
        StudentMetrics(total_event=1, hari_aktif=1, tugas_dikumpulkan=0, jenis_event=1),
    ]:
        hasil = hitung_engagement(m)
        assert 0.0 <= hasil["engagement_rate"] <= 1.0
        assert 0.0 <= hasil["engagement_score"] <= 100.0


def test_mahasiswa_pasif_nyata_mendapat_engagement_sangat_rendah():
    """Profil mahasiswa paling berisiko dari data nyata."""
    hasil = hitung_engagement(
        StudentMetrics(total_event=4, hari_aktif=1, tugas_dikumpulkan=0, jenis_event=3)
    )
    assert hasil["engagement_rate"] < 0.05


def test_semakin_konsisten_means_semakin_tinggi():
    rendah = hitung_engagement(metrik(hari_aktif=3))
    tinggi = hitung_engagement(metrik(hari_aktif=45))
    assert tinggi["konsistensi"] > rendah["konsistensi"]
    assert tinggi["engagement_rate"] > rendah["engagement_rate"]


def test_batas_kalibrasi_membuat_median_tidak_mentok_satu():
    """Mahasiswa median harus mendapat sekitar 0,7, bukan 1,0.

    Ini pengaman: batas yang terlalu kecil membuat lebih dari separuh
    mahasiswa mentok di 1,0 sehingga komponen itu tidak membedakan.
    """
    for komponen in ("frekuensi", "konsistensi", "tugas", "variasi"):
        assert hitung_engagement(metrik())[komponen] < 1.0, komponen


# ------------------------------------------------------------- FR-13 / FR-14


def test_kmeans_memisahkan_aktif_dan_pasif():
    aktif = [metrik().model_dump() for _ in range(6)]
    pasif = [
        StudentMetrics(total_event=5, hari_aktif=1, tugas_dikumpulkan=0, jenis_event=3)
        for _ in range(6)
    ]
    baris = []
    for i, m in enumerate(aktif):
        e = hitung_engagement(StudentMetrics(**m))
        baris.append({**e, "id": f"a{i}"})
    for i, m in enumerate(pasif):
        e = hitung_engagement(m)
        baris.append({**e, "id": f"p{i}"})

    labels, info = clusterize(baris, n_clusters=2)

    assert info["fallback_used"] is False
    assert len(set(labels)) == 2, "Harus terbentuk dua kelompok"
    assert info["silhouette_score"] > 0.4, "Pemisahan harus jelas"


def test_cluster_low_engagement_tidak_dilabel_aktif():
    """Regresi: label mengikuti nilai, bukan posisi cluster."""
    baris = []
    for i in range(6):
        e = hitung_engagement(metrik())
        baris.append({**e, "id": f"a{i}"})
    for i in range(6):
        e = hitung_engagement(
            StudentMetrics(total_event=5, hari_aktif=1, tugas_dikumpulkan=0, jenis_event=3)
        )
        baris.append({**e, "id": f"p{i}"})

    labels, info = clusterize(baris, n_clusters=2)

    # Kelompok pasif (engagement ~0,03) harus dilabeli "Pasif".
    # Inilah regresi yang sudah diperbaiki: dulu label mengikuti
    # urutan cluster, sehingga kelompok kedua selalu disebut "Aktif"
    # walau rata-ratanya hanya 1%.
    label_pasif = [
        info["label_names"][lab] for lab, baris_ke in zip(labels, baris, strict=True)
        if str(baris_ke["id"]).startswith("p")
    ]
    assert set(label_pasif) == {"Pasif"}

    # Kelompok aktif (engagement ~0,70) tidak boleh disebut "Sangat Aktif"
    label_aktif = [
        info["label_names"][lab] for lab, baris_ke in zip(labels, baris, strict=True)
        if str(baris_ke["id"]).startswith("a")
    ]
    assert set(label_aktif) == {"Aktif"}


def test_label_for_memakai_ambang_nilai():
    assert label_for(1.0) == "Sangat Aktif"
    assert label_for(0.80) == "Sangat Aktif"
    assert label_for(0.60) == "Aktif"
    assert label_for(0.30) == "Cukup"
    assert label_for(0.01) == "Pasif"
    assert label_for(0.0) == "Pasif"


def test_fallback_saat_data_terlalu_sedikit():
    baris = [hitung_engagement(metrik()) for _ in range(2)]
    labels, info = clusterize(baris, n_clusters=3)

    assert info["fallback_used"] is True
    assert info["silhouette_score"] is None
    assert info["warning"]
    assert len(labels) == 2


def test_fallback_saat_semua_engagement_sama():
    baris = [hitung_engagement(metrik()) for _ in range(6)]
    labels, info = clusterize(baris, n_clusters=3)

    assert info["fallback_used"] is True
    # Semua nilai sama, jadi semua mahasiswa masuk satu kelompok yang sama.
    assert len(set(labels)) == 1


def test_penalti_kuis_ditolak_dibatasi():
    """Penalti tidak boleh membuat risiko melebihi 1,0."""
    risiko = hitung_risk(0.9, kuis_ditolak=999)
    # dasar = 1 - 0,9 = 0,1 ; penalti dibatasi 0,15
    assert risiko["risk_score"] == pytest.approx(0.25)
    assert risiko["risk_score"] <= 1.0


def test_clusterize_deterministic():
    baris = []
    for i in range(10):
        m = metrik(total_event=200 + i * 40, hari_aktif=10 + i * 3)
        baris.append(hitung_engagement(m))
    a, _ = clusterize(baris, n_clusters=2, random_state=42)
    b, _ = clusterize(baris, n_clusters=2, random_state=42)
    assert a == b


def test_clusterize_tidak_pakai_fitur_yang_tidak_ada():
    """Keempat fitur yang dipakai harus benar-benar ada di tiap baris."""
    baris = []
    for i in range(8):
        e = hitung_engagement(metrik(total_event=300 + i * 30))
        baris.append(e)

    from app.services.clustering import FEATURE_KEYS

    for baris_ke in baris:
        for kunci in FEATURE_KEYS:
            assert kunci in baris_ke


# ------------------------------------------------------------- FR-15


def test_mahasiswa_aktif_risikonya_rendah():
    risiko = hitung_risk(0.95, kuis_ditolak=4, median_kuis=4)
    assert risiko["risk_score"] < 0.15
    assert risiko["risk_level"] == "low"


def test_mahasiswa_pasif_risikonya_tinggi():
    risiko = hitung_risk(0.03)
    assert risiko["risk_score"] > 0.9
    assert risiko["risk_level"] == "high"


def test_risk_score_selalu_dalam_rentang_0_sampai_1():
    for rate in (0.0, 0.25, 0.5, 0.75, 1.0):
        risiko = hitung_risk(rate, kuis_ditolak=99)
        assert 0.0 <= risiko["risk_score"] <= 1.0


def test_kuis_ditolak_menaikkan_risiko():
    tanpa = hitung_risk(0.5, kuis_ditolak=0, median_kuis=0)["risk_score"]
    dengan = hitung_risk(0.5, kuis_ditolak=3, median_kuis=0)["risk_score"]
    assert dengan > tanpa


def test_penalti_kuis_ditolak_dibatasi():
    """Penalti tidak boleh membuat risiko melebihi 1,0."""
    risiko = hitung_risk(0.9, kuis_ditolak=999, median_kuis=0)
    # dasar = 1 - 0,9 = 0,1 ; penalti dibatasi 0,15
    assert risiko["risk_score"] == pytest.approx(0.25)
    assert risiko["risk_score"] <= 1.0


def test_kuis_ditolak_tidak_menimpa_status_mahasiswa_aktif():
    """Mahasiswa aktif yang kuisnya ditolak tetap berstatus low."""
    risiko = hitung_risk(1.0, kuis_ditolak=13, median_kuis=4)
    assert risiko["risk_level"] == "low"


def test_kuis_ditolak_relatif_terhadap_median_batch():
    """Mahasiswa dengan penalti di bawah median TIDAK boleh kena penalti.

    Pada data IF209 hampir semua mahasiswa punya kuis_ditolak > 0, jadi
    penalti absolut tidak membedakan siapa pun.
    """
    sama_dengan_median = hitung_risk(0.5, kuis_ditolak=4, median_kuis=4)["risk_score"]
    bawah_median = hitung_risk(0.5, kuis_ditolak=1, median_kuis=4)["risk_score"]
    jauh_di_atas = hitung_risk(0.5, kuis_ditolak=20, median_kuis=4)["risk_score"]

    assert sama_dengan_median == pytest.approx(0.5)
    assert bawah_median == pytest.approx(0.5)
    assert jauh_di_atas > 0.5


def test_median_kuis_ditolak():
    assert median_kuis_ditolak([]) == 0.0
    assert median_kuis_ditolak([4, 4, 9, 1]) == 4.0
    assert median_kuis_ditolak([0, 0, 0]) == 0.0


def test_komponen_risk_dirinci():
    risiko = hitung_risk(0.3, kuis_ditolak=2, median_kuis=0)
    assert set(risiko["komponen_risk"]) == {"engagement_rendah", "kuis_ditolak_lebih"}
    assert risiko["komponen_risk"]["engagement_rendah"] == pytest.approx(0.7)
    assert risiko["median_kuis_ditolak"] == 0.0


# ------------------------------------------------------------- confidence


def test_confidence_nol_bila_silhouette_kosong():
    assert confidence_dari_silhouette(None, 3) == 0.0


def test_confidence_maksimum_saat_silhouette_sempurna():
    assert confidence_dari_silhouette(1.0, 3) == 1.0


def test_confidence_naik_seiring_silhouette():
    lemah = confidence_dari_silhouette(0.1, 3)
    kuat = confidence_dari_silhouette(0.7, 3)
    assert lemah < kuat
    assert 0.0 <= lemah <= kuat <= 1.0


# ------------------------------------------------------------- kalibrasi


def test_semua_komponen_ada_di_batas_kalibrasi():
    for kunci in ("total_event", "hari_aktif", "tugas_dikumpulkan", "jenis_event"):
        assert kunci in BATAS_KALIBRASI
        assert BATAS_KALIBRASI[kunci] > 0