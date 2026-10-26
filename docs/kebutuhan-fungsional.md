# Kebutuhan Fungsional & Status Implementasi

> ⚠️ Catatan konsistensi nomor: FR asli skripsi (FR-01..FR-27) dan nomor FR
> yang tercantum di komentar kode **tidak sama**. Daftar asli di bawah adalah
> acuan; saat menyebut FR beserta nomor di percakapan, gunakan tabel daftar
> asli per posisi.

Sumber daftar: `Downloads/Daftar_Kebutuhan_Fungsional.docx` (skripsi, 27 FR dalam 7 modul).
Kolom **status** disusun dari hasil audit kode pada 16 Okt 2026, didukung test:

- Laravel: 113/113 test lulus (321 assertion)
- Python analytics: 45/45 test lulus

Legenda:
✅ Selesai (ada kode + test) · ◻️ Sebagian (fondasi ada, belum lengkap) · ❌ Belum dikerjakan

---

# Bentuk konsolidasi — 19 FRK (gabungan 27 FR)

27 FR asli **tetap ada semua** di bagian berikutnya; di sini hanya dipadatan
untuk yang pekerjaannya satu alur. ID baru memakai awalan **FRK** supaya tidak
bertabrakan dengan nomor FR skripsi dan nomor di komentar kode. Kolom
**Fungsi** di tabel di bawah adalah rangkuman deskripsi asli masing-masing FR
pada `Daftar_Kebutuhan_Fungsional.docx` (deskripsi yang digabung dirangkai
jadi satu kalimat).

**Empat keputusan penggabungan yang layak didiskusikan ulang** (mudah dipisah
kembali kalau tidak setuju): FRK-03 (FR-03+04), FRK-05 (FR-06+07), FRK-12
(FR-16+18), FRK-16 (FR-22+23).

| FRK | Nama | Fungsi | Asal | Aktor | Status | Catatan gabung |
|---|---|---|---|---|---|---|
| FRK-01 | Autentikasi pengguna | Sistem memverifikasi email + password dan membuat sesi login. | FR-01 | Semua role | ✅ | — |
| FRK-02 | Manajemen akun pengguna | Admin membuat, mengubah, menonaktifkan, dan me-reset password akun. | FR-02 | Admin | ✅ | Halaman `/admin/akun` (menu Kelola Akun): buat, ubah, aktif/nonaktif, reset password. Akun Dosen/Kaprodi memakai password bawaan `password123` yang bisa diganti sendiri oleh pemiliknya lewat tab Settings; akun nonaktif tidak bisa login |
| FRK-03 | Data akademik & penetapan cakupan | Admin mengelola data prodi, mata kuliah, dan kelas beserta tahun akademik/semester, lalu menetapkan dosen pengampu pada kelas dan prodi yang diampu Kaprodi. | FR-03 + FR-04 | Admin | ✅ | Mata kuliah & kelas disusun dari log (`kelas:susun-dari-log`), akun dosen dibuat Admin (`/admin/akun`), dan **akses dosen per mata kuliah** ditetapkan dari Kelola Akun (tabel `dosen_mata_kuliah`). Penugasan pengampu per kelas tetap ada di `/admin/penugasan` untuk peran pengampu/pembantu |
| FRK-04 | Otorisasi berbasis peran & cakupan | Sistem menolak akses ke halaman/data di luar peran dan cakupan pengguna, dicek di server pada setiap request. | FR-05 | Semua role | ✅ | — |
| FRK-05 | Unggah & pratinjau log | Admin mengunggah satu file CSV log LMS; sistem menampilkan pratinjau header, jumlah baris/kolom, dan daftar masalah sebelum data disimpan, dan hanya mengizinkan simpan bila valid. | FR-06 + FR-07 | Admin | ✅ | Di UI, unggah dan pratinjau satu kegiatan yang sama: lepas file → ringkasan → simpan |
| FRK-06 | Pemrosesan & pembersihan log | Sistem menormalkan pemisah kolom, format timestamp, dan nama kolom, lalu membuang baris kosong/duplikat serta menandai baris tidak valid. | FR-08 + FR-09 | Sistem | ✅ | Keduanya dikerjakan oleh satu parser yang sama (`MoodleLogParser`) |
| FRK-07 | Pelacakan batch & log kesalahan | Setiap import disimpan sebagai satu batch beserta jumlah baris berhasil/gagal dan rincian baris bermasalah agar dapat ditelusuri. | FR-10 | Admin | ✅ | — |
| FRK-08 | Perhitungan engagement rate | Sistem menghitung tingkat keterlibatan mahasiswa terhadap kelasnya untuk satu periode. | FR-11 | Sistem | ✅ | — |
| FRK-09 | Integrasi layanan analytics | Sistem mengirim data teragregasi ke layanan analitik eksternal dan menerima hasil perhitungan. | FR-12 | Sistem | ✅ | Termasuk pencegahan saat layanan mati (FR-12a) |
| FRK-10 | Klasterisasi & profil | Sistem mengelompokkan mahasiswa berdasarkan kemiripan pola aktivitasnya, lalu setiap klaster diberi label dan deskripsi profil yang dipahami pengguna. | FR-13 + FR-14 | Sistem | ✅ | Profil adalah pelabelan atas hasil klaster — tidak berdiri sendiri |
| FRK-11 | Skor risiko prediktif | Sistem menghasilkan skor risiko beserta tingkat kepercayaan untuk setiap mahasiswa-kelas-periode. | FR-15 | Sistem | ✅ | — |
| FRK-12 | Penetapan status EWS & penghitungan ulang | Sistem menetapkan status Warning/At-Risk otomatis berdasarkan skor risiko dan threshold aktif; bila threshold berubah, seluruh status dihitung ulang pada periode terkait. | FR-16 + FR-18 | Sistem | ❌ | Menghitung ulang adalah penerapan ulang penetapan status atas data threshold yang sama |
| FRK-13 | Konfigurasi threshold EWS | Admin/Kaprodi menetapkan nilai batas Early Warning, termasuk nilai berbeda untuk Dosen vs Kaprodi. | FR-17 | Admin, Kaprodi | ❌ | — |
| FRK-14 | Penyajian insight otomatis | Sistem menampilkan penjelasan singkat mengapa seorang mahasiswa berstatus At-Risk, dalam bentuk pop-up. | FR-19 | Dosen, Kaprodi | ◻️ | — |
| FRK-15 | Dashboard analitik per peran | Dosen melihat statistik kelas-kelas yang ia ampu; Kaprodi melihat performa pengajaran dosen dan ringkasan EWS di prodi yang diampu — keduanya tidak boleh di luar cakupan. | FR-20 + FR-21 | Dosen, Kaprodi | ◻️ | Kedua sisi terisi. Perhitungan per kelas ada satu kali di `StatistikKelas`; `StatistikKaprodi` dan `StatistikDosen` cuma memilih cakupan dan penyajian. Cakupan selalu lewat `Kelas::scopeUntukPengguna()` (penugasan + izin mata kuliah), jadi kelas di luar wewenang tidak pernah ikut. **Sisi Kaprodi belum**: ringkasan performa per dosen dan EWS menunggu FR-16/17 |
| FRK-16 | Filter & visualisasi statistik | Pengguna menyaring data berdasarkan rentang tanggal, semester, kelas, mata kuliah, atau dosen, lalu sistem menyajikan tabel, grafik tren, dan matriks mengikuti filter tersebut. | FR-22 + FR-23 | Dosen, Kaprodi | ◻️ | **Sebagian**: penyaring mata kuliah, kelas asal (A/B/C/D), dan waktu (Pagi/Malam) berlaku di seluruh tab Dosen; tab Pengumpulan Tugas dan Keaktifan menambah filter per kelas, pencarian nama/NIM, dan rentang tanggal. Hasilnya disajikan sebagai tabel + bar bulanan, matriks minggu, dan tabel detail keaktifan. Nilai filter yang di luar pilihan diabaikan, bukan mengosongkan dashboard. **Belum**: filter per dosen dan per semester |
| FRK-17 | Notifikasi atas status At-Risk | Dosen/Kaprodi mengirim email langsung ke mahasiswa/dosen berstatus At-Risk beserta konteksnya, dan melihat riwayat kiriman beserta status pengirimannya. | FR-24 + FR-25 | Dosen, Kaprodi | ❌ | Mengirim dan melihat riwayatnya satu fitur notifikasi |
| FRK-18 | Ekspor laporan analitik | Pengguna mengunduh laporan PDF/Excel yang mengikuti filter dan cakupan akses yang sedang aktif. | FR-26 | Dosen, Kaprodi | ❌ | — |
| FRK-19 | Audit log aktivitas penting | Sistem mencatat setiap perubahan data master, perubahan threshold, dan import yang dilakukan. | FR-27 | Sistem | ❌ | — |

