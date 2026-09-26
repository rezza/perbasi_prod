<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'PERBASI Kabupaten Pemalang')</title>
    <meta name="description" content="Persatuan Bola Basket Seluruh Indonesia (PERBASI) Kabupaten Pemalang - Membangun Prestasi Basket Pemalang">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                    },
                    colors: {
                        navy: {
                            900: '#0b1e3f',
                            800: '#13294b',
                            700: '#1e3a8a',
                            600: '#1e40af',
                        },
                        brandred: {
                            600: '#dc2626',
                            700: '#b91c1c',
                        }
                    }
                }
            }
        }
    </script>
    

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        body { font-family: 'Inter', sans-serif; }
        .hero-bg {
            background-image: linear-gradient(rgba(11,30,63,0.85), rgba(11,30,63,0.6)), url('https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
        }
        .section-bg {
            background-image: linear-gradient(rgba(11,30,63,0.92), rgba(11,30,63,0.92)), url('https://images.unsplash.com/photo-1504450758481-7338eba7524a?auto=format&fit=crop&w=1920&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        .card-hover { transition: all .3s ease; }
        .card-hover:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(0,0,0,0.15); }
        .nav-link { position: relative; }
        .nav-link::after {
            content: ''; position: absolute; bottom: -4px; left: 0;
            width: 0; height: 2px; background: #dc2626; transition: width .3s;
        }
        .nav-link:hover::after, .nav-link.active::after { width: 100%; }
    </style>
    @stack('head')
</head>
<body class="bg-gray-50 text-gray-800 flex flex-col min-h-screen">

    {{-- ==================== NAVBAR ==================== --}}
    <nav id="mainNav" class="text-white fixed top-0 left-0 right-0 z-50 transition-all duration-300">
        <div class="max-w-7xl mx-auto lg:px-4">
            <div class="flex justify-between px-4 items-center h-16">
                <a href="{{ route('compro.dashboard') }}" class="flex items-center gap-3">
                    <img src="{{ asset('logo.png') }}" alt="Logo PERBASI" class="w-10 h-10 object-contain">
                    <div class="leading-tight">
                        <div class="font-extrabold text-lg tracking-wide">PERBASI</div>
                        <div class="text-[10px] text-gray-300 -mt-1">KABUPATEN PEMALANG</div>
                    </div>
                </a>

                <div class="hidden lg:flex items-center gap-8 text-sm font-medium">
                    <a href="{{ route('compro.dashboard') }}" class="nav-link {{ request()->routeIs('compro.dashboard') ? 'active text-brandred-600' : 'hover:text-brandred-600' }}">Beranda</a>
                    <div class="relative group">
                        <button class="nav-link flex items-center gap-1 {{ request()->routeIs('compro.tentang', 'compro.visi-misi.detail', 'compro.perkumpulan', 'compro.perkumpulan.detail', 'compro.daftar-pemain', 'compro.daftar-pengurus') ? 'active text-brandred-600' : 'hover:text-brandred-600' }}">
                            Tentang Kami <i class="fas fa-chevron-down text-[10px] mt-0.5"></i>
                        </button>
                        <div class="absolute top-full left-0 mt-2 w-52 bg-navy-900 rounded-xl shadow-xl overflow-hidden opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <a href="{{ route('compro.tentang') }}" class="block px-4 py-3 text-sm text-gray-200 hover:bg-white/10 hover:text-white {{ request()->routeIs('compro.tentang', 'compro.visi-misi.detail') ? 'text-brandred-400' : '' }}">
                                <i class="fas fa-building mr-2 text-xs opacity-60 w-4"></i> Perbasi Pemalang
                            </a>
                            <a href="{{ route('compro.perkumpulan') }}" class="block px-4 py-3 text-sm text-gray-200 hover:bg-white/10 hover:text-white {{ request()->routeIs('compro.perkumpulan', 'compro.perkumpulan.detail') ? 'text-brandred-400' : '' }}">
                                <i class="fas fa-shield-halved mr-2 text-xs opacity-60 w-4"></i> Perkumpulan
                            </a>
                            <a href="{{ route('compro.daftar-pemain') }}" class="block px-4 py-3 text-sm text-gray-200 hover:bg-white/10 hover:text-white {{ request()->routeIs('compro.daftar-pemain') ? 'text-brandred-400' : '' }}">
                                <i class="fas fa-users mr-2 text-xs opacity-60 w-4"></i> Daftar Pemain
                            </a>
                            <a href="{{ route('compro.daftar-pengurus') }}" class="block px-4 py-3 text-sm text-gray-200 hover:bg-white/10 hover:text-white {{ request()->routeIs('compro.daftar-pengurus') ? 'text-brandred-400' : '' }}">
                                <i class="fas fa-user-tie mr-2 text-xs opacity-60 w-4"></i> Daftar SDM
                            </a>
                        </div>
                    </div>
                    <a href="{{ route('compro.agenda') }}" class="nav-link {{ request()->routeIs('compro.agenda', 'compro.agenda.detail') ? 'active text-brandred-600' : 'hover:text-brandred-600' }}">Agenda &amp; Jadwal</a>
                    <a href="{{ route('compro.sponsor') }}" class="nav-link {{ request()->routeIs('compro.sponsor', 'compro.sponsor.detail') ? 'active text-brandred-600' : 'hover:text-brandred-600' }}">Sponsor &amp; Mitra</a>
                    <a href="{{ route('compro.berita') }}" class="nav-link {{ request()->routeIs('compro.berita', 'compro.berita.detail') ? 'active text-brandred-600' : 'hover:text-brandred-600' }}">Berita &amp; Artikel</a>
                    @if(session('jwt_token'))
                        <div class="relative" id="userMenuWrap">
                            <button type="button" id="userMenuBtn" class="nav-link flex items-center gap-1.5 hover:text-brandred-600">
                                <i class="fas fa-circle-user text-base"></i>
                                <span class="max-w-[140px] truncate">{{ $comproUserName ?? 'Member' }}</span>
                                <i class="fas fa-chevron-down text-[10px] mt-0.5"></i>
                            </button>
                            <div id="userMenuDropdown" class="hidden absolute top-full right-0 mt-2 w-52 bg-navy-900 rounded-xl shadow-xl overflow-hidden z-50">
                                <a href="{{ route('compro.sso.member') }}" target="_blank" rel="noopener noreferrer" class="block px-4 py-3 text-sm text-gray-200 hover:bg-white/10 hover:text-white">
                                    <i class="fas fa-gauge mr-2 text-xs opacity-60 w-4"></i> Dashboard
                                </a>
                                <form method="POST" action="{{ route('compro.logout') }}">
                                    @csrf
                                    <button type="submit" class="block w-full text-left px-4 py-3 text-sm text-gray-200 hover:bg-white/10 hover:text-white">
                                        <i class="fas fa-right-from-bracket mr-2 text-xs opacity-60 w-4"></i> Keluar
                                    </button>
                                </form>
                            </div>
                        </div>
                    @else
                        <a href="https://member.perbasipemalang.org" target="_blank" rel="noopener noreferrer" class="nav-link hover:text-brandred-600">Login Member</a>
                        <a href="{{ route('compro.login') }}" class="nav-link {{ request()->routeIs('compro.login', 'compro.register') ? 'active text-brandred-600' : 'hover:text-brandred-600' }}">Masuk</a>
                    @endif
                </div>

                <button id="mobileBtn" class="lg:hidden text-white text-xl">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            <div id="mobileMenu" class="hidden lg:hidden pb-4 space-y-2 text-sm bg-navy-900 px-4">
                <a href="{{ route('compro.dashboard') }}" class="block py-2 hover:text-brandred-600">Beranda</a>
                <div class="py-2">
                    <div class="font-semibold text-gray-300 mb-1">Tentang Kami</div>
                    <a href="{{ route('compro.tentang') }}" class="block py-1 pl-4 hover:text-brandred-600 {{ request()->routeIs('compro.tentang', 'compro.visi-misi.detail') ? 'text-brandred-600' : '' }}">Perbasi Pemalang</a>
                    <a href="{{ route('compro.perkumpulan') }}" class="block py-1 pl-4 hover:text-brandred-600 {{ request()->routeIs('compro.perkumpulan') ? 'text-brandred-600' : '' }}">Perkumpulan</a>
                    <a href="{{ route('compro.daftar-pemain') }}" class="block py-1 pl-4 hover:text-brandred-600 {{ request()->routeIs('compro.daftar-pemain') ? 'text-brandred-600' : '' }}">Daftar Pemain</a>
                    <a href="{{ route('compro.daftar-pengurus') }}" class="block py-1 pl-4 hover:text-brandred-600 {{ request()->routeIs('compro.daftar-pengurus') ? 'text-brandred-600' : '' }}">Daftar SDM</a>
                </div>
                <a href="{{ route('compro.agenda') }}" class="block py-2 hover:text-brandred-600">Agenda &amp; Jadwal</a>
                <a href="{{ route('compro.sponsor') }}" class="block py-2 hover:text-brandred-600">Sponsor &amp; Mitra</a>
                <a href="{{ route('compro.berita') }}" class="block py-2 hover:text-brandred-600">Berita &amp; Artikel</a>
                @if(session('jwt_token'))
                    <div class="py-2 mt-2 border-t border-white/10 pt-3">
                        <div class="flex items-center gap-2 text-gray-300 font-semibold mb-2">
                            <i class="fas fa-circle-user"></i> {{ $comproUserName ?? 'Member' }}
                        </div>
                        <a href="{{ route('compro.sso.member') }}" target="_blank" rel="noopener noreferrer" class="block py-1 pl-6 hover:text-brandred-600">Dashboard</a>
                        <form method="POST" action="{{ route('compro.logout') }}">
                            @csrf
                            <button type="submit" class="block py-1 pl-6 hover:text-brandred-600 text-left w-full">Keluar</button>
                        </form>
                    </div>
                @else
                    <a href="https://member.perbasipemalang.org" target="_blank" rel="noopener noreferrer" class="block py-2 hover:text-brandred-600">Login Member</a>
                    <a href="{{ route('compro.login') }}" class="block py-2 hover:text-brandred-600">Masuk</a>
                @endif
            </div>
        </div>
    </nav>

    {{-- ==================== CONTENT ==================== --}}
    <main class="flex-1">
        @if(session('success') || session('info') || session('error') || $errors->any())
        <div class=" bg-navy-900 w-full py-4">
            <div class="max-w-3xl mx-auto px-4 pt-24">
                @if(session('success'))
                    <div class="bg-green-50 text-green-700 border border-green-200 rounded-lg px-4 py-3 mb-4 text-sm">{{ session('success') }}</div>
                @endif
                @if(session('info'))
                    <div class="bg-blue-50 text-blue-700 border border-blue-200 rounded-lg px-4 py-3 mb-4 text-sm">{{ session('info') }}</div>
                @endif
                @if(session('error'))
                    <div class="bg-red-50 text-red-700 border border-red-200 rounded-lg px-4 py-3 mb-4 text-sm">{{ session('error') }}</div>
                @endif
                @if($errors->any())
                    <div class="bg-red-50 text-red-700 border border-red-200 rounded-lg px-4 py-3 mb-4 text-sm">
                        <ul class="list-disc list-inside">
                            @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                        </ul>
                    </div>
                @endif
            </div>
            </div>
        @endif
        @yield('content')
    </main>

    {{-- ==================== FOOTER ==================== --}}
    <footer class="bg-navy-900 text-gray-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 grid md:grid-cols-4 gap-8">
            <div>
                <div class="flex items-center gap-3 mb-4">
                    <img src="{{ asset('logo.png') }}" alt="Logo PERBASI" class="w-12 h-12 object-contain">
                    <div>
                        <div class="font-extrabold text-white text-lg">PERBASI</div>
                        <div class="text-xs text-gray-400">Kabupaten Pemalang</div>
                    </div>
                </div>
                <p class="text-sm text-gray-400">Persatuan Bola Basket Seluruh Indonesia Kabupaten Pemalang. Membangun prestasi basket Pemalang.</p>
            </div>
            <div>
                <h4 class="font-bold text-white mb-4">Navigasi</h4>
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('compro.dashboard') }}" class="hover:text-white">Beranda</a></li>
                    <li><a href="{{ route('compro.tentang') }}" class="hover:text-white">Tentang Kami</a></li>
                    <li><a href="{{ route('compro.agenda') }}" class="hover:text-white">Agenda</a></li>
                    <li><a href="{{ route('compro.sponsor') }}" class="hover:text-white">Sponsor</a></li>
                    <li><a href="{{ route('compro.berita') }}" class="hover:text-white">Berita</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold text-white mb-4">Kontak</h4>
                <ul class="space-y-2 text-sm">
                    <li><i class="fas fa-map-marker-alt mr-2 text-brandred-600"></i> JL. Sumbing I No. 16 Kel. Mulyoharjo Kec. Pemalang, Kab. Pemalang 52313</li>
                    <li><i class="fas fa-phone mr-2 text-brandred-600"></i> 0812 2555 3234</li>
                    <li><i class="fas fa-envelope mr-2 text-brandred-600"></i> perbasipml@gmail.com</li>
                </ul>
            </div>
            <div>
                <h4 class="font-bold text-white mb-4">Ikuti Kami</h4>
                <div class="flex gap-3">
                    <a href="https://www.instagram.com/perbasikabupatenpemalang/" target="_blank" rel="noopener noreferrer" class="w-10 h-10 bg-navy-700 hover:bg-brandred-600 rounded-full flex items-center justify-center transition"><i class="fab fa-instagram"></i></a>
                    <a href="https://www.youtube.com/@PemalangBasketball" target="_blank" rel="noopener noreferrer" class="w-10 h-10 bg-navy-700 hover:bg-brandred-600 rounded-full flex items-center justify-center transition"><i class="fab fa-youtube"></i></a>
                    <a href="https://www.tiktok.com/@PERBASI_Pemalang" target="_blank" rel="noopener noreferrer" class="w-10 h-10 bg-navy-700 hover:bg-brandred-600 rounded-full flex items-center justify-center transition"><i class="fab fa-tiktok"></i></a>
                </div>
            </div>
        </div>
        <div class="border-t border-navy-800 py-4 text-center text-xs text-gray-400">
            &copy; {{ date('Y') }} PERBASI Kabupaten Pemalang. All rights reserved.
        </div>
    </footer>

    <script>
        document.getElementById('mobileBtn')?.addEventListener('click', () => {
            document.getElementById('mobileMenu').classList.toggle('hidden');
        });

        (function () {
            const btn = document.getElementById('userMenuBtn');
            const dropdown = document.getElementById('userMenuDropdown');
            if (!btn || !dropdown) return;

            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                dropdown.classList.toggle('hidden');
            });
            document.addEventListener('click', function (e) {
                if (!dropdown.classList.contains('hidden') && !dropdown.contains(e.target) && e.target !== btn) {
                    dropdown.classList.add('hidden');
                }
            });
        })();

        (function () {
            const nav = document.getElementById('mainNav');
            function updateNav() {
                if (window.scrollY > 10) {
                    nav.style.backgroundColor = '#0b1e3f';
                    nav.style.boxShadow = '0 4px 20px rgba(0,0,0,0.3)';
                } else {
                    nav.style.backgroundColor = 'transparent';
                    nav.style.boxShadow = 'none';
                }
            }
            updateNav();
            window.addEventListener('scroll', updateNav, { passive: true });
        })();
    </script>
    @stack('scripts')
</body>
</html>
