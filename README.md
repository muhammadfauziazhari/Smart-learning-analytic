# Smart Learning Analytics

Sistem yang mengolah log aktivitas LMS Politeknik Negeri Batam menjadi informasi
untuk menunjang pembelajaran. Log mentah diubah menjadi statistik, skor risiko,
dan status Early Warning System (EWS) untuk)Dosen Pengampu dan Kepala Prodi.

## Struktur repository

Repository ini berisi dua aplikasi yang berjalan terpisah:

```
smart-learning-analytic/
├── laravel/        Aplikasi web (portal, impor CSV, dashboard, modul EWS)
├── analytics/      Layanan komputasi (Engagement Rate, K-Means, skor risiko)
├── docs/           Dokumen rancangan: FR, use case, ERD
└── README.md
```

Kedua aplikasi **tidak** berbagi database. Layanan analytics tidak pernah
menghubungi MySQL: Laravel mengirim data agregat lewat HTTP, menerima hasil
perhitungan, lalu menyimpan sendiri ke database. Dengan begitu aturan bisnis
dan jejak audit hanya ada di satu tempat.

```
Laravel  ──HTTP POST /analyze──>  Analytics (Python)
Laravel  <────JSON hasil───────  Analytics (Python)
Laravel  ──simpan ke MySQL─────>  MySQL
```

## Menjalankan di lokal

Butuh PHP 8.5, Composer, Node.js, dan Python 3.11+.

### 1. Aplikasi Laravel

```sh
cd laravel
composer install
npm install
cp .env.example .env         # Windows: copy .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

### 2. Layanan analytics

```sh
cd analytics
python -m venv .venv
.venv\Scripts\pip install -r requirements.txt   # Linux/macOS: .venv/bin/pip
.venv\Scripts\uvicorn app.main:app --port 8001  # Linux/macOS: .venv/bin/uvicorn ...
```

Dokumentasi API ada otomatis di <http://127.0.0.1:8001/docs>.

### 3. Cek koneksi antar kedua aplikasi

```sh
cd laravel
php artisan analytics:ping
```

Keluarannya `Layanan hidup.` kalau keduanya sudah terhubung.

## Kontrak API

| Endpoint | Keterangan |
|---|---|
| `GET /health` | Cek kesiapan layanan, dipakai `analytics:ping` |
| `POST /analyze` | Hitung analitik satu batch import (maks. 20.000 mahasiswa) |

Laravel mengirim agregat satu baris per mahasiswa-per-kelas-periode.
Mahasiswa identified by NIM, bukan id akun, karena mereka tidak punya akun:

```json
{
  "batch_id": 123,
  "period": "2026-09",
  "students": [
    {
      "nim": "3312501013",
      "kelas_id": 5,
      "metrics": {
        "total_event": 446,
        "hari_aktif": 29,
        "tugas_dikumpulkan": 12,
        "jenis_event": 21,
        "kuis_ditolak": 4
      }
    }
  ]
}
```

Layanan menolak field yang tidak dikenal (`extra="forbid"`). Kalau nama
field salah ketik, permintaan ditolak 422 — bukan diterima diam-diam dengan
seluruh nilai nol.

## Pengujian

```sh
cd laravel   && php artisan test                              # 98 test
cd analytics && .venv\Scripts\python.exe -m pytest tests -q   # 45 test
```

## Peran pengguna

| Peran | Cakupan |
|---|---|
| Admin | Kelola akun dan data master, impor log LMS, atur threshold EWS |
| Dosen | Analitik kelas yang ia ampu, quick action ke mahasiswa |
| Kaprodi | Analitik makro prodi yang diampu, performa dosen |

Mahasiswa **tidak punya akun**. Mereka tidak pernah masuk ke sistem ini,
jadi yang disimpan hanya NIM dan nama pada tabel `kelas_mahasiswa`.
Tidak ada password atau email yang dibuat-buat untuk mereka.

## Catatan untuk deploy

Folder `analytics/` tidak ikut ter-upload ke hosting. Saat `composer install
--optimize-autoloader` di server, folder itu sudah terpisah dari `laravel/`,
jadi cukup menyalin isi `laravel/` saja.