Rangkuman status FRK: **9 selesai** (01, 04, 05, 06, 07, 08, 09, 10, 11),
**3 sebagian** (03, 14, 15), **7 belum** (02, 12, 13, 16, 17, 18, 19).

## Modul 1 & 2 — Autentikasi, Manajemen Pengguna, Akademik & Import Log LMS

| FR | Nama | Aktor | Status | Catatan implementasi |
|---|---|---|---|---|
| FR-01 | Autentikasi Pengguna | Semua role | ✅ | `/login` + sesi + register multi-redirect (`AuthController`, `RegisterTest`) |
| FR-02 | Manajemen Akun Pengguna | Admin | ✅ | `/admin/akun` — buat/ubah/nonaktifkan/reset password (`AkunTest`). Pendaftaran mandiri dihapus; `nomor_induk` wajib karena penghubung ke log LMS. Password awal Dosen/Kaprodi = `password123` (`User::PASSWORD_BAWAAN`), diganti sendiri lewat tab Settings (`ProfilController@ubahPassword`) |
| FR-03 | Manajemen Data Master Akademik | Admin | ◻️ | Struktur tabel + seeding + penyusunan dari log (`kelas:susun-dari-log`), dan Penetapan akses dosen per mata kuliah sudah ada di `/admin/akun`. Yang belum: UI CRUD prodi/mata kuliah/kelas |
| FR-04 | Penetapan Cakupan Akses | Admin | ◻️ | Pengampu kelas sudah lewat UI `/admin/penugasan`; penetapan "prodi yang diampu Kaprodi" masih lewat data master, belum ada UI |
| FR-05 | Otorisasi Berbasis Peran dan Cakupan | Semua role | ✅ | `KelasPolicy`, `scopeUntukPengguna`, middleware `kelas.akses` — dicek server per request (`AksesKelasTest`). **Kaprodi tanpa batas prodi** (keputusan pengguna, 18 Okt 2026): ia melihat seluruh kelas. Relasi `kelasTerlihat()` dihapus supaya tidak ada dua sumber kebenaran |
| FR-06 | Pengunggahan File Log LMS | Admin | ✅ | `Admin/UploadLog` (drag&drop). Ekstensi CSV divalidasi; batas ukuran sengaja dihilangkan atas keputusan pengguna — file log nyata 30,8 MB |
| FR-07 | Validasi dan Pratinjau Data | Admin | ✅ | Pratinjau header/baris/kolom + daftar masalah di browser sebelum kirim; header tidak dikenal ditolak, baris invalid tidak disimpan |
| FR-08 | Parsing dan Normalisasi Data | Sistem | ✅ | Deteksi pemisah `;`/`,`/tab, `d/m/Y H:i` → datetime, pemetaan header, kolom IP dibuang (`MoodleLogParser`) |
| FR-09 | Pembersihan Data | Sistem | ✅ | Baris kosong dilewati, duplikat ditolak via `sidik_jari` UNIQUE, baris bermasalah dicatat (`LogImportTest`) |
| FR-10 | Pelacakan Batch Import & Log Kesalahan | Admin | ✅ | `import_batches` + `log_import_errors` + halaman riwayat + unduh file asli |

