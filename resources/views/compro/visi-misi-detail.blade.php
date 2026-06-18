@extends('compro.layout')

@section('title', ($item->judul ?? ucfirst($item->tipe)) . ' - PERBASI Kabupaten Pemalang')

@section('content')

@php
    $isVisi   = $item->tipe === 'visi';
    $bgHeader = $isVisi ? 'bg-navy-900' : 'bg-navy-900';
    try { $d = \Carbon\Carbon::parse($item->created_at); } catch (\Throwable $ex) { $d = null; }
@endphp

<section class="{{ $bgHeader }} pt-16">
    <div class="text-white py-12">
        <div class="max-w-7xl mx-auto px-4">
            <a href="{{ route('compro.tentang') }}" class="text-sm text-gray-300 hover:text-white">
                <i class="fas fa-arrow-left mr-1"></i> Kembali ke Tentang Kami
            </a>
            <h1 class="text-3xl md:text-5xl font-black mt-3 leading-tight">
                {{ $item->judul ?? ucfirst($item->tipe) }}
            </h1>
            <div class="flex flex-wrap gap-4 text-sm text-gray-300 mt-3">
                @if($d)
                    <span><i class="far fa-calendar mr-1"></i> {{ $d->translatedFormat('l, d F Y') }}</span>
                @endif
                <span class="px-2 py-0.5 rounded text-white text-xs font-bold
                    {{ $isVisi ? 'bg-navy-700' : 'bg-brandred-600' }}">
                    {{ ucfirst($item->tipe) }}
                </span>
            </div>
        </div>
    </div>
</section>

<section class="max-w-7xl mx-auto px-4 py-12 grid lg:grid-cols-3 gap-8">

    <article class="lg:col-span-2 bg-white rounded-2xl shadow p-6 lg:p-10">
        <div class="prose max-w-none text-gray-700 leading-relaxed [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_li]:mb-1">
            {!! $item->isi !!}
        </div>

        {{-- Share --}}
        <div class="mt-8 pt-6 border-t border-gray-200 flex items-center gap-3">
            <span class="text-sm font-semibold text-gray-600">Bagikan:</span>
            @php $url = urlencode(request()->fullUrl()); $title = urlencode($item->judul ?? ucfirst($item->tipe)); @endphp
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ $url }}" target="_blank"
               class="w-9 h-9 bg-blue-600 text-white rounded-full flex items-center justify-center">
                <i class="fab fa-facebook-f"></i>
            </a>
            <a href="https://twitter.com/intent/tweet?url={{ $url }}&text={{ $title }}" target="_blank"
               class="w-9 h-9 bg-sky-500 text-white rounded-full flex items-center justify-center">
                <i class="fab fa-twitter"></i>
            </a>
            <a href="https://wa.me/?text={{ $title }}%20{{ $url }}" target="_blank"
               class="w-9 h-9 bg-green-500 text-white rounded-full flex items-center justify-center">
                <i class="fab fa-whatsapp"></i>
            </a>
        </div>
    </article>

    <aside>
        <div class="bg-white rounded-2xl shadow p-6 sticky top-20">
            <h3 class="font-bold text-navy-900 mb-4">{{ ucfirst($item->tipe) }} Lainnya</h3>
            @if(!empty($related) && count($related) > 0)
                <ul class="space-y-4">
                    @foreach($related as $r)
                    <li>
                        <a href="{{ route('compro.visi-misi.detail', $r->id) }}" class="block hover:text-brandred-600">
                            <div class="text-sm font-semibold text-navy-900 leading-snug">
                                {{ $r->judul ?? ucfirst($r->tipe) }}
                            </div>
                        </a>
                    </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-500">Tidak ada {{ $item->tipe }} lainnya.</p>
            @endif

        </div>
    </aside>

</section>

@endsection
