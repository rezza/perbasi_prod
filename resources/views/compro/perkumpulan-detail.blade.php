@extends('compro.layout')

@section('title', $perkumpulan->nama . ' - PERBASI Kabupaten Pemalang')

@section('content')

{{-- Hero / Info Perkumpulan --}}
<section class="section-bg pt-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-4 py-16">
        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-8">
            {{-- Logo --}}
            <div class="shrink-0">
                @if(!empty($perkumpulan->logo))
                    <img src="{{ asset('perkumpulan/'.$perkumpulan->logo) }}" alt="{{ $perkumpulan->nama }}"
                         class="w-32 h-32 object-contain drop-shadow-xl"
                         onerror="this.style.display='none'">
                @else
                    <div class="w-32 h-32 bg-white/10 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-shield-halved text-white/40 text-6xl"></i>
                    </div>
                @endif
            </div>
            {{-- Info --}}
            <div class="text-center sm:text-left text-white">
                <a href="{{ route('compro.perkumpulan') }}"
                   class="text-red-300 text-sm hover:text-white mb-2 inline-block">
                    <i class="fas fa-arrow-left mr-1"></i> Kembali ke Daftar Perkumpulan
                </a>
                <h1 class="text-3xl md:text-4xl font-black mt-1">{{ $perkumpulan->nama }}</h1>
                <div class="mt-3 flex flex-wrap justify-center sm:justify-start gap-3 text-sm text-gray-300">
                    @if(!empty($perkumpulan->no_perkumpulan))
                    <span><i class="fas fa-hashtag mr-1 opacity-60"></i>{{ $perkumpulan->no_perkumpulan }}</span>
                    @endif
                    @if(!empty($perkumpulan->tahun_gabung))
                    <span><i class="fas fa-calendar mr-1 opacity-60"></i>Bergabung {{ $perkumpulan->tahun_gabung }}</span>
                    @endif
                    @if(!empty($perkumpulan->nama_kota))
                    <span><i class="fas fa-map-marker-alt mr-1 opacity-60"></i>{{ $perkumpulan->nama_kota }}</span>
                    @endif
                    @if(!empty($perkumpulan->nama_status_anggota))
                    <span class="px-3 py-0.5 bg-green-400/20 text-green-200 border border-green-400/30 rounded-full text-xs font-bold">
                        {{ $perkumpulan->nama_status_anggota }}
                    </span>
                    @endif
                </div>
                @if(!empty($perkumpulan->keterangan))
                <p class="mt-3 text-sm text-gray-300 leading-relaxed max-w-xl">{{ $perkumpulan->keterangan }}</p>
                @endif
                <div class="mt-3 flex gap-6 text-sm justify-center sm:justify-start">
                    <div class="text-center">
                        <div class="text-2xl font-black text-white">{{ count($pemain) }}</div>
                        <div class="text-gray-400 text-xs">Pemain</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-black text-white">{{ count($sdm) }}</div>
                        <div class="text-gray-400 text-xs">SDM</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- Pemain --}}
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-4 py-12">
    <h2 class="text-2xl font-extrabold text-navy-900 mb-6">
        <i class="fas fa-users mr-2 text-brandred-600"></i> Pemain
        <span class="text-base font-normal text-gray-400 ml-2">({{ count($pemain) }})</span>
    </h2>

    @if(count($pemain) > 0)
    <div id="pemainGrid" class="grid gap-4" style="grid-template-columns: repeat(auto-fill, 300px); justify-content: start;">
        @foreach($pemain as $p) 
        @php
            $badgeClass = match((string)$p->status) {
                '2'  => 'bg-green-400/20 text-green-200 border-green-400/30',
                '1'  => 'bg-blue-400/20 text-blue-200 border-blue-400/30',
                '0'  => 'bg-yellow-400/20 text-yellow-200 border-yellow-400/30',
                default => 'bg-white/10 text-red-200 border-white/20',
            };
        @endphp
        <div class="relative rounded-2xl overflow-hidden shadow-xl select-none"
             style="background: linear-gradient(180deg, #921212 0%, #7a0e0e 50%, #6b0c0c 100%); min-height: 380px; width:300px">
            <div class="relative flex justify-between items-start px-4 pt-4">
                <div class="flex items-center gap-2">
                    <img src="{{ asset('logo.png') }}" alt="Logo" class="w-7 h-7 object-contain drop-shadow">
                    <div class="leading-tight">
                        <div class="text-white text-[10px] font-extrabold tracking-widest">PERBASI</div>
                        <div class="text-red-300 text-[8px] tracking-wide -mt-0.5">KAB. PEMALANG</div>
                    </div>
                </div>
                <div class="text-right">
                    @if(!empty($p->no_anggota))
                    <div class="text-white text-[10px] font-mono font-bold tracking-wider">{{ $p->no_anggota }}</div>
                    @endif
                    <div class="text-red-300 text-[8px] mt-0.5">{{ $p->tahun }}</div>
                </div>
            </div>
            <div class="relative flex justify-center mt-6 mb-6">
                <div class="relative" style="width:150px; height:192px;">
                    @if(!empty($p->foto_url))
                        <img src="{{ $p->foto_url }}" alt="{{ $p->nama }}"
                             class="absolute inset-0 w-full h-full object-cover object-top"
                             style="clip-path: polygon(50% 0%, 100% 18%, 100% 68%, 50% 100%, 0% 68%, 0% 18%)"
                             onerror="this.style.display='none'">
                    @else
                        <div class="absolute inset-0 bg-white/15"
                             style="clip-path: polygon(50% 0%, 100% 18%, 100% 68%, 50% 100%, 0% 68%, 0% 18%)"></div>
                    @endif
                    <img src="{{ asset('shield3.png') }}" alt="" class="absolute inset-0 w-full h-full pointer-events-none" draggable="false">
                </div>
            </div>
            <div class="relative px-4 pb-4 text-center">
                <div class="text-white font-extrabold text-sm leading-tight">{{ $p->nama }}</div>
                @if(!empty($p->posisi))
                <div class="text-red-300 text-xs italic mt-0.5">{{ $p->posisi }}</div>
                @endif
                <span class="inline-block mt-2 px-3 py-0.5 rounded-full text-[10px] font-bold border {{ $badgeClass }}">
                    {{ $p->status_label }}
                </span>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-transparent via-white/30 to-transparent"></div>
        </div>
        @endforeach
    </div>
    @else
    <div class="py-12 text-center text-gray-400">
        <i class="fas fa-users text-5xl mb-4 opacity-30 block"></i>
        <p class="text-lg font-medium">Belum ada pemain terdaftar.</p>
    </div>
    @endif