## Modul 3 — Analitik & AI Engine

| FR | Nama | Aktor | Status | Catatan implementasi |
|---|---|---|---|---|
| FR-11 | Perhitungan Engagement Rate | Sistem | ✅ | `engagement.py` — 4 komponen terkalibrasi (aktifitas/keaktifan/ketuntasan/variasi) per mahasiswa-kelas-periode |
| FR-12 | Integrasi Layanan Analytics | Sistem | ✅ | `AnalyticsService` (HTTP ke FastAPI v3.0) + fallback FR-12a agar layanan mati tidak mematikan halaman |
| FR-13 | Klasterisasi Pola Belajar | Sistem | ✅ | `clustering.py` (K-Means, silhouette 0,5615) |
| FR-14 | Pemetaan Profil Klaster | Sistem | ✅ | Label & deskripsi profil per klaster (`clustering.py`, diuji `test_api.py`) |
| FR-15 | Skor Risiko Prediktif | Sistem | ✅ | `risk.py` + penalti relatif median batch |

## Modul 4 — Early Warning System (EWS)

| FR | Nama | Aktor | Status | Catatan implementasi |
|---|---|---|---|---|
| FR-16 | Penetapan Status EWS | Sistem | ◻️ | **Sebagian (sementara)**: tab Keaktifan menampilkan status EWS yang diterjemahkan dari level risiko analytics (Aman / Perhatian / At-Risk). Ambangnya ditulis di `config/services.php` bagian `ews`, dan sengaja ditandai sebagai status sementara. Mahasiswa yang belum dianalisis berstatus "Belum dihitung", bukan "Aman". Aturan resmi belum ditetapkan |
| FR-17 | Konfigurasi Threshold EWS | Admin, Kaprodi | ❌ | Belum ada tabel threshold maupun halamannya; sementara ini ambang ditulis manual di `config/services.php` |
| FR-18 | Penghitungan Ulang Status EWS | Sistem | ❌ | Belum ada (bergantung FR-16 & FR-17) |
| FR-19 | Penyajian Insight Otomatis | Dosen, Kaprodi | ◻️ | Data sudah tersedia di respons API (breakdown komponen + profil klaster, `schemas.py`) — pop-up di UI belum ada |

## Modul 5 — Dashboard & Analitik

| FR | Nama | Aktor | Status | Catatan implementasi |
|---|---|---|---|---|
| FR-20 | Dashboard Analitik Dosen | Dosen | ✅ | **Terisi**: `StatistikDosen` memberi statistik kelas yang diampu/berizin (event, hari, tugas, jenis event, kuis ditolak, engagement, klaster, risiko per mahasiswa), asal akses tiap kelas (Pengampu/Pembantu/Izin mata kuliah), dan daftar kerjaan "Perlu perhatian" (maksimal 10 teratas dari seluruh risiko tinggi + menengah, urut skor). Kalau layanan analytics mati, daftar itu tetap kosong tetapi alasannya dinyatakan, bukan disamarkan sebagai "tidak ada yang berisiko". Data nyata: 245 mahasiswa unik di kelas hasil pecahan roster, engagement rata-rata 63% (IF209), 43 risiko tinggi. Tab **Pengumpulan Tugas** dan **Keaktifan** sudah terisi; tab EWS masih kosong |
| FR-21 | Dashboard Makro Kaprodi | Kaprodi | ◻️ | **Terisi** (statistik): `StatistikKaprodi` menghitung per kelas di prodi yang diampu — rekap prodi, sebaran aktivitas bulanan, tabel per mahasiswa (event/hari/tugas/jenis event/kuis ditolak), engagement, klaster, dan skor risiko dari layanan Python, diurutkan paling berisiko. Data nyata 2 file log (131.188 baris, 512 mahasiswa). **Belum ada**: ringkasan performa per dosen (butuh FR-10 selesai + ambang EWS FR-16/17) |
| FR-22 | Filter dan Parameter | Dosen, Kaprodi | ◻️ | Penyaring bersama seluruh tab Dosen: mata kuliah (`?mata_kuliah=`), kelas asal A/B/C/D (`?asal=`), dan waktu Pagi/Malam (`?jenis=`) memakai kolom `kelas.asal_kelas` dan `kelas.jenis_kelas` hasil impor roster. Tab Pengumpulan Tugas dan Keaktifan menambah filter per kelas (`?kelas_id=`), pencarian nama/NIM (`?q=`), dan rentang tanggal (`?dari=`/`?sampai=`). Nilai di luar pilihan diabaikan, bukan mengosongkan halaman. **Belum**: filter per dosen dan rentang per semester |
| FR-23 | Visualisasi Statistik | Dosen, Kaprodi | ◻️ | Bar sebaran bulanan di dashboard statistik + **matriks pengumpulan tugas** (baris mahasiswa x kolom minggu, sel berwarna sesuai ketepatan waktu) + **tabel detail keaktifan** (satu baris satu mahasiswa: akses course, akses materi, forum, submission, tepat waktu/terlambat, status EWS, engagement). Visual lain (grafik tren) belum ada |

## Modul 6 & 7 — Interaksi, Laporan & Audit

| FR | Nama | Aktor | Status | Catatan implementasi |
|---|---|---|---|---|
| FR-24 | Quick Action Notifikasi | Dosen, Kaprodi | ❌ | Belum ada |
| FR-25 | Riwayat Notifikasi | Dosen, Kaprodi | ❌ | Belum ada |
| FR-26 | Ekspor Laporan Analitik | Dosen, Kaprodi | ❌ | Belum ada |
| FR-27 | Audit Log Aktivitas Penting | Sistem | ❌ | Pelacakan import ada (FR-10), tapi audit perubahan data master & threshold belum |

## Rekap (berdasar 27 FR asli)

| Status | Jumlah | FR |
|---|---|---|
| ✅ Selesai | 12 | 01, 05, 06, 07, 08, 09, 10, 11, 12, 13, 14, 15 |
| ◻️ Sebagian | 5 | 03, 04, 19, 20, 21 |
| ❌ Belum | 10 | 02, 16, 17, 18, 22, 23, 24, 25, 26, 27 |

