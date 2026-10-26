{{--
    Form ganti password sendiri.

    Dipakai tab Settings milik Dosen dan Kaprodi. Satu form untuk semua
    peran supaya aturannya tidak berbeda antara satu akun dan akun lain.

    Disesuaikan untuk password bawaan: form ini yang membuat keputusan
    "semua akun mulai dari password123" aman dipakai — begitu pengguna
    masuk, ia bisa langsung menggantinya sendiri.

    Tema: Modern Academic Analytics
      Navy #0F1939 · Deep Blue #26428B · Sapphire #516AC8 · Gold #E3AF64
      Cream #FAEBD7 · Off-white #F8F7F5
    Font: Bricolage Grotesque (judul halaman) · Plus Jakarta Sans (selebihnya).
    Pastikan kedua font sudah dimuat di layout.

    Layout: profil di kiri, form di kanan (menumpuk di layar kecil).
    Indikator kekuatan, checklist syarat, dan tombol lihat/sembunyi hanya
    membantu di sisi klien; validasi sebenarnya tetap di server.
--}}
@php
    $labelDasar = 'block text-xs font-semibold uppercase tracking-wider text-[#5D6788] mb-1.5';
    $inputDasar = 'w-full h-[46px] bg-[#F8F7F5] border-[1.5px] rounded-xl pl-3.5 pr-11 text-sm text-[#0F1939] placeholder-slate-400 focus:bg-white focus:border-[#516AC8] focus:ring-[3px] focus:ring-[#516AC8]/20 focus:outline-none transition-all';
    $tombolMata = 'absolute right-1.5 top-1/2 -translate-y-1/2 w-9 h-9 rounded-lg text-[#5D6788] hover:text-[#0F1939] flex items-center justify-center';
@endphp

