@extends('compro.layout')

@section('title', $program->judul . ' - Program PERBASI Pemalang')

@section('content')

@php
    $ytId = null;
    if ($program->cover_type === 'youtube' && !empty($program->youtube_url)) {
        if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $program->youtube_url, $m)) $ytId = $m[1];
        elseif (preg_match('/[?&]v=([a-zA-Z0-9_-]+)/', $program->youtube_url, $m)) $ytId = $m[1];
    }
    $faIcon = \App\BannerProgram::$iconMap[$program->icon_name ?? ''] ?? 'fa-star';
@endphp

{{-- HERO --}}
<section class="bg-navy-900 pt-16">
    @if($program->cover_type === 'image' && !empty($program->image_url))
        <div class="h-72 md:h-96 w-full overflow-hidden relative">
            <img src="{{ asset('banner-program/' . $program->image_url) }}"
                 class="w-full h-full object-cover"
                 alt="{{ $program->judul }}">
            <div class="absolute inset-0 bg-gradient-to-t from-navy-900/80 to-transparent"></div>
        </div>
    @elseif($program->cover_type === 'youtube' && $ytId)
        <div class="max-w-4xl mx-auto px-4 pt-8 pb-0">
            <div class="relative w-full rounded-2xl overflow-hidden shadow-2xl" style="padding-top: 56.25%;">
                <iframe class="absolute inset-0 w-full h-full"
                        src="https://www.youtube.com/embed/{{ $ytId }}"
                        title="{{ $program->judul }}"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen></iframe>
            </div>
        </div>
    @else
        <div class="h-56 flex items-center justify-center bg-gradient-to-br from-navy-800 to-navy-900">
            <i class="fas {{ $faIcon }} text-8xl text-white/20"></i>
        </div>
    @endif
    <div class="text-white py-6">
        <div class="max-w-4xl mx-auto px-4">
            <a href="{{ route('compro.dashboard') }}" class="text-sm text-gray-300 hover:text-white">
                <i class="fas fa-arrow-left mr-1"></i> Kembali ke Beranda
            </a>
        </div>
    </div>
</section>

{{-- KONTEN --}}
<section class="max-w-4xl mx-auto px-4 -mt-4 pb-16">
    <div class="bg-white rounded-2xl shadow-xl p-8 md:p-12">

        <span class="inline-block bg-navy-700 text-white text-xs px-3 py-1 rounded-full font-semibold uppercase tracking-wider">
            <i class="fas fa-basketball mr-1 text-[10px]"></i> Program
        </span>

        <h1 class="text-3xl md:text-4xl font-black text-navy-900 mt-4 leading-tight">
            {{ $program->judul }}
        </h1>

        <p class="mt-3 text-gray-500 text-base leading-relaxed border-l-4 border-brandred-600 pl-4">
            {{ $program->short_description }}
        </p>

        @if(!empty($program->artikel) && $program->artikel !== '<p><br></p>')
        <div class="mt-8 pt-8 border-t border-gray-100">
            <div class="prose prose-lg max-w-none text-gray-700 leading-relaxed
                        [&_h1]:text-navy-900 [&_h1]:font-black
                        [&_h2]:text-navy-900 [&_h2]:font-bold
                        [&_h3]:text-navy-800 [&_h3]:font-semibold
                        [&_ul]:list-disc [&_ul]:pl-5
                        [&_ol]:list-decimal [&_ol]:pl-5
                        [&_li]:mb-1
                        [&_a]:text-brandred-600 [&_a]:underline
                        [&_blockquote]:border-l-4 [&_blockquote]:border-brandred-600 [&_blockquote]:pl-4 [&_blockquote]:italic [&_blockquote]:text-gray-500
                        [&_img]:rounded-xl [&_img]:shadow-md [&_img]:my-4">
                {!! $program->artikel !!}
            </div>
        </div>
        @endif

        @if(!empty($program->external_link))
        <div class="mt-10 pt-8 border-t border-gray-100 flex flex-wrap gap-4 items-center">
            <a href="{{ $program->external_link }}" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-2 bg-brandred-600 hover:bg-brandred-700 text-white font-semibold px-6 py-3 rounded-xl shadow-lg transition">
                <i class="fas fa-external-link-alt"></i>
                Kunjungi Halaman Program
            </a>
            <span class="text-xs text-gray-400 break-all">{{ $program->external_link }}</span>
        </div>
        @endif

        <div class="mt-8 pt-6 border-t border-gray-100 flex items-center justify-between text-xs text-gray-400">
            <span><i class="far fa-clock mr-1"></i> Diperbarui {{ \Carbon\Carbon::parse($program->updated_at)->translatedFormat('d M Y') }}</span>
            
        </div>
    </div>
</section>

@endsection