## Catatan penting kebijakan desain yang membedakan dari draf awal

1. Mahasiswa tidak punya akun sama sekali (keputusan pengguna) — `kelas_mahasiswa` menyimpan `nim` + `nama` langsung.
2. Mesin analitik: Python (FastAPI) yang tidak pernah menyentuh DB; Laravel mengirim agregat dan menerima hasil (FR-11 s.d. FR-15).
3. Status EWS (FR-16) ditetapkan Laravel berdasarkan threshold (FR-17), bukan Python.
4. Batas ukuran unggahan dihilangkan (keputusan pengguna, 16 Okt 2026); batas nyata yang tinggal ada di `php.ini` (2G).
5. **Kode MK dilengkapi dari batch** (16 Okt 2026). Log Moodle hanya menyebut kode MK pada baris bertipe konteks `Course: Nama/KODE`; 90.721 dari 131.188 baris (69%) bertipe Assignment/Quiz/File/URL sehingga kolomnya kosong dan seluruh statistik jadi buta. `LogCsvImporter::lengkapiKodeMataKuliah()` mengisi kode itu HANYA kalau satu batch berisi tepat satu kode MK — kalau ada dua, dibiarkan kosong dan dilaporkan, tidak ditebak.
6. **Jendela analisis = satu semester** (16 Okt 2026). Ambang engagement dikalibrasi dari aktivitas semester penuh (median 446 event, 29 hari, 12 tugas). Analisis per bulan membuat semua mahasiswa terbaca rendah secara buatan (terbukti: 515 dari 516 mahasiswa "risiko tinggi"). Kontrak Python dinaikkan ke **v3.1** untuk menerima `period` rentang `2026-02..2026-07`.
7. **Daftar mahasiswa kelas disusun dari log dengan bukti**, bukan tebakan panjang digit: `BuktiPeran::classify()` memakai jenis event (Course module created → pengajar, User unenrolled → pengelola). Hasil: 19 identitas IF209 + 17 IF207 dikeluarkan (dosen & pengelola tanpa akun).
8. **Mahasiswa prodi ini berawalan NIM 3312** (keputusan pengguna, 18 Okt 2026). Di dua file log, 263 dari 269 identitas 10 digit berawalan 3312; enam sisanya (awalan 4312/4342) dikeluarkan dari daftar — termasuk "Prayoga 4312201010" yang aktivitasnya justru tinggi (146 event, 21 hari), kemungkinan mahasiswa prodi lain. Kalau memang perlu ikut dihitung, jalankan ulang dengan `--awalan-nim=3312,4312`.
9. **Kalibrasi engagement per mata kuliah** (keputusan pengguna, 18 Okt 2026). Ambang IF209 (600/45/18/28) dipakai juga untuk IF207, padahal aktivitas IF207 jauh lebih rendah (median 91 event vs 325), sehingga 128 dari 251 mahasiswa IF207 terbaca "Pasif". Ambang IF207 dihitung dari p75 semester Feb-Jul 2026: 190/36/8/14. Kontrak Python menerima `params.batas` dan mengembalikan ambang yang dipakai di `meta.kalibrasi` (v3.1). Efeknya: rata-rata engagement IF207 13% → 56%, risiko tinggi 128 → 70.
10. **Akun Dosen dan Kaprodi TANPA prodi** (keputusan pengguna, 18 Okt 2026).
    - Dosen: akses hanya dari penugasan pengampu (`kelas_dosen`).
    - Kaprodi: melihat **seluruh** kelas, semua prodi. Sebelumnya ia dibatasi `prodi_id` miliknya sendiri.
    - Konsekuensi: kolom `users.prodi_id` tidak lagi dipakai untuk otorisasi apa pun; kolom dibiarkan karena masih jadi data atribut. Relasi `User::kelasTerlihat()` dihapus karena "seluruh kelas" bukan representasi pivot — satu-satunya jalan adalah `Kelas::scopeUntukPengguna()`.
11. **Pendaftaran mandiri dihapus** (keputusan pengguna, 18 Okt 2026). `/register` dulu terbuka untuk publik dan menerima role `admin`, sehingga siapa pun bisa membuat akun admin. Akun sekarang hanya dibuat Admin lewat menu Kelola Akun. `is_active` kini ikut diperiksa saat login — sebelumnya akun yang dinonaktifkan masih bisa masuk.
12. **Akses dosen diberikan per MATA KULIAH** (18 Okt 2026). Admin mencentang mata kuliah di baris dosen pada menu Kelola Akun — konsekuensinya: semua kelas mata kuliah itu ikut terlihat, **termasuk kelas yang dibuat setelah izin diberikan** (mis. Admin mengimpor log "Pemrograman Web", lalu cukup mencentangnya sekali). Tabel baru `dosen_mata_kuliah`. Jalur lama `kelas_dosen` (penugasan pengampu/pembantu per kelas) tetap berlaku sebagai sumber izin kedua; keduanya digabung di `Kelas::scopeUntukPengguna()` dan `KelasPolicy`.
13. **Semua menu Admin jadi tab dalam satu halaman** (18 Okt 2026). Dashboard, Upload Log, Kelola Akun, dan Penugasan Akademik kini panel di dalam `Admin/AdminDashboard.blade.php`; sidebar hanya tombol `switchTab()`, berpindah tanpa memuat ulang. URL lama (`/admin/akun`, `/admin/penugasan`) tetap bekerja — keduanya merender cangkang yang sama dengan `tabAktif` berbeda, jadi redirect setelah POST tetap kembali ke tab yang benar. Konsekuensi teknis:
    - Semua panel dirender sekaligus (kelas `hidden` hanya mengatur tampilan), supaya tab yang baru diklik tidak kosong. Datanya disuntikkan `PanelAdminComposer`; `DaftarAkun` dan `DaftarPenugasan` jadi satu sumber query yang dipakai controller maupun composer.
    - Penyaring (search) milik URL masing-masing: form filter Akun menuju `/admin/akun`, Penugasan ke `/admin/penugasan`. Kalau tidak, mengetik di satu tab akan ikut menyaring daftar di tab lain.
    - Notifikasi flash hanya dirender di tab aktif; kalau tidak, pesan yang sama muncul di dua panel.
    - Ukuran halaman naik jadi ±1,3 MB dan waktu respons ±0,4 s (semua panel ikut dirender). Kalau nanti terasa berat, panel berat (Penugasan) sebaiknya dimuat lazy saat tab diklik, bukan dirender di awal.
    - Kelas panel memakai `@class`, bukan `@if` di dalam atribut `class`: `@if` menyisakan spasi/baris baru sehingga hasil HTML tidak rapi dan sulit diperiksa test.
