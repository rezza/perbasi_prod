@extends('compro.layout')

@section('title', 'Agenda & Jadwal - PERBASI Kabupaten Pemalang')

@section('content')

<section class="section-bg pt-16"> 
    <div class="text-white py-20 text-center">
        <h1 class="text-4xl md:text-5xl font-black">Agenda &amp; Jadwal</h1>
        <p class="mt-3 text-gray-300">Jadwal kegiatan, kompetisi, dan event basket Pemalang</p>
    </div>
</section>

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
<div class="flex flex-col lg:flex-row gap-10">

    {{-- ========== KIRI: Search + Daftar Agenda ========== --}}
    <div class="flex-1 min-w-0">

        <form method="GET" class="mb-8">
            <div class="flex bg-white rounded-full shadow overflow-hidden">
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari agenda..." class="flex-1 px-5 py-3 outline-none text-sm">
                <button class="bg-brandred-600 hover:bg-brandred-700 text-white px-6 font-semibold"><i class="fas fa-search"></i></button>
            </div>
        </form>

        @if(!empty($events) && count($events) > 0)
        <div class="space-y-4">
            @foreach($events as $e)
                @php
                    try { $d = \Carbon\Carbon::parse($e->tanggal); } catch (\Throwable $ex) { $d = null; }
                @endphp
                <a href="{{ route('compro.agenda.detail', $e->id) }}" class="bg-white rounded-2xl shadow card-hover p-5 flex flex-col sm:flex-row gap-5 items-start sm:items-center">
                    <div class="bg-navy-900 text-white rounded-xl px-4 py-3 text-center min-w-[80px]">
                        <div class="text-2xl font-black leading-none">{{ $d ? $d->format('d') : '--' }}</div>
                        <div class="text-xs mt-1 uppercase">{{ $d ? $d->translatedFormat('M Y') : '' }}</div>
                    </div>
                    <div class="flex-1 min-w-0 w-full">
                        <span class="inline-block text-xs bg-brandred-600 text-white px-2 py-0.5 rounded mb-1">Agenda</span>
                        <h3 class="font-bold text-navy-900 text-lg">{{ $e->judul }}</h3>
                        <p class="text-sm text-gray-600 mt-1">{{ \Illuminate\Support\Str::limit(strip_tags($e->konten), 160) }}</p>
                        @include('compro.partials.event-info-bar', ['info' => $e->pertandingan ?? null, 'pos' => 'relative mt-3 w-full'])
                    </div>
                    <span class="hidden md:inline-block bg-navy-700 hover:bg-navy-800 text-white px-4 py-2 rounded-lg text-sm font-semibold">Detail</span>
                </a>
            @endforeach
        </div>
        @else
        <div class="text-center py-16 text-gray-500">
            <i class="fas fa-calendar-times text-5xl mb-4 opacity-50"></i>
            <p>Belum ada agenda yang dijadwalkan.</p>
        </div>
        @endif

    </div>

    {{-- ========== KANAN: Kalender + Artikel Populer ========== --}}
    <div class="lg:w-80 xl:w-96 flex-shrink-0 space-y-8">

        {{-- CALENDAR --}}
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <button id="cal-prev" class="w-8 h-8 rounded-full bg-gray-100 hover:bg-navy-800 hover:text-white transition flex items-center justify-center text-sm">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <span id="cal-title" class="font-extrabold text-navy-900 text-base"></span>
                <button id="cal-next" class="w-8 h-8 rounded-full bg-gray-100 hover:bg-navy-800 hover:text-white transition flex items-center justify-center text-sm">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div class="grid grid-cols-7 text-center text-xs font-bold text-gray-400 mb-2 gap-1">
                @foreach(['Min','Sen','Sel','Rab','Kam','Jum','Sab'] as $day)
                <div>{{ $day }}</div>
                @endforeach
            </div>
            <div id="cal-grid" class="grid grid-cols-7 gap-1 text-center text-sm"></div>
        </div>

        {{-- ARTIKEL POPULER --}}
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <h3 class="font-extrabold text-navy-900 text-base mb-5 flex items-center gap-2">
                <i class="fas fa-fire text-brandred-600"></i> Artikel Terbaru
            </h3>
            @if(!empty($popularArticles) && count($popularArticles) > 0)
            <div class="space-y-4">
                @foreach($popularArticles as $i => $a)
                <a href="{{ route('compro.berita.detail', $a->id) }}" class="flex gap-3 items-start group">
                    <span class="flex-shrink-0 w-7 h-7 rounded-full bg-navy-800 text-white text-xs font-black flex items-center justify-center mt-0.5">{{ $i + 1 }}</span>
                    <div>
                        <p class="text-sm font-semibold text-navy-900 group-hover:text-brandred-600 leading-snug transition">{{ $a->judul }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            @php try { echo \Carbon\Carbon::parse($a->tanggal)->translatedFormat('d M Y'); } catch(\Throwable $ex) { echo '-'; } @endphp
                        </p>
                    </div>
                </a>
                @endforeach
            </div>
            @else
            <p class="text-sm text-gray-400">Belum ada artikel.</p>
            @endif
        </div>

    </div>
</div>
</section>

<script>
    const EVENT_DATES = @json($eventDates);
    const eventSet = new Set(EVENT_DATES);

    const MONTHS_ID = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

    let currentYear, currentMonth;

    function pad(n){ return String(n).padStart(2,'0'); }

    function renderCalendar(year, month){
        const today = new Date();
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        document.getElementById('cal-title').textContent = MONTHS_ID[month] + ' ' + year;

        const grid = document.getElementById('cal-grid');
        grid.innerHTML = '';

        for(let i = 0; i < firstDay; i++){
            grid.insertAdjacentHTML('beforeend', '<div></div>');
        }

        for(let d = 1; d <= daysInMonth; d++){
            const dateStr = year + '-' + pad(month + 1) + '-' + pad(d);
            const hasEvent = eventSet.has(dateStr);
            const isToday = (today.getFullYear() === year && today.getMonth() === month && today.getDate() === d);

            let cellClass = 'flex flex-col items-center justify-center rounded-xl py-1 ';
            let numClass  = 'font-semibold leading-none ';

            if(isToday){
                cellClass += 'bg-navy-800 text-white ';
                numClass  += 'text-white ';
            } else {
                cellClass += 'hover:bg-gray-100 ';
                numClass  += 'text-gray-700 ';
            }

            const dot = hasEvent
                ? `<span class="mt-1 w-1.5 h-1.5 rounded-full ${isToday ? 'bg-brandred-400' : 'bg-brandred-600'} block"></span>`
                : `<span class="mt-1 w-1.5 h-1.5 block"></span>`;

            grid.insertAdjacentHTML('beforeend',
                `<div class="${cellClass}"><span class="${numClass}">${d}</span>${dot}</div>`
            );
        }
    }

    function init(){
        const now = new Date();
        currentYear  = now.getFullYear();
        currentMonth = now.getMonth();
        renderCalendar(currentYear, currentMonth);
    }

    document.getElementById('cal-prev').addEventListener('click', function(){
        currentMonth--;
        if(currentMonth < 0){ currentMonth = 11; currentYear--; }
        renderCalendar(currentYear, currentMonth);
    });

    document.getElementById('cal-next').addEventListener('click', function(){
        currentMonth++;
        if(currentMonth > 11){ currentMonth = 0; currentYear++; }
        renderCalendar(currentYear, currentMonth);
    });

    init();
</script>

@endsection
