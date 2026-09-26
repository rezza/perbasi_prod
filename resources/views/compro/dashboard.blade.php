@extends('compro.layout')

@section('title', 'Beranda - PERBASI Kabupaten Pemalang')

@section('content')

{{-- HERO CAROUSEL --}}
<section class="relative text-white overflow-hidden" id="heroCarousel" style="height: 100vh; min-height: 560px;">

    {{-- Background slides --}}
    @if(!empty($banners) && count($banners) > 0)
        @foreach($banners as $i => $banner)
        <div class="hero-slide absolute inset-0 transition-opacity duration-1000 ease-in-out {{ $i === 0 ? 'opacity-100' : 'opacity-0' }}"
             style="background-image: linear-gradient(rgba(11,30,63,0.80), rgba(11,30,63,0.55)), url('{{ !empty($banner->filename) ? asset('news/'.$banner->filename) : 'https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=1920&q=80' }}'); background-size: cover; background-position: center;">
        </div>
        @endforeach
    @else
        <div class="absolute inset-0" style="background-image: linear-gradient(rgba(11,30,63,0.85), rgba(11,30,63,0.6)), url('https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=1920&q=80'); background-size: cover; background-position: center;"></div>
    @endif

    {{-- Info event per slide (bottom of the hero, above the overlapping program cards) --}}
    @if(!empty($banners) && count($banners) > 0)
        @foreach($banners as $i => $banner)
        <div class="hero-info max-w-7xl {{ $i === 0 ? '' : 'hidden' }}">
            @include('compro.partials.event-info-bar', ['info' => $banner->pertandingan ?? null, 'size' => 'lg', 'pos' => 'absolute inset-x-0 bottom-20 md:bottom-24'])
        </div>
        @endforeach
    @endif

    {{-- Content --}}
    <div class="relative z-10 h-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-4 flex flex-col justify-center" style="padding-top: 80px; padding-bottom: 48px;">
        <div class="max-w-2xl">
            @if(!empty($banners) && count($banners) > 0)
                @foreach($banners as $i => $banner)
                <div class="hero-text {{ $i === 0 ? '' : 'hidden' }}">
                    <h1 class="text-3xl lg:text-6xl font-black leading-tight tracking-tight">{{ $banner->judul }}</h1>
                    <p class="mt-6 text-md lg:text-lg text-gray-200">{{ \Illuminate\Support\Str::limit(strip_tags($banner->konten), 180) }}</p>
                    <div class="mt-8 flex flex-wrap gap-4">
                        <a href="{{ route('compro.berita.detail', $banner->id) }}" class="bg-brandred-600 hover:bg-brandred-700 text-white px-6 py-3 rounded-lg font-semibold shadow-lg transition">
                            <i class="fas fa-newspaper mr-2"></i> Lihat Selengkapnya
                        </a>
                    </div>
                </div>
                @endforeach
            @else
                <h1 class="text-5xl lg:text-7xl font-black leading-tight tracking-tight">
                    PERBASI<br><span class="text-brandred-600">KABUPATEN PEMALANG</span>
                </h1>
                <p class="mt-6 text-lg text-gray-200">Membangun Prestasi Basket Pemalang melalui pembinaan usia dini, kompetisi rutin, dan pelatihan profesional.</p>
            @endif
        </div>

        {{-- Dot navigation --}}
        @if(!empty($banners) && count($banners) > 1)
        <div class="flex gap-2 mt-8" id="heroDots">
            @foreach($banners as $i => $banner)
            <button class="hero-dot transition-all duration-300 h-2 rounded-full {{ $i === 0 ? 'bg-white w-6' : 'bg-white/40 w-2' }}" data-index="{{ $i }}"></button>
            @endforeach
        </div>
        @endif
    </div>
</section>

