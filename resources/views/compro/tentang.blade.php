@extends('compro.layout')

@section('title', 'Tentang Kami - PERBASI Kabupaten Pemalang')

@section('content')

<section class="section-bg pt-16"> 
    <div class="text-white py-20 text-center">
        <h1 class="text-4xl md:text-5xl font-black">Tentang Kami</h1>
        <p class="mt-3 text-gray-300">Mengenal lebih dekat PERBASI Kabupaten Pemalang</p>
    </div> 
</section>

{{-- VISI & MISI --}}
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-4 py-16">
    <h2 class="text-center text-3xl font-extrabold text-navy-900 mb-10">Visi &amp; Misi</h2>
    <div class="grid md:grid-cols-2 gap-6">
        {{-- VISI --}}
        <div class="bg-navy-700 text-white rounded-2xl p-8 shadow-xl card-hover">
            <!-- <h3 class="text-3xl font-black mb-4">Visi</h3> -->
            @if(!empty($visi) && count($visi) > 0)
                @foreach($visi as $vIdx => $v)
                <div class="visi-item{{ $vIdx >= 2 ? ' hidden' : '' }}">
                    @if(!empty($v->judul))<h4 class="font-bold mb-2">{{ $v->judul }}</h4>@endif
                    <div class="leading-relaxed text-gray-100 mb-2 line-clamp-5 prose prose-invert prose-sm max-w-none [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5">{!! $v->isi !!}</div>
                    <a href="{{ route('compro.visi-misi.detail', $v->id) }}" class="inline-block text-xs text-white/70 hover:text-white underline mt-1 mb-3">
                        Selengkapnya <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                @endforeach
                @if(count($visi) > 2)
                <button onclick="toggleItems('visi', this)"
                    class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-white/80 hover:text-white border border-white/30 hover:border-white rounded-lg px-4 py-2 transition">
                    <span>Lihat Lainnya ({{ count($visi) - 2 }})</span>
                    <i class="fas fa-chevron-down text-xs toggle-icon"></i>
                </button>
                @endif
            @else
                <p class="leading-relaxed text-gray-100">Menjadikan basket Pemalang sebagai olahraga unggulan yang berprestasi di tingkat regional maupun nasional.</p>
            @endif
        </div>

        {{-- MISI --}}
        <div class="bg-brandred-600 text-white rounded-2xl p-8 shadow-xl card-hover">
            <!-- <h3 class="text-3xl font-black mb-4">Misi</h3> -->
            @if(!empty($misi) && count($misi) > 0)
                <ul class="space-y-3" id="misi-list">
                    @foreach($misi as $mIdx => $m)
                    <li class="misi-item{{ $mIdx >= 2 ? ' hidden' : '' }} flex gap-3 items-start">
                        <!-- <i class="fas fa-check-circle mt-1 shrink-0"></i> -->
                        <div>
                            <div class="line-clamp-6 prose prose-invert prose-sm max-w-none [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5">{!! $m->isi !!}</div>
                            <a href="{{ route('compro.visi-misi.detail', $m->id) }}" class="text-xs text-white/70 hover:text-white underline mt-1 inline-block">
                                Selengkapnya <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>
                    </li>
                    @endforeach
                </ul>
                @if(count($misi) > 2)
                <button onclick="toggleItems('misi', this)"
                    class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-white/80 hover:text-white border border-white/30 hover:border-white rounded-lg px-4 py-2 transition">
                    <span>Lihat Lainnya ({{ count($misi) - 2 }})</span>
                    <i class="fas fa-chevron-down text-xs toggle-icon"></i>
                </button>
                @endif
            @endif
        </div>
    </div>
</section>

@push('scripts')
<script>
function toggleItems(type, btn) {
    const items = document.querySelectorAll('.' + type + '-item');
    const icon  = btn.querySelector('.toggle-icon');
    const label = btn.querySelector('span');
    const hidden = [...items].filter(el => el.classList.contains('hidden'));
    const isExpanded = hidden.length === 0;

    if (isExpanded) {
        items.forEach((el, i) => { if (i >= 2) el.classList.add('hidden'); });
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
        const total = items.length;
        label.textContent = 'Lihat Lainnya (' + (total - 2) + ')';
    } else {
        hidden.forEach(el => el.classList.remove('hidden'));
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
        label.textContent = 'Sembunyikan';
    }
}
</script>
@endpush

{{-- STRUKTUR ORGANISASI --}}
<section class="bg-gray-100 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-4">
        <h2 class="text-center text-3xl font-extrabold text-navy-900 mb-10">Struktur Organisasi</h2>

        @if(!empty($struktur) && count($struktur) > 0)
        <div class="grid sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 max-w-7xl mx-auto">
            @foreach($struktur as $s)
            <div class="bg-white rounded-2xl overflow-hidden shadow-lg card-hover text-center">
                @if(!empty($s->filename))
                    <img src="{{ asset('struktur/'.$s->filename) }}" alt="{{ $s->nama }}" class="w-full h-96 object-cover"
                         onerror="this.outerHTML='<div class=\'h-96 bg-gradient-to-br from-navy-800 to-navy-600 flex items-center justify-center\'><i class=\'fas fa-user-circle text-white text-8xl opacity-80\'></i></div>'">
                @else
                    <div class="h-56 bg-gradient-to-br from-navy-800 to-navy-600 flex items-center justify-center">
                        <i class="fas fa-user-circle text-white text-8xl opacity-80"></i>
                    </div>
                @endif
                <div class="p-5">
                    <div class="text-brandred-600 font-bold text-sm uppercase tracking-wider">{{ $s->jabatan }}</div>
                    <div class="text-navy-900 font-extrabold text-lg mt-1">{{ $s->nama }}</div>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <div class="text-center text-gray-500 py-8">Data struktur organisasi belum tersedia.</div>
        @endif
    </div>
</section>

@endsection
