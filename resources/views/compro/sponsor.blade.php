@extends('compro.layout')

@section('title', 'Sponsor & Mitra - PERBASI Kabupaten Pemalang')

@section('content')

<section class="section-bg pt-16"> 
    <div class="text-white py-20 text-center">
    <h1 class="text-4xl md:text-5xl font-black">Sponsor &amp; Mitra</h1>
    <p class="mt-3 text-gray-300">Bersama membangun prestasi basket Pemalang</p>
    </div>
</section>

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-4 py-16">

    <form method="GET" class="max-w-xl mx-auto mb-10">
        <div class="flex bg-white rounded-full shadow overflow-hidden border border-gray-200">
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari sponsor / mitra..." class="flex-1 px-5 py-3 outline-none text-sm">
            <button class="bg-brandred-600 hover:bg-brandred-700 text-white px-6 font-semibold"><i class="fas fa-search"></i></button>
        </div>
    </form>

    @if(!empty($sponsors) && count($sponsors) > 0)
    <div class="grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        @foreach($sponsors as $sp)
        <a href="{{ route('compro.sponsor.detail', $sp->id) }}" class="bg-white rounded-2xl shadow card-hover overflow-hidden block">
            <div class="h-40 bg-gray-50 flex items-center justify-center p-4">
                @if(!empty($sp->filename))
                    <img src="{{ asset('sponsor/'.$sp->filename) }}" alt="{{ $sp->nama }}" class="max-h-28 object-contain"
                         onerror="this.outerHTML='<span class=\'text-navy-900 font-bold text-lg\'>{{ addslashes($sp->nama) }}</span>'">
                @else
                    <span class="text-navy-900 font-bold text-lg text-center">{{ $sp->nama }}</span>
                @endif
            </div>
            <div class="p-5 border-t border-gray-100">
                <div class="font-bold text-navy-900">{{ $sp->nama }}</div>
                <p class="text-xs text-gray-500 mt-1">{{ \Illuminate\Support\Str::limit(strip_tags($sp->deskripsi ?? ''), 80) }}</p>
                <div class="text-xs text-brandred-600 font-semibold mt-3">Lihat Detail <i class="fas fa-arrow-right ml-1"></i></div>
            </div>
        </a>
        @endforeach
    </div>
    @else
    <div class="text-center py-16 text-gray-500">
        <i class="fas fa-handshake text-5xl mb-4 opacity-50"></i>
        <p>Belum ada sponsor / mitra terdaftar.</p>
    </div>
    @endif

    <!-- <div class="mt-16 bg-navy-900 rounded-2xl p-10 text-center text-white">
        <h2 class="text-2xl md:text-3xl font-extrabold">Tertarik Menjadi Mitra Kami?</h2>
        <p class="mt-3 text-gray-300 max-w-2xl mx-auto">Mari berkontribusi dalam pengembangan basket di Kabupaten Pemalang.</p>
        <a href="mailto:sponsor@perbasipemalang.or.id" class="inline-block mt-6 bg-brandred-600 hover:bg-brandred-700 px-8 py-3 rounded-lg font-semibold transition">
            <i class="fas fa-envelope mr-2"></i> Hubungi Tim Kemitraan
        </a>
    </div> -->
</section>

@endsection