14. **Satu mesin statistik untuk dua peran** (18 Okt 2026). Sisi Dosen (FR-20) dan sisi Kaprodi (FR-21) memakai angka yang sama persis, jadi perhitungannya dipindah ke `StatistikKelas` (satu kelas + rekap beberapa kelas). `StatistikDosen` lalu menambah tiga hal khas Dosen: asal akses tiap kelas, daftar kerjaan "Perlu perhatian", dan penjelasan bahwa daftar itu kosong karena skor belum dihitung, bukan karena semua aman. Tampilan per kelas dipindah ke komponen `components/analitik/blok-kelas.blade.php` dan `status-layanan.blade.php` supaya kedua peran tidak punya dua salinan tabel yang bisa berbeda.
15. **PASSWORD BAWAAN AKUN DOSEN & KAPRODI = `password123`** (keputusan pengguna, 18 Okt 2026). Tiap akun sebelumnya dibuat dengan password acak yang hanya tampil sekali; pada praktiknya password itu jarang sampai ke pemiliknya sehingga Admin sering harus mereset. Sekarang semua akun Dosen/Kaprodi memakai satu password yang sama dan mudah diingat, lalu **pemiliknya sendiri** yang menggantinya lewat tab Settings.
    - Satu sumber nilai: `User::PASSWORD_BAWAAN`. Dipakai `AkunController@store` (form dikosongkan), `AkunController@resetPassword` (tombol Password), perintah `dosen:daftar-dari-log`, dan seeder.
    - `php artisan akun:password-bawaan [--dry-run] [--peran=...]` mengembalikan password akun yang sudah ada ke password bawaan. Akun **admin tidak pernah ikut**, dan `--dry-run` hanya menampilkan. Perintah ini idempoten: akun yang sudah memakai password bawaan tidak di-hash ulang.
    - **Syarat agar keputusan ini masuk akal: halaman Settings harus benar-benar bisa mengganti password.** Route `POST /akun/password` (`ProfilController@ubahPassword`) terbuka untuk semua peran, mewajibkan password lama, minimal 8 karakter, konfirmasi, dan harus berbeda dari yang lama. Form-nya dipakai bersama oleh tab Settings Dosen dan Kaprodi (`components/akun/form-ubah-password.blade.php`).
    - Karena seluruh isi dashboard berupa tab di satu halaman, setelah form dikirim pengguna dikembalikan ke `?tab=settings`. Kalau tidak, pesan "Password lama salah" muncul di panel yang masih tersembunyi dan pengguna mengira tidak terjadi apa-apa. Parameter `?tab=` hanya diterima kalau nilainya ada di daftar tab (`DashboardController::TAB_PERAN`).
    - Konsekuensi yang harus diingat: selama belum diganti, password ini diketahui semua orang yang punya akses ke sistem. Kolom "Password lama" pada tabel perintah di atas menunjukkan akun mana yang belum menggantinya.
16. **KELAS DIPECAH MENJADI PAGI/MALAM DAN ASAL A/B/C/D** (keputusan pengguna, 18 Okt 2026). Daftar mahasiswa yang dipecah per kelas asal dan waktu pembelajaran masuk dari berkas Excel milik pengguna di `sample-data/`.
    - Sumbernya satu berkas: kolom `Nama Mahasiswa`, `NIM`, `Kelas Asal` ("Kelas A Pagi" dan sejenisnya). Baris judul dicari, bukan diasumsikan di baris pertama, karena berkas aslinya punya baris keterangan di atas header.
    - Berkas `.xlsx` **tidak bisa dibaca PHP di sini** (ekstensi `zip` tidak ada, sedangkan xlsx itu arsip zip). Karena itu `analytics/scripts/xlsx_ke_csv.py` mengonversinya dulu memakai openpyxl; jalurnya diatur di `config/services.php` bagian `services.excel`.
    - `php artisan kelas:impor-roster [--file=] [--mata-kuliah=*] [--dry-run] [--perbarui-nama] [--tanpa-pindahkan-penugasan] [--hapus-kelas-lama]` memecah tiap mata kuliah menjadi 8 kelas + "Tanpa Kelas Asal". Tahun akademik dan semester **dikalin** dari kelas lama; kalau tidak, jendela analisis satu semester ikut bergeser dan semua angka historis berubah.
    - **Mahasiswa yang tidak ada di roster tidak dibuang** (keputusan pengguna): 15 orang angkatan 21/31/41 dipindah ke kelas "Tanpa Kelas Asal" supaya aktivitasnya tetap terhitung, hanya tanpa label asal/jenis.
    - **Penugasan dosen ikut diteruskan** dari kelas lama ke semua kelas hasil pecahan. Tanpa ini, memecah satu kelas jadi delapan membuat seluruh penugasan menunjuk ke kelas kosong dan dashboard semua dosen jadi kosong tanpa sebab jelas. Sumbernya hanya kelas lama (`asal_kelas` masih NULL), supaya penugasan yang memang hanya untuk satu kelas tidak ikut menyebar saat perintah dijalankan ulang.
    - Kelas lama yang kosong **tidak dihapus** secara default karena menghapus kelas adalah keputusan Admin; tersedia `--hapus-kelas-lama`.
    - Kolom baru `kelas.asal_kelas` dan `kelas.jenis_kelas` (migration `2026_10_18_200001`). Keduanya WAJIB ada di `$fillable` model `Kelas`: kolom yang tidak terdaftar di situ dibuang diam-diam tanpa pesan apa pun, dan filter pun tidak akan pernah bekerja.
    - Perhitungan analitik dikumpulkan **per mata kuliah**, bukan per kelas: semua kelas satu MK membaca `kode_mata_kuliah` yang sama, jadi menghitung per kelas mengulang agregasi atas 90 ribu baris log dan memanggil layanan Python sembilan kali (diukur 9 detik). Setelah dikumpulkan, halaman Dosen turun ke sekitar 1,3 detik. Hasil analitik tetap harus **dipotong per kelas** sebelum menghitung klaster dan risiko — kalau tidak, tiap blok melaporkan angka seluruh mata kuliah dan jumlah risiko tinggi bisa melebihi jumlah mahasiswa.
