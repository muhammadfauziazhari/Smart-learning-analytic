<!DOCTYPE html>
<html lang="id" class="h-full bg-ivory">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'EduTrack')</title>
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
                    // Palet resmi EduTrack (dipakai halaman login dan
                    // menjadi acuan halaman frontend berikutnya).
                    colors: {
                        navy: '#0F1939',
                        royal: '#26428B',
                        sapphire: '#516AC8',
                        gold: '#E3AF64',
                        cream: '#FAEBD7',
                        ivory: '#F8F7F5'
                    }
                }
            }
        }
    </script>
</head>
<body class="h-full bg-ivory font-sans text-navy antialiased flex overflow-hidden">

    <!-- SIDEBAR, diisi oleh layout per role (admin/dosen/kaprodi) -->
    @yield('sidebar')

    <!-- Latar gelap saat sidebar dibuka di layar kecil -->
    <div id="sidebarOverlay"
        class="fixed inset-0 z-30 hidden bg-navy/40 backdrop-blur-[1px] lg:hidden"
        onclick="closeSidebar()"></div>

    <!-- MAIN CONTENT WRAPPER -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-ivory">
        <!-- Top Header -->
        <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 sm:px-6 z-20">
            <!-- Tombol Toggle Sidebar & Judul Halaman Aktif -->
            <div class="flex items-center gap-3 min-w-0">
                <button type="button" onclick="toggleSidebar()"
                    class="w-9 h-9 rounded-xl border border-slate-200 flex items-center justify-center text-slate-500 hover:text-navy hover:bg-ivory transition-colors focus:outline-none"
                    aria-label="Buka atau tutup menu">
                    <i class="fa-solid fa-bars text-sm"></i>
                </button>
                <h1 id="page-title" class="text-base font-semibold text-navy tracking-wide truncate">@yield('page-title', 'Dashboard')</h1>
            </div>

            <!-- Header Right Actions -->
            <div class="flex items-center gap-3">
                <button type="button"
                    class="w-9 h-9 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:text-navy hover:bg-ivory transition-colors">
                    <i class="fa-regular fa-bell text-sm"></i>
                </button>
                <div class="h-6 w-px bg-slate-200"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-700 transition-colors flex items-center gap-2">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        <span class="hidden sm:inline">Keluar</span>
                    </button>
                </form>
            </div>
        </header>

        <!-- Dynamic Content Area -->
        <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-7 bg-ivory">
            <div class="mx-auto w-full max-w-[1600px]">
                @yield('content')
            </div>
        </main>
    </div>

    <style>
        /* Di layar kecil sidebar menjadi panel geser, bukan mengecilkan konten. */
        @media (max-width: 1023px) {
            #sidebar {
                position: fixed;
                inset-block: 0;
                left: 0;
                z-index: 40;
                transform: translateX(-100%);
            }

            #sidebar.sidebar-open {
                transform: translateX(0);
            }
        }
    </style>

    <!-- JavaScript untuk Interaksi Sidebar -->
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (!sidebar) return;

            // Mobile: buka/tutup panel geser beserta lapisan gelapnya.
            if (window.matchMedia('(max-width: 1023px)').matches) {
                const terbuka = sidebar.classList.toggle('sidebar-open');
                document.getElementById('sidebarOverlay')?.classList.toggle('hidden', !terbuka);
                return;
            }

            // Desktop: perkecil sidebar menjadi ikon saja.
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

        function closeSidebar() {
            document.getElementById('sidebar')?.classList.remove('sidebar-open');
            document.getElementById('sidebarOverlay')?.classList.add('hidden');
        }

        // Menu yang dipilih di mobile langsung menutup panel.
        document.querySelectorAll('#sidebar .nav-item').forEach((item) => {
            item.addEventListener('click', () => {
                if (window.innerWidth < 1024) closeSidebar();
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closeSidebar();
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024) {
                closeSidebar();
                return;
            }

            // Kembali ke lebar penuh kalau sebelumnya diciutkan di desktop.
            const sidebar = document.getElementById('sidebar');
            sidebar?.classList.remove('w-20');
            sidebar?.classList.add('w-64');
            document.querySelectorAll('.sidebar-text').forEach(el => el.classList.remove('hidden'));
        });
    </script>

    <!-- JavaScript khusus halaman -->
    @yield('scripts')

    <!-- JavaScript dari component/partial -->
    @stack('upload-log-script')
    @stack('flash-alert-script')
</body>
</html>