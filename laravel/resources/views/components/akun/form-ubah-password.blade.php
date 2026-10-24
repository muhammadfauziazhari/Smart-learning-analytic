{{--
    Form ganti password sendiri.

    Dipakai tab Settings milik Dosen dan Kaprodi. Satu form untuk semua
    peran supaya aturannya tidak berbeda antara satu akun dan akun lain.

    Disesuaikan untuk password bawaan: form ini yang membuat keputusan
    "semua akun mulai dari password123" aman dipakai — begitu pengguna
    masuk, ia bisa langsung menggantinya sendiri.
--}}
<div class="space-y-6">
    <div>
        <h2 class="text-xl font-bold text-white">Pengaturan Akun</h2>
        <p class="text-xs text-slate-400 mt-0.5">
            Ganti password yang Anda pakai untuk masuk ke sistem ini.
        </p>
    </div>

    @include('partials.alert')

    {{-- Info akun, hanya dibaca --}}
    <div class="rounded-2xl border border-slate-800 bg-slate-900/40 overflow-hidden">
        <header class="px-5 py-4 border-b border-slate-800">
            <h3 class="text-sm font-semibold text-white">
                <i class="fa-solid fa-circle-user text-slate-500 mr-1.5"></i>Akun Anda
            </h3>
        </header>
        <dl class="grid sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-slate-800/70">
            <div class="px-5 py-4">
                <dt class="text-[11px] uppercase tracking-wider text-slate-500">Nama</dt>
                <dd class="text-sm text-slate-200 mt-1">{{ $user->nama }}</dd>
            </div>
            <div class="px-5 py-4">
                <dt class="text-[11px] uppercase tracking-wider text-slate-500">Email</dt>
                <dd class="text-sm text-slate-200 mt-1 font-mono text-xs break-all">{{ $user->email }}</dd>
            </div>
            <div class="px-5 py-4">
                <dt class="text-[11px] uppercase tracking-wider text-slate-500">Nomor induk</dt>
                <dd class="text-sm text-slate-200 mt-1 font-mono text-xs">
                    {{ $user->nomor_induk ?: '—' }}
                </dd>
            </div>
        </dl>
    </div>

    {{-- Ganti password --}}
    <div class="rounded-2xl border border-slate-800 bg-slate-900/40 overflow-hidden">
        <header class="px-5 py-4 border-b border-slate-800">
            <h3 class="text-sm font-semibold text-white">
                <i class="fa-solid fa-key text-slate-500 mr-1.5"></i>Ganti Password
            </h3>
        </header>

        <form method="POST" action="{{ route('akun.password.ubah') }}" class="p-5 space-y-5">
            <input type="hidden" name="_token" value="{{ csrf_token() }}" autocomplete="off">

            <div class="max-w-md">
                <label for="password_lama" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">
                    Password sekarang
                </label>
                <input id="password_lama" name="password_lama" type="password" required
                    autocomplete="current-password"
                    class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none">
                @error('password_lama')
                    <p class="text-[11px] text-rose-400 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4 max-w-2xl">
                <div>
                    <label for="password_baru" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">
                        Password baru
                    </label>
                    <input id="password_baru" name="password_baru" type="password" required
                        autocomplete="new-password" minlength="8"
                        class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none">
                    <p class="text-[11px] text-slate-500 mt-1">Minimal 8 karakter, dan harus beda dari password lama.</p>
                    @error('password_baru')
                        <p class="text-[11px] text-rose-400 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_baru_konfirmasi" class="block text-[11px] uppercase tracking-wider text-slate-500 mb-1.5">
                        Ulangi password baru
                    </label>
                    <input id="password_baru_konfirmasi" name="password_baru_confirmation" type="password" required
                        autocomplete="new-password" minlength="8"
                        class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:border-indigo-500 focus:outline-none">
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit"
                    class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium transition-colors">
                    <i class="fa-solid fa-floppy-disk mr-1.5"></i>Simpan Password Baru
                </button>
            </div>
        </form>
    </div>
</div>