17. **TAB "PENGUMPULAN TUGAS": MATRIKS MAHASISWA x MINGGU** (keputusan pengguna, 18 Okt 2026). Dosen melihat kotak berwarna per tugas: hijau = tepat waktu, kuning = terlambat, merah = tidak mengumpulkan, abu-abu = minggu tanpa tugas. Filter: Mata Kuliah, Jenis (Pagi/Malam), Kelas, dan pencarian nama/NIM.
    - **Tenggat = 7 hari setelah tugas dibuat** (keputusan pengguna). Tanggal "dibuat" diambil dari event `Course module created` dengan `context_type` = "Assignment"; nama konteksnya PERSIS sama dengan nama tugas saat pengumpulan, jadi keduanya bisa dipasangkan. Dari 111 tugas IF209, **111 punya catatan pembuatan** — tidak ada tugas yang kehilangan tanggal.
    - Event itu **tidak** memakai titik di akhir kalimatnya, berbeda dari kebanyakan event lain. Salah ketik satu karakter membuat seluruh tenggat kosong tanpa error yang terlihat; makanya ada konstanta `LogAktivitas::EVENT_TUGAS_DIBUAT`.
    - **Kolom minggu mengikuti NOMOR DI NAMA TUGAS** (keputusan pengguna, 21 Okt 2026, menggantikan aturan lama yang memakai minggu semester tanggal dibuat). "Praktikum 7" jatuh di kolom Minggu 7, "Minggu 13" di kolom Minggu 13 — walau tugasnya di Moodle dibuat tanggal lain — supaya setiap minggu memuat satu tugas praktikum. Angka dibaca konservatif oleh `RekapTugas::nomorDariNama()`: angka harus SEGERA mengikuti kata "Praktikum" atau "Minggu (ke-…)", jadi angka dalam kurung seperti "Upload hasil design (1)" dan angka nama kelas dalam "… Praktikum - IF 2C Pagi" (AAS) TIDAK dianggap nomor minggu — jeda keduanya pernah karena regex yang terlalu lintas (terbukti salah di data asli 21 Okt 2026).
        - Tugas **tanpa nomor** di nama (ATS, AAS, dan sebagian tugas IF207 seperti "Upload Tugas Manpro HT") **tetap tampil**, diletakkan di kolom minggu semester sesuai tanggal tugasnya dibuat (keputusan pengguna, 21 Okt 2026).
        - Minggu yang tidak ada tugasnya (contoh nyata: kelas 2C Pagi tidak pernah punya "Praktikum 4") **tidak dibuat-buat**: kolomnya ada tapi abu-abu.
        - Dua tugas asli yang menabrak nomor minggu yang sama (contoh di kelas C Malam: "Praktikum 5" dan "Minggu 5") **digabung di satu sel**, status terburuk yang menang (merah > kuning > hijau), dan keduanya tetap tercantum di tooltip.
        - **Aturan tenggat tidak berubah**: masih 7 hari setelah tugas dibuat, jadi warna hijau/kuning tetap mengukur kelancaran mahasiswa terhadap ketersediaan tugas, bukan terhadap nomor minggu. Sejarah keputusan lama (18 Okt): kolom dipilih "minggu semester" karena tugas "Minggu 13" nyatanya dibuat 1 Juli; diubah 21 Okt setelah pengguna meminta 1 minggu = 1 praktikum. Rentang tanggal kolom tetap ditampilkan agar tidak ambigu.
    - Hanya tugas yang **namanya menyebut kelas** yang ditampilkan (keputusan pengguna). Tugas bersama seperti "Batamindo", "Upload Sertifikat Pelatihan AI", dan "Pengumpulan AAS" tidak ikut. Cocoknya kelas dibaca dari teks yang hanya berisi huruf dan angka, jadi "Pengumpulan Praktikum 7 IF 2A Pagi" tetap dikenali untuk kelas "A Pagi".
    - Pengumpulan yang dipakai adalah yang **pertama**; mahasiswa yang mengunggah dua kali tetap dihitung sekali.
    - Kolom yang belum ada tugasnya **tidak dihapus**, ditandai abu-abu supaya dosen melihat kontinuitas minggu (minggu 6, lalu lompat ke 14, menyesatkan).
    - Kartu "Contoh pesan yang bisa dikirim" hanyalah **pratinjau**. Tidak ada pesan yang benar-benar dikirim — itu FR-24/FR-25 yang belum dikerjakan.
