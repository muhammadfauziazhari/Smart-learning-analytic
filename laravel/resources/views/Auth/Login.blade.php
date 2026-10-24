<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Edutrack</title>

    <!-- Fonts: Bricolage Grotesque (judul) + Plus Jakarta Sans (isi) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,700;12..96,800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        display: ['"Bricolage Grotesque"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                        sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif']
                    }
                }
            }
        }
    </script>

    <!-- FontAwesome untuk ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Alpine.js untuk Interaktif -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        body { -webkit-font-smoothing: antialiased; }
        .theme-shift { transition: background-color .5s ease, color .3s ease, box-shadow .3s ease, border-color .3s ease; }

        /* Satu momen animasi saat halaman dibuka: batang grafik tumbuh */
        @keyframes grow { from { transform: scaleY(.15); } to { transform: scaleY(1); } }
        .bar { transform-origin: bottom; animation: grow .9s cubic-bezier(.2,.8,.2,1) both; }
        .bar:nth-child(2) { animation-delay: .06s; } .bar:nth-child(3) { animation-delay: .12s; }
        .bar:nth-child(4) { animation-delay: .18s; } .bar:nth-child(5) { animation-delay: .24s; }
        .bar:nth-child(6) { animation-delay: .30s; } .bar:nth-child(7) { animation-delay: .36s; }
        @media (prefers-reduced-motion: reduce) {
            .bar { animation: none; }
            .theme-shift { transition: none; }
        }
    </style>
</head>
<body class="h-full bg-white font-sans text-slate-900"
      x-data="{
          role: 'dosen',
          showPassword: false,
          roles: {
              dosen:   { label: 'Dosen',   color: '#2F3CFF', soft: '#ECEEFF', placeholder: 'dosen@univ.ac.id',   desc: 'Lihat keterlibatan mahasiswa di setiap mata kuliah dan temukan yang perlu perhatian lebih awal.' },
              kaprodi: { label: 'Kaprodi', color: '#8A2BE2', soft: '#F4EBFD', placeholder: 'kaprodi@univ.ac.id', desc: 'Bandingkan tren keterlibatan antar mata kuliah dan angkatan untuk mendukung keputusan program studi.' },
              admin:   { label: 'Admin',   color: '#008F73', soft: '#E3F6F1', placeholder: 'admin@univ.ac.id',   desc: 'Kelola akun pengguna, sumber data LMS, dan pengaturan analitik.' }
          }
      }">

