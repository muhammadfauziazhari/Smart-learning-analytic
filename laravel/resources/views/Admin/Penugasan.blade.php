
{{-- Partial: tab Penugasan Akademik }}
     Dipakai sebagai salah satu tab di Admin/AdminDashboard.blade.php, bukan
     halaman mandiri. --}}

<div class="space-y-6">

    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-white">Penugasan Akademik</h2>
            <p class="text-xs text-slate-400 mt-0.5">
                Tetapkan dosen pengampu dan catat mahasiswa ke setiap kelas.
                Inilah yang menentukan siapa boleh melihat siapa.
            </p>
        </div>
    </div>

    {{-- Notifikasi hanya di tab yang aktif. Semua tab dirender sekaligus,
         jadi tanpa ini pesan yang sama muncul di dua panel. --}}
    @if (($tabAktif ?? 'dashboard') === 'penugasan')
        @include('partials.alert')
    @endif

    {{-- Penjelasan importance --}}
    <div class="rounded-2xl border border-indigo-500/30 bg-indigo-500/5 p-5">
        <div class="flex items-start gap-3">
            <i class="fa-solid fa-circle-info text-indigo-400 mt-0.5"></i>
            <div class="text-xs text-slate-300 space-y-1">
                <p><strong class="text-white">Kenapa ini penting.</strong> Dosen hanya bisa membuka kelas yang tercatat di sini, dan hanya melihat mahasiswa di kelas itu. Belum ada penugasan, dashboard Dosen akan kosong.</p>
                <p>Mahasiswa <strong>tidak dibuatkan akun</strong>. Yang disimpan hanya NIM dan nama, karena mahasiswa tidak pernah masuk ke sistem ini. Nama boleh dikosongkan dan dilengkapi nanti dari data log LMS.</p>
            </div>
        </div>
    </div>

    {{-- FILTER
         Form menuju ke /admin/penugasan supaya hasil saringannya tetap
         muncul di tab ini, bukan di tab lain. --}}
    <form method="GET" action="{{ route('admin.penugasan') }}" class="flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[220px]">
            <label class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">Cari</label>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Nama kelas, kode, atau nama mata kuliah"
                class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:border-indigo-500 focus:outline-none">
        </div>
        <div class="min-w-[220px]">
            <label class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">Mata Kuliah</label>
            <select name="mata_kuliah"
                class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none">
                <option value="">Semua mata kuliah</option>
                @foreach ($pilihanMataKuliah as $mk)
                    <option value="{{ $mk->id }}" @selected(request('mata_kuliah') == $mk->id)>
                        {{ $mk->kode }} - {{ $mk->nama }}
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-medium bg-indigo-600 hover:bg-indigo-500 text-white transition-all">
            <i class="fa-solid fa-filter mr-1.5"></i>Terapkan
        </button>
        @if (request()->hasAny(['q', 'mata_kuliah']))
            <a href="{{ route('admin.penugasan') }}" class="px-4 py-2.5 rounded-xl text-xs text-slate-400 hover:text-white transition-all">
                Reset
            </a>
        @endif
    </form>

    @if ($kelas->isEmpty())
        <div class="border-2 border-dashed border-slate-800 rounded-2xl p-12 text-center text-slate-600">
            <i class="fa-solid fa-school text-3xl mb-2"></i>
            <p class="text-sm">Belum ada kelas. Jalankan <span class="font-mono text-slate-500">php artisan db:seed --class=Database\Seeders\DataMasterSeeder</span></p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($kelas as $k)
                <details class="rounded-2xl border border-slate-800 bg-slate-900/30 overflow-hidden group" @if (request('buka') == $k->id) open @endif>
                    <summary class="cursor-pointer select-none px-5 py-4 flex items-center justify-between hover:bg-slate-900/60 transition-colors">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-white truncate">{{ $k->label() }}</p>
                            <p class="text-[11px] text-slate-500 mt-0.5">
                                {{ $k->tahun_akademik }} - Semester {{ $k->semester }}
                                @if ($k->prodi) · {{ $k->prodi->kode }} @endif
                            </p>
                        </div>
                        <div class="flex items-center gap-4 text-[11px] shrink-0 ml-4">
                            <span class="text-slate-400">
                                <i class="fa-solid fa-chalkboard-user text-slate-600 mr-1"></i>{{ $k->dosen->count() }} dosen
                            </span>
                            <span class="text-slate-400">
                                <i class="fa-solid fa-users text-slate-600 mr-1"></i>{{ $k->mahasiswa->count() }} mhs
                            </span>
                            @if ($k->dosen->isEmpty())
                                <span class="px-2 py-1 rounded-md bg-amber-500/10 text-amber-400 text-[10px] uppercase tracking-wider">Tanpa dosen</span>
                            @endif
                            <i class="fa-solid fa-chevron-down text-slate-600 group-open:rotate-180 transition-transform"></i>
                        </div>
                    </summary>

                    <div class="border-t border-slate-800 p-5 space-y-6 bg-slate-950/40">

                        {{-- DOSEN PENGAMPU --}}
                        <div>
                            <p class="text-xs font-semibold text-slate-300 mb-3">
                                <i class="fa-solid fa-chalkboard-user text-slate-500 mr-1.5"></i>Dosen Pengampu
                            </p>

                            @forelse ($k->dosen as $dosen)
                                <div class="flex items-center justify-between bg-slate-900/60 border border-slate-800 rounded-xl px-4 py-2.5 mb-2">
                                    <div class="min-w-0">
                                        <p class="text-sm text-white truncate">{{ $dosen->nama }}</p>
                                        <p class="text-[11px] text-slate-500 font-mono">
                                            {{ $dosen->nomor_induk ?? 'tanpa NIP' }} ·
                                            <span class="uppercase">{{ $dosen->pivot->peran }}</span>
                                        </p>
                                    </div>
                                    <form method="POST" action="{{ route('admin.penugasan.dosen.hapus', [$k, $dosen]) }}"
                                        onsubmit="return confirm('Cabut akses {{ $dosen->nama }} dari kelas ini?')">
                                        @csrf @method('DELETE')
                                        <button class="text-slate-500 hover:text-red-400 transition-colors" title="Cabut akses">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </form>
                                </div>
                            @empty
                                <p class="text-xs text-amber-400/80 mb-3">
                                    <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                                    Belum ada dosen. Selama kosong, kelas ini tidak bisa dibuka Dosen mana pun.
                                </p>
                            @endforelse

                            <form method="POST" action="{{ route('admin.penugasan.dosen', $k) }}"
                                class="flex flex-wrap gap-2 mt-3">
                                @csrf
                                <input type="text" name="nomor_induk" placeholder="NIP dosen" required
                                    class="flex-1 min-w-[180px] bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white placeholder-slate-600 focus:border-indigo-500 focus:outline-none">
                                <select name="peran" class="bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none">
                                    <option value="pengampu">Pengampu</option>
                                    <option value="pembantu">Pembantu</option>
                                </select>
                                <button class="px-5 py-2.5 rounded-xl text-xs font-medium bg-indigo-600 hover:bg-indigo-500 text-white transition-all">
                                    <i class="fa-solid fa-plus mr-1"></i>Tambah
                                </button>
                            </form>
                        </div>

                        {{-- MAHASISWA --}}
                        <div class="pt-5 border-t border-slate-800">
                            <p class="text-xs font-semibold text-slate-300 mb-3">
                                <i class="fa-solid fa-users text-slate-500 mr-1.5"></i>Mahasiswa
                                <span class="text-slate-600 font-normal">- tempel daftar NIM, satu per baris</span>
                            </p>

                            @if ($k->mahasiswa->isNotEmpty())
                                <div class="max-h-48 overflow-y-auto border border-slate-800 rounded-xl divide-y divide-slate-800/70 mb-3">
                                    @foreach ($k->mahasiswa as $mhs)
                                        @php $sudahLain = $nimTercatat[$mhs->nim] ?? null; @endphp
                                        <div class="flex items-center justify-between px-4 py-2 bg-slate-900/40">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <div class="w-7 h-7 rounded-full bg-slate-700 flex items-center justify-center text-[10px] font-semibold text-slate-300 shrink-0">
                                                    {{ $mhs->inisial() }}
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="text-xs text-slate-200 truncate">{{ $mhs->namaTampil() }}</p>
                                                    <p class="text-[10px] text-slate-500 font-mono">
                                                        {{ $mhs->nim }}
                                                        @if ($sudahLain)
                                                            @php
                                                                $kelasLain = collect($sudahLain)
                                                                    ->pluck('kode')
                                                                    ->unique()
                                                                    ->reject(fn ($kode) => $kode === $k->kode)
                                                                    ->values();
                                                            @endphp
                                                            @if ($kelasLain->isNotEmpty())
                                                                <span class="text-amber-500">
                                                                    · juga di kelas {{ $kelasLain->implode(', ') }}
                                                                </span>
                                                            @endif
                                                        @endif
                                                        @if (! $mhs->nama)
                                                            <span class="text-slate-600">· nama belum dilengkapi</span>
                                                        @endif
                                                    </p>
                                                </div>
                                            </div>
                                            <form method="POST" action="{{ route('admin.penugasan.mahasiswa.hapus', [$k, $mhs]) }}"
                                                onsubmit="return confirm('Keluarkan NIM {{ $mhs->nim }} dari kelas ini?')">
                                                @csrf @method('DELETE')
                                                <button class="text-slate-600 hover:text-red-400 transition-colors">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </form>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-slate-600 mb-3">Belum ada mahasiswa tercatat.</p>
                            @endif

                            <form method="POST" action="{{ route('admin.penugasan.mahasiswa', $k) }}">
                                @csrf
                                <textarea name="nim" rows="5" required
                                    placeholder="3312501013&#10;3312501014 - Budi Santoso&#10;3312501015  Siti Aminah"
                                    class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-xs font-mono text-white placeholder-slate-700 focus:border-indigo-500 focus:outline-none"></textarea>
                                <p class="text-[11px] text-slate-600 mt-2 mb-3">
                                    Boleh juga dipisah koma atau titik koma. Tulis <span class="font-mono">NIM - Nama</span> bila nama sudah diketahui.
                                </p>
                                <button class="px-5 py-2.5 rounded-xl text-xs font-medium bg-emerald-600 hover:bg-emerald-500 text-white transition-all">
                                    <i class="fa-solid fa-user-plus mr-1.5"></i>Daftarkan Mahasiswa
                                </button>
                            </form>
                        </div>
                    </div>
                </details>
            @endforeach
        </div>

        <div class="flex justify-end">
            {{ $kelas->links() }}
        </div>
    @endif
</div>

