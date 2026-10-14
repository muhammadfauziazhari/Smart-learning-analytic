<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pendaftaran Akun - Edutrack LMS Analytics</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Font Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        navy: {
                            800: '#1e293b',
                            900: '#0f172a',
                            950: '#090d16',
                        },
                        brand: {
                            blue: '#2563eb',
                            hover: '#1d4ed8'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 font-sans text-slate-800 min-h-screen flex flex-col justify-between selection:bg-blue-600 selection:text-white">

    <!-- Header / Top Navigation -->
    <header class="w-full bg-white border-b border-slate-200 py-3 px-6 sm:px-10 flex justify-between items-center shadow-xs">
        <div class="flex items-center space-x-3">
            <div class="bg-blue-600 text-white p-2.5 rounded-xl flex items-center justify-center shadow-md">
                <i class="fa-solid fa-graduation-cap text-lg"></i>
            </div>
            <div>
                <h1 class="font-bold text-slate-900 tracking-tight leading-tight text-base sm:text-lg">Edutrack</h1>
                <p class="text-xs text-slate-500 font-medium">LMS Analytics</p>
            </div>
        </div>

        <div class="flex items-center space-x-4">
            <span class="hidden sm:inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                <span class="w-2 h-2 mr-1.5 bg-emerald-500 rounded-full animate-pulse"></span> Politeknik Negeri Batam
            </span>
            <a href="#" class="text-xs font-semibold text-slate-600 hover:text-blue-600 flex items-center gap-1.5 transition">
                <i class="fa-regular fa-circle-question text-slate-400"></i> Pusat Bantuan LMS
            </a>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-grow flex items-center justify-center p-4 sm:p-6 lg:p-8">
        <div class="w-full max-w-5xl bg-white rounded-3xl shadow-xl border border-slate-100 overflow-hidden grid grid-cols-1 lg:grid-cols-12">

            <!-- Left Banner Column -->
            <div class="lg:col-span-5 bg-gradient-to-br from-slate-900 via-slate-900 to-navy-950 p-8 sm:p-10 flex flex-col justify-between text-white relative overflow-hidden">
                <div class="absolute -right-12 -bottom-12 w-64 h-64 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

                <div>
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-blue-500/10 border border-blue-500/20 text-blue-400 text-xs font-semibold mb-6">
                        <i class="fa-solid fa-bolt text-[10px]"></i> v2.4 - Registration Portal
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-bold tracking-tight mb-3 text-white">
                        Bergabung dengan Portal Analitik.
                    </h2>
                    <p class="text-slate-400 text-sm leading-relaxed">
                        Daftarkan kredensial akun universitas Anda untuk mengakses dasbor analitik pembelajaran dan manajemen akademik terpadu.
                    </p>
                </div>

                <div class="space-y-3 mt-8">
                    <div class="bg-slate-800/60 backdrop-blur-md border border-slate-700/50 p-4 rounded-2xl flex items-center gap-3">
                        <div class="bg-blue-600/20 text-blue-400 p-2.5 rounded-xl">
                            <i class="fa-solid fa-shield-halved text-sm"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-semibold text-slate-200">Keamanan Enkripsi SSO</h4>
                            <p class="text-[11px] text-slate-400">Dilindungi standar enkripsi 256-bit</p>
                        </div>
                    </div>

                    <div class="bg-slate-800/60 backdrop-blur-md border border-slate-700/50 p-4 rounded-2xl flex items-center gap-3">
                        <div class="bg-indigo-600/20 text-indigo-400 p-2.5 rounded-xl">
                            <i class="fa-solid fa-building-columns text-sm"></i>
                        </div>
                        <div>
                            <h4 class="text-xs font-semibold text-slate-200">Akademik Terakreditasi</h4>
                            <p class="text-[11px] text-slate-400">Integrasi langsung sistem kampus</p>
                        </div>
                    </div>
                </div>

                <div class="pt-6 border-t border-slate-800/80 flex items-center justify-between text-xs text-slate-400 font-medium">
                    <span>Enkripsi SSO 256-Bit</span>
                    <span>Universitas Terakreditasi Unggul</span>
                </div>
            </div>

            <!-- Right Form Column -->
            <div class="lg:col-span-7 p-8 sm:p-10 flex flex-col justify-center">
                <div class="mb-6">
                    <h3 class="text-xl sm:text-2xl font-bold text-slate-900 tracking-tight">Pendaftaran Akun Baru</h3>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1">Lengkapi data diri dan pilih peran akses Anda di sistem.</p>
                </div>

                <!-- Menampilkan Error Validasi Laravel jika ada -->
                @if ($errors->any())
                    <div class="mb-6 p-4 rounded-xl text-xs sm:text-sm font-medium bg-rose-50 border border-rose-200 text-rose-800">
                        <ul class="list-disc pl-4 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Form Register Terhubung ke Route Laravel -->
                <form action="{{ route('register') }}" method="POST" class="space-y-4">
                    @csrf

                    <!-- Input Nama Lengkap (name="nama" sesuai Controller dan Model) -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nama Lengkap & Gelar</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                                <i class="fa-regular fa-user text-sm"></i>
                            </span>
                            <input type="text" name="nama" id="nama" value="{{ old('nama') }}" required placeholder="Cth: Dr. Budi Santoso, M.Kom."
                                class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition placeholder:text-slate-400">
                        </div>
                    </div>

                    <!-- Input Email Universitas (name="email") -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Email Universitas / NIP</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                                <i class="fa-regular fa-envelope text-sm"></i>
                            </span>
                            <input type="email" name="email" id="email" value="{{ old('email') }}" required placeholder="nama@dosen.univ.ac.id"
                                class="w-full pl-10 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition placeholder:text-slate-400">
                        </div>
                    </div>

                    <!-- Input Password (name="password") -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Kata Sandi</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
                                <i class="fa-solid fa-key text-sm"></i>
                            </span>
                            <input type="password" name="password" id="password" required placeholder="••••••••••••"
                                class="w-full pl-10 pr-12 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-600/20 focus:border-blue-600 transition placeholder:text-slate-400">
                            <button type="button" onclick="togglePassword()" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none">
                                <i id="toggleIcon" class="fa-regular fa-eye text-sm"></i>
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Gunakan minimal 6 karakter kombinasi huruf dan angka.</p>
                    </div>

                    <!-- Pilihan Role (Dosen, Kaprodi, Admin) -->
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Pilih Peran (Role)</label>
                        <div class="grid grid-cols-3 gap-3">
                            <!-- Role Dosen -->
                            <label class="cursor-pointer relative">
                                <input type="radio" name="role" value="dosen" checked class="peer sr-only">
                                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-center transition-all peer-checked:bg-blue-50 peer-checked:border-blue-600 peer-checked:text-blue-700 hover:bg-slate-100 flex flex-col items-center justify-center gap-1.5">
                                    <i class="fa-solid fa-chalkboard-user text-base text-slate-500 peer-checked:text-blue-600"></i>
                                    <span class="text-xs font-semibold">Dosen</span>
                                </div>
                            </label>

                            <!-- Role Kaprodi -->
                            <label class="cursor-pointer relative">
                                <input type="radio" name="role" value="kaprodi" class="peer sr-only">
                                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-center transition-all peer-checked:bg-blue-50 peer-checked:border-blue-600 peer-checked:text-blue-700 hover:bg-slate-100 flex flex-col items-center justify-center gap-1.5">
                                    <i class="fa-solid fa-user-tie text-base text-slate-500 peer-checked:text-blue-600"></i>
                                    <span class="text-xs font-semibold">Kaprodi</span>
                                </div>
                            </label>

                            <!-- Role Admin -->
                            <label class="cursor-pointer relative">
                                <input type="radio" name="role" value="admin" class="peer sr-only">
                                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl text-center transition-all peer-checked:bg-blue-50 peer-checked:border-blue-600 peer-checked:text-blue-700 hover:bg-slate-100 flex flex-col items-center justify-center gap-1.5">
                                    <i class="fa-solid fa-user-shield text-base text-slate-500 peer-checked:text-blue-600"></i>
                                    <span class="text-xs font-semibold">Admin</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3.5 px-4 rounded-xl shadow-lg shadow-blue-600/25 transition flex items-center justify-center gap-2 text-sm group">
                            <span>Daftarkan Akun Sekarang</span>
                            <i class="fa-solid fa-arrow-right-long text-xs group-hover:translate-x-1 transition-transform"></i>
                        </button>
                    </div>

                    <!-- Footer Link Login -->
                    <div class="text-center pt-2">
                        <p class="text-xs text-slate-500">
                            Sudah memiliki akun?
                            <a href="{{ route('login') }}" class="font-semibold text-blue-600 hover:text-blue-700 hover:underline">Masuk ke Halaman Login</a>
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Footer Copyright -->
    <footer class="w-full py-4 text-center text-xs text-slate-400 border-t border-slate-200 bg-white">
        © 2026 Smart Learning Analytics • Fakultas Ilmu Komputer. Hak Cipta Dilindungi Undang-Undang.
    </footer>

    <!-- JavaScript Interactivity -->
    <script>
        // Toggle Password Visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