18. **TAB "KEAKTIFAN": DETAIL PER MAHASISWA** (18 Okt 2026). Satu baris satu mahasiswa, kolom: akses course, akses materi, forum, submission, tepat waktu, terlambat, status EWS, engagement. Sumber kolom diambil dari `context_type` di log, bukan dari tebakan nama kolom.
    - **Kolom Forum TIDAK bisa dipakai menilai mahasiswa.** Log yang diimpor tidak memuat event post forum sama sekali (dicek 18 Okt 2026: pencarian event berisi "post"/"forum"/"discussion" mengembalikan nol baris). Yang tercatat hanya "Course module viewed" pada konteks Forum. Keterbatasan ini ditulis di halaman, bukan disembunyikan, karena angka kecil yang kelihatan "mahasiswa pasif" sebenarnya hasil ekspor log.
    - **Rentang waktu default = bulan semester** (Februari s.d. Juli untuk genap, Agustus s.d. Januari untuk ganjil) — keputusan pengguna 18 Okt 2026, supaya angkanya bisa dibandingkan dengan tab lain yang memakai jendela semester penuh. Tanggal yang diketik pengguna mengalahkan default; tanggal ngawur di URL diabaikan; tanggal awal yang lebih besar dari tanggal akhir ditukar.
    - **Status EWS masih sementara** (keputusan pengguna, 18 Okt 2026): diterjemahkan dari level risiko analytics (low = Aman, medium = Perhatian, high = At-Risk), ambangnya di `config/services.php` bagian `ews`. Mahasiswa yang belum dianalisis berstatus "Belum dihitung", BUKAN "Aman" — menampilkan aman untuk yang belum pernah dianalisis adalah kesalahan paling berbahaya di halaman ini. Ambang resmi (FR-17) belum ada.
    - Angka **tepat waktu/terlambat** diambil dari `RekapTugas`, bukan dihitung ulang, supaya dua tab tidak mungkin punya definisi tenggat yang berbeda.
    - **Matriks dibatasi 100 baris** (`RekapTugas::BATAS_MAHASISWA`). Alasannya terukur: 245 mahasiswa x 22 minggu = 5.390 sel membuat HTML halaman dashboard naik jadi 5,7 MB. Yang dipotong adalah bagian paling akhir (paling sedikit menunggak) dan halaman menyebutkannya terbuka. Tab Keaktifan memuat seluruh mahasiswa dalam satu baris yang jauh lebih ringan.
    - Penyaring mata kuliah/jenis/asal berlaku di **seluruh tab** Dosen; filter per kelas, pencarian, dan rentang tanggal hanya berlaku di tab Pengumpulan Tugas dan Keaktifan.
19. **TIGA KARTU AKTIVITAS + "ANALYTICS MATERI" DI DASHBOARD DOSEN** (keputusan pengguna, 22 Okt 2026). Tiga donut di atas blok kelas, lalu dua daftar materi (paling sering dan paling jarang diakses) dengan progress bar.
    - **"Mahasiswa login" TIDAK tercatat di log.** Pencarian event berisi "login"/"logged"/"masuk" atas log IF209 mengembalikan nol baris; yang diekspor Moodle hanya aktivitas course. Karena itu ukurannya disepakati pengguna: **mahasiswa berbeda yang punya aktivitas apa pun pada satu hari**, lalu diambil minimal, rata-rata, dan maksimal. Rata-rata dihitung atas **hari yang ada aktivitasnya** (164 hari pada data Feb–Jul 2026), bukan 181 hari kalender — hari libur tanpa aktivitas bukan "mahasiswa tidak login". Angka nyata: min 4, rata-rata 49,2, maks 169.
    - **"Rata-rata persentase akses materi" = rata-rata persentase per kelas** (keputusan pengguna), bukan persentase gabungan. Tiap kelas dihitung sendiri (mahasiswa yang membuka minimal satu materi dibagi jumlah mahasiswa kelas), lalu dirata-ratakan supaya satu kelas besar tidak mendominasi. Data nyata: 98,1% (rentang 92%–100% di 8 kelas).
    - **"Clustering aktif/sedang/at risk" memakai LEVEL RISIKO analytics** (`low`/`medium`/`high`), bukan label K-Means. Label K-Means yang ada hanya "Sangat Aktif/Aktif/Cukup" dan tidak pernah menghasilkan "Pasif" pada data IF209, sehingga donut tiga bagian akan selalu kosong di "At Risk". Data nyata: Aktif 139, Sedang 63, At Risk 43. Mahasiswa tanpa skor masuk "Belum dianalisis" (kelabu), tidak dianggap Aktif.
    - **Daftar materi hanya nama berawalan "Modul ..." atau "Materi ..."** (keputusan pengguna). Soal ATS/AAS, "Download Packet Tracer", dan video pendek tidak ikut: daftar teratas akan didominasi soal ujian, sedangkan daftar terbawah didominasi video pendek. Pada data IF209: 28 materi, 7.681 akses. Persentase = bagian dari total akses kelompok itu; panjang bar diskalakan terhadap materi teratas **di daftarnya masing-masing** supaya daftar "jarang" tetap terbaca.
    - **Jendela waktu dan cakupan mengikuti filter dashboard.** Perhitungan memakai `periodeAwal..periodeAkhir` kelas yang sedang tampil dan hanya mahasiswa yang terdaftar di kelas itu. Saat filter C Pagi dipilih, seluruh kartu ikut menjadi 29 mahasiswa dan 1 kelas; tidak ada angka yang diam-diam tetap memakai seluruh mata kuliah.
    - **Donut dirender SVG di server, tanpa Chart.js.** Kartu berada di dalam tab yang tersembunyi; canvas yang digambar saat elemen masih `hidden` berukuran nol dan tidak otomatis digambar ulang saat tab dibuka. SVG tidak butuh resize dan tidak menambah CDN baru. Komponennya `components/analitik/donut.blade.php`, kartunya `components/analitik/aktivitas.blade.php`, perhitungannya `App\Services\StatistikAktivitas`.
    - Sumber event materi: `LogAktivitas::EVENT_MODUL_DILIHAT` (`Course module viewed`) dengan `context_type` di `StatistikKeaktifan::KONTEKS_MATERI`, peran mahasiswa. Nama event ditambahkan sebagai konstanta karena sebelumnya ditulis mentah di beberapa tempat.
    - Biaya tambahan terukur: **±0,6 detik** (3 query atas ±85 ribu baris log), di luar `StatistikDosen` yang sudah ±1,3–2 detik. Query terberat adalah hitung mahasiswa per hari (0,4–0,5 detik) karena hampir seluruh log masuk jendela. Index percobaan `(kode_mata_kuliah, periode, occurred_at, actor_identifier, actor_role)` hanya memperbaiki 0,57 → 0,39 detik, jadi **tidak** dipakai: tidak sepadan dengan biaya penyimpanan dan waktu impor.