<div class="space-y-6 rounded-3xl bg-[#F8F7F5] p-5 lg:p-7 font-['Plus_Jakarta_Sans'] text-sm text-[#0F1939]">
    <div>
        <h2 class="font-['Bricolage_Grotesque'] text-[28px] font-bold tracking-tight text-[#0F1939]">Pengaturan Akun</h2>
        <p class="text-sm text-[#5D6788] mt-1">
            Kelola data akun dan keamanan password yang Anda pakai untuk masuk ke sistem ini.
        </p>
    </div>

    @include('partials.alert')

    <div class="grid grid-cols-1 lg:grid-cols-[320px_minmax(0,1fr)] gap-5 items-start max-w-5xl">

        {{-- Info akun, hanya dibaca --}}
        <section class="rounded-2xl bg-white border border-[#EFE6D8] shadow-sm overflow-hidden">
            <div class="bg-[#0F1939] px-6 py-7 text-center">
                <div class="w-[76px] h-[76px] mx-auto rounded-full bg-[#E3AF64] text-[#0F1939] flex items-center justify-center font-['Bricolage_Grotesque'] text-2xl font-extrabold">
                    {{ str($user->nama)->substr(0, 2)->upper() }}
                </div>
                <p class="mt-3 text-base font-bold text-white break-words">{{ $user->nama }}</p>
                <span class="inline-block mt-2 px-3 py-0.5 rounded-full bg-[#E3AF64]/15 text-[#E3AF64] text-xs font-semibold">
                    {{ ucfirst($user->role ?? 'pengguna') }}
                </span>
            </div>

            <dl class="px-6 py-2 divide-y divide-[#EFE6D8]">
                <div class="py-3.5">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-[#5D6788]">Nama</dt>
                    <dd class="text-sm font-semibold text-[#0F1939] mt-0.5 break-words">{{ $user->nama }}</dd>
                </div>
                <div class="py-3.5">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-[#5D6788]">Email</dt>
                    <dd class="text-sm font-semibold text-[#0F1939] mt-0.5 break-all">{{ $user->email }}</dd>
                </div>
                <div class="py-3.5">
                    <dt class="text-xs font-semibold uppercase tracking-wider text-[#5D6788]">Nomor induk</dt>
                    <dd class="text-sm font-semibold text-[#0F1939] mt-0.5">{{ $user->nomor_induk ?: '—' }}</dd>
                </div>
            </dl>
        </section>

        {{-- Ganti password --}}
        <section class="rounded-2xl bg-white border border-[#EFE6D8] shadow-sm overflow-hidden">
            <header class="flex items-center gap-3.5 px-7 py-5 border-b border-[#EFE6D8]">
                <div class="w-10 h-10 rounded-xl bg-[#FAEBD7] text-[#0F1939] flex items-center justify-center">
                    <i class="fa-solid fa-key text-sm"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-[#0F1939]">Ganti Password</h3>
                    <p class="text-xs text-[#5D6788]">Gunakan kombinasi yang sulit ditebak dan tidak dipakai di tempat lain.</p>
                </div>
            </header>

            <form id="form-ubah-password" method="POST" action="{{ route('akun.password.ubah') }}" class="px-7 pt-6 pb-7 space-y-5">
                <input type="hidden" name="_token" value="{{ csrf_token() }}" autocomplete="off">

                <div>
                    <label for="password_lama" class="{{ $labelDasar }}">Password sekarang</label>
                    <div class="relative">
                        <input id="password_lama" name="password_lama" type="password" required
                            autocomplete="current-password"
                            class="{{ $inputDasar }} {{ $errors->has('password_lama') ? 'border-rose-500' : 'border-[#EFE6D8]' }}">
                        <button type="button" data-toggle="password_lama" aria-label="Tampilkan password" class="{{ $tombolMata }}">
                            <i class="fa-solid fa-eye text-sm"></i>
                        </button>
                    </div>
                    @error('password_lama')
                        <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label for="password_baru" class="{{ $labelDasar }}">Password baru</label>
                        <div class="relative">
                            <input id="password_baru" name="password_baru" type="password" required
                                autocomplete="new-password" minlength="8"
                                class="{{ $inputDasar }} {{ $errors->has('password_baru') ? 'border-rose-500' : 'border-[#EFE6D8]' }}">
                            <button type="button" data-toggle="password_baru" aria-label="Tampilkan password" class="{{ $tombolMata }}">
                                <i class="fa-solid fa-eye text-sm"></i>
                            </button>
                        </div>
                        <div class="flex gap-1 mt-2" id="meter-password" aria-hidden="true">
                            <i class="flex-1 h-1 rounded bg-[#EFE6D8]"></i>
                            <i class="flex-1 h-1 rounded bg-[#EFE6D8]"></i>
                            <i class="flex-1 h-1 rounded bg-[#EFE6D8]"></i>
                            <i class="flex-1 h-1 rounded bg-[#EFE6D8]"></i>
                        </div>
                        @error('password_baru')
                            <p class="text-xs text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_baru_konfirmasi" class="{{ $labelDasar }}">Ulangi password baru</label>
                        <div class="relative">
                            <input id="password_baru_konfirmasi" name="password_baru_confirmation" type="password" required
                                autocomplete="new-password" minlength="8"
                                class="{{ $inputDasar }} border-[#EFE6D8]">
                            <button type="button" data-toggle="password_baru_konfirmasi" aria-label="Tampilkan password" class="{{ $tombolMata }}">
                                <i class="fa-solid fa-eye text-sm"></i>
                            </button>
                        </div>
                        <p id="petunjuk-cocok" class="text-xs text-[#5D6788] mt-1.5">&nbsp;</p>
                    </div>
                </div>

                {{-- Checklist syarat --}}
                <div id="syarat-password" class="rounded-xl bg-[#F8F7F5] px-4 py-3.5 space-y-1.5 text-xs text-[#5D6788]">
                    <div data-syarat="panjang" class="flex items-center gap-2">
                        <span class="w-4 h-4 rounded-full border-[1.5px] border-[#5D6788] flex items-center justify-center text-[9px] shrink-0"><i class="fa-solid fa-check"></i></span>
                        Minimal 8 karakter
                    </div>
                    <div data-syarat="beda" class="flex items-center gap-2">
                        <span class="w-4 h-4 rounded-full border-[1.5px] border-[#5D6788] flex items-center justify-center text-[9px] shrink-0"><i class="fa-solid fa-check"></i></span>
                        Berbeda dari password lama
                    </div>
                    <div data-syarat="cocok" class="flex items-center gap-2">
                        <span class="w-4 h-4 rounded-full border-[1.5px] border-[#5D6788] flex items-center justify-center text-[9px] shrink-0"><i class="fa-solid fa-check"></i></span>
                        Kedua password baru cocok
                    </div>
                </div>

                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-1">
                    <button type="reset" id="batal-password"
                        class="h-[46px] px-5 rounded-xl border-[1.5px] border-[#EFE6D8] text-sm font-semibold text-[#0F1939] hover:bg-[#F8F7F5] transition-colors">
                        Batal
                    </button>
                    <button type="submit" id="simpan-password"
                        class="inline-flex items-center justify-center gap-2 h-[46px] px-6 rounded-xl bg-[#26428B] hover:bg-[#0F1939] disabled:opacity-45 disabled:cursor-not-allowed text-white text-sm font-bold shadow-md shadow-[#0F1939]/15 transition-all active:scale-[0.99]">
                        <i class="fa-solid fa-floppy-disk text-xs"></i>Simpan Password Baru
                    </button>
                </div>
            </form>
        </section>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('form-ubah-password');
    if (!form) return;

    var lama = document.getElementById('password_lama');
    var baru = document.getElementById('password_baru');
    var ulang = document.getElementById('password_baru_konfirmasi');
    var simpan = document.getElementById('simpan-password');
    var petunjuk = document.getElementById('petunjuk-cocok');
    var segmen = document.querySelectorAll('#meter-password i');
    var syarat = document.querySelectorAll('#syarat-password [data-syarat]');

    // Tombol lihat / sembunyikan password
    form.querySelectorAll('[data-toggle]').forEach(function (tombol) {
        tombol.addEventListener('click', function () {
            var input = document.getElementById(tombol.dataset.toggle);
            var tampil = input.type === 'password';
            input.type = tampil ? 'text' : 'password';
            var ikon = tombol.querySelector('i');
            ikon.classList.toggle('fa-eye', !tampil);
            ikon.classList.toggle('fa-eye-slash', tampil);
            tombol.setAttribute('aria-label', tampil ? 'Sembunyikan password' : 'Tampilkan password');
        });
    });

    function perbarui() {
        var panjang = baru.value.length >= 8;
        var beda = baru.value !== '' && baru.value !== lama.value;
        var cocok = baru.value !== '' && baru.value === ulang.value;
        var status = { panjang: panjang, beda: beda, cocok: cocok };

        syarat.forEach(function (el) {
            var ok = status[el.dataset.syarat];
            var bulat = el.querySelector('span');
            el.classList.toggle('text-[#0F1939]', ok);
            bulat.classList.toggle('bg-[#26428B]', ok);
            bulat.classList.toggle('border-[#26428B]', ok);
            bulat.classList.toggle('text-white', ok);
            bulat.classList.toggle('border-[#5D6788]', !ok);
        });

        var skor = 0;
        if (panjang) skor++;
        if (/[A-Z]/.test(baru.value) && /[a-z]/.test(baru.value)) skor++;
        if (/\d/.test(baru.value)) skor++;
        if (/[^A-Za-z0-9]/.test(baru.value)) skor++;
        segmen.forEach(function (el, n) {
            var aktif = n < skor;
            el.classList.toggle('bg-[#EFE6D8]', !aktif);
            el.classList.toggle('bg-[#E3AF64]', aktif && skor < 3);
            el.classList.toggle('bg-[#26428B]', aktif && skor >= 3);
        });

        petunjuk.textContent = ulang.value === '' ? '\u00a0' : (cocok ? 'Password cocok' : 'Belum cocok');
        simpan.disabled = !(lama.value && panjang && beda && cocok);
    }

    [lama, baru, ulang].forEach(function (el) { el.addEventListener('input', perbarui); });
    document.getElementById('batal-password').addEventListener('click', function () { setTimeout(perbarui, 0); });
    perbarui();
})();
</script>
