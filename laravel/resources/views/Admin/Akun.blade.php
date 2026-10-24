
{{-- Partial: tab Kelola Akun }}
     Dipakai sebagai salah satu tab di Admin/AdminDashboard.blade.php, bukan
     halaman mandiri. --}}

<div class="space-y-6">

    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-white">Kelola Akun</h2>
            <p class="text-xs text-slate-400 mt-0.5">
                Buat dan atur akun Dosen serta Kaprodi. Akun hanya dibuat di sini —
                pendaftaran mandiri sudah ditutup.
            </p>
        </div>
        <div class="flex gap-4 text-center shrink-0">
            <div>
                <p class="text-xl font-bold text-white">{{ $totalDosen }}</p>
                <p class="text-[10px] uppercase tracking-wider text-slate-500">Dosen</p>
            </div>
            <div>
                <p class="text-xl font-bold text-white">{{ $totalKaprodi }}</p>
                <p class="text-[10px] uppercase tracking-wider text-slate-500">Kaprodi</p>
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
        <div class="rounded-2xl border border-amber-500/40 bg-amber-500/5 p-5">
            <div class="flex items-start gap-3">
                <i class="fa-solid fa-key text-amber-400 mt-0.5"></i>
                <div class="text-xs text-amber-200 space-y-1">
                    <p class="font-semibold">Password berikut hanya ditampilkan sekali.</p>
                    <p>Catat atau bagikan sekarang. Di database hanya tersimpan hash-nya, jadi tidak bisa dibaca lagi.</p>
                    <p class="mt-2 font-mono text-sm bg-slate-950/60 border border-amber-500/30 rounded-lg px-3 py-2">
                        {{ $baru['email'] }} &nbsp;→&nbsp; {{ $baru['password'] }}
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================ FORM --}}
    <div class="rounded-2xl border border-slate-800 bg-slate-900/40 overflow-hidden">
        <header class="px-5 py-4 border-b border-slate-800">
            <h3 class="text-sm font-semibold text-white">
                <i class="fa-solid fa-user-plus text-slate-500 mr-1.5"></i>Buat Akun Baru
            </h3>
        </header>

        <form method="POST" action="{{ route('admin.akun.store') }}" class="p-5 space-y-5">
            @csrf

            <div class="grid md:grid-cols-2 gap-4">
                {{-- Nama --}}
                <div>
                    <label for="nama" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">Nama Lengkap</label>
                    <input id="nama" name="nama" value="{{ old('nama') }}" required
                        placeholder="Dr. Budi Santoso, M.Kom."
                        class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:border-indigo-500 focus:outline-none @error('nama') border-rose-500 @enderror">
                    @error('nama') <p class="text-[11px] text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">Email (dipakai untuk login)</label>
                    <input id="email" type="email" name="email" value="{{ old('email') }}" required
                        placeholder="budi.santoso@edutrack.local"
                        class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:border-indigo-500 focus:outline-none @error('email') border-rose-500 @enderror">
                    @error('email') <p class="text-[11px] text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Nomor induk --}}
                <div>
                    <label for="nomor_induk" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">
                        Nomor Induk (NIP / NIM)
                        <i class="fa-solid fa-circle-info text-slate-600 ml-1" title="Penghubung ke log LMS"></i>
                    </label>
                    <input id="nomor_induk" name="nomor_induk" value="{{ old('nomor_induk') }}" required
                        placeholder="198602192014042001"
                        class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:border-indigo-500 focus:outline-none @error('nomor_induk') border-rose-500 @enderror">
                    <p class="text-[11px] text-slate-500 mt-1">
                        Harus sama persis dengan identitas di log LMS. Tanpa ini, aktivitas orang ini di log terhitung sebagai mahasiswa.
                    </p>
                    @error('nomor_induk') <p class="text-[11px] text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Role --}}
                <div>
                    <label for="role" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">Peran</label>
                    <select id="role" name="role" required
                        class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none">
                        <option value="dosen" @selected(old('role', 'dosen') === 'dosen')>Dosen</option>
                        <option value="kaprodi" @selected(old('role') === 'kaprodi')>Kaprodi</option>
                    </select>
                    <p class="text-[11px] text-slate-500 mt-1">
                        Dosen hanya melihat kelas yang diampu. Kaprodi melihat seluruh mata kuliah.
                    </p>
                    @error('role') <p class="text-[11px] text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Password --}}
                <div class="md:col-span-2">
                    <label for="password" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">Password</label>
                    <input id="password" type="text" name="password" value="{{ old('password') }}"
                        placeholder="Kosongkan untuk membuat password acak"
                        class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:border-indigo-500 focus:outline-none @error('password') border-rose-500 @enderror">
                    <p class="text-[11px] text-slate-500 mt-1">
                        Kalau dikosongkan, sistem membuat password acak dan menampilkannya satu kali di atas.
                    </p>
                    @error('password') <p class="text-[11px] text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium transition-colors">
                    <i class="fa-solid fa-plus mr-1.5"></i>Buat Akun
                </button>
            </div>
        </form>
    </div>

    {{-- ========================================================= DAFTAR --}}
    {{-- FILTER
         Form menuju ke /admin/akun supaya hasil saringannya muncul di
         tab ini, bukan ikut menyaring tab lain. --}}
    <form method="GET" action="{{ route('admin.akun') }}" class="flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[220px]">
            <label for="q" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">Cari</label>
            <input id="q" type="search" name="q" value="{{ $q }}" placeholder="Nama, email, atau nomor induk"
                class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:border-indigo-500 focus:outline-none">
        </div>
        <div class="min-w-[180px]">
            <label for="filter-role" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">Peran</label>
            <select id="filter-role" name="role"
                class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none">
                <option value="">Semua</option>
                <option value="dosen" @selected($role === 'dosen')>Dosen</option>
                <option value="kaprodi" @selected($role === 'kaprodi')>Kaprodi</option>
                <option value="admin" @selected($role === 'admin')>Admin</option>
            </select>
        </div>
        <button type="submit" class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-sm transition-colors">
            <i class="fa-solid fa-filter mr-1"></i>Terapkan
        </button>
        @if (request()->hasAny(['q', 'role']))
            <a href="{{ route('admin.akun') }}" class="px-4 py-2.5 rounded-xl text-xs text-slate-400 hover:text-white transition-all">Reset</a>
        @endif
    </form>

    @if ($tanpaNomorInduk > 0)
        <div class="rounded-xl border border-amber-500/40 bg-amber-500/5 px-4 py-3 text-xs text-amber-400 flex items-start gap-2">
            <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
            <span>{{ $tanpaNomorInduk }} akun Dosen belum punya nomor induk. Aktivitasnya di log LMS tidak akan terhubung ke akun mereka.</span>
        </div>
    @endif

    <div class="rounded-2xl border border-slate-800 bg-slate-900/40 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-[11px] uppercase tracking-wider text-slate-500 bg-slate-900/60">
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
                <tbody class="divide-y divide-slate-800/70">
                    @forelse ($akun as $a)
                        <tr class="hover:bg-slate-800/20">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-slate-200">{{ $a->nama }}</span>
                                    @unless ($a->is_active)
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-rose-500/10 text-rose-400 border border-rose-500/30">NONAKTIF</span>
                                    @endunless
                                </div>
                            </td>
                            <td class="px-3 py-3 text-slate-400 font-mono text-xs">{{ $a->email }}</td>
                            <td class="px-3 py-3 font-mono text-xs">
                                @if ($a->nomor_induk)
                                    <span class="text-slate-300">{{ $a->nomor_induk }}</span>
                                @else
                                    <span class="text-amber-400">belum diisi</span>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                @php
                                    $gaya = match ($a->role) {
                                        'admin' => 'bg-slate-800 text-slate-300 border-slate-700',
                                        'kaprodi' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                                        default => 'bg-indigo-500/10 text-indigo-300 border-indigo-500/30',
                                    };
                                @endphp
                                <span class="text-[11px] rounded-full border px-2 py-0.5 {{ $gaya }}">{{ $a->role }}</span>
                            </td>
                            <td class="px-3 py-3 text-right text-slate-400">
                                {{ $a->kelas_diampu_count }}
                                @if ($a->role === 'dosen' && $a->kelas_diampu_count === 0)
                                    <span class="block text-[10px] text-amber-400">belum ditugaskan</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center">
                                @if ($a->role === 'dosen')
                                    <details class="relative inline-block text-left">
                                        <summary class="cursor-pointer list-none px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-[11px] text-slate-300 transition-colors">
                                            <i class="fa-solid fa-book-open mr-1"></i>
                                            {{ $a->mata_kuliah_akses_count }} mata kuliah
                                        </summary>

                                        <div class="absolute right-0 mt-2 w-80 rounded-xl border border-slate-700 bg-slate-900 p-4 shadow-xl z-20">
                                            <p class="text-[11px] font-semibold text-slate-200 mb-1">Mata kuliah yang boleh dilihat</p>
                                            <p class="text-[10px] text-slate-500 mb-3">
                                                Centang matakuliah yang boleh dipantau. Berlaku untuk semua kelasnya, termasuk kelas yang dibuat nanti.
                                            </p>

                                            @if ($mataKuliah->isEmpty())
                                                <p class="text-[11px] text-amber-400">Belum ada mata kuliah. Buat lewat Penugasan Akademik atau susun dari log.</p>
                                            @else
                                                <form method="POST" action="{{ route('admin.akun.akses', $a) }}" class="space-y-2">
                                                    @csrf
                                                    @method('PUT')

                                                    @php
                                                        // Kolom pivot selalu berawalan "pivot_", jadi `id`
                                                        // di sini murni mata_kuliahs.id.
                                                        $terpilih = $a->mataKuliahAkses->pluck('id')->map(fn ($id) => (int) $id)->all();
                                                    @endphp

                                                    @foreach ($mataKuliah as $mk)
                                                        <label class="flex items-start gap-2 text-[11px] text-slate-300 hover:bg-slate-800/60 rounded px-1.5 py-1">
                                                            <input type="checkbox" name="mata_kuliah[]" value="{{ $mk->id }}"
                                                                @checked(in_array($mk->id, $terpilih, false))
                                                                class="mt-0.5 rounded bg-slate-700 border-slate-600">
                                                            <span class="flex-1">
                                                                <span class="text-slate-200">{{ $mk->kode }}</span> {{ $mk->nama }}
                                                                <span class="block text-[10px] text-slate-500">
                                                                    {{ $mk->kelas_count }} kelas
                                                                    @if ($mk->prodi) · {{ $mk->prodi->kode }} @endif
                                                                </span>
                                                            </span>
                                                        </label>
                                                    @endforeach

                                                    <div class="flex gap-2 pt-2">
                                                        <button type="submit" class="flex-1 px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs transition-colors">
                                                            Simpan Akses
                                                        </button>
                                                    </div>
                                                </form>
                                            @endif
                                        </div>
                                    </details>
                                @else
                                    <span class="text-slate-600 text-[11px]">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <details class="relative">
                                        <summary class="cursor-pointer list-none px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-[11px] text-slate-300 transition-colors">
                                            <i class="fa-solid fa-pen mr-1"></i>Ubah
                                        </summary>
                                        <div class="absolute right-0 mt-2 w-80 rounded-xl border border-slate-700 bg-slate-900 p-4 shadow-xl z-20 space-y-3">
                                            <form method="POST" action="{{ route('admin.akun.update', $a) }}" class="space-y-3">
                                                @csrf
                                                @method('PUT')
                                                <div>
                                                    <label class="block text-[10px] uppercase tracking-wider text-slate-500 mb-1">Nama</label>
                                                    <input name="nama" value="{{ $a->nama }}" required
                                                        class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:border-indigo-500 focus:outline-none">
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] uppercase tracking-wider text-slate-500 mb-1">Email</label>
                                                    <input name="email" type="email" value="{{ $a->email }}" required
                                                        class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:border-indigo-500 focus:outline-none">
                                                </div>
                                                <div>
                                                    <label class="block text-[10px] uppercase tracking-wider text-slate-500 mb-1">Nomor induk</label>
                                                    <input name="nomor_induk" value="{{ $a->nomor_induk }}" required
                                                        class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-xs text-white focus:border-indigo-500 focus:outline-none">
                                                </div>
                                                <label class="flex items-center gap-2 text-[11px] text-slate-300">
                                                    <input type="hidden" name="is_active" value="0">
                                                    <input type="checkbox" name="is_active" value="1" @checked($a->is_active) class="rounded bg-slate-700 border-slate-600">
                                                    Akun aktif
                                                </label>
                                                <button type="submit" class="w-full px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs transition-colors">
                                                    Simpan
                                                </button>
                                            </form>
                                        </div>
                                    </details>

                                    <form method="POST" action="{{ route('admin.akun.password', $a) }}">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-[11px] text-slate-300 transition-colors"
                                            title="Buat password acak baru">
                                            <i class="fa-solid fa-key mr-1"></i>Password
                                        </button>
                                    </form>

                                    @if ($a->id !== auth()->id())
                                        <form method="POST" action="{{ route('admin.akun.aktif', $a) }}">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 rounded-lg text-[11px] transition-colors
                                                {{ $a->is_active ? 'bg-rose-500/10 text-rose-400 hover:bg-rose-500/20' : 'bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20' }}">
                                                {{ $a->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-slate-600">
                                <i class="fa-solid fa-user-slash text-3xl mb-2"></i>
                                <p class="text-sm">Belum ada akun yang cocok dengan penyaringan ini.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($akun->hasPages())
            <div class="px-5 py-3 border-t border-slate-800">
                {{ $akun->links() }}
            </div>
        @endif
    </div>

</div>

