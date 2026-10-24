"""Test logika perhitungan (FR-11, FR-13, FR-14, FR-15)."""

from __future__ import annotations

import sys
from pathlib import Path

import pytest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from app.schemas import StudentMetrics  # noqa: E402
from app.services.clustering import clusterize  # noqa: E402
from app.services.engagement import hitung_engagement  # noqa: E402
from app.services.risk import confidence_dari_silhouette, hitung_risk  # noqa: E402


def metrik(**overrides) -> StudentMetrics:
    """Mahasiswa yang maksimal aktif, dipakai sebagai dasar.

    Nilai default sengaja dibuat menyentuh semua batas atas komponen
    engagement (10 log/hari, hadir setiap hari, tugas dan diskusi maksimal)
    supaya engagement_rate-nya tepat 1.0.
    """
    dasar = {
        "total_log": 300,
        "days_active": 30,
        "days_in_period": 30,
        "upload_count": 20,
        "assignment_done": 20,
        "quiz_done": 20,
        "discussion_count": 30,
        "avg_session_minutes": 60.0,
    }
    dasar.update(overrides)
    return StudentMetrics(**dasar)


def rows_dari(list_metrik: list[StudentMetrics]) -> list[dict]:
    """Ubah StudentMetrics menjadi baris fitur yang dipakai clustering."""
    baris = []
    for m in list_metrik:
        e = hitung_engagement(m)
        baris.append(
            {
                "frekuensi": e["frekuensi"],
                "konsistensi": e["konsistensi"],
                "tugas": e["tugas"],
                "interaksi": e["interaksi"],
                "avg_session_minutes": m.avg_session_minutes,
                "engagement_rate": e["engagement_rate"],
            }
        )
    return baris


# ------------------------------------------------------------- FR-11


def test_mahasiswa_aktif_dapat_engagement_sempurna():
    hasil = hitung_engagement(metrik())
    assert hasil["engagement_rate"] == 1.0
    assert hasil["engagement_score"] == 100.0


def test_mahasiswa_nol_aktivitas_dapat_engagement_nol():
    hasil = hitung_engagement(
        metrik(
            total_log=0,
            days_active=0,
            upload_count=0,
            assignment_done=0,
            quiz_done=0,
            discussion_count=0,
            avg_session_minutes=0.0,
        )
    )
    assert hasil["engagement_rate"] == 0.0
    assert hasil["engagement_score"] == 0.0


def test_engagement_selalu_dalam_rentang_0_sampai_1():
    """Nilai ekstrem tidak boleh menembus batas 0..1."""
    for m in [
        metrik(total_log=999_999, days_active=999, days_in_period=1),
        metrik(total_log=1, days_active=999, days_in_period=1),
    ]:
        hasil = hitung_engagement(m)
        assert 0.0 <= hasil["engagement_rate"] <= 1.0
        assert 0.0 <= hasil["engagement_score"] <= 100.0


def test_semakin_konsisten_means_semakin_tinggi():
    rendah = hitung_engagement(metrik(days_active=3))
    tinggi = hitung_engagement(metrik(days_active=28))
    assert tinggi["konsistensi"] > rendah["konsistensi"]
    assert tinggi["engagement_rate"] > rendah["engagement_rate"]


def test_bobot_komponen_total_satu():
    from app.services.engagement import WEIGHTS

    assert sum(WEIGHTS.values()) == pytest.approx(1.0)


# ------------------------------------------------------------- FR-13 / FR-14


def test_kmeans_mengelompokkan_dua_kelompok_yang_jauh():
    aktif = [metrik() for _ in range(5)]
    pasif = [
        metrik(
            total_log=2,
            days_active=1,
            upload_count=0,
            assignment_done=0,
            quiz_done=0,
            discussion_count=0,
            avg_session_minutes=3.0,
        )
        for _ in range(5)
    ]

    labels, info = clusterize(rows_dari(aktif + pasif), n_clusters=2)

    assert info["fallback_used"] is False
    assert info["warning"] is None
    assert set(labels[:5]) == {0}, "Mahasiswa aktif harus berada di cluster 0"
    assert set(labels[5:]) == {1}, "Mahasiswa pasif harus berada di cluster 1"


