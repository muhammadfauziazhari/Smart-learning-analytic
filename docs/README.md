# Dokumen Rancangan

Folder ini menampung dokumen rancangan proyek.

## Yang sudah tersedia

| Dokumen | Isi | Status |
|---|---|---|
| Kebutuhan Fungsional | 27 FR dalam 7 modul, beserta kriteria terima | Disusun di percakapan, belum ditulis ke file |
| Use Case Diagram | Aktor, use case, relasi `include`/`extend`/`communicate` (PlantUML) | Disusun di percakapan, belum ditulis ke file |
| Desain Basis Data | ERD, kamus data, relasi logis, dan panduan membaca database | Tersedia di `docs/desain-basis-data.md` |

Dokumen desain basis data sudah ditulis karena bentuk tabelnya dapat dibaca
langsung dari migration dan database. Dokumen lainnya masih menunggu keputusan
berikut:

1. Contoh header CSV log LMS Polymedi Batam
2. Penamaan konsisten "Kepala Prodi" atau "Kaprodi"
3. Periode analisis: harian, mingguan, atau bulanan
4. Kontrak APIFinal untuk layanan analytics

## Struktur file

```
docs/
├── kebutuhan-fungsional.md   FR-01 s.d. FR-27 + kriteria terima
├── use-case.puml             Diagram use case (PlantUML)
├── use-case.pdf              Hasil render diagram
└── desain-basis-data.md      ERD + kamus data
```

## Referensi cepat

Semua kode analis berada di `analytics/app/services/`, dengan pemetaan:

| Kode | FR |
|---|---|
| `analytics/app/services/engagement.py` | FR-11 Perhitungan Engagement Rate |
| `analytics/app/services/clustering.py` | FR-13 & FR-14 Klasterisasi dan profil |
| `analytics/app/services/risk.py` | FR-15 Skor risiko prediktif |
| `laravel/app/Services/AnalyticsService.php` | FR-12 Integrasi layanan analytics |