"""
Layanan analytics Smart Learning Analytics.
"""
from __future__ import annotations

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
from .services.engagement import BATAS_KALIBRASI, hitung_engagement
from .services.risk import confidence_dari_silhouette, hitung_risk, median_kuis_ditolak

API_VERSION = "3.1"

app = FastAPI(
    title="Smart Learning Analytics Service",
    version=API_VERSION,
    description=(
        "Menghitung Engagement Rate, klasterisasi K-Means, dan skor risiko "
        "prediktif dari data aktivitas mahasiswa."
    ),
)

MAX_STUDENTS = int(__import__("os").getenv("ANALYTICS_MAX_STUDENTS", "20000"))


@app.get("/health", summary="Cek apakah layanan hidup")
def health() -> dict:
    return {
        "status": "ok",
        "api_version": API_VERSION,
        "time": datetime.now(timezone.utc).isoformat(),
    }


@app.post("/analyze", response_model=AnalyzeResponse, summary="Analisis satu batch")
def analyze(payload: AnalyzeRequest) -> AnalyzeResponse:
    """Laravel mengirim agregat satu baris per mahasiswa-per-kelas-periode."""
    if len(payload.students) > MAX_STUDENTS:
        raise HTTPException(
            status_code=413,
            detail=(
                f"Jumlah mahasiswa ({len(payload.students)}) melebihi batas "
                f"{MAX_STUDENTS}. Pecah per kelas atau per periode."
            ),
        )

    # 1. FR-11: engagement per mahasiswa
    #
    # Ambang khusus kelas dipakai lebih dulu kalau dikirim. Kalau tidak,
    # dipakai kalibrasi bawaan yang dihitung dari IF209.
    batas = payload.params.batas.model_dump() if payload.params.batas else None

    baris = []
    komponen_engagement = {}
    for siswa in payload.students:
        e = hitung_engagement(siswa.metrics, batas)
        # Breakdown komponen disimpan terpisah untuk Insight Pop-up (FR-19)
        komponen_engagement[(siswa.nim, siswa.kelas_id)] = {
            "frekuensi": e["frekuensi"],
            "konsistensi": e["konsistensi"],
            "tugas": e["tugas"],
            "variasi": e["variasi"],
        }
        baris.append(
            {
                "nim": siswa.nim,
                "kelas_id": siswa.kelas_id,
                "frekuensi": e["frekuensi"],
                "konsistensi": e["konsistensi"],
                "tugas": e["tugas"],
                "variasi": e["variasi"],
                "engagement_rate": e["engagement_rate"],
                "engagement_score": e["engagement_score"],
                "kuis_ditolak": siswa.metrics.kuis_ditolak,
            }
        )

    # 2. FR-13 & FR-14: klasterisasi + label
    labels, info = clusterize(
        baris,
        n_clusters=payload.params.n_clusters,
        random_state=payload.params.random_state,
    )
    label_names: dict[int, str] = info.get("label_names") or {}

    # 3. FR-15: skor risiko
    #
    # Median kuis_ditolak dihitung sekali per batch, supaya penalti hanya
    # diberikan kepada mahasiswa yang jauh lebih sering diblokir daripada
    # teman-temannya. Tanpa ini, penalti tidak membedakan siapa pun.
    median_kuis = median_kuis_ditolak([b["kuis_ditolak"] for b in baris])

    hasil: list[StudentResult] = []
    for baris_ke, label in zip(baris, labels, strict=True):
        risiko = hitung_risk(
            baris_ke["engagement_rate"],
            kuis_ditolak=baris_ke["kuis_ditolak"],
            median_kuis=median_kuis,
        )

        # TINGKAT BAHAYA TIDAK DITENTUKAN DI SINI.
        # Status EWS adalah FR-16 dan dihitung Laravel berdasarkan
        # threshold yang disetel Kaprodi/Dosen. Nilai di bawah hanya
        # untuk kebutuhan ditampilkan, bukan keputusan.
        if risiko["risk_score"] >= 0.65:
            level = "high"
        elif risiko["risk_score"] >= 0.40:
            level = "medium"
        else:
            level = "low"

        hasil.append(
            StudentResult(
                nim=baris_ke["nim"],
                kelas_id=baris_ke["kelas_id"],
                engagement_rate=baris_ke["engagement_rate"],
                engagement_score=baris_ke["engagement_score"],
                komponen=komponen_engagement[(baris_ke["nim"], baris_ke["kelas_id"])],
                cluster=label,
                cluster_label=label_names.get(label, f"Klaster {label}"),
                risk_score=risiko["risk_score"],
                risk_level=level,
                confidence=confidence_dari_silhouette(
                    info.get("silhouette_score"),
                    jumlah_cluster=len(set(labels)),
                ),
            )
        )

    # 4. Profil tiap cluster (FR-14), dipakai untuk Insight Pop-up (FR-19)
    profil: list[ClusterProfile] = []
    for cluster_id in sorted(set(labels)):
        anggota = [r for r, lab in zip(hasil, labels, strict=True) if lab == cluster_id]
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
            # Ambang yang benar-benar dipakai, supaya angka di layar bisa
            # ditelusuri ke sumbernya (FR-11).
            kalibrasi={**BATAS_KALIBRASI, **(batas or {})},
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
            "meningkatkan variasi jenis aktivitas dapat membantu."
        )
    if rata_risk >= 0.55:
        return (
            "Mahasiswa dalam kelompok ini jarang aktif dan tugasnya menumpuk. "
            "Disarankan menghubungi mahasiswa secara langsung."
        )
    return (
        "Mahasiswa dalam kelompok ini memiliki aktivitas di bawah rata-rata. "
        "Periksa apakah ada kendala akses atau kesulitan memahami materi."
    )