def test_cluster_low_engagement_tidak_dilabel_aktif():
    """Regresi: label harus mengikuti nilai, bukan posisi cluster.

    Dengan 2 cluster, mahasiswa ber-engagement ~1% sebelumnya ikut
    disebut "Aktif" karena label mengikuti urutan cluster.
    """
    aktif = [metrik() for _ in range(5)]
    pasif = [
        metrik(
            total_log=2,
            days_active=1,
            assignment_done=0,
            quiz_done=0,
            discussion_count=0,
            avg_session_minutes=4.0,
        )
        for _ in range(5)
    ]

    labels, info = clusterize(rows_dari(aktif + pasif), n_clusters=2)

    assert info["label_names"][0] == "Sangat Aktif"
    assert info["label_names"][1] == "Pasif", (
        "Cluster dengan engagement ~1% tidak boleh disebut Aktif"
    )


def test_label_for_memakai_ambang_nilai():
    from app.services.clustering import label_for

    assert label_for(1.0) == "Sangat Aktif"
    assert label_for(0.80) == "Sangat Aktif"
    assert label_for(0.60) == "Aktif"
    assert label_for(0.30) == "Cukup"
    assert label_for(0.01) == "Pasif"
    assert label_for(0.0) == "Pasif"


def test_fallback_saat_data_terlalu_sedikit():
    labels, info = clusterize(rows_dari([metrik(), metrik()]), n_clusters=3)

    assert info["fallback_used"] is True
    assert info["silhouette_score"] is None
    assert "tidak cukup" in (info["warning"] or "")
    assert len(labels) == 2


def test_fallback_saat_semua_engagement_sama():
    """Semua nilai identik membuat K-Means tidak bisa membedakan cluster."""
    labels, info = clusterize(rows_dari([metrik() for _ in range(6)]), n_clusters=3)

    assert info["fallback_used"] is True
    assert all(label == 0 for label in labels)


def test_clusterize_deterministic_dengan_random_state_tetap():
    baris = rows_dari([metrik(), metrik(), metrik(total_log=10, days_active=2)])
    a, _ = clusterize(baris, n_clusters=2, random_state=42)
    b, _ = clusterize(baris, n_clusters=2, random_state=42)
    assert a == b


# ------------------------------------------------------------- FR-15


def test_mahasiswa_aktif_risikonya_rendah():
    risiko = hitung_risk(
        0.95,
        konsistensi=0.93,
        tugas=1.0,
        interaksi=1.0,
    )
    assert risiko["risk_score"] < 0.2


def test_mahasiswa_pasif_risikonya_tinggi():
    risiko = hitung_risk(0.0, konsistensi=0.0, tugas=0.0, interaksi=0.0)
    assert risiko["risk_score"] > 0.9


def test_risk_score_selalu_dalam_rentang_0_sampai_1():
    for rate in [0.0, 0.25, 0.5, 0.75, 1.0]:
        risiko = hitung_risk(rate, konsistensi=rate, tugas=rate, interaksi=rate)
        assert 0.0 <= risiko["risk_score"] <= 1.0


def test_bobot_risk_default_total_satu():
    from app.services.risk import RISK_WEIGHTS

    assert sum(RISK_WEIGHTS.values()) == pytest.approx(1.0)


def test_bobot_risk_bisa_disesuaikan():
    default = hitung_risk(0.3, konsistensi=0.1, tugas=0.1, interaksi=0.1)

    # Bobot penuh hanya pada komponen konsistensi
    hanya_konsistensi = hitung_risk(
        0.3,
        konsistensi=0.1,
        tugas=0.1,
        interaksi=0.1,
        bobot={
            "engagement_rendah": 0.0,
            "konsistensi_rendah": 1.0,
            "tugas_menumpuk": 0.0,
            "interaksi_rendah": 0.0,
        },
    )

    # Konsistensi yang dikuatkan menghasilkan risiko lebih tinggi
    # dibanding bobot default yang masih meredam engagement rendah.
    assert hanya_konsistensi["risk_score"] > default["risk_score"]


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