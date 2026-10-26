"""Kontrak data antara Laravel (klien) dan layanan analytics (server).

Versi 3.1. Perubahan dari versi 3.0:

- `AnalyzeRequest.period` sekarang menerima RENTANG: `2026-02..2026-07`.
  Alasannya ambang batas engagement dikalibrasi dari aktivitas satu
  semester penuh. Kalau dikirim satu bulan saja, semua mahasiswa otomatis
  terbaca rendah karena jumlahnya memang lebih kecil dari ambang, bukan
  karena mereka tidak blushing.

Perubahan dari versi 2.0:

- `mahasiswa_id` (int) DIGANTI menjadi `nim` (str). Mahasiswa tidak punya
  akun di sistem ini, jadi tidak ada baris `users` yang bisa dijadikan id.
  NIM adalah identitas mahasiswa yang memang ada di log LMS.

Perubahan dari versi 1.0:

- `avg_session_minutes`, `days_active`, `days_in_period`, `upload_count`,
  `assignment_done`, `quiz_done`, dan `discussion_count` DIHAPUS karena
  tidak punya dasar data pada log Moodle (lihat catatan di
  `services/engagement.py`).
- `StudentResult` sekarang membawa `komponen`, breakdown engagement per
  mahasiswa, yang dipakai untuk Insight Pop-up (FR-19).
- `AnalyzeMeta` membawa `kalibrasi`, supaya Laravel bisa menampilkan
  angka batas yang dipakai saat perhitungan.

Perubahan di file ini berarti breaking change: naikkan `api_version`
di `app/main.py`.
"""

from __future__ import annotations

from typing import Literal

from pydantic import BaseModel, ConfigDict, Field, field_validator

# Field yang tidak dikenal HARUS ditolak.
#
# Tanpa ini, Pydantic diam-diam mengabaikannya. Kalau Laravel mengirim
# `total_log` (nama versi 1) karena salah ketik, permintaan tetap diterima
# tetapi semua mahasiswa dapat total_event = 0, sehingga seluruh kelas
# terbaca "berisiko tinggi". Kegagalan senyap yang paling berbahaya.
STRICT = ConfigDict(extra="forbid")


# ---------------------------------------------------------------- request


class StudentMetrics(BaseModel):
    """Agregasi aktivitas satu mahasiswa pada satu kelas dalam satu periode.

    Dihitung di Laravel dari tabel `log_aktivitas`, bukan dihitung ulang
    di sini, supaya aturan bisnis tetap berada di satu tempat.
    """

    model_config = STRICT

    total_event: int = Field(
        ..., ge=0, description="Jumlah seluruh baris aktivitas pada periode"
    )
    hari_aktif: int = Field(
        ..., ge=0, description="Jumlah hari unik yang ada aktivitas"
    )
    tugas_dikumpulkan: int = Field(
        ..., ge=0, description="Jumlah tugas yang benar-benar dikumpulkan"
    )
    jenis_event: int = Field(
        ..., ge=0, description="Jumlah jenis aktivitas berbeda yang dilakukan"
    )
    kuis_ditolak: int = Field(
        0,
        ge=0,
        description="Banyaknya akses kuis yang ditolak Moodle (Quiz access was prevented)",
    )


class StudentInput(BaseModel):
    model_config = STRICT

    nim: str = Field(
        ...,
        min_length=1,
        max_length=32,
        description=(
            "Nomor induk mahasiswa. Mahasiswa tidak punya akun di sistem ini, "
            "jadi NIM adalah satu-satunya identitasnya."
        ),
    )
    kelas_id: int
    metrics: StudentMetrics


class BatasKalibrasi(BaseModel):
    """Ambang atas tiap komponen engagement (1,0 berarti komponen penuh).

    Dikirim Laravel karena keputusan kebijakan ada di sisi aplikasi: tiap
    mata kuliah punya intensitas aktivitas yang berbeda, jadi satu angka
    global akan membuat kelas yang minim aktivitas terbaca seluruhnya pasif.
    """

    model_config = STRICT

    total_event: float = Field(..., gt=0)
    hari_aktif: float = Field(..., gt=0)
    tugas_dikumpulkan: float = Field(..., gt=0)
    jenis_event: float = Field(..., gt=0)


class AnalyzeParams(BaseModel):
    model_config = STRICT

    n_clusters: int = Field(3, ge=2, le=10, description="Jumlah cluster K-Means")
    random_state: int = Field(42, ge=0)
    batas: BatasKalibrasi | None = Field(
        None,
        description=(
            "Ambang engagement khusus kelas ini. Kalau kosong, dipakai "
            "kalibrasi bawaan yang dihitung dari IF209."
        ),
    )


class AnalyzeRequest(BaseModel):
    model_config = STRICT

    batch_id: int = Field(..., description="ID import_batches di Laravel")
    period: str = Field(
        ...,
        description=(
            "Jendela analisis: '2026-09' atau '2026-W35' untuk satu bulan/minggu, "
            "atau '2026-02..2026-07' untuk satu rentang"
        ),
    )
    params: AnalyzeParams = Field(default_factory=AnalyzeParams)
    students: list[StudentInput] = Field(..., min_length=1)

    @field_validator("period")
    @classmethod
    def period_format(cls, value: str) -> str:
        # Rentang: YYYY-MM..YYYY-MM
        if ".." in value:
            awal, _, akhir = value.partition("..")
            cls._cek_satu_periode(awal)
            cls._cek_satu_periode(akhir)

            if awal > akhir:
                raise ValueError(
                    f"periode rentang terbalik: {awal} lebih besar dari {akhir}",
                )

            return value

        cls._cek_satu_periode(value)

        return value

    @staticmethod
    def _cek_satu_periode(bagian: str) -> None:
        # YYYY-MM untuk bulanan, YYYY-Www untuk mingguan
        potong = bagian.split("-")
        if len(potong) != 2 or not potong[0].isdigit():
            raise ValueError("period harus berformat YYYY-MM atau YYYY-Www")
        if not (potong[1].isdigit() or potong[1].upper().startswith("W")):
            raise ValueError("period harus berformat YYYY-MM atau YYYY-Www")


# ---------------------------------------------------------------- response

RiskLevel = Literal["low", "medium", "high"]


class StudentResult(BaseModel):
    nim: str
    kelas_id: int

    # FR-11
    engagement_rate: float = Field(..., ge=0, le=1)
    engagement_score: float = Field(..., ge=0, le=100)
    komponen: dict[str, float] = Field(
        ...,
        description="Breakdown komponen engagement, dipakai untuk Insight Pop-up (FR-19)",
    )

    # FR-13 / FR-14
    cluster: int = Field(..., ge=0)
    cluster_label: str

    # FR-15
    risk_score: float = Field(..., ge=0, le=1)
    risk_level: RiskLevel = Field(
        ...,
        description="Hanya untuk tampilan. Status EWS ditentukan Laravel (FR-16).",
    )
    confidence: float = Field(
        ...,
        ge=0,
        le=1,
        description="Keyakinan terhadap pengelompokan, bukan terhadap risiko",
    )


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
        False, description="True bila K-Means gagal dan memakai ambang engagement."
    )
    warning: str | None = None
    kalibrasi: dict[str, float] = Field(
        default_factory=dict,
        description="Batas atas komponen engagement yang dipakai saat menghitung",
    )


class AnalyzeResponse(BaseModel):
    batch_id: int
    period: str
    results: list[StudentResult]
    clusters: list[ClusterProfile]
    meta: AnalyzeMeta