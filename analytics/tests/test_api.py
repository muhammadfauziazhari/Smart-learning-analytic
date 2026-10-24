"""Test endpoint HTTP (FR-12).

Pakai TestClient FastAPI, jadi tidak perlu menjalankan uvicorn.
"""

from __future__ import annotations

import sys
from pathlib import Path

import pytest
from fastapi.testclient import TestClient

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from app.main import app  # noqa: E402

client = TestClient(app)


def payload(mahasiswa: list[dict] | None = None, **params) -> dict:
    """Bangun request /analyze yang valid."""
    if mahasiswa is None:
        mahasiswa = [
            {
                "mahasiswa_id": i + 1,
                "kelas_id": 5,
                "metrics": {
                    "total_log": 200,
                    "days_active": 28,
                    "days_in_period": 30,
                    "upload_count": 20,
                    "assignment_done": 20,
                    "quiz_done": 20,
                    "discussion_count": 30,
                    "avg_session_minutes": 60.0,
                },
            }
            for i in range(5)
        ]
    return {
        "batch_id": 123,
        "period": "2026-09",
        "params": params,
        "students": mahasiswa,
    }


def test_health_mengonfirmasi_layanan_hidup():
    res = client.get("/health")
    assert res.status_code == 200
    body = res.json()
    assert body["status"] == "ok"
    assert body["api_version"] == "1.0"


def test_analyze_mengembalikan_hasil_lengkap():
    res = client.post("/analyze", json=payload())
    assert res.status_code == 200
    body = res.json()

    assert body["batch_id"] == 123
    assert body["period"] == "2026-09"
    assert len(body["results"]) == 5

    hasil = body["results"][0]
    assert 0 <= hasil["engagement_rate"] <= 1
    assert 0 <= hasil["engagement_score"] <= 100
    assert hasil["cluster"] >= 0
    assert hasil["cluster_label"]
    assert 0 <= hasil["risk_score"] <= 1
    assert hasil["risk_level"] in {"low", "medium", "high"}
    assert 0 <= hasil["confidence"] <= 1


def test_analyze_mengembalikan_profil_cluster():
    res = client.post("/analyze", json=payload())
    profil = res.json()["clusters"]

    assert profil, "Harus ada minimal satu profil cluster"
    for p in profil:
        assert p["jumlah_mahasiswa"] > 0
        assert p["deskripsi"], "Profil harus punya kalimat penjelasan (FR-19)"
        assert 0 <= p["rata_rata_engagement"] <= 1


def test_meta_menyebut_algoritma_dan_jumlah_data():
    meta = client.post("/analyze", json=payload()).json()["meta"]
    assert meta["jumlah_mahasiswa"] == 5
    assert meta["api_version"] == "1.0"
    assert meta["algorithm"] in {"kmeans", "threshold-fallback"}


def test_semua_mahasiswa_aktif_dapat_risiko_rendah():
    res = client.post("/analyze", json=payload())
    assert all(r["risk_level"] == "low" for r in res.json()["results"])


def test_mahasiswa_nol_aktivitas_dapat_risiko_tinggi():
    idf = {
        "mahasiswa_id": 1,
        "kelas_id": 5,
        "metrics": {
            "total_log": 0,
            "days_active": 0,
            "days_in_period": 30,
            "upload_count": 0,
            "assignment_done": 0,
            "quiz_done": 0,
            "discussion_count": 0,
            "avg_session_minutes": 0.0,
        },
    }
    res = client.post("/analyze", json=payload([idf]))
    hasil = res.json()["results"][0]

    assert hasil["risk_level"] == "high"
    assert hasil["engagement_rate"] == 0.0


def test_periode_tidak_valid_ditolak():
    data = payload()
    data["period"] = "September 2026"
    res = client.post("/analyze", json=data)
    assert res.status_code == 422


def test_daftar_mahasiswa_kosong_ditolak():
    data = payload()
    data["students"] = []
    res = client.post("/analyze", json=data)
    assert res.status_code == 422


def test_metrik_negatif_ditolak():
    data = payload()
    data["students"][0]["metrics"]["total_log"] = -5
    res = client.post("/analyze", json=data)
    assert res.status_code == 422


def test_n_cluster_diluar_batas_ditolak():
    res = client.post("/analyze", json=payload(n_clusters=99))
    assert res.status_code == 422


def test_fallback_terpicu_saat_data_terlalu_sedikit():
    """FR-12a: layanan tetap menjawab, dengan penanda fallback."""
    data = payload(mahasiswa=payload()["students"][:1], n_clusters=3)
    body = client.post("/analyze", json=data).json()

    assert body["meta"]["fallback_used"] is True
    assert body["meta"]["warning"]
    assert body["meta"]["algorithm"] == "threshold-fallback"
    assert len(body["results"]) == 1


def test_respons_bukan_json_bila_endpoint_salah():
    assert client.post("/endpoint-yang-tidak-ada", json={}).status_code == 404


if __name__ == "__main__":
    sys.exit(pytest.main([__file__, "-v"]))