"""FR-11: Perhitungan Engagement Rate.

Engagement Rate dihitung dari empat komponen yang dinormalisasi 0..1,
lalu digenbam rata. Bobotnya dibatasi agar satu komponen tidak
men overpower tiga komponen lain.
"""

from __future__ import annotations

from ..schemas import StudentMetrics

# Bobot komponen engagement, total = 1.0
WEIGHTS = {
    "frekuensi": 0.30,
    "konsistensi": 0.25,
    "tugas": 0.25,
    "interaksi": 0.20,
}


def _clamp(value: float, low: float = 0.0, high: float = 1.0) -> float:
    return max(low, min(high, value))


def hitung_engagement(m: StudentMetrics) -> dict[str, float]:
    """Kembalikan engagement_rate (0..1), engagement_score (0..100), dan rincian komponen."""
    # Frekuensi: aktivitas per hari, dianggap penuh pada 10 log/hari
    frekuensi = _clamp((m.total_log / m.days_in_period) / 10.0)

    # Konsistensi: berapa persen hari dalam periode yang ada aktivitas
    konsistensi = _clamp(m.days_active / m.days_in_period)

    # Tugas: gabungan tugas dikumpulkan dan kuis dikerjakan, dianggap penuh pada 10 item
    tugas = _clamp(((m.assignment_done + m.quiz_done) / 2.0) / 10.0)

    # Interaksi: diskusi, penuh pada 15 diskusi
    interaksi = _clamp(m.discussion_count / 15.0)

    rate = (
        frekuensi * WEIGHTS["frekuensi"]
        + konsistensi * WEIGHTS["konsistensi"]
        + tugas * WEIGHTS["tugas"]
        + interaksi * WEIGHTS["interaksi"]
    )

    return {
        "frekuensi": round(frekuensi, 4),
        "konsistensi": round(konsistensi, 4),
        "tugas": round(tugas, 4),
        "interaksi": round(interaksi, 4),
        "engagement_rate": round(_clamp(rate), 4),
        "engagement_score": round(_clamp(rate) * 100, 2),
    }