{{-- 3 PROGRAM CARDS --}}
@if(!empty($programs) && count($programs) > 0)
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-4 -mt-16 relative z-10">
    <div class="grid md:grid-cols-{{ count($programs) === 1 ? '1 max-w-sm mx-auto' : (count($programs) === 2 ? '2 max-w-2xl mx-auto' : '3') }} gap-6">
        @foreach($programs as $i => $p)
        @php
            $accentColor = $i === 1 ? 'brandred-600' : 'navy-700';
            $ytId = null;
            if ($p->cover_type === 'youtube' && !empty($p->youtube_url)) {
                if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $p->youtube_url, $m)) $ytId = $m[1];
                elseif (preg_match('/[?&]v=([a-zA-Z0-9_-]+)/', $p->youtube_url, $m)) $ytId = $m[1];
            }
            $faIcon = \App\BannerProgram::$iconMap[$p->icon_name ?? ''] ?? 'fa-star';
        @endphp
        @php
            $cardTarget = route('compro.program.detail', $p->id);
            $cardRel    = '';
            $cardTarget2 = '_self';
        @endphp
        <a href="{{ $cardTarget }}" target="{{ $cardTarget2 }}" @if($cardRel) rel="{{ $cardRel }}" @endif
           class="bg-white rounded-2xl shadow-xl overflow-hidden card-hover block">
            {{-- Cover --}}
            @if($p->cover_type === 'image' && !empty($p->image_url))
                <img src="{{ asset('banner-program/' . $p->image_url) }}" class="h-40 w-full object-cover" alt="{{ $p->judul }}">
            @elseif($p->cover_type === 'youtube' && $ytId)
                <div class="h-40 relative overflow-hidden">
                    <img src="https://img.youtube.com/vi/{{ $ytId }}/mqdefault.jpg" class="w-full h-full object-cover" alt="{{ $p->judul }}">
                    <div class="absolute inset-0 bg-black/30 flex items-center justify-center">
                        <i class="fab fa-youtube text-5xl text-white/90"></i>
                    </div>
                </div>
            @else
                <div class="h-40 bg-gradient-to-br from-navy-800 to-navy-900 flex items-center justify-center">
                    <i class="fas {{ $faIcon }} text-6xl text-white/80"></i>
                </div>
            @endif
            <div class="bg-{{ $accentColor }} text-white text-center py-3 font-bold leading-snug px-3">{{ $p->judul }}</div>
            <div class="p-5 text-sm text-gray-600 text-center">{{ $p->short_description }}</div>
        </a>
        @endforeach
    </div>
</section>
@endif

{{-- STATS --}}
<section class="py-16 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-4">
        <div class="text-center mb-10">
            <h2 class="text-3xl font-extrabold text-navy-900">Tentang Aplikasi Beyond Perbasi</h2>
            <p class="text-gray-500 mt-2">Data dan statistik anggota PERBASI Kabupaten Pemalang</p>
        </div>
        @php
            $statItems = [
                ['icon' => 'fa-users',          'value' => $stats['jumlah_anggota_perbasi'], 'label' => 'Anggota Perbasi',  'url' => route('compro.perkumpulan') . '?kategori=Anggota+Perbasi'],
                ['icon' => 'fa-handshake',      'value' => $stats['jumlah_mitra_pembinaan'], 'label' => 'Mitra Pembinaan',  'url' => route('compro.perkumpulan') . '?kategori=Mitra+Pembinaan+Perbasi'],
                ['icon' => 'fa-shield-halved',  'value' => $stats['jumlah_mitra_binaan'],    'label' => 'Mitra Binaan',     'url' => route('compro.perkumpulan') . '?kategori=Mitra+Binaan+Perbasi'],
                ['icon' => 'fa-user-check',     'value' => $stats['jumlah_anggota'],         'label' => 'Pemain',          'url' => route('compro.daftar-pemain')],
                ['icon' => 'fa-user-tie',       'value' => $stats['jumlah_sdm'],             'label' => 'SDM',   'url' => route('compro.daftar-pengurus')],
                ['icon' => 'fa-calendar-check', 'value' => $stats['jumlah_event'],           'label' => 'Event Terdaftar',  'url' => route('compro.agenda')],
            ];
        @endphp
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-6 text-center">
            @foreach($statItems as $s)
            <a href="{{ $s['url'] }}" class="bg-white rounded-2xl shadow p-6 flex flex-col items-center gap-3 hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 group">
                <div class="w-14 h-14 bg-navy-700 group-hover:bg-brandred-600 rounded-full flex items-center justify-center text-white text-2xl shadow transition-colors duration-200">
                    <i class="fas {{ $s['icon'] }}"></i>
                </div>
                <div class="text-3xl font-extrabold text-brandred-600">{{ number_format($s['value']) }}</div>
                <div class="text-sm text-gray-600 leading-snug">{{ $s['label'] }}</div>
            </a>
            @endforeach
        </div>
    </div>
