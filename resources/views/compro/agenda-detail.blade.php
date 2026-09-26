@extends('compro.layout')

@section('title', $event->judul . ' - Agenda PERBASI')

@section('content')

@php
    try { $d = \Carbon\Carbon::parse($event->tanggal); } catch (\Throwable $ex) { $d = null; }
@endphp

<section class="bg-navy-900 pt-16">
        <div class="bg-navy-900 text-white py-12">
            <div class="max-w-7xl mx-auto px-4">
                <a href="{{ route('compro.agenda') }}" class="text-sm text-gray-300 hover:text-white"><i class="fas fa-arrow-left mr-1"></i> Kembali ke Agenda</a>
                <h1 class="text-3xl md:text-5xl font-black mt-3">{{ $event->judul }}</h1>
                <div class="flex flex-wrap gap-4 text-sm text-gray-300 mt-3">
                    <span><i class="far fa-calendar mr-1"></i> {{ $d ? $d->translatedFormat('l, d F Y') : '-' }}</span>
                    @if(!empty($event->admin_username))
                        <span><i class="fas fa-user mr-1"></i> {{ $event->admin_username }}</span>
                    @endif
                    <span class="bg-brandred-600 px-2 py-0.5 rounded">Agenda</span>
                </div>
            </div>
        </div>
</section>

<section class="max-w-7xl mx-auto px-4 py-12 grid lg:grid-cols-3 gap-8">
    <article class="lg:col-span-2 bg-white rounded-2xl shadow p-6 lg:p-10">
        @if(!empty($event->filename))
            <div class="relative rounded-xl overflow-hidden mb-6">
                <img src="{{ asset('news/'.$event->filename) }}" alt="{{ $event->judul }}" class="w-full">
                @include('compro.partials.event-info-bar', ['info' => $pertandingan, 'size' => 'lg'])
            </div>
        @endif
        <div class="prose max-w-none text-gray-700 leading-relaxed">
            {!! $event->konten !!}
        </div>
        @include('compro.partials.event-schedule', ['schedules' => $schedules])
    </article>

    <aside>
        <div class="bg-white rounded-2xl shadow p-6 sticky top-20">
            <h3 class="font-bold text-navy-900 mb-4">Agenda Lainnya</h3>
            @if(!empty($related) && count($related) > 0)
                <ul class="space-y-3">
                    @foreach($related as $r)
                        @if($r->id !== $event->id_news)
                        <li>
                            <a href="{{ route('compro.agenda.detail', $r->id) }}" class="block hover:text-brandred-600">
                                <div class="text-sm font-semibold text-navy-900">{{ $r->judul }}</div>
                                <div class="text-xs text-gray-500"><i class="far fa-calendar mr-1"></i> {{ \Carbon\Carbon::parse($r->tanggal)->translatedFormat('d M Y') }}</div>
                            </a>
                        </li>
                        @endif
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-gray-500">Belum ada agenda lain.</p>
            @endif
        </div>
    </aside>
</section>

@endsection