20. **BAHASA VISUAL BARU: LOGIN DUA PANEL + PALET RESMI** (keputusan pengguna, 24 Okt 2026). Halaman login menjadi acuan tampilan frontend berikutnya.
    - **Palet resmi** (dari gambar acuan pengguna): navy `#0F1939`, royal `#26428B`, sapphire `#516AC8`, emas `#E3AF64`, krem `#FAEBD7`, putih gading `#F8F7F5`. Token Tailwind bernama `navy`, `royal`, `sapphire`, `gold`, `cream`, `ivory` didaftarkan di `layouts/base.blade.php` dan halaman login, jadi halaman berikutnya tinggal memakai nama itu — tidak perlu menyalin kode hex.
    - **Tata letak**: panel kiri navy berisi logo, tagline "Pantau, pahami, tingkatkan pembelajaran.", dan ilustrasi grafik batang CSS. Panel kanan putih berisi form. Di layar < 1024 px panel kiri tetap tampil di atas form (logo ikut), tetapi kartu grafik disembunyikan supaya kolom password dan tombol Masuk tidak terdorong ke bawah lipatan.
    - **Tombol utama biru royal `#26428B`**, emas hanya aksen (logo, cincin dekoratif, sorotan). Kontras teks putih di royal ±10,6:1.
    - **Pemilih peran DIHAPUS** (keputusan pengguna). Peran ditentukan akun saat login, bukan dipilih di form; input tersembunyi `selected_role` juga dihapus karena sejak awal tidak pernah dibaca `AuthController`. Halaman "Pilih Peran Anda" pada mockup tidak dibuat.
    - **Ilustrasi dibuat CSS**, bukan file gambar: tidak ada aset yang perlu diunggah dan warnanya otomatis mengikuti palet.
    - Tautan "Lupa password?" masih **belum punya alur** (tidak ada route reset). Saat diklik, muncul keterangan bahwa reset dilakukan Admin, bukan dibiarkan tampak berfungsi.
21. **LOGIKA LOGIN DIHUBUNGKAN KE BACKEND** (perbaikan 25 Okt 2026). `Auth/Login.blade.php` sebelumnya masih template statis: form tanpa `action`/`@csrf`, submit dicegat JavaScript, dan halaman berisi dashboard demo dengan data palsu. Sekarang:
    - Form memakai `method="POST" action="{{ route('login') }}"`, `@csrf`, `name="email"`, `name="password"`, dan `name="remember"`. Email yang gagal login dipertahankan lewat `old('email')`; pesan error dan pesan sukses logout ditampilkan di atas form.
    - **Peran tidak dipilih di form.** `AuthController` membaca peran dari akun, lalu mengarahkan ke `admin.dashboard`, `kaprodi.dashboard`, atau `dosen.dashboard`. Pengguna yang sudah punya sesi dan membuka `/login` langsung diarahkan ke dashboard perannya.
    - **Batas percobaan**: maksimal 5 kegagalan per email + alamat IP dalam 60 detik. Percobaan berikutnya ditolak dengan pesan "Terlalu banyak percobaan login..." walau passwordnya benar. Login berhasil menghapus hitungan. Kuncinya memakai email + IP, bukan IP saja, supaya satu kelas di balik NAT kampus tidak saling memblokir.
    - **Ingat saya** mengirim `remember=1` dan membuat cookie `remember_web_*`. Logout memanggil `Auth::logout()`, `invalidate()`, dan `regenerateToken()`, lalu menampilkan pesan "Anda telah keluar dari sistem."
    - Pesan validasi memakai bahasa Indonesia (`Email wajib diisi.`, `Format email tidak valid.`, `Password wajib diisi.`). Password salah dan akun nonaktif sengaja memakai pesan yang sama agar tidak membocorkan akun mana yang terdaftar.
    - Dashboard demo, Chart.js, dan pemilih peran dihapus dari halaman login; setelah login pengguna memakai dashboard asli. Ujinya ada di `tests/Feature/LoginTest.php`.

## Perintah CLI untuk kanal data

| Perintah | Guna |
|---|---|
| `php artisan log:impor <file>` | Impor file log dari terminal, memakai `LogCsvImporter` yang sama dengan form `/admin/log-import` |
| `php artisan log:lengkapi-kode-mk` | Melengkapi kode MK pada batch lama (untuk batch yang diimpor sebelum poin 5 berlaku) |
| `php artisan kelas:susun-dari-log [--dry-run]` | Membuat kelas + daftar mahasiswa dari log yang sudah diimpor; `--prodi`, `--tahun-akademik`, `--semester`, `--awalan-nim` bisa ditetapkan admin. Default: prodi diambil dari akun pengajar yang sudah terdaftar, tahun akademik dari bulan paling ramai |
| `php artisan dosen:daftar-dari-log [--dry-run] [--pengampu]` | Mendaftarkan akun dosen dari identitas pengajar di log (semua akun memakai password bawaan `password123`) dan mencatat pengampu tiap kelas. Pengelola hanya ikut bila `--sertakan-pengelola` |
| `php artisan akun:password-bawaan [--dry-run] [--peran=*]` | Mengembalikan password akun Dosen/Kaprodi ke `password123`. Akun admin tidak ikut; `--dry-run` hanya menampilkan |
| `php artisan kelas:impor-roster [--dry-run] [--mata-kuliah=*]` | Memecah kelas tiap mata kuliah menjadi Pagi/Malam + asal A/B/C/D dari daftar mahasiswa di `sample-data/*.xlsx` (dikonversi ke CSV oleh `analytics/scripts/xlsx_ke_csv.py`) |
| `php artisan identitas:belum-terdaftar` | Melihat identitas di log yang belum punya akun atau belum masuk daftar kelas, dibagi per tingkat bukti |
| `php artisan analytics:ping` | Mengecek layanan analytics Python hidup dan versinya |
