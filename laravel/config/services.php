<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Analytics Service
    |--------------------------------------------------------------------------
    |
    | Layanan Python terpisah di folder ../analytics. Berisi perhitungan
    | Engagement Rate, K-Means, dan skor risiko (FR-11 s.d. FR-15).
    |
    | Jalankan layanan dari folder analytics:
    |   .venv\Scripts\uvicorn app.main:app --port 8001
    |
    | Uji koneksinya dengan: php artisan analytics:ping
    |
    */

    'analytics' => [
        'url' => env('ANALYTICS_SERVICE_URL', 'http://127.0.0.1:8001'),
        'timeout' => (int) env('ANALYTICS_SERVICE_TIMEOUT', 30),
        'connect_timeout' => (int) env('ANALYTICS_SERVICE_CONNECT_TIMEOUT', 5),

        /*
        |----------------------------------------------------------------------
        | Kalibrasi engagement per mata kuliah
        |----------------------------------------------------------------------
        |
        | Ambang atas tiap komponen engagement. Kalau satu mata kuliah tidak
        | disebut di sini, dipakai kalibrasi bawaan di analytics/app/
        | services/engagement.py (BATAS_KALIBRASI), yang dihitung dari IF209.
        |
        | Kenapa perlu per mata kuliah: ambang itu harus mengikuti intensitas
        | aktivitas kelas. Angka dari semester Feb-Jul 2026 (nilai yang dipakai
        | di bawah):
        |
        |   komponen         IF209 median/p75     IF207 median/p75
        |   total_event         325 / 433            91 / 184
        |   hari_aktif           29 /  42            21 /  34
        |   tugas_dikumpulkan   12 /  16             1 /   6
        |   jenis_event         21 /  24            11 /  13
        |
        | Dengan ambang IF209, mahasiswa IF207 (median 91 event) mentok di
        | komponen frekuensi 0,15 dan terbaca pasif semua. Ambang di bawah
        | dipilih sedikit di atas p75, sama seperti aturan yang dipakai
        | untuk BATAS_KALIBRASI.
        |
        */
        'kalibrasi' => [
            'IF209' => [
                'total_event' => 600,
                'hari_aktif' => 45,
                'tugas_dikumpulkan' => 18,
                'jenis_event' => 28,
            ],
            'IF207' => [
                'total_event' => 190,
                'hari_aktif' => 36,
                'tugas_dikumpulkan' => 8,
                'jenis_event' => 14,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Status EWS (FR-16) dan ambangnya (FR-17)
    |--------------------------------------------------------------------------
    |
    | PENTING: ini belum aturan resmi Early Warning.
    |
    | Halaman pengaturan ambang (FR-17) belum ada, dan siapa pun belum
    | menetapkan ambang academically. Yang dipakai sekarang hanyalah
    | penerjemahan level risiko yang sudah dihitung layanan analytics
    | menjadi tiga status, supaya kolom "Status EWS" tidak kosong total.
    |
    | `ambang` dipakai kalau layanan analytics tidak memberikan level
    | sehingga hanya skor risikonya yang ada. Nilainya
    | diambil dari batas atas yang dipakai layanan analytics.
    |
    | Keputusan pengguna 18 Okt 2026: pakai status sementara ini dulu,
    | dengan ambang yang bisa diubah lewat berkas ini.
    |
    | Ganti bagian ini dengan aturan resmi begitu FR-17 selesai; semua
    | tempat yang membaca status EWS memakai konstanta di
    | app/Services/StatistikKeaktifan.php, jadi perubahannya hanya di sini.
    |
    */

    'ews' => [
        'sementara' => true,

        // Terjemahan level risiko -> status EWS.
        'label' => [
            'low' => 'Aman',
            'medium' => 'Perhatian',
            'high' => 'At-Risk',
        ],

        // Cadangan: dipakai hanya bila level risiko tidak tersedia.
        'ambang' => [
            'perhatian' => 0.35,
            'at_risk' => 0.60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Konversi berkas Excel untuk impor daftar kelas
    |--------------------------------------------------------------------------
    |
    | Daftar mahasiswa per kelas (Pagi/Malam, A/B/C/D) disusun di Excel.
    | PHP di komputer ini tidak punya ekstensi `zip`, sedangkan .xlsx itu
    | sebenarnya arsip zip, jadi Laravel tidak bisa membacanya sendiri.
    |
    | Karena itu dikonversi dulu oleh Python (analisis/scripts/
    | xlsx_ke_csv.py) memakai openpyxl dari virtual environment analytics.
    |
    | Kalau `python` dikosongkan, perintah kelas:impor-dari-roster akan
    | mencoba `python` dari PATH. Kalau tidak ada, perintah berhenti dengan
    | pesan jelas — bukan diam-diam mengimpor data kosong.
    |
    */

    'excel' => [
        'python' => env('EXCEL_PYTHON', base_path('../analytics/.venv/Scripts/python.exe')),
        'script' => base_path('../analytics/scripts/xlsx_ke_csv.py'),
        'berkas' => [
            base_path('sample-data/Data_Mahasiswa_Pagi_Malam_Terurut-v3.xlsx'),
            base_path('sample-data/Data_Mahasiswa_Pagi_Malam_Terurut-v2.xlsx'),
        ],
    ],

];
