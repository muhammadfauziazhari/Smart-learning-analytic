{{-- Partial: tab Kelola Akun
     Dipakai sebagai salah satu tab di Admin/AdminDashboard.blade.php, bukan
     halaman mandiri. --}}

@php
    // Kelas dasar input, dipakai berulang. Warna border error ditambahkan per field.
    $inputDasar = 'w-full bg-slate-50 border rounded-xl px-4 py-2.5 text-sm text-slate-800 placeholder-slate-400 focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 focus:outline-none transition-all';
    $labelDasar = 'block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5';
    $inputKecil = 'w-full bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-800 focus:bg-white focus:border-blue-600 focus:outline-none';
    $tombolSekunder = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white border border-slate-200 hover:border-blue-300 hover:bg-blue-50 text-[11px] font-semibold text-slate-600 hover:text-blue-700 transition-all';
@endphp

<div class="space-y-6 rounded-3xl bg-slate-100 p-5 lg:p-7">

    {{-- ------------------------------------------------ header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-extrabold text-[#0B1B3D]">Kelola Akun</h2>
            <p class="text-sm text-blue-600 mt-1">
                Buat dan atur akun Dosen serta Kaprodi.
            </p>
            <p class="text-[11px] text-slate-500 mt-1">
                Akun hanya dibuat di sini — pendaftaran mandiri sudah ditutup.
            </p>
        </div>
        <div class="flex gap-3 shrink-0">
            <div class="rounded-2xl bg-[#0B1B3D] text-white px-5 py-3 text-center shadow-md min-w-[92px]">
                <p class="text-2xl font-extrabold leading-none">{{ $totalDosen }}</p>
                <p class="text-[10px] uppercase tracking-wider text-slate-300 mt-1.5">Dosen</p>
            </div>
            <div class="rounded-2xl bg-gradient-to-br from-amber-300 to-amber-400 text-[#0B1B3D] px-5 py-3 text-center shadow-md min-w-[92px]">
                <p class="text-2xl font-extrabold leading-none">{{ $totalKaprodi }}</p>
                <p class="text-[10px] uppercase tracking-wider font-semibold mt-1.5">Kaprodi</p>
            </div>
        </div>
    </div>

    {{-- Notifikasi hanya di tab yang aktif. Semua tab dirender sekaligus,
         jadi tanpa ini pesan yang sama muncul di dua panel. --}}
    @if (($tabAktif ?? 'dashboard') === 'akun')
        @include('partials.alert')
    @endif

    {{-- Password acak: hanya tampil sekali, di halaman ini saja --}}
    @if (session('password_baru'))
        @php $baru = session('password_baru'); @endphp
        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-5 shadow-sm">
            <div class="flex items-start gap-3">
                <div class="w-9 h-9 rounded-full bg-amber-400 text-[#0B1B3D] flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-key text-sm"></i>
                </div>
                <div class="text-xs text-amber-900 space-y-1">
                    <p class="font-bold">Password berikut hanya ditampilkan sekali.</p>
                    <p>Catat atau bagikan sekarang. Di database hanya tersimpan hash-nya, jadi tidak bisa dibaca lagi.</p>
                    <p class="mt-2 font-mono text-sm bg-white border border-amber-300 rounded-lg px-3 py-2 text-[#0B1B3D]">
                        {{ $baru['email'] }} &nbsp;→&nbsp; {{ $baru['password'] }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================ FORM --}}
    <section class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
        <header class="flex items-center gap-3 px-5 py-4 border-b border-slate-100">
            <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                <i class="fa-solid fa-user-plus text-sm"></i>
            </div>
            <h3 class="text-sm font-bold text-[#0B1B3D]">Buat Akun Baru</h3>
        </header>

        <form method="POST" action="{{ route('admin.akun.store') }}" class="p-5 space-y-5">
            @csrf

            <div class="grid md:grid-cols-2 gap-4">
                {{-- Nama --}}
                <div>
                    <label for="nama" class="{{ $labelDasar }}">Nama Lengkap</label>
                    <input id="nama" name="nama" value="{{ old('nama') }}" required
                        placeholder="Dr. Budi Santoso, M.Kom."
                        class="{{ $inputDasar }} {{ $errors->has('nama') ? 'border-rose-500' : 'border-slate-200' }}">
                    @error('nama') <p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Email --}}
                <div>
                    <label for="email" class="{{ $labelDasar }}">Email (dipakai untuk login)</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required
                        placeholder="budi.santoso@edutrack.local"
                        class="{{ $inputDasar }} {{ $errors->has('email') ? 'border-rose-500' : 'border-slate-200' }}">
                    @error('email') <p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Nomor induk --}}
                <div>
                    <label for="nomor_induk" class="{{ $labelDasar }}">
                        Nomor Induk (NIP / NIM)
                        <i class="fa-solid fa-circle-info text-slate-400 ml-1" title="Penghubung ke log LMS"></i>
                    </label>
                    <input id="nomor_induk" name="nomor_induk" value="{{ old('nomor_induk') }}" required
                        placeholder="198602192014042001"
                        class="{{ $inputDasar }} {{ $errors->has('nomor_induk') ? 'border-rose-500' : 'border-slate-200' }}">
                    <p class="text-[11px] text-slate-500 mt-1">
                        Harus sama persis dengan identitas di log LMS. Tanpa ini, aktivitas orang ini di log terhitung sebagai mahasiswa.
                    </p>
                    @error('nomor_induk') <p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Role --}}
                <div>
                    <label for="role" class="{{ $labelDasar }}">Peran</label>
                    <select id="role" name="role" required class="{{ $inputDasar }} border-slate-200">
                        <option value="dosen" @selected(old('role', 'dosen') === 'dosen')>Dosen</option>
                        <option value="kaprodi" @selected(old('role') === 'kaprodi')>Kaprodi</option>
                    </select>
                    <p class="text-[11px] text-slate-500 mt-1">
                        Dosen hanya melihat kelas yang diampu. Kaprodi melihat seluruh mata kuliah.
                    </p>
                    @error('role') <p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Password --}}
                <div class="md:col-span-2">
                    <label for="password" class="{{ $labelDasar }}">Password</label>
                    <input id="password" type="text" name="password" value="{{ old('password') }}"
                        placeholder="Kosongkan untuk membuat password acak"
                        class="{{ $inputDasar }} {{ $errors->has('password') ? 'border-rose-500' : 'border-slate-200' }}">
                    <p class="text-[11px] text-slate-500 mt-1">
                        Kalau dikosongkan, sistem membuat password acak dan menampilkannya satu kali di atas.
                    </p>
                    @error('password') <p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl bg-[#0F3277] hover:bg-[#0B255B] text-white text-sm font-semibold shadow-md hover:shadow-lg shadow-blue-950/20 transition-all active:scale-[0.99]">
                    <i class="fa-solid fa-plus text-xs"></i>Buat Akun
                </button>
            </div>
        </form>
    </section>

    {{-- ========================================================= DAFTAR --}}
    {{-- FILTER
         Form menuju ke /admin/akun supaya hasil saringannya muncul di
         tab ini, bukan ikut menyaring tab lain. --}}
    <form method="GET" action="{{ route('admin.akun') }}"
        class="flex flex-wrap items-end gap-3 rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
        <div class="flex-1 min-w-[220px]">
            <label for="q" class="{{ $labelDasar }}">Cari</label>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                <input id="q" type="search" name="q" value="{{ $q }}" placeholder="Nama, email, atau nomor induk"
                    class="{{ $inputDasar }} border-slate-200 pl-10">
            </div>
        </div>
        <div class="min-w-[180px]">
            <label for="filter-role" class="{{ $labelDasar }}">Peran</label>
            <select id="filter-role" name="role" class="{{ $inputDasar }} border-slate-200">
                <option value="">Semua</option>
                <option value="dosen" @selected($role === 'dosen')>Dosen</option>
                <option value="kaprodi" @selected($role === 'kaprodi')>Kaprodi</option>
                <option value="admin" @selected($role === 'admin')>Admin</option>
            </select>
        </div>
        <button type="submit"
            class="px-5 py-2.5 rounded-xl bg-[#0F3277] hover:bg-[#0B255B] text-white text-xs font-semibold transition-all">
            <i class="fa-solid fa-filter mr-1.5"></i>Terapkan
        </button>
        @if (request()->hasAny(['q', 'role']))
            <a href="{{ route('admin.akun') }}" class="px-4 py-2.5 rounded-xl text-xs text-slate-500 hover:text-slate-900 transition-all">Reset</a>
        @endif
    </form>

    @if ($tanpaNomorInduk > 0)
        <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-xs text-amber-800 flex items-start gap-2">
            <i class="fa-solid fa-triangle-exclamation mt-0.5 text-amber-500"></i>
            <span>{{ $tanpaNomorInduk }} akun Dosen belum punya nomor induk. Aktivitasnya di log LMS tidak akan terhubung ke akun mereka.</span>
        </div>
    @endif

    {{-- Tidak memakai overflow-hidden di kartu: popover Ubah / Mata Kuliah
         akan terpotong. Layar kecil tetap bisa digeser horizontal. --}}
    <section class="rounded-2xl bg-white border border-slate-200 shadow-sm">
        <header class="px-5 py-4 border-b border-slate-100">
            <h3 class="text-sm font-bold text-[#0B1B3D]">Daftar Akun</h3>
            <p class="text-[11px] text-slate-500 mt-0.5">Atur data, akses mata kuliah, password, dan status akun.</p>
        </header>

        <div class="overflow-x-auto xl:overflow-visible">
            <table class="w-full text-sm">
                <thead class="text-left text-[11px] uppercase tracking-wider text-slate-500 bg-slate-50">
                    <tr>
                        <th class="px-5 py-3 font-medium">Nama</th>
                        <th class="px-3 py-3 font-medium">Email</th>
                        <th class="px-3 py-3 font-medium">Nomor Induk</th>
                        <th class="px-3 py-3 font-medium">Peran</th>
                        <th class="px-3 py-3 font-medium text-right">Kelas Diampu</th>
                        <th class="px-3 py-3 font-medium text-center">Mata Kuliah Terpantau</th>
                        <th class="px-5 py-3 font-medium text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($akun as $a)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <div @class([
                                        'w-9 h-9 rounded-full flex items-center justify-center text-xs font-bold shrink-0',
                                        'bg-gradient-to-tr from-blue-500 to-blue-700 text-white' => $a->is_active,
                                        'bg-slate-200 text-slate-500' => ! $a->is_active,
                                    ])>
                                        {{ str($a->nama)->substr(0, 2)->upper() }}
                                    </div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-semibold text-slate-800">{{ $a->nama }}</span>
                                        @unless ($a->is_active)
                                            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-rose-50 text-rose-600 border border-rose-200">NONAKTIF</span>
                                        @endunless
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3 text-slate-500 font-mono text-xs">{{ $a->email }}</td>
                            <td class="px-3 py-3 font-mono text-xs">
                                @if ($a->nomor_induk)
                                    <span class="text-slate-700">{{ $a->nomor_induk }}</span>
                                @else
                                    <span class="text-amber-600">belum diisi</span>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                @php
                                    $gaya = match ($a->role) {
                                        'admin' => 'bg-slate-100 text-slate-600 border-slate-200',
                                        'kaprodi' => 'bg-emerald-50 text-emerald-600 border-emerald-200',
                                        default => 'bg-blue-50 text-blue-600 border-blue-200',
                                    };
                                @endphp
                                <span class="text-[11px] font-semibold capitalize rounded-full border px-2.5 py-0.5 {{ $gaya }}">{{ $a->role }}</span>
                            </td>
                            <td class="px-3 py-3 text-right text-slate-700 font-semibold">
                                {{ $a->kelas_diampu_count }}
                                @if ($a->role === 'dosen' && $a->kelas_diampu_count === 0)
                                    <span class="block text-[10px] font-medium text-amber-600">belum ditugaskan</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center">
                                @if ($a->role === 'dosen')
                                    <details class="relative inline-block text-left">
                                        <summary class="{{ $tombolSekunder }} cursor-pointer list-none">
                                            <i class="fa-solid fa-book-open text-amber-500"></i>
                                            {{ $a->mata_kuliah_akses_count }} mata kuliah
                                            <i class="fa-solid fa-chevron-down text-[9px] text-slate-400"></i>
                                        </summary>

                                        <div class="absolute right-0 mt-2 w-80 rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl shadow-slate-900/10 z-20">
                                            <p class="text-xs font-bold text-[#0B1B3D] mb-1">Mata kuliah yang boleh dilihat</p>
                                            <p class="text-[10px] text-slate-500 mb-3">
                                                Centang matakuliah yang boleh dipantau. Berlaku untuk semua kelasnya, termasuk kelas yang dibuat nanti.
                                            </p>

                                            @if ($mataKuliah->isEmpty())
                                                <p class="text-[11px] text-amber-600">Belum ada mata kuliah. Buat lewat Penugasan Akademik atau susun dari log.</p>
                                            @else
                                                <form method="POST" action="{{ route('admin.akun.akses', $a) }}" class="space-y-2">
                                                    @csrf
                                                    @method('PUT')

                                                    @php
                                                        // Kolom pivot selalu berawalan "pivot_", jadi `id`
                                                        // di sini murni mata_kuliahs.id.
                                                        $terpilih = $a->mataKuliahAkses->pluck('id')->map(fn ($id) => (int) $id)->all();
                                                    @endphp

                                                    <div class="max-h-56 overflow-y-auto space-y-0.5 pr-1">
                                                        @foreach ($mataKuliah as $mk)
                                                            <label class="flex items-start gap-2.5 text-[11px] text-slate-600 hover:bg-blue-50 rounded-lg px-2 py-1.5 cursor-pointer">
                                                                <input type="checkbox" name="mata_kuliah[]" value="{{ $mk->id }}"
                                                                    @checked(in_array($mk->id, $terpilih, false))
                                                                    class="mt-0.5 rounded border-slate-300 accent-[#0F3C8A]">
                                                                <span class="flex-1">
                                                                    <span class="font-semibold text-slate-800">{{ $mk->kode }}</span> {{ $mk->nama }}
                                                                    <span class="block text-[10px] text-slate-400">
                                                                        {{ $mk->kelas_count }} kelas
                                                                        @if ($mk->prodi) · {{ $mk->prodi->kode }} @endif
                                                                    </span>
                                                                </span>
                                                            </label>
                                                        @endforeach
                                                    </div>

                                                    <div class="flex gap-2 pt-2">
                                                        <button type="submit"
                                                            class="flex-1 px-3 py-2 rounded-xl bg-[#0F3277] hover:bg-[#0B255B] text-white text-xs font-semibold transition-colors">
                                                            Simpan Akses
                                                        </button>
                                                    </div>
                                                </form>
                                            @endif
                                        </div>
                                    </details>
                                @else
                                    <span class="text-slate-300 text-[11px]">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <details class="relative">
                                        <summary class="{{ $tombolSekunder }} cursor-pointer list-none">
                                            <i class="fa-solid fa-pen text-blue-500"></i>
                                        </summary>
                                        <div class="absolute right-0 mt-2 w-80 rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl shadow-slate-900/10 z-20">
                                            <form method="POST" action="{{ route('admin.akun.update', $a) }}" class="space-y-3">
                                                @csrf
                                                @method('PUT')
                                                <div>
                                                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Nama</label>
                                                    <input name="nama" value="{{ $a->nama }}" required class="{{ $inputKecil }}">
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Email</label>
                                                    <input name="email" type="email" value="{{ $a->email }}" required class="{{ $inputKecil }}">
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Nomor induk</label>
                                                    <input name="nomor_induk" value="{{ $a->nomor_induk }}" required class="{{ $inputKecil }}">
                                                </div>
                                                <label class="flex items-center gap-2 text-[11px] text-slate-600">
                                                    <input type="hidden" name="is_active" value="0">
                                                    <input type="checkbox" name="is_active" value="1" @checked($a->is_active)
                                                        class="rounded border-slate-300 accent-[#0F3C8A]">
                                                    Akun aktif
                                                </label>
                                                <button type="submit"
                                                    class="w-full px-3 py-2 rounded-xl bg-[#0F3277] hover:bg-[#0B255B] text-white text-xs font-semibold transition-colors">
                                                    Simpan
                                                </button>
                                            </form>
                                        </div>
                                    </details>

                                    <form method="POST" action="{{ route('admin.akun.password', $a) }}">
                                        @csrf
                                        <button type="submit" class="{{ $tombolSekunder }}" title="Buat password acak baru">
                                            <i class="fa-solid fa-key text-amber-500"></i>
                                        </button>
                                    </form>

                                    @if ($a->id !== auth()->id())
                                        <form method="POST" action="{{ route('admin.akun.aktif', $a) }}">
                                            @csrf
                                            <button type="submit" @class([
                                                'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-[11px] font-semibold transition-colors',
                                                'bg-rose-50 text-rose-600 border-rose-200 hover:bg-rose-100' => $a->is_active,
                                                'bg-emerald-50 text-emerald-600 border-emerald-200 hover:bg-emerald-100' => ! $a->is_active,
                                            ])>
                                                <i @class([
                                                    'fa-solid text-[10px]',
                                                    'fa-ban' => $a->is_active,
                                                    'fa-circle-check' => ! $a->is_active,
                                                ])></i>
                                                {{ $a->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-slate-500">
                                <i class="fa-solid fa-user-slash text-3xl mb-2 text-slate-300"></i>
                                <p class="text-sm">Belum ada akun yang cocok dengan penyaringan ini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($akun->hasPages())
            <div class="px-5 py-3 border-t border-slate-100">
                {{ $akun->links() }}
            </div>
        @endif
    </section>

</div>
