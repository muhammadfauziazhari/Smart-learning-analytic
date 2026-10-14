<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Dosen - EduTrack</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome untuk Icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                }
            }
        }
    </script>
</head>
<body class="h-full font-sans text-slate-100 antialiased flex overflow-hidden">

    <!-- SIDEBAR -->
    <aside id="sidebar" class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col transition-all duration-300 z-30">
        <!-- Sidebar Header / Logo -->
        <div class="h-16 flex items-center justify-between px-6 border-b border-slate-800">
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-indigo-600 flex items-center justify-center text-white font-bold shadow-lg shadow-indigo-500/20">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <span class="font-bold text-lg tracking-wide text-white sidebar-text">EduTrack</span>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 px-4 py-6 space-y-1.5 overflow-y-auto">
            <!-- Menu Dashboard -->
            <button onclick="switchTab('dashboard')" class="nav-item w-full flex items-center space-x-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 bg-indigo-600 text-white shadow-lg shadow-indigo-600/20" data-target="dashboard">
                <i class="fa-solid fa-house-chimney w-5 text-center"></i>
                <span class="sidebar-text">Dashboard</span>
            </button>

            <!-- Menu Keaktifan -->
            <button onclick="switchTab('keaktifan')" class="nav-item w-full flex items-center space-x-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 text-slate-400 hover:text-white hover:bg-slate-800/60" data-target="keaktifan">
                <i class="fa-solid fa-chart-line w-5 text-center"></i>
                <span class="sidebar-text">Keaktifan</span>
            </button>

            <!-- Menu Pengumpulan Tugas -->
            <button onclick="switchTab('tugas')" class="nav-item w-full flex items-center space-x-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 text-slate-400 hover:text-white hover:bg-slate-800/60" data-target="tugas">
                <i class="fa-solid fa-folder-open w-5 text-center"></i>
                <span class="sidebar-text">Pengumpulan Tugas</span>
            </button>

            <!-- Menu EWS (Early Warning System) -->
            <button onclick="switchTab('ews')" class="nav-item w-full flex items-center space-x-3 px-4 py-3 rounded-xl text-sm font-medium transition-all duration-200 text-slate-400 hover:text-white hover:bg-slate-800/60" data-target="ews">
                <i class="fa-solid fa-triangle-exclamation w-5 text-center"></i>
                <span class="sidebar-text">EWS</span>
            </button>
        </nav>

        <!-- Sidebar Footer / Profile Dosen -->
        <div class="p-4 border-t border-slate-800">
            <div class="flex items-center space-x-3 px-3 py-2 rounded-xl bg-slate-800/50">
                <div class="w-9 h-9 rounded-full bg-slate-700 flex items-center justify-center font-semibold text-slate-300">
                    DR
                </div>
                <div class="sidebar-text overflow-hidden">
                    <p class="text-xs font-semibold text-white truncate">Dr. Dosen, M.Kom.</p>
                    <p class="text-[11px] text-slate-400 truncate">Dosen Pengampu</p>
                </div>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT WRAPPER -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-950">
        <!-- Top Header -->
        <header class="h-16 bg-slate-900/50 backdrop-blur-md border-b border-slate-800 flex items-center justify-between px-6 z-20">
            <!-- Tombol Toggle Sidebar & Judul Halaman Aktif -->
            <div class="flex items-center space-x-4">
                <button onclick="toggleSidebar()" class="text-slate-400 hover:text-white transition-colors focus:outline-none">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <h1 id="page-title" class="text-base font-semibold text-white tracking-wide">Dashboard</h1>
            </div>

            <!-- Header Right Actions -->
            <div class="flex items-center space-x-4">
                <button class="w-9 h-9 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-800 transition-all">
                    <i class="fa-regular fa-bell text-sm"></i>
                </button>
                <div class="h-6 w-px bg-slate-800"></div>
                <a href="#" class="text-xs font-medium text-red-400 hover:text-red-300 transition-colors flex items-center space-x-2">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                    <span class="hidden sm:inline">Keluar</span>
                </a>
            </div>
        </header>

        <!-- Dynamic Content Area -->
        <main class="flex-1 overflow-y-auto p-6 bg-slate-950">

            <!-- Konten: DASHBOARD -->
            <div id="content-dashboard" class="tab-content space-y-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-white">Ringkasan Dashboard</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Selamat datang kembali di panel kontrol akademik Anda.</p>
                    </div>
                </div>
                <!-- Isi dikosongkan dahulu -->
                <div class="border-2 border-dashed border-slate-800 rounded-2xl p-12 text-center text-slate-600">
                    <i class="fa-solid fa-hammer text-3xl mb-2"></i>
                    <p class="text-sm">Konten Dashboard akan ditampilkan di sini.</p>
                </div>
            </div>

            <!-- Konten: KEAKTIFAN -->
            <div id="content-keaktifan" class="tab-content space-y-6 hidden">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-white">Keaktifan Mahasiswa</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Pantau tingkat kehadiran dan partisipasi mahasiswa.</p>
                    </div>
                </div>
                <!-- Isi dikosongkan dahulu -->
                <div class="border-2 border-dashed border-slate-800 rounded-2xl p-12 text-center text-slate-600">
                    <i class="fa-solid fa-hammer text-3xl mb-2"></i>
                    <p class="text-sm">Konten Keaktifan akan ditampilkan di sini.</p>
                </div>
            </div>

            <!-- Konten: PENGUMPULAN TUGAS -->
            <div id="content-tugas" class="tab-content space-y-6 hidden">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-white">Pengumpulan Tugas</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Kelola dan nilai penugasan mahasiswa.</p>
                    </div>
                </div>
                <!-- Isi dikosongkan dahulu -->
                <div class="border-2 border-dashed border-slate-800 rounded-2xl p-12 text-center text-slate-600">
                    <i class="fa-solid fa-hammer text-3xl mb-2"></i>
                    <p class="text-sm">Konten Pengumpulan Tugas akan ditampilkan di sini.</p>
                </div>
            </div>

            <!-- Konten: EWS (Early Warning System) -->
            <div id="content-ews" class="tab-content space-y-6 hidden">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-white">Early Warning System (EWS)</h2>
                        <p class="text-xs text-slate-400 mt-0.5">Analisis peringatan dini performa akademik mahasiswa.</p>
                    </div>
                </div>
                <!-- Isi dikosongkan dahulu -->
                <div class="border-2 border-dashed border-slate-800 rounded-2xl p-12 text-center text-slate-600">
                    <i class="fa-solid fa-hammer text-3xl mb-2"></i>
                    <p class="text-sm">Konten EWS akan ditampilkan di sini.</p>
                </div>
            </div>

        </main>
    </div>

    <!-- JavaScript untuk Interaksi Sidebar & Tab Switcher -->
    <script>
        function switchTab(tabId) {
            // Sembunyikan semua konten
            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.add('hidden');
            });
            // Tampilkan konten yang dipilih
            document.getElementById('content-' + tabId).classList.remove('hidden');

            // Reset style semua tombol navigasi
            document.querySelectorAll('.nav-item').forEach(btn => {
                btn.classList.remove('bg-indigo-600', 'text-white', 'shadow-lg', 'shadow-indigo-600/20');
                btn.classList.add('text-slate-400', 'hover:text-white', 'hover:bg-slate-800/60');
            });

            // Aktifkan style tombol yang diklik
            const activeBtn = document.querySelector(`[data-target="${tabId}"]`);
            activeBtn.classList.add('bg-indigo-600', 'text-white', 'shadow-lg', 'shadow-indigo-600/20');
            activeBtn.classList.remove('text-slate-400', 'hover:text-white', 'hover:bg-slate-800/60');

            // Update judul di header
            const titles = {
                'dashboard': 'Dashboard',
                'keaktifan': 'Keaktifan Mahasiswa',
                'tugas': 'Pengumpulan Tugas',
                'ews': 'Early Warning System (EWS)'
            };
            document.getElementById('page-title').innerText = titles[tabId];
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const texts = document.querySelectorAll('.sidebar-text');

            if (sidebar.classList.contains('w-64')) {
                sidebar.classList.remove('w-64');
                sidebar.classList.add('w-20');
                texts.forEach(el => el.classList.add('hidden'));
            } else {
                sidebar.classList.remove('w-20');
                sidebar.classList.add('w-64');
                texts.forEach(el => el.classList.remove('hidden'));
            }
        }
    </script>
</body>
</html>