</section>

{{-- AGENDA TERDEKAT --}}
@if(!empty($upcomingAgenda) && count($upcomingAgenda) > 0)
<section class="bg-gray-100 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-4">
        <div class="flex justify-between items-end mb-8">
            <div>
                <h2 class="text-3xl font-extrabold text-navy-900">Agenda Mendatang</h2>
                <p class="text-gray-600 mt-1">Jadwal kegiatan terdekat</p>
            </div>
            <a href="{{ route('compro.agenda') }}" class="text-brandred-600 hover:underline text-sm font-semibold">Lihat Semua <i class="fas fa-arrow-right ml-1"></i></a>
        </div>
        <div class="grid md:grid-cols-3 gap-6">
            @foreach($upcomingAgenda as $ev)
            <a href="{{ route('compro.agenda.detail', $ev->id) }}" class="bg-white rounded-xl overflow-hidden shadow card-hover block">
                @if(!empty($ev->filename))
                    <div class="relative">
                        <img src="{{ asset('news/'.$ev->filename) }}" class="h-44 w-full object-cover" alt="{{ $ev->judul }}">
                        @include('compro.partials.event-info-bar', ['info' => $ev->pertandingan ?? null])
                    </div>
                @else
                    <div class="h-44 bg-gradient-to-br from-navy-700 to-navy-900 flex items-center justify-center">
                        <i class="fas fa-calendar-alt text-white text-5xl opacity-70"></i>
                    </div>
                @endif
                <div class="p-5">
                    <span class="inline-block bg-navy-700 text-white text-xs px-2 py-1 rounded">Agenda</span>
                    <h3 class="font-bold text-navy-900 mt-3 leading-snug">{{ $ev->judul }}</h3>
                    <div class="text-xs text-gray-500 mt-2"><i class="far fa-calendar mr-1"></i> {{ \Carbon\Carbon::parse($ev->tanggal)->translatedFormat('d M Y') }}</div>
                </div>
            </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- LATEST NEWS --}}
<section class="py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-4">
        <div class="flex justify-between items-end mb-8">
            <div>
                <h2 class="text-3xl font-extrabold text-navy-900">Berita Terbaru</h2>
                <p class="text-gray-600 mt-1">Update terbaru seputar basket Pemalang</p>
            </div>
            <a href="{{ route('compro.berita') }}" class="text-brandred-600 hover:underline text-sm font-semibold">Lihat Semua <i class="fas fa-arrow-right ml-1"></i></a>
        </div>

        @if(!empty($latestNews) && count($latestNews) > 0)
        <div class="grid md:grid-cols-3 gap-6">
            @foreach($latestNews as $n)
            <a href="{{ route('compro.berita.detail', $n->id) }}" class="bg-white rounded-xl overflow-hidden shadow card-hover block">
                @if(!empty($n->filename))
                    <div class="relative">
                        <img src="{{ asset('news/'.$n->filename) }}" class="h-44 w-full object-cover" alt="{{ $n->judul }}">
                        @include('compro.partials.event-info-bar', ['info' => $n->pertandingan ?? null])
                    </div>
                @else
                    <div class="h-44 bg-gradient-to-br from-navy-700 to-brandred-700 flex items-center justify-center">
                        <i class="fas fa-newspaper text-white text-5xl opacity-70"></i>
                    </div>
                @endif
                <div class="p-5">
                    <span class="inline-block bg-brandred-600 text-white text-xs px-2 py-1 rounded">Berita</span>
                    <h3 class="font-bold text-navy-900 mt-3 leading-snug">{{ $n->judul }}</h3>
                    <div class="text-xs text-gray-500 mt-2"><i class="far fa-calendar mr-1"></i> {{ \Carbon\Carbon::parse($n->tanggal)->translatedFormat('d M Y') }}</div>
                </div>
            </a>
            @endforeach
        </div>
        @else
        <div class="text-center py-12 text-gray-500">Belum ada berita.</div>
        @endif
    </div>
</section>