</section>

{{-- SDM --}}
<section class="bg-gray-50 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-4">
        <h2 class="text-2xl font-extrabold text-navy-900 mb-6">
            <i class="fas fa-id-badge mr-2 text-brandred-600"></i> SDM / Non-Anggota
            <span class="text-base font-normal text-gray-400 ml-2">({{ count($sdm) }})</span>
        </h2>

        @if(count($sdm) > 0)
        <div class="grid gap-4" style="grid-template-columns: repeat(auto-fill, 300px); justify-content: start;">
            @foreach($sdm as $s)
            @php
                $sdmBadge = match((string)$s->status) {
                    '2'  => 'bg-green-400/20 text-green-200 border-green-400/30',
                    '1'  => 'bg-blue-400/20 text-blue-200 border-blue-400/30',
                    '0'  => 'bg-yellow-400/20 text-yellow-200 border-yellow-400/30',
                    default => 'bg-white/10 text-red-200 border-white/20',
                };
            @endphp
            <div class="relative rounded-2xl overflow-hidden shadow-xl select-none"
                style="background: linear-gradient(180deg, #921212 0%, #7a0e0e 50%, #6b0c0c 100%); min-height: 380px; width:300px">
                <div class="relative flex justify-between items-start px-4 pt-4">
                    <div class="flex items-center gap-2">
                        <img src="{{ asset('logo.png') }}" alt="Logo" class="w-7 h-7 object-contain drop-shadow">
                        <div class="leading-tight">
                            <div class="text-white text-[10px] font-extrabold tracking-widest">PERBASI</div>
                            <div class="text-blue-300 text-[8px] tracking-wide -mt-0.5">KAB. PEMALANG</div>
                        </div>
                    </div>
                    <div class="text-right">
                        @if(!empty($s->no_anggota))
                        <div class="text-white text-[10px] font-mono font-bold tracking-wider">{{ $s->no_anggota }}</div>
                        @endif
                        <div class="text-blue-300 text-[8px] mt-0.5">{{ $s->tahun }}</div>
                    </div>
                </div>
                <div class="relative flex justify-center mt-6 mb-6">
                    <div class="relative" style="width:150px; height:192px;">
                        @if(!empty($s->foto_url))
                            <img src="{{ $s->foto_url }}" alt="{{ $s->nama }}"
                                 class="absolute inset-0 w-full h-full object-cover object-top"
                                 style="clip-path: polygon(50% 0%, 100% 18%, 100% 68%, 50% 100%, 0% 68%, 0% 18%)"
                                 onerror="this.style.display='none'">
                        @else
                            <div class="absolute inset-0 bg-white/15"
                                 style="clip-path: polygon(50% 0%, 100% 18%, 100% 68%, 50% 100%, 0% 68%, 0% 18%)"></div>
                        @endif
                        <img src="{{ asset('shield3.png') }}" alt="" class="absolute inset-0 w-full h-full pointer-events-none" draggable="false">
                    </div>
                </div>
                <div class="relative px-4 pb-4 text-center">
                    <div class="text-white font-extrabold text-sm leading-tight">{{ $s->nama }}</div>
                    @if(!empty($s->jabatan))
                    <div class="text-blue-300 text-xs italic mt-0.5">{{ $s->jabatan }}</div>
                    @elseif(!empty($s->nama_posisi))
                    <div class="text-blue-300 text-xs italic mt-0.5">{{ $s->nama_posisi }}</div>
                    @endif
                    <span class="inline-block mt-2 px-3 py-0.5 rounded-full text-[10px] font-bold border {{ $sdmBadge }}">
                        {{ $s->status_label }}
                    </span>
                </div>
                <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-transparent via-white/30 to-transparent"></div>
            </div>
            @endforeach
        </div>
        @else
        <div class="py-12 text-center text-gray-400">
            <i class="fas fa-id-badge text-5xl mb-4 opacity-30 block"></i>
            <p class="text-lg font-medium">Belum ada SDM terdaftar.</p>
        </div>
        @endif
    </div>
</section>

@endsection
