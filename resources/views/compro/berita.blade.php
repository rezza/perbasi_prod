@extends('compro.layout')

@section('title', 'Berita & Artikel - PERBASI Kabupaten Pemalang')

@section('content')

<section class="relative text-white overflow-hidden" style="height: 45vh; min-height: 320px;">
    {{-- Background dari gambar featured berita --}}
    @if(!empty($featured) && !empty($featured->filename))
        <div class="absolute inset-0" style="background-image: linear-gradient(rgba(11,30,63,0.75), rgba(11,30,63,0.60)), url('{{ asset('news/'.$featured->filename) }}'); background-size: cover; background-position: center;"></div>
    @else
        <div class="absolute inset-0 section-bg"></div>
    @endif

    <div class="relative z-10 h-full flex flex-col justify-center items-center text-center px-4" style="padding-top: 64px;">
        <h1 class="text-4xl md:text-5xl font-black">Berita &amp; Artikel</h1>
        <p class="mt-3 text-gray-300">Update terbaru, prestasi, dan artikel seputar basket Pemalang</p>
    </div>
</section>

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">

    <form method="GET" class="max-w-xl mx-auto mb-10">
        <div class="flex bg-white rounded-full shadow overflow-hidden border border-gray-200">
            <input type="text" name="q" value="{{ $keyword ?? '' }}" placeholder="Cari berita..." class="flex-1 px-5 py-3 outline-none text-sm">
            <button class="bg-brandred-600 hover:bg-brandred-700 text-white px-6 font-semibold"><i class="fas fa-search"></i></button>
        </div>
    </form>

    @if(empty($news) || count($news) === 0)
        <div class="text-center py-16 text-gray-500">
            <i class="fas fa-newspaper text-5xl mb-4 opacity-50"></i>
            <p>Belum ada berita yang dipublikasikan.</p>
        </div>
    @else
        <div class="grid lg:grid-cols-3 gap-8 mb-16">
            @if($featured)
            <article class="lg:col-span-2 bg-white rounded-2xl shadow-lg overflow-hidden card-hover">
                <a href="{{ route('compro.berita.detail', $featured->id) }}" class="block">
                    @if(!empty($featured->filename ?? null))
                        <img src="{{ asset('news/'.$featured->filename) }}" alt="{{ $featured->judul }}" class="w-full h-72 object-cover">
                    @else
                        <div class="h-72 bg-gradient-to-br from-navy-800 to-brandred-700 flex items-center justify-center">
                            <i class="fas fa-basketball text-white text-7xl opacity-70"></i>
                        </div>
                    @endif
                    <div class="p-8">
                        <div class="text-xs text-gray-500 mb-2">
                            <i class="far fa-calendar mr-1"></i> {{ \Carbon\Carbon::parse($featured->tanggal)->translatedFormat('d F Y') }}
                            • <span class="text-brandred-600 font-semibold">FEATURED</span>
                        </div>
                        <h2 class="text-3xl font-extrabold text-navy-900 leading-tight">{{ $featured->judul }}</h2>
                        <p class="mt-4 text-gray-600 leading-relaxed">{{ \Illuminate\Support\Str::limit(strip_tags($featured->konten), 220) }}</p>
                        <span class="inline-block mt-5 text-brandred-600 font-semibold">Baca Selengkapnya <i class="fas fa-arrow-right ml-1"></i></span>
                    </div>
                </a>
            </article>
            @endif

            <aside class="space-y-3">
                <h3 class="font-bold text-navy-900 text-lg mb-2">Berita Populer</h3>
                @foreach($popular as $p)
                <a href="{{ route('compro.berita.detail', $p->id) }}" class="block bg-white rounded-xl p-4 shadow card-hover">
                    <div class="text-sm font-semibold text-navy-900">{{ $p->judul }}</div>
                    <div class="text-xs text-gray-500 mt-1"><i class="far fa-clock mr-1"></i> {{ \Carbon\Carbon::parse($p->tanggal)->diffForHumans() }}</div>
                </a>
                @endforeach
            </aside>
        </div>

        @if(!empty($rest) && count($rest) > 0)
        <h3 class="text-xl font-extrabold text-navy-900 mb-6">Artikel Lainnya</h3>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($rest as $a)
            <a href="{{ route('compro.berita.detail', $a->id) }}" class="bg-white rounded-2xl shadow card-hover overflow-hidden block">
                @if(!empty($a->filename ?? null))
                    <img src="{{ asset('news/'.$a->filename) }}" alt="{{ $a->judul }}" class="h-44 w-full object-cover">
                @else
                    <div class="h-44 bg-gradient-to-br from-navy-700 to-navy-900 flex items-center justify-center">
                        <i class="fas fa-newspaper text-white text-5xl opacity-70"></i>
                    </div>
                @endif
                <div class="p-5">
                    <span class="inline-block bg-brandred-600 text-white text-xs px-2 py-1 rounded">Berita</span>
                    <h3 class="font-bold text-navy-900 mt-3 leading-snug">{{ $a->judul }}</h3>
                    <div class="flex justify-between items-center mt-4 text-xs text-gray-500">
                        <span><i class="far fa-calendar mr-1"></i> {{ \Carbon\Carbon::parse($a->tanggal)->translatedFormat('d M Y') }}</span>
                        <span class="text-brandred-600 font-semibold">Baca <i class="fas fa-arrow-right ml-1"></i></span>
                    </div>
                </div>
            </a>
            @endforeach
        </div>
        @endif
    @endif
</section>

@endsection
