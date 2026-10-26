"""Test endpoint HTTP (FR-12). Memakai TestClient, tanpa uvicorn."""

from __future__ import annotations

import sys
from pathlib import Path

import pytest
from fastapi.testclient import TestClient

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from app.main import app  # noqa: E402

client = TestClient(app)

# Profil mahasiswa dari data nyata IF209.
# Field mengikuti kontrak v2 (total_event, bukan total_log).
AKTIF = {
    "total_event": 446, "hari_aktif": 29, "tugas_dikumpulkan": 12,
    "jenis_event": 21, "kuis_ditolak": 4,
}
PASIF = {
    "total_event": 5, "hari_aktif": 1, "tugas_dikumpulkan": 0,
    "jenis_event": 3, "kuis_ditolak": 0,
}


def payload(mahasiswa: list[dict] | None = None, **params) -> dict:
    if mahasiswa is None:
        mahasiswa = [
            # dict(AKTIF) dipakai supaya tiap mahasiswa punya salinan sendiri.
            # Kalau hanya AKTIF yang dipakai, satu test yang mengubah
            # metrics akan merusak semua test berikutnya.
            {"nim": f"33125010{i:02d}", "kelas_id": 5, "metrics": dict(AKTIF)}
            for i in range(6)
        ]
    return {
        "batch_id": 123,
        "period": "2026-09",
        "params": params,
        "students": mahasiswa,
    }


def test_health_mengonfirmasi_layanan_hidup():
    body = client.get("/health").json()
    assert body["status"] == "ok"
    assert body["api_version"] == "3.1"


def test_analyze_mengembalikan_hasil_lengkap():
    body = client.post("/analyze", json=payload()).json()

    assert body["batch_id"] == 123
    assert body["period"] == "2026-09"
    assert len(body["results"]) == 6

    hasil = body["results"][0]
    assert hasil["nim"] == "3312501000", "NIM harus kembali apa adanya, tanpa id substitusi"
    assert 0 <= hasil["engagement_rate"] <= 1
    assert 0 <= hasil["engagement_score"] <= 100
    assert set(hasil["komponen"]) == {"frekuensi", "konsistensi", "tugas", "variasi"}
    assert hasil["cluster"] >= 0
    assert hasil["cluster_label"]
    assert 0 <= hasil["risk_score"] <= 1
    assert hasil["risk_level"] in {"low", "medium", "high"}
    assert 0 <= hasil["confidence"] <= 1


def test_analyze_mengembalikan_profil_cluster():
    profil = client.post("/analyze", json=payload()).json()["clusters"]

    assert profil
    for p in profil:
        assert p["jumlah_mahasiswa"] > 0
        assert p["deskripsi"], "Profil harus punya penjelasan (FR-19)"
        assert 0 <= p["rata_rata_engagement"] <= 1


def test_meta_menyertakan_kalibrasi():
    """Laravel butuh tahu batas mana yang dipakai, untuk penjelasan."""
    meta = client.post("/analyze", json=payload()).json()["meta"]
    assert meta["jumlah_mahasiswa"] == 6
    assert meta["api_version"] == "3.1"
    assert meta["kalibrasi"], "Meta harus membawa angka kalibrasi"
    assert "total_event" in meta["kalibrasi"]


def test_mahasiswa_aktif_dapat_risiko_rendah():
    body = client.post("/analyze", json=payload()).json()
    assert all(r["risk_level"] == "low" for r in body["results"])


def test_mahasiswa_pasif_dapat_risiko_tinggi():
    mhs = [{"nim": "3312501013", "kelas_id": 5, "metrics": PASIF}]
    hasil = client.post("/analyze", json=payload(mhs)).json()["results"][0]

    assert hasil["risk_level"] == "high"
    assert hasil["engagement_rate"] < 0.1


def test_komponen_terkai_eksplisit_untuk_insight():
    """FR-19: Insight Pop-up butuh breakdown, bukan cuma angka akhir."""
    mhs = [{"nim": "3312501013", "kelas_id": 5, "metrics": PASIF}]
    komponen = client.post("/analyze", json=payload(mhs)).json()["results"][0]["komponen"]
    assert komponen["frekuensi"] < 0.05
    assert komponen["konsistensi"] < 0.05
    assert komponen["tugas"] == 0.0


def test_mahasiswa_aktif_bernilai_engagement_tinggi_sesuai_data_nyata():
    hasil = client.post("/analyze", json=payload()).json()["results"][0]
    assert hasil["engagement_rate"] == pytest.approx(0.70, abs=0.02)


# ------------------------------------------------------------- validasi


