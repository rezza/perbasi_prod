@extends('compro.layout')

@section('title', $sponsor->nama . ' - Sponsor & Mitra PERBASI')

@section('content')


<section class="bg-navy-900 pt-16">
        <div class="bg-navy-900 text-white py-20">
            <div class="max-w-7xl mx-auto px-4">
                <a href="{{ route('compro.sponsor') }}" class="text-sm text-gray-300 hover:text-white"><i class="fas fa-arrow-left mr-1"></i> Kembali ke Sponsor &amp; Mitra</a>
            </div>
        </div>
</section>

<section class="max-w-7xl mx-auto px-4 -mt-8">
    <div class="bg-white rounded-2xl shadow-xl p-8 md:p-12">
        <div class="flex justify-center bg-gray-50 rounded-xl p-6 mb-8">
            @if(!empty($sponsor->filename))
                <img src="{{ asset('sponsor/'.$sponsor->filename) }}" alt="{{ $sponsor->nama }}" class="max-w object-contain"
                     onerror="this.outerHTML='<span class=\'text-navy-900 font-bold text-2xl text-center\'>{{ addslashes($sponsor->nama) }}</span>'">
            @else
                <span class="text-navy-900 font-bold text-2xl text-center">{{ $sponsor->nama }}</span>
            @endif
        </div>
        <span class="inline-block bg-brandred-600 text-white text-xs px-3 py-1 rounded-full font-semibold">SPONSOR / MITRA</span>
        <h1 class="text-3xl md:text-4xl font-black text-navy-900 mt-3">{{ $sponsor->nama }}</h1>
        <div class="prose max-w-none mt-5 text-gray-700 leading-relaxed [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 [&_li]:mb-1">
            {!! $sponsor->deskripsi ?? '-' !!}
        </div>
    </div>
</section>

@if(!empty($other) && count($other) > 0)
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-4 py-16">
    <h2 class="text-2xl font-extrabold text-navy-900 mb-6">Sponsor &amp; Mitra Lainnya</h2>
    <div class="grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        @foreach($other as $o)
        <a href="{{ route('compro.sponsor.detail', $o->id) }}" class="rounded-xl shadow overflow-hidden h-32 block hover:shadow-lg transition">
            @if(!empty($o->filename))
                <img src="{{ asset('sponsor/'.$o->filename) }}" alt="{{ $o->nama }}" class="w-full mx-h-32 object-cover">
            @else
                <div class="w-full h-full bg-navy-800 flex items-center justify-center p-3">
                    <span class="text-white font-semibold text-xs text-center">{{ $o->nama }}</span>
                </div>
            @endif
        </a>
        @endforeach
    </div>
</section>
@endif

@endsection
