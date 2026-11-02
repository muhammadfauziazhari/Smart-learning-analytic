{{--
    Token desain bersama EduTrack.

    Dimuat oleh layouts/base.blade.php (semua halaman setelah login) dan
    Auth/Login.blade.php. Jangan mendefinisikan ulang tailwind.config atau
    memuat font lain di halaman lain; cukup @include partial ini supaya
    palet dan tipografi tetap satu sumber.

    Palet resmi:
      navy   #0B1B3D  - teks utama, sidebar, permukaan gelap
      royal  #0F3C8A  - aksi utama (tombol), tautan, grafik
      amber  #EBA83A  - aksen, menu aktif, sorotan
      ivory  #F8F7F5  - latar halaman
    Warna pendukung (hanya untuk permukaan lembut/grafik):
      cream #FAEBD7, sapphire #516AC8
--}}
<script src="https://cdn.tailwindcss.com"></script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<script>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: {
                    sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                },
                colors: {
                    navy: '#0B1B3D',
                    royal: '#0F3C8A',
                    // `amber` tetap berupa skala supaya kelas bawaan
                    // (amber-50/100/.../600) untuk badge peringatan tidak
                    // hilang; `bg-amber`/`text-amber` memakai DEFAULT.
                    amber: {
                        DEFAULT: '#EBA83A',
                        50: '#FDF6E7',
                        100: '#FAEBD7',
                        200: '#F6DDB4',
                        300: '#F2CD8A',
                        400: '#EBA83A',
                        500: '#D8942A',
                        600: '#B87A1E',
                        700: '#8F5C13',
                        800: '#6B440E',
                        900: '#4A2E09',
                    },
                    ivory: '#F8F7F5',
                    cream: '#FAEBD7',
                    sapphire: '#516AC8',
                },
            },
        },
    };
</script>