def test_periode_tidak_valid_ditolak():
    data = payload()
    data["period"] = "September 2026"
    assert client.post("/analyze", json=data).status_code == 422


def test_periode_mingguan_diterima():
    data = payload()
    data["period"] = "2026-W35"
    assert client.post("/analyze", json=data).status_code == 200


def test_periode_rentang_satu_semester_diterima():
    """v3.1: jendela analisis boleh satu semester, bukan cuma satu bulan."""
    data = payload()
    data["period"] = "2026-02..2026-07"

    respons = client.post("/analyze", json=data)

    assert respons.status_code == 200
    assert respons.json()["period"] == "2026-02..2026-07"


def test_periode_rentang_terbalik_ditolak():
    data = payload()
    data["period"] = "2026-07..2026-02"
    assert client.post("/analyze", json=data).status_code == 422


def test_periode_rentang_setengah_ditolak():
    data = payload()
    data["period"] = "2026-02..Juli"
    assert client.post("/analyze", json=data).status_code == 422


def test_daftar_mahasiswa_kosong_ditolak():
    data = payload()
    data["students"] = []
    assert client.post("/analyze", json=data).status_code == 422


def test_metrik_negatif_ditolak():
    data = payload()
    data["students"][0]["metrics"]["total_event"] = -5
    assert client.post("/analyze", json=data).status_code == 422


def test_kolom_lama_yang_dihapus_ditolak():
    """avg_session_minutes dihapus di v2, jadi tidak boleh diterima diam-diam."""
    data = payload()
    data["students"][0]["metrics"]["avg_session_minutes"] = 30
    assert client.post("/analyze", json=data).status_code == 422


def test_n_cluster_diluar_batas_ditolak():
    assert client.post("/analyze", json=payload(n_clusters=99)).status_code == 422


def test_ambang_khusus_kelas_menimpa_kalibrasi_bawaan():
    """v3.1: kalibrasi bisa per mata kuliah lewat params.batas."""
    data = payload()
    data["params"] = {
        "batas": {
            "total_event": 100.0,
            "hari_aktif": 10.0,
            "tugas_dikumpulkan": 5.0,
            "jenis_event": 10.0,
        },
    }

    respons = client.post("/analyze", json=data)

    assert respons.status_code == 200
    isi = respons.json()

    # Metrik mahasiswa: 446 event, 29 hari, 12 tugas, 21 jenis event.
    # Dengan ambang bawaan (600/45/18/28) engagement-nya di bawah 1; dengan
    # ambang kelas (100/10/5/10) tiap komponen mentok di 1,0.
    assert isi["results"][0]["engagement_rate"] == 1.0
    assert isi["meta"]["kalibrasi"]["total_event"] == 100.0
    assert isi["meta"]["kalibrasi"]["hari_aktif"] == 10.0


def test_tanpa_ambang_khusus_kalibrasi_bawaan_dipakai():
    isi = client.post("/analyze", json=payload()).json()

    assert isi["meta"]["kalibrasi"]["total_event"] == 600.0


def test_ambang_harus_positif():
    data = payload()
    data["params"] = {
        "batas": {
            "total_event": 0,
            "hari_aktif": 10,
            "tugas_dikumpulkan": 5,
            "jenis_event": 10,
        },
    }

    assert client.post("/analyze", json=data).status_code == 422


def test_ambang_kurang_komponen_ditolak():
    data = payload()
    data["params"] = {
        "batas": {"total_event": 100, "hari_aktif": 10},
    }

    assert client.post("/analyze", json=data).status_code == 422


def test_endpoint_tidak_ada_menghasilkan_404():
    assert client.post("/endpoint-yang-tidak-ada", json={}).status_code == 404


# ------------------------------------------------------------- fallback (FR-12a)


def test_fallback_terpicu_saat_data_terlalu_sedikit():
    data = payload(mahasiswa=payload()["students"][:1], n_clusters=3)
    body = client.post("/analyze", json=data).json()

    assert body["meta"]["fallback_used"] is True
    assert body["meta"]["warning"]
    assert body["meta"]["algorithm"] == "threshold-fallback"
    assert len(body["results"]) == 1


def test_layanan_tetap_menjawab_saat_data_kurang():
    """FR-12a: gulpanya harus tampil dengan penanda, bukan halaman kosong."""
    data = payload(mahasiswa=payload()["students"][:1], n_clusters=3)
    body = client.post("/analyze", json=data).json()
    assert body["results"][0]["risk_level"] in {"low", "medium", "high"}


if __name__ == "__main__":
    sys.exit(pytest.main([__file__, "-v"]))