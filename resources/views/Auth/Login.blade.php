<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk ke Akun - Smart Learning LMS Analytics</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome untuk ikon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Alpine.js untuk Interaktif -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-100 min-h-screen flex flex-col justify-between font-sans text-slate-800" x-data="{ role: 'dosen', showPassword: false }">

    <!-- Top Bar / Header -->
    <header class="w-full bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between shadow-xs">
        <!-- Logo & Title -->
        <div class="flex items-center space-x-3">
            <div class="bg-blue-600 text-white p-2.5 rounded-xl flex items-center justify-center shadow-sm">
                <i class="fa-solid fa-graduation-cap text-lg"></i>
            </div>
            <div>
                <h1 class="font-bold text-slate-900 text-base leading-tight">Edutrack</h1>
                <p class="text-xs text-slate-500 font-medium">LMS Analytics</p>
            </div>
        </div>

        <!-- Right Header Status -->
        <div class="flex items-center space-x-6">
            <div class="hidden sm:flex items-center space-x-2 bg-emerald-50 border border-emerald-200 px-3 py-1 rounded-full text-xs font-medium text-emerald-700">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Politeknik Negeri Batam</span>
            </div>
            <a href="#" class="text-xs font-medium text-slate-600 hover:text-blue-600 flex items-center space-x-1.5 transition-colors">
                <i class="fa-regular fa-circle-question text-sm"></i>
                <span>Pusat Bantuan LMS</span>
            </a>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1 flex items-center justify-center p-4 sm:p-6 lg:p-8">
        <!-- Card Wrapper -->
        <div class="bg-white w-full max-w-5xl rounded-2xl shadow-xl border border-slate-200/80 overflow-hidden grid grid-cols-1 lg:grid-cols-12">

            <!-- Left Hero Section (Navy Blue Background) -->
            <div class="lg:col-span-5 bg-gradient-to-br from-slate-950 via-blue-950 to-cyan-950 p-8 sm:p-10 text-white flex flex-col justify-between relative overflow-hidden">
                <div class="absolute -top-24 -left-24 w-64 h-64 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

                <div>
                    <div class="inline-flex items-center space-x-1.5 bg-white/15 backdrop-blur-md px-3 py-1 rounded-full text-xs font-semibold text-blue-200 border border-white/10 mb-6">
                        <i class="fa-solid fa-bolt text-blue-400"></i>
                        <span>v2.4 - <span x-text="role.toUpperCase()"></span> Mode</span>
                    </div>

                    <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight leading-snug mb-3">
                        Pantau Keterlibatan Mahasiswa Presisi.
                    </h2>
                    <p class="text-slate-300 text-sm leading-relaxed">
                        Portal analitik akademik terpadu khusus <span class="font-semibold text-white" x-text="role"></span>.
                    </p>
                </div>

                <!-- Middle Widgets/Cards Preview -->
                <div class="space-y-3 my-8">
                    <div class="bg-white/5 backdrop-blur-md border border-white/10 p-3.5 rounded-xl flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="bg-blue-600/30 p-2 rounded-lg text-blue-300">
                                <i class="fa-solid fa-chart-line text-sm"></i>
                            </div>
                            <div>
                                <p class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Pemetaan Siswa</p>
                                <p class="text-sm font-bold text-white">Mahasiswa Teknik Informatika</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white/5 backdrop-blur-md border border-white/10 p-3.5 rounded-xl flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="bg-rose-600/30 p-2 rounded-lg text-rose-300">
                                <i class="fa-solid fa-triangle-exclamation text-sm"></i>
                            </div>
                            <div>
                                <p class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Mahasiswa Berisiko</p>
                                <p class="text-sm font-bold text-white">Intervensi Proaktif</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer Info inside Left Panel -->
                <div class="pt-4 border-t border-white/10 flex items-center justify-between text-[11px] text-slate-400">
                    <div class="flex items-center space-x-1.5">
                        <i class="fa-solid fa-shield-halved text-blue-400"></i>
                        <span>Enkripsi SSO 256-Bit</span>
                    </div>
                    <span>Universitas Terakreditasi Unggul</span>
                </div>
            </div>

            <!-- Right Login Form Section -->
            <div class="lg:col-span-7 p-8 sm:p-10 flex flex-col justify-between bg-white">
                <div>
                    <!-- Form Header -->
                    <div class="mb-6">
                        <h3 class="text-2xl font-bold text-slate-900">Masuk ke Akun</h3>
                        <p class="text-xs sm:text-sm text-slate-500 mt-1">Gunakan kredensial akun universitas Anda.</p>
                    </div>

                    <!-- Role Selector Tabs (Interactive Alpine.js) -->
                    <div class="bg-slate-100 p-1 rounded-xl grid grid-cols-3 gap-1 mb-6 text-center text-xs font-semibold">
                        <button type="button"
                            @click="role = 'dosen'"
                            :class="role === 'dosen' ? 'bg-white text-blue-600 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                            class="py-2.5 rounded-lg flex items-center justify-center space-x-1.5 transition-all cursor-pointer">
                            <i class="fa-solid fa-chalkboard-user"></i>
                            <span>Dosen</span>
                        </button>
                        <button type="button"
                            @click="role = 'kaprodi'"
                            :class="role === 'kaprodi' ? 'bg-white text-blue-600 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                            class="py-2.5 rounded-lg flex items-center justify-center space-x-1.5 transition-all cursor-pointer">
                            <i class="fa-solid fa-user-tie"></i>
                            <span>Kaprodi</span>
                        </button>
                        <button type="button"
                            @click="role = 'admin'"
                            :class="role === 'admin' ? 'bg-white text-blue-600 shadow-xs' : 'text-slate-600 hover:text-slate-900'"
                            class="py-2.5 rounded-lg flex items-center justify-center space-x-1.5 transition-all cursor-pointer">
                            <i class="fa-solid fa-user-shield"></i>
                            <span>Admin</span>
                        </button>
                    </div>

                    <!-- Login Form (Laravel Route & CSRF) -->
                    <form method="POST" action="{{ route('login') }}" class="space-y-4">
                        @csrf

                        <!-- Hidden Input untuk mengirim Role ke Backend jika dibutuhkan -->
                        <input type="hidden" name="selected_role" :value="role">

                        <!-- Email / NIP Input -->
                        <div>
                            <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Email / NIP</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                    <i class="fa-regular fa-envelope text-sm"></i>
                                </span>
                                <input type="email" name="email" value="{{ old('email') }}"
                                    :placeholder="role === 'admin' ? 'admin@univ.ac.id' : (role === 'kaprodi' ? 'kaprodi@univ.ac.id' : 'dosen@univ.ac.id')"
                                    class="w-full pl-10 pr-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:bg-white transition-all" required autofocus>
                            </div>
                            @error('email')
                                <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Password Input -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">Kata Sandi</label>
                                <a href="#" class="text-xs font-medium text-blue-600 hover:underline">Lupa kata sandi?</a>
                            </div>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                    <i class="fa-solid fa-key text-sm"></i>
                                </span>
                                <input :type="showPassword ? 'text' : 'password'" name="password" placeholder="••••••••••••"
                                    class="w-full pl-10 pr-10 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:bg-white transition-all" required>
                                <span @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 cursor-pointer hover:text-slate-600">
                                    <i :class="showPassword ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye'" class="text-sm"></i>
                                </span>
                            </div>
                            @error('password')
                                <span class="text-xs text-rose-500 mt-1 block">{{ $message }}</span>
                            @enderror
                        </div>

                        <!-- Remember Me & Session Info -->
                        <div class="flex items-center justify-between pt-1">
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" name="remember" class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500">
                                <span class="text-xs text-slate-600 font-medium">Ingat sesi masuk di perangkat ini</span>
                            </label>
                            <span class="text-[11px] font-medium text-slate-400">Sesi Genap 24/25</span>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="w-full mt-2 bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 rounded-xl shadow-lg shadow-blue-600/25 flex items-center justify-center space-x-2 transition-all cursor-pointer text-sm">
                            <span>Masuk ke Dashboard Analytics</span>
                            <i class="fa-solid fa-arrow-right text-xs"></i>
                        </button>
                    </form>
                </div>

                <!-- Right Card Footer Links -->
                <div class="pt-6 mt-6 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between text-xs text-slate-500 font-medium space-y-2 sm:space-y-0">
                    <a href="#" class="hover:text-blue-600 transition-colors">Bantuan Helpdesk</a>
                    <div class="flex items-center space-x-4">
                        <a href="#" class="hover:text-blue-600 transition-colors">Kebijakan Privasi</a>
                        <span>•</span>
                        <a href="#" class="hover:text-blue-600 transition-colors">Panduan LMS</a>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Global Footer -->
    <footer class="w-full py-4 text-center text-xs text-slate-400 border-t border-slate-200 bg-white">
        <p>&copy; 2026 Smart Learning Analytics • Fakultas Ilmu Komputer. Hak Cipta Dilindungi Undang-Undang.</p>
    </footer>

</body>
</html>
