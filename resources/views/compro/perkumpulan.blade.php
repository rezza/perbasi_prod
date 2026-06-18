@extends('compro.layout')

@section('title', 'Perkumpulan - PERBASI Kabupaten Pemalang')

@section('content')

<section class="section-bg pt-16">
    <div class="text-white py-20 text-center">
        <h1 class="text-4xl md:text-5xl font-black">Perkumpulan</h1>
        <p class="mt-3 text-gray-300">Daftar perkumpulan anggota PERBASI Kabupaten Pemalang</p>
    </div>
</section>

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-4 py-12">

    {{-- Search & Filter --}}
    <form method="GET" action="{{ route('compro.perkumpulan') }}" class="mb-8 space-y-3">
        <div class="flex gap-3 w-full">
            <input id="searchInput" name="q" type="text"
                   value="{{ $keyword ?? '' }}"
                   placeholder="Cari nama, nomor, atau keterangan perkumpulan..."
                   class="flex flex-1 border border-gray-300 rounded-md px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700">
            @if($keyword || $id_status_anggota)
            <a href="{{ route('compro.perkumpulan') }}"
               class="bg-gray-100 hover:bg-gray-200 text-gray-600 px-4 py-2.5 rounded-xl text-sm transition flex items-center">
                Reset
            </a>
            @endif
        </div>
        <div class="flex flex-wrap gap-3">
            <select name="id_status_anggota"
                    onchange="this.form.submit()"
                    class="flex md:flex-1 border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700 bg-white" style="height: 45px;">
                <option value="">Semua Kategori</option>
                @foreach($statusAnggotaList as $sa)
                <option value="{{ $sa->id }}" {{ (string)$id_status_anggota === (string)$sa->id ? 'selected' : '' }}>
                    {{ $sa->nama }}
                </option>
                @endforeach
            </select>
        </div>
    </form>

    {{-- Count label --}}
    @if($keyword || $id_status_anggota)
    <p class="text-sm text-gray-500 mb-4">Menampilkan {{ count($perkumpulan) }} perkumpulan</p>
    @endif

    {{-- Table --}}
    @if(!empty($perkumpulan) && count($perkumpulan) > 0)
    <div class="overflow-x-auto rounded-2xl shadow-lg">
        <table class="w-full text-sm text-left">
            <thead class="bg-navy-800 text-white">
                <tr>
                    <th class="px-5 py-4 font-semibold">#</th>
                    <th class="px-5 py-4 font-semibold">Logo</th>
                    <th class="px-5 py-4 font-semibold">Nama Perkumpulan</th>
                    <th class="px-5 py-4 font-semibold">No. Perkumpulan</th>
                    <th class="px-5 py-4 font-semibold">Tahun Gabung</th>
                    <th class="px-5 py-4 font-semibold">Kategori</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($perkumpulan as $i => $p)
                <tr class="bg-white hover:bg-gray-50 transition cursor-pointer"
                    onclick="window.location='{{ route('compro.perkumpulan.detail', $p->id) }}'">
                    <td class="px-5 py-4 text-gray-500">{{ $i + 1 }}</td>
                    <td class="px-5 py-4">
                        @if(!empty($p->logo))
                            <img src="{{ asset('perkumpulan/'.$p->logo) }}" alt="{{ $p->nama }}"
                                 class="w-10 h-10 object-contain rounded"
                                 onerror="this.style.display='none'">
                        @else
                            <div class="w-10 h-10 bg-gray-100 rounded flex items-center justify-center">
                                <i class="fas fa-shield-halved text-gray-300 text-lg"></i>
                            </div>
                        @endif
                    </td>
                    <td class="px-5 py-4">
                        <div class="font-semibold text-navy-900 hover:text-brandred-600">{{ $p->nama }}</div>
                        @if(!empty($p->keterangan))
                        <div class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ $p->keterangan }}</div>
                        @endif
                    </td>
                    <td class="px-5 py-4 font-mono text-navy-700">{{ $p->no_perkumpulan ?? '-' }}</td>
                    <td class="px-5 py-4 text-gray-600">{{ $p->tahun_gabung ?? '-' }}</td>
                    <td class="px-5 py-4">
                        <span class="inline-block px-3 py-1 rounded-full text-xs font-bold
                            {{ $p->nama_status_anggota ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $p->nama_status_anggota ?? 'Aktif' }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="text-center text-gray-500 py-16">
        <i class="fas fa-shield-halved text-5xl mb-4 opacity-30 block"></i>
        <p class="text-lg font-medium">Tidak ada perkumpulan yang ditemukan.</p>
    </div>
    @endif

</section>

@push('scripts')
<script>
    // Submit form on Enter in search input
    document.getElementById('searchInput').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') this.form.submit();
    });
</script>
@endpush

@endsection
