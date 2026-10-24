<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\User;
use App\Support\DaftarAkun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Kelola akun Dosen dan Kaprodi (FR-02).
 *
 * Yang membuat akun: Admin. Pendaftaran mandiri sudah dihapus, karena
 * formnya dulu terbuka untuk publik dan bisa membuat akun admin.
 *
 * Atribut per peran (keputusan pengguna, 18 Okt 2026):
 *
 * - Dosen  : nama, email, password, nomor induk, dan daftar kelas yang
 *            diampu. Aksesnya HANYA dari kelas itu — tidak ada prodi.
 * - Kaprodi: nama, email, password, nomor induk. Tanpa prodi: Kaprodi
 *            melihat seluruh kelas (FR-05), jadi prodi tidak membatasi
 *            apa pun dan tidak punya arti.
 *
 * `nomor_induk` bukan aksesoris. Impor log mencocokkan
 * `log_aktivitas.actor_identifier` dengan `users.nomor_induk`; tanpa itu,
 * aktivitas dosen di log akan dihitung sebagai aktivitas mahasiswa.
 */
class AkunController extends Controller
{
    /**
     * Tab "Kelola Akun" di dalam halaman dashboard Admin.
     *
     * Renders halaman yang sama dengan tab lain; hanya tab aktifnya yang
     * berbeda, supaya sidebar bisa berpindah tanpa memuat ulang.
     */
    public function index(Request $request): View
    {
        return view('Admin.AdminDashboard', [
            'user' => Auth::user(),
            'tabAktif' => 'akun',
        ] + DaftarAkun::data($request));
    }

    /**
     * Tentukan mata kuliah mana saja yang boleh dilihat satu dosen.
     *
     * Inilah maksud "Admin yang menentukan": begitu log mata kuliah baru
     * diimpor, Admin cukup mencentang mata kuliah itu di baris dosen yang
     * bersangkutan — tidak perlu menandai kelas satu per satu.
     *
     * Berlaku untuk SEMUA kelas mata kuliah tersebut, termasuk kelas yang
     * dibuat setelah izin ini diberikan.
     */
    public function updateAkses(Request $request, User $user): RedirectResponse
    {
        if ($user->role !== 'dosen') {
            return back()->with('error', 'Izin mata kuliah hanya berlaku untuk akun Dosen.');
        }

        $data = $request->validate([
            'mata_kuliah' => ['nullable', 'array'],
            'mata_kuliah.*' => ['integer', 'exists:mata_kuliahs,id'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['mata_kuliah'] ?? [])));

        $user->mataKuliahAkses()->sync($ids);

        $jumlahKelas = Kelas::whereIn('mata_kuliah_id', $ids)->count();

        return back()->with('success', sprintf(
            '%s kini boleh melihat %d mata kuliah (%d kelas).',
            $user->nama,
            count($ids),
            $jumlahKelas
        ));
    }

    /**
     * Buat satu akun Dosen atau Kaprodi.
     *
     * Password boleh dikosongkan: kalau begitu sistem membangkitkan
     * password acak dan menampilkannya SATU KALI di halaman ini, karena
     * di tempat lain hanya hash-nya yang tersimpan.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'nomor_induk' => [
                'required', 'string', 'max:32', 'unique:users,nomor_induk',
                // NIP dosen 18 digit pada data nyata. Kaprodi boleh memakai
                // nomor yang lebih pendek.
            ],
            'role' => ['required', Rule::in(['dosen', 'kaprodi'])],
            'password' => ['nullable', 'string', 'min:8'],
        ], [
            'nomor_induk.required' => 'Nomor induk wajib diisi: tanpa ini aktivitas di log tidak terhubung ke akun.',
            'nomor_induk.unique' => 'Nomor induk ini sudah dipakai akun lain.',
            'email.unique' => 'Email ini sudah dipakai akun lain.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        // Kunci 'password' hanya ada kalau form mengirimnya. Kalau form
        // tidak mengirim field sama sekali, accessor-nya null.
        $passwordDiminta = $data['password'] ?? null;

        // Password kosong berarti pakai password bawaan yang mudah diingat,
        // lalu pemiliknya sendiri yang menggantinya lewat tab Settings
        // (keputusan pengguna, 18 Okt 2026). Nilai standarnya ada di
        // User::PASSWORD_BAWAAN supaya tidak tersebar di beberapa tempat.
        $password = filled($passwordDiminta)
            ? $passwordDiminta
            : User::PASSWORD_BAWAAN;

        $akun = User::create([
            'nama' => $data['nama'],
            'email' => $data['email'],
            'nomor_induk' => $data['nomor_induk'],
            // Hash dilakukan oleh cast 'hashed' pada model User.
            'password' => $password,
            'role' => $data['role'],
            'is_active' => true,
        ]);

        $pesan = sprintf('Akun %s (%s) dibuat.', $akun->nama, $akun->email);

        if (blank($passwordDiminta)) {
            $request->session()->flash('password_baru', [
                'email' => $akun->email,
                'password' => $password,
            ]);

            $pesan .= sprintf(
                ' Password bawaannya "%s" — suruh mereka menggantinya lewat tab Settings.',
                User::PASSWORD_BAWAAN
            );
        }

        if ($akun->role === 'dosen') {
            $pesan .= ' Lanjut ke Penugasan Akademik untuk menetapkannya sebagai pengampu kelas — tanpa itu dashboardnya kosong.';
        }

        return redirect()->route('admin.akun')->with('success', $pesan);
    }

    /**
     * Ubah nama, email, nomor induk, dan status aktif.
     *
     * Password tidak ikut di sini; pakai resetPassword().
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'nomor_induk' => ['required', 'string', 'max:32', Rule::unique('users', 'nomor_induk')->ignore($user->id)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user->update([
            'nama' => $data['nama'],
            'email' => $data['email'],
            'nomor_induk' => $data['nomor_induk'],
            'is_active' => (bool) $data['is_active'],
        ]);

        return back()->with('success', "Data akun {$user->nama} diperbarui.");
    }

    /** Aktifkan/nonaktifkan tanpa menghapus riwayat login dan analytics. */
    public function toggleAktif(User $user): RedirectResponse
    {
        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', sprintf(
            'Akun %s sekarang %s.',
            $user->nama,
            $user->is_active ? 'aktif' : 'nonaktif'
        ));
    }

    /**
     * Kembalikan password akun ke password bawaan.
     *
     * Dulu di sini dibuat password acak. Sekarang sengaja dikembalikan ke
     * `User::PASSWORD_BAWAAN` supaya Admin cukup menyuruh pemiliknya masuk
     * dan menggantinya sendiri lewat tab Settings — jauh lebih mudah
     * daripada menghafal hasil acak yang hanya tampil sekali.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $user->update(['password' => User::PASSWORD_BAWAAN]);

        $request->session()->flash('password_baru', [
            'email' => $user->email,
            'password' => User::PASSWORD_BAWAAN,
        ]);

        return back()->with('success', sprintf(
            'Password %s kembali ke "%s". Suruh yang mengganti lewat tab Settings.',
            $user->nama,
            User::PASSWORD_BAWAAN
        ));
    }
}
