{{-- Partial: tab Penugasan Akademik
     Dipakai sebagai salah satu tab di Admin/AdminDashboard.blade.php, bukan
     halaman mandiri. --}}

@php
    $labelDasar = 'block text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5';
    $inputDasar = 'w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-800 placeholder-slate-400 focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 focus:outline-none transition-all';
@endphp

<div class="space-y-6 rounded-3xl bg-slate-100 p-5 lg:p-7">

    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-extrabold text-[#0B1B3D]">Penugasan Akademik</h2>
            <p class="text-sm text-blue-600 mt-1">
                Tetapkan dosen pengampu dan catat mahasiswa ke setiap kelas.
            </p>
            <p class="text-[11px] text-slate-500 mt-1">
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
    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5">
        <div class="flex items-start gap-3">
            <div class="w-9 h-9 rounded-full bg-blue-600 text-white flex items-center justify-center shrink-0">
                <i class="fa-solid fa-circle-info text-sm"></i>
            </div>
            <div class="text-xs text-slate-600 space-y-1.5 leading-relaxed">
                <p><strong class="text-[#0B1B3D]">Kenapa ini penting.</strong> Dosen hanya bisa membuka kelas yang tercatat di sini, dan hanya melihat mahasiswa di kelas itu. Belum ada penugasan, dashboard Dosen akan kosong.</p>
                <p>Mahasiswa <strong class="text-[#0B1B3D]">tidak dibuatkan akun</strong>. Yang disimpan hanya NIM dan nama, karena mahasiswa tidak pernah masuk ke sistem ini. Nama boleh dikosongkan dan dilengkapi nanti dari data log LMS.</p>
            </div>
        </div>
    </div>

    {{-- FILTER
         Form menuju ke /admin/penugasan supaya hasil saringannya tetap
         muncul di tab ini, bukan di tab lain. --}}
    <form method="GET" action="{{ route('admin.penugasan') }}"
        class="flex flex-wrap items-end gap-3 rounded-2xl bg-white border border-slate-200 p-4 shadow-sm">
        <div class="flex-1 min-w-[220px]">
            <label class="{{ $labelDasar }}">Cari</label>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Nama kelas, kode, atau nama mata kuliah"
                    class="{{ $inputDasar }} pl-10">
            </div>
        </div>
        <div class="min-w-[220px]">
            <label class="{{ $labelDasar }}">Mata Kuliah</label>
            <select name="mata_kuliah" class="{{ $inputDasar }}">
                <option value="">Semua mata kuliah</option>
                @foreach ($pilihanMataKuliah as $mk)
                    <option value="{{ $mk->id }}" @selected(request('mata_kuliah') == $mk->id)>
                        {{ $mk->kode }} - {{ $mk->nama }}
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="px-5 py-2.5 rounded-xl text-xs font-semibold bg-[#0F3277] hover:bg-[#0B255B] text-white transition-all">
            <i class="fa-solid fa-filter mr-1.5"></i>Terapkan
        </button>
        @if (request()->hasAny(['q', 'mata_kuliah']))
            <a href="{{ route('admin.penugasan') }}" class="px-4 py-2.5 rounded-xl text-xs text-slate-500 hover:text-slate-900 transition-all">
                Reset
            </a>
        @endif
    </form>

    @if ($kelas->isEmpty())
        <div class="border-2 border-dashed border-slate-300 rounded-2xl bg-white p-12 text-center text-slate-500">
            <i class="fa-solid fa-school text-3xl mb-2 text-slate-300"></i>
            <p class="text-sm">Belum ada kelas. Jalankan <span class="font-mono text-slate-600">php artisan db:seed --class=Database\Seeders\DataMasterSeeder</span></p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($kelas as $k)
                <details class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden group" @if (request('buka') == $k->id) open @endif>
                    <summary class="cursor-pointer select-none list-none px-5 py-4 flex flex-wrap items-center justify-between gap-3 hover:bg-slate-50 transition-colors">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-[#0B1B3D] text-amber-400 flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-chalkboard"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-[#0B1B3D] truncate">{{ $k->label() }}</p>
                                <p class="text-[11px] text-slate-500 mt-0.5">
                                    {{ $k->tahun_akademik }} - Semester {{ $k->semester }}
                                    @if ($k->prodi) · {{ $k->prodi->kode }} @endif
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-[11px] shrink-0">
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 border border-blue-100 px-2.5 py-1 font-semibold text-blue-700">
                                <i class="fa-solid fa-chalkboard-user"></i>{{ $k->dosen->count() }} dosen
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 border border-slate-200 px-2.5 py-1 font-semibold text-slate-600">
                                <i class="fa-solid fa-users"></i>{{ $k->mahasiswa->count() }} mhs
                            </span>
                            @if ($k->dosen->isEmpty())
                                <span class="px-2.5 py-1 rounded-full bg-amber-50 border border-amber-200 text-amber-600 text-[10px] font-semibold uppercase tracking-wider">Tanpa dosen</span>
                            @endif
                            <i class="fa-solid fa-chevron-down text-slate-400 ml-1 group-open:rotate-180 transition-transform"></i>
                        </div>
                    </summary>

                    <div class="border-t border-slate-100 p-5 space-y-6 bg-slate-50/70">

                        {{-- DOSEN PENGAMPU --}}
                        <div>
                            <p class="text-xs font-bold text-[#0B1B3D] mb-3">
                                <i class="fa-solid fa-chalkboard-user text-blue-500 mr-1.5"></i>Dosen Pengampu
                            </p>

                            @forelse ($k->dosen as $dosen)
                                <div class="flex items-center justify-between bg-white border border-slate-200 rounded-xl px-4 py-2.5 mb-2 shadow-sm">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-blue-500 to-blue-700 flex items-center justify-center text-[11px] font-bold text-white shrink-0">
                                            {{ str($dosen->nama)->substr(0, 2)->upper() }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-slate-800 truncate">{{ $dosen->nama }}</p>
                                            <p class="text-[11px] text-slate-500 font-mono">
                                                {{ $dosen->nomor_induk ?? 'tanpa NIP' }} ·
                                                <span class="uppercase font-semibold text-amber-600">{{ $dosen->pivot->peran }}</span>
                                            </p>
                                        </div>
                                    </div>
                                    <form method="POST" action="{{ route('admin.penugasan.dosen.hapus', [$k, $dosen]) }}"
                                        onsubmit="return confirm('Cabut akses {{ $dosen->nama }} dari kelas ini?')">
                                        @csrf @method('DELETE')
                                        <button class="w-8 h-8 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="Cabut akses">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </form>
                                </div>
                            @empty
                                <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-xl px-4 py-2.5 mb-3">
                                    <i class="fa-solid fa-triangle-exclamation text-amber-500 mr-1"></i>
                                    Belum ada dosen. Selama kosong, kelas ini tidak bisa dibuka Dosen mana pun.
                                </p>
                            @endforelse

                            <form method="POST" action="{{ route('admin.penugasan.dosen', $k) }}"
                                class="flex flex-wrap gap-2 mt-3">
                                @csrf
                                <input type="text" name="nomor_induk" placeholder="NIP dosen" required
                                    class="{{ $inputDasar }} flex-1 min-w-[180px] !w-auto">
                                <select name="peran" class="{{ $inputDasar }} !w-auto">
                                    <option value="pengampu">Pengampu</option>
                                    <option value="pembantu">Pembantu</option>
                                </select>
                                <button class="px-5 py-2.5 rounded-xl text-xs font-semibold bg-[#0F3277] hover:bg-[#0B255B] text-white transition-all">
                                    <i class="fa-solid fa-plus mr-1"></i>Tambah
                                </button>
                            </form>
                        </div>

                        {{-- MAHASISWA --}}
                        <div class="pt-5 border-t border-slate-200">
                            <p class="text-xs font-bold text-[#0B1B3D] mb-3">
                                <i class="fa-solid fa-users text-blue-500 mr-1.5"></i>Mahasiswa
                                <span class="text-slate-400 font-normal">- tempel daftar NIM, satu per baris</span>
                            </p>

                            @if ($k->mahasiswa->isNotEmpty())
                                <div class="max-h-48 overflow-y-auto bg-white border border-slate-200 rounded-xl divide-y divide-slate-100 mb-3 shadow-sm">
                                    @foreach ($k->mahasiswa as $mhs)
                                        @php $sudahLain = $nimTercatat[$mhs->nim] ?? null; @endphp
                                        <div class="flex items-center justify-between px-4 py-2 hover:bg-slate-50">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <div class="w-7 h-7 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center text-[10px] font-bold text-slate-600 shrink-0">
                                                    {{ $mhs->inisial() }}
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="text-xs font-medium text-slate-800 truncate">{{ $mhs->namaTampil() }}</p>
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
                                                                <span class="text-amber-600">
                                                                    · juga di kelas {{ $kelasLain->implode(', ') }}
                                                                </span>
                                                            @endif
                                                        @endif
                                                        @if (! $mhs->nama)
                                                            <span class="text-slate-400">· nama belum dilengkapi</span>
                                                        @endif
                                                    </p>
                                                </div>
                                            </div>
                                            <form method="POST" action="{{ route('admin.penugasan.mahasiswa.hapus', [$k, $mhs]) }}"
                                                onsubmit="return confirm('Keluarkan NIM {{ $mhs->nim }} dari kelas ini?')">
                                                @csrf @method('DELETE')
                                                <button class="w-7 h-7 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors" title="Keluarkan dari kelas">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </form>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-slate-500 mb-3">Belum ada mahasiswa tercatat.</p>
                            @endif

                            <form method="POST" action="{{ route('admin.penugasan.mahasiswa', $k) }}">
                                @csrf
                                <textarea name="nim" rows="5" required
                                    placeholder="3312501013&#10;3312501014 - Budi Santoso&#10;3312501015  Siti Aminah"
                                    class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 text-xs font-mono text-slate-800 placeholder-slate-400 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15 focus:outline-none transition-all"></textarea>
                                <p class="text-[11px] text-slate-500 mt-2 mb-3">
                                    Boleh juga dipisah koma atau titik koma. Tulis <span class="font-mono text-slate-700">NIM - Nama</span> bila nama sudah diketahui.
                                </p>
                                <button class="px-5 py-2.5 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white shadow-md shadow-emerald-600/20 transition-all">
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