{{-- SPONSOR STRIP --}}
@if(!empty($sponsors) && count($sponsors) > 0)
<section class="bg-navy-200 py-12">
    <div class="max-w-7xl mx-auto px-4">
        <h3 class="text-center text-2xl font-bold mb-8">Didukung Oleh</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
            @foreach($sponsors as $sp)
            <a href="{{ route('compro.sponsor.detail', $sp->id) }}" class="bg-white rounded-lg overflow-hidden flex items-center justify-center h-64 hover:scale-105 transition">
                @if(!empty($sp->filename))
                    <img src="{{ asset('sponsor/'.$sp->filename) }}" alt="{{ $sp->nama }}" class="h-64 object-cover w-full"
                         onerror="this.outerHTML='<span class=\'text-navy-900 font-bold text-sm\'>{{ addslashes($sp->nama) }}</span>'">
                @else
                    <span class="text-navy-900 font-bold text-sm">{{ $sp->nama }}</span>
                @endif
            </a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- BEYOND PERBASI APP --}}
<section class="bg-gradient-to-r from-navy-900 to-navy-700 py-14">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-4 flex flex-col md:flex-row items-center justify-between gap-8">
    
        <div class="flex-shrink-0 flex items-center justify-center relative">
            {{-- glow ring --}}
            <div class="absolute inset-0 rounded-full bg-brandred-600 opacity-20 blur-3xl scale-75"></div>
            {{-- circle backdrop --}}
            <div class="absolute w-72 h-72 rounded-full bg-white/5 border border-white/10"></div>
            <img src="{{ asset('app_screenshots.png') }}" alt="Beyond Perbasi App" class="relative z-10 max-h-80 w-auto object-contain drop-shadow-2xl">
        </div>
    <div class="text-white text-center md:text-left">
            <div class="inline-block bg-brandred-600 text-white text-xs font-bold px-3 py-1 rounded-full mb-3 uppercase tracking-wider">
                Aplikasi Mobile
            </div>
            <h2 class="text-3xl md:text-4xl font-extrabold leading-tight">Beyond Perbasi</h2>
            <p class="mt-3 text-gray-300 max-w-md">
                Kelola keanggotaan, pantau event, dan tetap terhubung dengan komunitas basket Pemalang langsung dari genggamanmu.
            </p>
            <div class="mt-6 flex flex-wrap gap-4 justify-center md:justify-start">
                <a href="https://play.google.com/store/apps/details?id=com.perbasi.member"
                   target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-3 bg-white text-navy-900 font-bold px-5 py-3 rounded-xl shadow-lg hover:bg-gray-100 transition">
                    <i class="fab fa-google-play text-2xl text-green-600"></i>
                    <div class="text-left">
                        <div class="text-xs text-gray-500 leading-none">Tersedia di</div>
                        <div class="text-base font-extrabold leading-tight">Google Play</div>
                    </div>
                </a>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    const slides = document.querySelectorAll('.hero-slide');
    const texts  = document.querySelectorAll('.hero-text');
    const infos  = document.querySelectorAll('.hero-info');
    const dots   = document.querySelectorAll('.hero-dot');
    if (slides.length <= 1) return;

    let current = 0;

    function goTo(index) {
        slides[current].classList.remove('opacity-100');
        slides[current].classList.add('opacity-0');
        texts[current].classList.add('hidden');
        if (infos[current]) infos[current].classList.add('hidden');
        if (dots[current]) {
            dots[current].classList.remove('bg-white', 'w-6');
            dots[current].classList.add('bg-white/40', 'w-2');
        }

        current = index;

        slides[current].classList.add('opacity-100');
        slides[current].classList.remove('opacity-0');
        texts[current].classList.remove('hidden');
        if (infos[current]) infos[current].classList.remove('hidden');
        if (dots[current]) {
            dots[current].classList.add('bg-white', 'w-6');
            dots[current].classList.remove('bg-white/40', 'w-2');
        }
    }

    dots.forEach(function (dot, i) {
        dot.addEventListener('click', function () { goTo(i); resetTimer(); });
    });

    let timer = setInterval(function () { goTo((current + 1) % slides.length); }, 5000);

    function resetTimer() {
        clearInterval(timer);
        timer = setInterval(function () { goTo((current + 1) % slides.length); }, 5000);
    }
})();
</script>

@endsection
