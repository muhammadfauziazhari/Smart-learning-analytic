<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RekapTugas;
use App\Services\StatistikAktivitas;
use App\Services\StatistikDosen;
use App\Services\StatistikKaprodi;
use App\Services\StatistikKeaktifan;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Tab yang boleh dibuka lewat `?tab=` pada dashboard Dosen/Kaprodi.
     *
     * Satu daftar untuk dua peran karena keduanya memakai sidebar dengan
     * lima menu yang sama (dijaga oleh test DashboardAccessTest).
     *
     * @var list<string>
     */
    private const TAB_PERAN = [
        'dashboard', 'pengumpulan-tugas', 'keaktifan', 'ews', 'settings',
    ];

    public function __construct(
        private StatistikKaprodi $statistikKaprodi,
        private StatistikDosen $statistikDosen,
        private StatistikAktivitas $statistikAktivitas,
        private RekapTugas $rekapTugas,
        private StatistikKeaktifan $statistikKeaktifan,
    ) {}

    /**
     * Menampilkan dashboard utama berdasarkan role pengguna yang sedang login.
     */
    public function index()
    {
        $user = Auth::user();

        // Arahkan ke view atau logika masing-masing role
        if ($user->role === 'admin') {
            return $this->adminDashboard();
        } elseif ($user->role === 'kaprodi') {
            return $this->kaprodiDashboard();
        } elseif ($user->role === 'dosen') {
            return $this->dosenDashboard();
        }

        // Jika role tidak dikenali, lakukan logout
        Auth::logout();

        return redirect()->route('login')->withErrors(['email' => 'Role akun tidak valid.']);
    }

    /**
     * Cangkang dashboard Admin dengan tab "dashboard" terbuka.
     *
     * Angka ringkasannya disuntikkan RingkasanAdminComposer, bukan di sini,
     * supaya tab Dashboard tetap terisi walau halaman dibuka lewat URL tab
     * lain (/admin/akun, /admin/penugasan).
     */
    public function adminDashboard()
    {
        return view('Admin.AdminDashboard', [
            'user' => Auth::user(),
            'tabAktif' => 'dashboard',
        ]);
    }

    public function kaprodiDashboard()
    {
        $statistik = $this->statistikKaprodi->untuk(Auth::user());

        return view('Kaprodi.Dashboard', [
            'user' => Auth::user(),
            'tabAktif' => $this->tabAktif(),
        ] + $statistik);
    }

    /**
     * Dashboard Dosen (FR-20).
     *
     * Angkanya dihitung StatistikDosen, yang memakai mesin per kelas yang
     * sama dengan Kaprodi (StatistikKelas) tetapi membatasi kelas sesuai
     * izin Dosen ini (FR-05).
     */
    public function dosenDashboard()
    {
        $user = Auth::user();

        // Penyaring bersama seluruh tab: mata kuliah, kelas asal, dan jenis
        // (Pagi/Malam), dari daftar yang diminta pengguna. Nilai tidak sah
        // tidak di sini; penyaringan dan validasinya dilakukan
        // StatistikDosen terhadap kelas Dosen ini.
        $filter = [
            'mata_kuliah' => request()->string('mata_kuliah')->trim()->value() ?: null,
            'jenis' => request()->string('jenis')->trim()->value() ?: null,
            'asal' => request()->string('asal')->trim()->value() ?: null,
        ];

        $statistik = $this->statistikDosen->untuk($user, $filter);

        // Kartu ringkasan aktivitas (login, akses materi, clustering) dan
        // daftar materi paling sering/jarang diakses. Sumbernya log yang sama
        // dengan blok kelas, dengan jendela semester yang sama pula.
        $aktivitas = $this->statistikAktivitas->untuk($statistik['kelas'] ?? []);

        // Tab "Pengumpulan Tugas" dan "Keaktifan" memakai penyaring yang
        // lebih rinci: satu kelas bisa dipilih sekaligus, dan Keaktifan
        // menambah rentang tanggal.
        $filterTabel = $filter + [
            'kelas_id' => request()->integer('kelas_id') ?: null,
            'q' => request()->string('q')->trim()->value() ?: null,
        ];

        $rekapTugas = $this->rekapTugas->untuk($user, $filterTabel);

        // Engagement dan level risiko sudah dihitung untuk tab Dashboard.
        // Disampaikan lewat peta NIM supaya tab Keaktifan tidak memanggil
        // layanan analytics kedua kali hanya untuk dua kolom.
        $keaktifan = $this->statistikKeaktifan->untuk(
            $user,
            $filterTabel + [
                'dari' => request()->string('dari')->trim()->value() ?: null,
                'sampai' => request()->string('sampai')->trim()->value() ?: null,
            ],
            $this->petaAnalitik($statistik),
        );

        return view('Dosen.Dashboard', [
            'user' => $user,
            'tabAktif' => $this->tabAktif(),
            'rekapTugas' => $rekapTugas,
            'keaktifan' => $keaktifan,
            'aktivitas' => $aktivitas,
        ] + $statistik);
    }

    /**
     * Peta NIM ke data analitik dari tab Dashboard.
     *
     * Kunci berawalan "_" karena NIM yang isinya angka murni akan diubah
     * PHP menjadi integer, dan pencarian dengan kunci string lalu gagal
     * senyap.
     *
     * @param  array<string, mixed>  $statistik
     * @return array<string, array{engagement: ?float, level: ?string, risk: ?float}>
     */
    private function petaAnalitik(array $statistik): array
    {
        $peta = [];

        foreach ($statistik['kelas'] ?? [] as $kelas) {
            foreach ($kelas['mahasiswa'] ?? [] as $mhs) {
                $peta['_'.$mhs['nim']] = [
                    'engagement' => $mhs['engagement'] ?? null,
                    'level' => $mhs['level'] ?? null,
                    'risk' => $mhs['risk'] ?? null,
                ];
            }
        }

        return $peta;
    }

    /**
     * Tab yang harus dibuka saat halaman dimuat.
     *
     * Semua isi dashboard ada di satu halaman sebagai tab (lihat
     * partials/tab-switcher), jadi `?tab=settings` dipakai untuk kembali
     * ke tab Settings setelah form ganti password gagal atau berhasil.
     * Tanpa itu, pesannya muncul di tab yang sedang tersembunyi dan
     * pengguna mengira tidak ada yang terjadi.
     *
     * Nilai dari query string tidak dipercaya begitu saja: kalau tidak
     * ada di daftar tab, diabaikan dan kembali ke dashboard.
     */
    private function tabAktif(string $bawaan = 'dashboard'): string
    {
        $diminta = request()->string('tab')->trim()->value();

        return in_array($diminta, self::TAB_PERAN, true) ? $diminta : $bawaan;
    }
}