<div class="min-h-full flex flex-col lg:flex-row">

    <!-- ===================== Kolom kiri: formulir ===================== -->
    <div class="flex-1 flex flex-col px-6 sm:px-12 lg:px-16 xl:px-24">

        <!-- Header -->
        <header class="flex items-center justify-between py-6">
            <div class="flex items-center gap-2.5">
                <div class="theme-shift h-9 w-9 rounded-xl text-white flex items-center justify-center"
                     :style="`background:${roles[role].color}`">
                    <i class="fa-solid fa-graduation-cap text-sm"></i>
                </div>
                <span class="font-display text-lg font-bold tracking-tight">Edutrack</span>
            </div>
            <a href="#" class="text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors rounded focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-900">
                Butuh bantuan?
            </a>
        </header>

        <!-- Form -->
        <main class="flex-1 flex items-center">
            <div class="w-full max-w-[420px] mx-auto py-10">

                <h1 class="font-display text-[40px] leading-[1.05] font-extrabold tracking-tight text-slate-900">
                    Selamat datang kembali
                </h1>
                <p class="mt-3 text-[15px] text-slate-500">Masuk dengan akun universitas Anda.</p>

                <!-- Pemilih peran -->
                <div class="mt-8" role="tablist" aria-label="Pilih peran">
                    <div class="grid grid-cols-3 gap-2 text-sm font-semibold">
                        <template x-for="(r, key) in roles" :key="key">
                            <button type="button" role="tab" @click="role = key" :aria-selected="role === key"
                                :style="role === key ? `background:${r.soft}; border-color:${r.color}; color:${r.color}` : ''"
                                :class="role === key ? '' : 'border-slate-200 text-slate-500 hover:border-slate-300 hover:text-slate-900'"
                                class="theme-shift flex items-center justify-center gap-2 rounded-xl border-2 py-2.5 cursor-pointer focus:outline-none focus-visible:ring-4 focus-visible:ring-slate-900/15">
                                <span class="h-2 w-2 rounded-full" :style="`background:${r.color}`"></span>
                                <span x-text="r.label"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Notifikasi flash (mis. "Akun berhasil dibuat") -->
                <div class="mt-6">
                    @include('partials.alert')
                </div>

                <!-- Login Form (Laravel Route & CSRF) -->
                <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
                    @csrf

                    <!-- Hidden Input untuk mengirim Role ke Backend jika dibutuhkan -->
                    <input type="hidden" name="selected_role" :value="role">

                    <!-- Email / NIP Input -->
                    <div>
                        <label for="email" class="block text-sm font-semibold text-slate-700 mb-1.5">Email / NIP</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}"
                            :placeholder="roles[role].placeholder" autocomplete="username"
                            :style="`--c:${roles[role].color}`"
                            class="theme-shift block w-full h-12 px-4 rounded-xl border-2 border-slate-200 bg-white text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[var(--c)] focus:ring-4 focus:ring-[var(--c)]/15"
                            required autofocus>
                        @error('email')
                            <span class="mt-1.5 flex items-center gap-1.5 text-sm text-rose-600">
                                <i class="fa-solid fa-circle-exclamation text-xs"></i>{{ $message }}
                            </span>
                        @enderror
                    </div>

                    <!-- Password Input -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-sm font-semibold text-slate-700">Kata sandi</label>
                            <a href="#" class="theme-shift text-sm font-semibold hover:underline focus:outline-none focus-visible:underline"
                               :style="`color:${roles[role].color}`">Lupa kata sandi?</a>
                        </div>
                        <div class="relative" :style="`--c:${roles[role].color}`">
                            <input id="password" :type="showPassword ? 'text' : 'password'" name="password" placeholder="Masukkan kata sandi"
                                autocomplete="current-password"
                                class="theme-shift block w-full h-12 pl-4 pr-12 rounded-xl border-2 border-slate-200 bg-white text-sm text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[var(--c)] focus:ring-4 focus:ring-[var(--c)]/15"
                                required>
                            <button type="button" @click="showPassword = !showPassword"
                                :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                                class="absolute inset-y-0 right-0 flex items-center px-4 text-slate-400 hover:text-slate-700 cursor-pointer focus:outline-none focus-visible:text-slate-900">
                                <i :class="showPassword ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye'" class="text-sm"></i>
                            </button>
                        </div>
                        @error('password')
                            <span class="mt-1.5 flex items-center gap-1.5 text-sm text-rose-600">
                                <i class="fa-solid fa-circle-exclamation text-xs"></i>{{ $message }}
                            </span>
                        @enderror
                    </div>

                    <!-- Remember Me -->
                    <label class="flex items-center gap-2.5 cursor-pointer w-fit">
                        <input type="checkbox" name="remember"
                            :style="`accent-color:${roles[role].color}`"
                            class="h-4 w-4 rounded border-slate-300">
                        <span class="text-sm text-slate-600">Ingat saya di perangkat ini</span>
                    </label>

                    <!-- Submit Button -->
                    <button type="submit"
                        :style="`background:${roles[role].color}; box-shadow: 0 10px 24px -8px ${roles[role].color}`"
                        class="theme-shift w-full h-12 rounded-xl text-white text-[15px] font-bold cursor-pointer hover:brightness-110 active:brightness-95 focus:outline-none focus-visible:ring-4 focus-visible:ring-slate-900/20">
                        Masuk sebagai <span x-text="roles[role].label"></span>
                    </button>
                </form>

                <p class="mt-8 text-center text-xs text-slate-500">
                    Dengan masuk, Anda menyetujui
                    <a href="#" class="underline underline-offset-2 hover:text-slate-900">Kebijakan Privasi</a>
                    dan
                    <a href="#" class="underline underline-offset-2 hover:text-slate-900">Panduan LMS</a>.
                </p>
            </div>
        </main>

        <!-- Footer -->
        <footer class="py-6 text-xs text-slate-400">
            &copy; 2026 Smart Learning Analytics. Fakultas Ilmu Komputer.
        </footer>
    </div>

    <!-- ===================== Kolom kanan: panel berwarna (ganti warna sesuai peran) ===================== -->
    <aside class="theme-shift hidden lg:flex lg:w-[48%] xl:w-1/2 relative overflow-hidden items-center justify-center p-12 text-white"
           :style="`background:${roles[role].color}`">

        <!-- Bentuk dekoratif besar -->
        <div class="absolute -right-24 -top-24 h-80 w-80 rounded-full bg-white/10"></div>
        <div class="absolute -left-16 bottom-10 h-56 w-56 rounded-[3rem] rotate-12 bg-black/10"></div>

        <div class="relative w-full max-w-lg">

            <p class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3.5 py-1.5 text-xs font-semibold">
                <span class="h-1.5 w-1.5 rounded-full bg-[#FFD23F]"></span>
                Politeknik Negeri Batam
            </p>

            <h2 class="mt-5 font-display text-[52px] leading-[1.02] font-extrabold tracking-tight">
                Tahu siapa yang butuh bantuan, sebelum terlambat.
            </h2>
            <p class="mt-4 max-w-md text-[15px] leading-relaxed text-white/80" x-text="roles[role].desc"></p>

            <!-- Kolase ilustrasi (statis, hanya contoh tampilan) -->
            <div class="relative mt-10 h-[290px]" aria-hidden="true">

                <!-- Kartu grafik batang -->
                <div class="absolute left-0 top-6 w-64 -rotate-3 rounded-2xl bg-white p-5 text-slate-900 shadow-2xl shadow-black/20">
                    <p class="text-sm font-bold">Keterlibatan mingguan</p>
                    <p class="text-xs text-slate-500">Teknik Informatika</p>
                    <div class="mt-4 flex h-24 items-end gap-2">
                        <div class="bar flex-1 rounded-md bg-[#FFD23F]" style="height:40%"></div>
                        <div class="bar flex-1 rounded-md bg-[#22D3A0]" style="height:62%"></div>
                        <div class="bar flex-1 rounded-md bg-[#FF6FB5]" style="height:50%"></div>
                        <div class="bar flex-1 rounded-md bg-[#FF7A3D]" style="height:78%"></div>
                        <div class="bar flex-1 rounded-md bg-[#FFD23F]" style="height:66%"></div>
                        <div class="bar flex-1 rounded-md bg-[#22D3A0]" style="height:92%"></div>
                        <div class="bar flex-1 rounded-md bg-[#FF6FB5]" style="height:82%"></div>
                    </div>
                </div>

                <!-- Kartu donat -->
                <div class="absolute right-0 top-0 flex w-44 rotate-3 flex-col items-center rounded-2xl bg-[#FFD23F] p-5 text-slate-900 shadow-2xl shadow-black/20">
                    <div class="relative h-24 w-24 rounded-full"
                         style="background: conic-gradient(#0F172A 0 64%, #FF7A3D 64% 86%, #ffffff 86% 100%)">
                        <div class="absolute inset-[18px] flex items-center justify-center rounded-full bg-[#FFD23F]">
                            <span class="font-display text-xl font-extrabold">64%</span>
                        </div>
                    </div>
                    <p class="mt-3 text-center text-xs font-bold">Aktif di LMS</p>
                </div>

                <!-- Chip mahasiswa berisiko -->
                <div class="absolute bottom-4 right-6 flex -rotate-2 items-center gap-3 rounded-2xl bg-[#FF6FB5] px-4 py-3 text-slate-900 shadow-2xl shadow-black/20">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white">
                        <i class="fa-solid fa-triangle-exclamation text-[#FF3D8B]"></i>
                    </span>
                    <div>
                        <p class="font-display text-lg font-extrabold leading-none">12 mahasiswa</p>
                        <p class="mt-0.5 text-xs font-semibold">perlu ditindaklanjuti</p>
                    </div>
                </div>

                <!-- Chip tren naik -->
                <div class="absolute bottom-0 left-10 flex rotate-2 items-center gap-2 rounded-full bg-[#22D3A0] px-4 py-2 text-sm font-bold text-slate-900 shadow-xl shadow-black/20">
                    <i class="fa-solid fa-arrow-trend-up"></i> +18% minggu ini
                </div>
            </div>
        </div>
    </aside>
</div>

@stack('flash-alert-script')
</body>
</html>
