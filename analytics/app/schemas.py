"""Kontrak data antara Laravel (klien) dan layanan analytics (server).

Perubahan di file ini berarti breaking change: versi API di
`app/main.py` (field `api_version`) harus ikut dinaikkan.
"""

from __future__ import annotations

from typing import Literal

from pydantic import BaseModel, Field, field_validator

# ---------------------------------------------------------------- request


class StudentMetrics(BaseModel):
    """Agregasi aktivitas satu mahasiswa pada satu kelas dalam satu periode.

    Angkanya dikirim dari Laravel (tabel `analitik_*`), bukan dihitung ulang
    di sini, supaya logika bisnis tetap berada di satu tempat.
    """

    total_log: int = Field(..., ge=0, description="Jumlah baris log aktivitas")
    days_active: int = Field(..., ge=0, description="Jumlah hari unik ada aktivitas")
    days_in_period: int = Field(..., gt=0, description="Jumlah hari dalam periode")
    upload_count: int = Field(..., ge=0)
    assignment_done: int = Field(..., ge=0)
    quiz_done: int = Field(..., ge=0)
    discussion_count: int = Field(..., ge=0)
    avg_session_minutes: float = Field(..., ge=0)


class StudentInput(BaseModel):
    mahasiswa_id: int
    kelas_id: int
    metrics: StudentMetrics


class AnalyzeParams(BaseModel):
    n_clusters: int = Field(3, ge=2, le=10, description="Jumlah cluster K-Means")
    random_state: int = Field(42, ge=0)
    risk_weights: dict[str, float] | None = Field(
        None, description="Bobot risk custom. None = bobot default."
    )


class AnalyzeRequest(BaseModel):
    batch_id: int = Field(..., description="ID import_batches di Laravel")
    period: str = Field(..., description="Periode analisis, mis. '2026-09' atau '2026-09-1'")
    params: AnalyzeParams = Field(default_factory=AnalyzeParams)
    students: list[StudentInput] = Field(..., min_length=1)

    @field_validator("period")
    @classmethod
    def period_format(cls, value: str) -> str:
        # Format YYYY-MM atau YYYY-WW (mingguan)
        parts = value.split("-")
        if len(parts) not in (2, 3) or not all(p.isdigit() for p in parts):
            raise ValueError("period harus berformat YYYY-MM atau YYYY-WW")
        return value


# ---------------------------------------------------------------- response

RiskLevel = Literal["low", "medium", "high"]


class StudentResult(BaseModel):
    mahasiswa_id: int
    kelas_id: int

    # FR-11
    engagement_rate: float = Field(..., ge=0, le=1)
    engagement_score: float = Field(..., ge=0, le=100)

    # FR-13 / FR-14
    cluster: int = Field(..., ge=0)
    cluster_label: str

    # FR-15
    risk_score: float = Field(..., ge=0, le=1)
    risk_level: RiskLevel
    confidence: float = Field(..., ge=0, le=1)


class ClusterProfile(BaseModel):
    """Deskripsi profil satu cluster, ditampilkan di Insight Pop-up (FR-19)."""

    cluster: int
    label: str
    jumlah_mahasiswa: int
    rata_rata_engagement: float
    rata_rata_risk: float
    deskripsi: str


class AnalyzeMeta(BaseModel):
    api_version: str
    algorithm: str
    jumlah_mahasiswa: int
    silhouette_score: float | None = Field(
        None, description="Kualitas pengelompokan. None = jumlah data < n_clusters."
    )
    fallback_used: bool = Field(
        False, description="True bila K-Means gagal dan memakai pengelompokan berbasis ambang."
    )
    warning: str | None = None


class AnalyzeResponse(BaseModel):
    batch_id: int
    period: str
    results: list[StudentResult]
    clusters: list[ClusterProfile]
    meta: AnalyzeMeta