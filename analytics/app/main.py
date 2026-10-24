"""Layanan Analytics untuk Smart Learning Analytics.

Dipanggil oleh Laravel sebagai HTTP service (FR-12). Layanan ini stateless:
menerima data agregat, mengembalikan hasil perhitungan. Layanan ini TIDAK
pernah terhubung langsung ke database.
"""

from __future__ import annotations

import os
from datetime import datetime, timezone

from fastapi import FastAPI, HTTPException

from .schemas import (
    AnalyzeMeta,
    AnalyzeRequest,
    AnalyzeResponse,
    ClusterProfile,
    StudentResult,
)
from .services.clustering import clusterize
from .services.engagement import hitung_engagement
from .services.risk import confidence_dari_silhouette, hitung_risk

API_VERSION = "1.0"

app = FastAPI(
    title="Smart Learning Analytics Service",
    version=API_VERSION,
    description=(
        "Menghitung Engagement Rate, klasterisasi K-Means, dan skor risiko "
        "prediktif dari data aktivitas mahasiswa."
    ),
)

# Batas jumlah mahasiswa per satu request, supaya tidak menabrak memory
MAX_STUDENTS = int(os.getenv("ANALYTICS_MAX_STUDENTS", "20000"))


@app.get("/health", summary="Cek apakah layanan hidup")
def health() -> dict:
    """Dipakai Laravel untuk memastikan layanan siap sebelum mengirim data."""
    return {
        "status": "ok",
        "api_version": API_VERSION,
        "time": datetime.now(timezone.utc).isoformat(),
    }


@app.post("/analyze", response_model=AnalyzeResponse, summary="Analisis satu batch")
def analyze(payload: AnalyzeRequest) -> AnalyzeResponse:
    """Titik masuk utama: Laravel mengirim agregat satu batch import."""
    if len(payload.students) > MAX_STUDENTS:
        raise HTTPException(
            status_code=413,
            detail=(
                f"Jumlah mahasiswa ({len(payload.students)}) melebihi batas "
                f"{MAX_STUDENTS}. Pecah per kelas atau per periode."
            ),
        )

    # 1. FR-11: engagement per mahasiswa
    baris: list[dict] = []
    for siswa in payload.students:
        m = siswa.metrics
        engagement = hitung_engagement(m)
        baris.append(
            {
                "mahasiswa_id": siswa.mahasiswa_id,
                "kelas_id": siswa.kelas_id,
                "frekuensi": engagement["frekuensi"],
                "konsistensi": engagement["konsistensi"],
                "tugas": engagement["tugas"],
                "interaksi": engagement["interaksi"],
                "avg_session_minutes": m.avg_session_minutes,
                "engagement_rate": engagement["engagement_rate"],
                "engagement_score": engagement["engagement_score"],
            }
        )

    # 2. FR-13 & FR-14: klasterisasi + label
    labels, info = clusterize(
        baris,
        n_clusters=payload.params.n_clusters,
        random_state=payload.params.random_state,
    )
    label_names: dict[int, str] = info.get("label_names") or {}

    # 3. FR-15: skor risiko per mahasiswa
    hasil: list[StudentResult] = []
    for baris_ke, label in zip(baris, labels, strict=True):
        risiko = hitung_risk(
            baris_ke["engagement_rate"],
            konsistensi=baris_ke["konsistensi"],
            tugas=baris_ke["tugas"],
            interaksi=baris_ke["interaksi"],
            bobot=payload.params.risk_weights,
        )
        # Cluster hanya menunjukkan pola, bukan tingkat bahaya.
        # Tingkat bahaya ditentukan Laravel lewat threshold FR-16.
        if risiko["risk_score"] >= 0.65:
            level = "high"
        elif risiko["risk_score"] >= 0.40:
            level = "medium"
        else:
            level = "low"

        confidence = confidence_dari_silhouette(
            info.get("silhouette_score"),
            jumlah_cluster=len(set(labels)),
        )

        hasil.append(
            StudentResult(
                mahasiswa_id=baris_ke["mahasiswa_id"],
                kelas_id=baris_ke["kelas_id"],
                engagement_rate=baris_ke["engagement_rate"],
                engagement_score=baris_ke["engagement_score"],
                cluster=label,
                cluster_label=label_names.get(label, f"Klaster {label}"),
                risk_score=risiko["risk_score"],
                risk_level=level,
                confidence=confidence,
            )
        )

    # 4. Profil tiap cluster (FR-14), untuk Insight Pop-up
    profil: list[ClusterProfile] = []
    for cluster_id in sorted(set(labels)):
        anggota = [r for r, label in zip(hasil, labels, strict=True) if label == cluster_id]
        rata_engagement = sum(r.engagement_rate for r in anggota) / len(anggota)
        rata_risk = sum(r.risk_score for r in anggota) / len(anggota)
        profil.append(
            ClusterProfile(
                cluster=cluster_id,
                label=anggota[0].cluster_label,
                jumlah_mahasiswa=len(anggota),
                rata_rata_engagement=round(rata_engagement, 4),
                rata_rata_risk=round(rata_risk, 4),
                deskripsi=_deskripsi_cluster(rata_engagement, rata_risk),
            )
        )

    return AnalyzeResponse(
        batch_id=payload.batch_id,
        period=payload.period,
        results=hasil,
        clusters=profil,
        meta=AnalyzeMeta(
            api_version=API_VERSION,
            algorithm="kmeans" if not info["fallback_used"] else "threshold-fallback",
            jumlah_mahasiswa=len(hasil),
            silhouette_score=info.get("silhouette_score"),
            fallback_used=bool(info["fallback_used"]),
            warning=info.get("warning"),
        ),
    )


def _deskripsi_cluster(rata_engagement: float, rata_risk: float) -> str:
    """Kalimat singkat untuk Insight Pop-up (FR-19)."""
    if rata_engagement >= 0.75:
        return (
            "Mahasiswa dalam kelompok ini sangat aktif: frekuensi, konsistensi, "
            "dan penyelesaian tugas tinggi. Tidak ada tindakan yang perlu dilakukan."
        )
    if rata_engagement >= 0.50:
        return (
            "Mahasiswa dalam kelompok ini aktif secara wajar. Ajakan singkat untuk "
            "meningkatkan partisipasi diskusi dapat membantu."
        )
    if rata_risk >= 0.55:
        return (
            "Mahasiswa dalam kelompok ini jarang berinteraksi dan tugasnya menumpuk. "
            "Disarankan menghubungi mahasiswa secara langsung."
        )
    return (
        "Mahasiswa dalam kelompok ini memiliki aktivitas di bawah rata-rata. "
        "Periksa apakah ada kendala akses atau misunderstandings materi."
    )