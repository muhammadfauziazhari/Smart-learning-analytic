<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-950">
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
                }
            }
        }
    </script>
</head>
<body class="h-full font-sans text-slate-100 antialiased flex overflow-hidden">

    <!-- SIDEBAR, diisi oleh layout per role (admin/dosen/kaprodi) -->
    @yield('sidebar')

    <!-- MAIN CONTENT WRAPPER -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-950">
        <!-- Top Header -->
        <header class="h-16 bg-slate-900/50 backdrop-blur-md border-b border-slate-800 flex items-center justify-between px-6 z-20">
            <!-- Tombol Toggle Sidebar & Judul Halaman Aktif -->
            <div class="flex items-center space-x-4">
                <button onclick="toggleSidebar()" class="text-slate-400 hover:text-white transition-colors focus:outline-none">
                    <i class="fa-solid fa-bars text-lg"></i>
                </button>
                <h1 id="page-title" class="text-base font-semibold text-white tracking-wide">@yield('page-title', 'Dashboard')</h1>
            </div>

            <!-- Header Right Actions -->
            <div class="flex items-center space-x-4">
                <button class="w-9 h-9 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-center text-slate-400 hover:text-white hover:bg-slate-800 transition-all">
                    <i class="fa-regular fa-bell text-sm"></i>
                </button>
                <div class="h-6 w-px bg-slate-800"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-xs font-medium text-red-400 hover:text-red-300 transition-colors flex items-center space-x-2">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        <span class="hidden sm:inline">Keluar</span>
                    </button>
                </form>
            </div>
        </header>

        <!-- Dynamic Content Area -->
        <main class="flex-1 overflow-y-auto p-6 bg-slate-950">
            @yield('content')
        </main>
    </div>

    <!-- JavaScript untuk Interaksi Sidebar -->
    <script>
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

    <!-- JavaScript khusus halaman -->
    @yield('scripts')

    <!-- JavaScript dari component/partial -->
    @stack('upload-log-script')
    @stack('flash-alert-script')
</body>
</html>