@extends('compro.layout')

@section('title', $article->judul . ' - Berita PERBASI')

@section('content')

@php
    try { $d = \Carbon\Carbon::parse($article->tanggal); } catch (\Throwable $ex) { $d = null; }
@endphp


<section class="bg-navy-900 pt-16"> 
        <div class="bg-navy-900 text-white py-12">
            <div class="max-w-7xl mx-auto px-4">
        <a href="{{ route('compro.berita') }}" class="text-sm text-gray-300 hover:text-white"><i class="fas fa-arrow-left mr-1"></i> Kembali ke Berita</a>
        <h1 class="text-3xl md:text-5xl font-black mt-3 leading-tight">{{ $article->judul }}</h1>
        <div class="flex flex-wrap gap-4 text-sm text-gray-300 mt-3">
            <span><i class="far fa-calendar mr-1"></i> {{ $d ? $d->translatedFormat('l, d F Y') : '-' }}</span>
            @if(!empty($article->admin_username))
                <span><i class="fas fa-user mr-1"></i> {{ $article->admin_username }}</span>
            @endif
            <span class="bg-brandred-600 px-2 py-0.5 rounded">{{ $article->kategori == 2 ? 'Agenda' : 'Berita' }}</span>
        </div>
            </div>
        </div>
</section>


<section class="max-w-7xl mx-auto px-4 py-12 grid lg:grid-cols-3 gap-8">
    <article class="lg:col-span-2 bg-white rounded-2xl shadow p-6 lg:p-10">
        @if(!empty($article->filename))
            <div class="relative rounded-xl overflow-hidden mb-6">
                <img src="{{ asset('news/'.$article->filename) }}" alt="{{ $article->judul }}" class="w-full">
                @include('compro.partials.event-info-bar', ['info' => $pertandingan, 'size' => 'lg'])
            </div>
        @endif
        <div class="prose max-w-none text-gray-700 leading-relaxed">
            {!! $article->konten !!}
        </div>

        <div class="mt-8">
            @include('compro.partials.event-schedule', ['schedules' => $schedules])
        </div>

        {{-- Share --}}
        <div class="mt-8 pt-6 border-t border-gray-200 flex items-center gap-3">
            <span class="text-sm font-semibold text-gray-600">Bagikan:</span>
            @php $url = urlencode(request()->fullUrl()); $title = urlencode($article->judul); @endphp
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ $url }}" target="_blank" class="w-9 h-9 bg-blue-600 text-white rounded-full flex items-center justify-center"><i class="fab fa-facebook-f"></i></a>
            <a href="https://twitter.com/intent/tweet?url={{ $url }}&text={{ $title }}" target="_blank" class="w-9 h-9 bg-sky-500 text-white rounded-full flex items-center justify-center"><i class="fab fa-twitter"></i></a>
            <a href="https://wa.me/?text={{ $title }}%20{{ $url }}" target="_blank" class="w-9 h-9 bg-green-500 text-white rounded-full flex items-center justify-center"><i class="fab fa-whatsapp"></i></a>
        </div>
    </article>

    <aside>
        <div class="bg-white rounded-2xl shadow p-6 sticky top-20">
            <h3 class="font-bold text-navy-900 mb-4">Berita Terkait</h3>
            @if(!empty($related) && count($related) > 0)
                <ul class="space-y-4">
                    @foreach($related as $r)
                        @if($r->id !== $article->id_news)
                        <li>
                            <a href="{{ route('compro.berita.detail', $r->id) }}" class="block hover:text-brandred-600">
                                <div class="text-sm font-semibold text-navy-900 leading-snug">{{ $r->judul }}</div>
                                <div class="text-xs text-gray-500 mt-1"><i class="far fa-calendar mr-1"></i> {{ \Carbon\Carbon::parse($r->tanggal)->translatedFormat('d M Y') }}</div>
                            </a>
                        </li>
                        @endif
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-500">Belum ada berita terkait.</p>
            @endif
        </div>
    </aside>
</section>

@endsection
