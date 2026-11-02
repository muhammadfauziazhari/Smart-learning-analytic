<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EduTrack - Smart Learning Analytics Login</title>

  <!-- Token desain bersama: Tailwind CDN, Plus Jakarta Sans, palet EduTrack -->
  @include('partials.design-tokens')

  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      overflow-x: hidden;
    }
  </style>
</head>

<body class="w-screen h-screen overflow-hidden bg-ivory font-sans antialiased">

  <!-- Main Full Screen Container -->
  <div class="w-full h-full flex flex-col lg:flex-row overflow-hidden">

    <!-- ============================================================
         LEFT PANEL: Brand Info & Dashboard Mockup
         ============================================================ -->
    <div class="w-full lg:w-1/2 h-full bg-gradient-to-br from-[#08152F] via-[#0B1B3D] to-[#12356F] text-white p-8 lg:p-14 flex flex-col justify-between relative overflow-hidden select-none">

      <!-- Ambient Glow Effects -->
      <div class="absolute -bottom-20 -left-20 w-96 h-96 bg-royal/20 rounded-full blur-3xl pointer-events-none"></div>
      <div class="absolute top-1/3 -right-20 w-80 h-80 bg-amber/10 rounded-full blur-3xl pointer-events-none"></div>

      <!-- Brand Logo & Title -->
      <div class="relative z-10 flex items-center gap-3.5">
        <div class="w-11 h-11 bg-gradient-to-tr from-amber to-[#F3C06B] rounded-2xl flex items-center justify-center text-navy shadow-lg shadow-amber/20">
          <i class="fa-solid fa-graduation-cap text-2xl"></i>
        </div>
        <div>
          <h1 class="text-2xl font-extrabold tracking-tight text-white leading-none">
            Edu<span class="text-amber">Track</span>
          </h1>
          <p class="text-xs text-slate-400 font-medium tracking-wide mt-1">Smart Learning Analytics</p>
        </div>
      </div>

      <!-- Main Tagline -->
      <div class="relative z-10 my-auto py-6 max-w-lg">
        <h2 class="text-3xl lg:text-4xl font-extrabold leading-tight tracking-tight text-white mb-4">
          Pantau, Pahami,<br />Tingkatkan Pembelajaran
        </h2>
        <p class="text-slate-300 text-sm lg:text-base leading-relaxed font-normal opacity-90">
          EduTrack membantu perguruan tinggi mengelola data pembelajaran dan menganalisis aktivitas mahasiswa secara real-time.
        </p>
      </div>

      <!-- Dashboard Illustration Mockup -->
      <div class="relative z-10 w-full flex justify-center items-end pt-2">
        <div class="w-full max-w-md relative">

          <!-- Floating Badge -->
          <div class="absolute -top-6 -right-2 bg-gradient-to-r from-amber to-[#D8942A] p-2.5 rounded-2xl shadow-xl text-navy flex items-center gap-2 transform rotate-6 z-20">
            <i class="fa-solid fa-bolt text-xs"></i>
            <span class="text-[11px] font-bold tracking-wide">Real-time Analytics</span>
          </div>

          <!-- Decorative Circles -->
          <div class="absolute top-10 -left-6 w-5 h-5 rounded-full bg-amber/80 shadow-md"></div>
          <div class="absolute -bottom-2 left-10 w-8 h-8 rounded-full border-2 border-amber/40"></div>

          <!-- Dashboard Window -->
          <div class="bg-[#0E2249] rounded-2xl p-4 border border-white/10 shadow-2xl backdrop-blur-md relative z-10">

            <!-- Window Title Bar -->
            <div class="flex items-center gap-1.5 mb-3">
              <div class="w-2.5 h-2.5 rounded-full bg-rose-500/80"></div>
              <div class="w-2.5 h-2.5 rounded-full bg-amber/80"></div>
              <div class="w-2.5 h-2.5 rounded-full bg-emerald-500/80"></div>
              <div class="ml-2 h-2 w-20 bg-slate-700/60 rounded-full"></div>
            </div>

            <!-- Dashboard Layout -->
            <div class="grid grid-cols-3 gap-2.5">
              <div class="col-span-2 bg-white/5 rounded-xl p-3 border border-white/10">
                <div class="flex justify-between items-center mb-3">
                  <div class="h-2 w-16 bg-slate-400/60 rounded"></div>
                  <div class="h-2 w-8 bg-amber/60 rounded"></div>
                </div>
                <svg class="w-full h-20 text-sapphire overflow-visible" viewBox="0 0 120 40">
                  <defs>
                    <linearGradient id="blue-gradient" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stop-color="#516AC8" />
                      <stop offset="100%" stop-color="#516AC8" stop-opacity="0" />
                    </linearGradient>
                  </defs>
                  <path d="M 0,35 Q 20,15 40,25 T 80,10 T 120,18" stroke="#516AC8" stroke-width="2.5" fill="none" stroke-linecap="round" />
                  <path d="M 0,35 Q 20,15 40,25 T 80,10 T 120,18 L 120,40 L 0,40 Z" fill="url(#blue-gradient)" fill-opacity="0.25" />
                  <circle cx="40" cy="25" r="2.5" fill="#EBA83A" />
                  <circle cx="80" cy="10" r="2.5" fill="#516AC8" />
                </svg>
              </div>

              <div class="col-span-1 bg-white/5 rounded-xl p-3 border border-white/10 flex flex-col justify-between">
                <div>
                  <div class="h-2 w-10 bg-slate-400/60 rounded mb-2"></div>
                  <div class="text-lg font-bold text-amber">98.4%</div>
                </div>
                <div class="space-y-1.5 mt-2">
                  <div class="h-1.5 w-full bg-slate-600/60 rounded"></div>
                  <div class="h-1.5 w-4/5 bg-slate-600/60 rounded"></div>
                  <div class="h-1.5 w-3/5 bg-slate-600/60 rounded"></div>
                </div>
              </div>
            </div>

            <div class="mt-2.5 bg-white/5 rounded-xl p-2.5 border border-white/10 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-lg bg-sapphire/20 flex items-center justify-center text-sapphire text-xs">
                  <i class="fa-solid fa-chart-pie"></i>
                </div>
                <div class="h-2 w-24 bg-slate-400/50 rounded"></div>
              </div>
              <div class="h-2 w-12 bg-amber/80 rounded-full"></div>
            </div>
          </div>

          <div class="w-28 h-2 bg-white/10 mx-auto rounded-b-md"></div>
          <div class="w-40 h-1 bg-white/5 mx-auto rounded-b-xl opacity-60"></div>
        </div>
      </div>
    </div>

    <!-- ============================================================
         RIGHT PANEL: Login Form
         ============================================================ -->
    <div class="w-full lg:w-1/2 h-full bg-ivory flex flex-col justify-center items-center p-6 sm:p-10 lg:p-16 overflow-y-auto">
      <div class="w-full max-w-md my-auto space-y-7">

        <!-- Header Text -->
        <div class="space-y-1.5">
          <h2 class="text-3xl font-extrabold text-navy tracking-tight">Selamat Datang!</h2>
          <p class="text-slate-500 text-sm font-medium">Silakan login untuk melanjutkan ke dashboard.</p>
        </div>

        <!-- Alert Status / Error Session -->
        @if (session('status'))
          <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium">
            {{ session('status') }}
          </div>
        @endif

        @if ($errors->any())
          <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm font-medium">
            <ul class="list-disc list-inside space-y-1">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <!-- Form Login Laravel -->
        <form id="loginForm" method="POST" action="{{ route('login') }}" class="space-y-5">
          @csrf

          <!-- Email / Username -->
          <div class="space-y-1.5">
            <label for="emailInput" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
              Email / Username
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                <i class="fa-regular fa-user text-base"></i>
              </div>
              <input
                id="emailInput"
                name="email"
                type="email"
                value="{{ old('email') }}"
                required
                placeholder="contoh@kampus.ac.id"
                class="w-full pl-11 pr-4 py-3.5 bg-white border border-slate-200 rounded-xl text-navy placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-royal focus:border-transparent transition-all shadow-sm">
            </div>
          </div>

          <!-- Password -->
          <div class="space-y-1.5">
            <label for="passwordInput" class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
              Password
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                <i class="fa-solid fa-lock text-base"></i>
              </div>
              <input
                id="passwordInput"
                name="password"
                type="password"
                required
                placeholder="Masukkan password"
                class="w-full pl-11 pr-11 py-3.5 bg-white border border-slate-200 rounded-xl text-navy placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-royal focus:border-transparent transition-all shadow-sm">
              <button
                type="button"
                onclick="togglePasswordVisibility()"
                class="absolute inset-y-0 right-0 pr-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors"
                aria-label="Toggle password visibility">
                <i id="eyeIcon" class="fa-regular fa-eye text-base"></i>
              </button>
            </div>
          </div>

          <!-- Remember Me & Forgot Password -->
          <div class="flex items-center justify-between text-xs pt-0.5">
            <label class="flex items-center gap-2 cursor-pointer select-none text-slate-600 font-medium">
              <input
                type="checkbox"
                name="remember"
                class="w-4 h-4 rounded border-slate-300 text-royal focus:ring-royal cursor-pointer">
              <span>Ingat saya</span>
            </label>
            <a href="#" class="font-bold text-royal hover:text-navy hover:underline transition-all">
              Lupa password?
            </a>
          </div>

          <!-- Submit Button -->
          <button
            type="submit"
            class="w-full bg-royal hover:bg-navy text-white font-semibold py-3.5 px-4 rounded-xl shadow-md hover:shadow-lg shadow-blue-950/20 transition-all flex items-center justify-center gap-2.5 text-sm transform active:scale-[0.99]">
            <span>Login</span>
            <i class="fa-solid fa-arrow-right text-xs"></i>
          </button>
        </form>

        <!-- Divider -->
        <div class="relative my-6">
          <div class="absolute inset-0 flex items-center">
            <div class="w-full border-t border-slate-200"></div>
          </div>
          <div class="relative flex justify-center text-xs">
            <span class="bg-ivory px-3 text-slate-400 font-medium">atau login sebagai</span>
          </div>
        </div>

        <!-- Quick Role Select -->
        <div class="grid grid-cols-3 gap-3">

          <!-- Dosen Role Card (Biru) -->
          <button
            type="button"
            onclick="selectRole('dosen')"
            class="relative group flex flex-col items-center justify-center p-4 bg-blue-600 border border-blue-600 rounded-2xl text-white shadow-md hover:bg-blue-700 hover:border-blue-700 hover:shadow-lg hover:shadow-blue-900/20 hover:scale-105 transition-all duration-200 cursor-pointer overflow-hidden">
            <div class="absolute top-0 inset-x-0 h-1 bg-white/70 opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="w-11 h-11 rounded-2xl bg-white/15 text-white group-hover:bg-white/25 flex items-center justify-center mb-2 shadow-sm transition-all duration-200">
              <i class="fa-solid fa-user-tie text-base"></i>
            </div>
            <span class="text-xs font-bold text-white transition-colors">Dosen</span>
          </button>

          <!-- Admin Role Card (Merah) -->
          <button
            type="button"
            onclick="selectRole('admin')"
            class="relative group flex flex-col items-center justify-center p-4 bg-red-600 border border-red-600 rounded-2xl text-white shadow-md hover:bg-red-700 hover:border-red-700 hover:shadow-lg hover:shadow-red-900/20 hover:scale-105 transition-all duration-200 cursor-pointer overflow-hidden">
            <div class="absolute top-0 inset-x-0 h-1 bg-white/70 opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="w-11 h-11 rounded-2xl bg-white/15 text-white group-hover:bg-white/25 flex items-center justify-center mb-2 shadow-sm transition-all duration-200">
              <i class="fa-solid fa-user-gear text-base"></i>
            </div>
            <span class="text-xs font-bold text-white transition-colors">Admin</span>
          </button>

          <!-- Kaprodi Role Card (Hijau) -->
          <button
            type="button"
            onclick="selectRole('kaprodi')"
            class="relative group flex flex-col items-center justify-center p-4 bg-green-600 border border-green-600 rounded-2xl text-white shadow-md hover:bg-green-700 hover:border-green-700 hover:shadow-lg hover:shadow-green-900/20 hover:scale-105 transition-all duration-200 cursor-pointer overflow-hidden">
            <div class="absolute top-0 inset-x-0 h-1 bg-white/70 opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <div class="w-11 h-11 rounded-2xl bg-white/15 text-white group-hover:bg-white/25 flex items-center justify-center mb-2 shadow-sm transition-all duration-200">
              <i class="fa-solid fa-graduation-cap text-base"></i>
            </div>
            <span class="text-xs font-bold text-white transition-colors">Kaprodi</span>
          </button>

        </div>

      </div>
    </div>
  </div>

  <!-- Interactive Logic -->
  <script>
    function togglePasswordVisibility() {
      const passwordInput = document.getElementById('passwordInput');
      const eyeIcon = document.getElementById('eyeIcon');
      const isHidden = passwordInput.type === 'password';

      passwordInput.type = isHidden ? 'text' : 'password';
      eyeIcon.className = isHidden ?
        'fa-regular fa-eye-slash text-base' :
        'fa-regular fa-eye text-base';
    }

    // Fungsi Pengisian Otomatis Sesuai Role
    function selectRole(roleKey) {
      const emailInput = document.getElementById('emailInput');
      const passwordInput = document.getElementById('passwordInput');

      const roleCredentials = {
        'dosen': {
          email: 'dosen@polibatam.ac.id',
          password: 'password123'
        },
        'admin': {
          email: 'admin@polibatam.ac.id',
          password: 'password123'
        },
        'kaprodi': {
          email: 'kaprodi@polibatam.ac.id',
          password: 'password123'
        }
      };

      if (roleCredentials[roleKey]) {
        emailInput.value = roleCredentials[roleKey].email;
        passwordInput.value = roleCredentials[roleKey].password;
      }
    }
  </script>

</body>

</html>
