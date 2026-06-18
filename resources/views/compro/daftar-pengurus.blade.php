@extends('compro.layout')

@section('title', 'Daftar Pengurus - PERBASI Kabupaten Pemalang')

@section('content')

<section class="section-bg pt-16">
    <div class="text-white py-20 text-center">
        <h1 class="text-4xl md:text-5xl font-black">Daftar Pengurus</h1>
        <p class="mt-3 text-gray-300">SDM terdaftar PERBASI Kabupaten Pemalang</p>
    </div>
</section>

<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-4 py-12">

    {{-- Search & Filter --}}
    <div class="mb-8 space-y-3">
        <div class="flex gap-3 w-full">
            <input id="searchInput" type="text" placeholder="Cari nama, jabatan, atau perkumpulan..."
                   class="flex flex-1 border border-gray-300 rounded-md px-4 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700"
                   style="height: 45px;">
            <button id="resetBtn" onclick="resetFilters()"
                    class="hidden bg-green-500 hover:bg-green-600 text-white px-4 py-2.5 rounded-md text-sm transition">
                Reset
            </button>
        </div>
        <div class="flex flex-wrap gap-3">
            <select id="genderSelect"
                    class="flex md:flex-1 border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700 bg-white" style="height: 45px;">
                <option value="">Semua Gender</option>
                <option value="1">Laki-laki</option>
                <option value="0">Perempuan</option>
            </select>

            <select id="posisiSelect"
                    class="flex md:flex-1 border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700 bg-white">
                <option value="">Semua Jabatan</option>
                @foreach($posisiList as $pos)
                <option value="{{ $pos }}">{{ $pos }}</option>
                @endforeach
            </select>

            <select id="perkumpulanSelect"
                    class="flex md:flex-1 border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700 bg-white">
                <option value="">Semua Perkumpulan</option>
                @foreach($perkumpulanList as $pk)
                <option value="{{ $pk->id }}">{{ $pk->nama }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Count label --}}
    <p id="countLabel" class="text-sm text-gray-500 mb-4 hidden"></p>

    {{-- Results grid --}}
    <div id="resultsGrid" class="grid gap-4" style="grid-template-columns: repeat(auto-fill, 300px); justify-content: start;">
        <div id="loadingState" class="col-span-full py-20 text-center text-gray-400">
            <i class="fas fa-spinner fa-spin text-3xl"></i>
        </div>
    </div>

    {{-- Pagination --}}
    <div id="paginationContainer" class="mt-8 flex justify-center gap-2 hidden"></div>

</section>

@push('scripts')
<script>
(function () {
    const API_URL   = '{{ url("/api/public/pengurus") }}';
    const BASE_URL  = '{{ url("/") }}';
    const SHIELD3   = '{{ asset("shield3.png") }}';
    const LOGO      = '{{ asset("logo.png") }}';

    const PER_PAGE  = 12;
    let currentPage = 1;
    let searchTimer = null;

    const grid       = document.getElementById('resultsGrid');
    const loading    = document.getElementById('loadingState');
    const countLabel = document.getElementById('countLabel');
    const pagination = document.getElementById('paginationContainer');
    const resetBtn   = document.getElementById('resetBtn');

    /* ---------- filters ---------- */
    function getParams() {
        return {
            q:               document.getElementById('searchInput').value.trim(),
            jenis_kelamin:   document.getElementById('genderSelect').value,
            posisi:          document.getElementById('posisiSelect').value,
            id_perkumpulan:  document.getElementById('perkumpulanSelect').value,
            page:            currentPage,
        };
    }

    function hasFilter(p) {
        return p.q || p.jenis_kelamin || p.posisi || p.id_perkumpulan;
    }

    function resetFilters() {
        document.getElementById('searchInput').value       = '';
        document.getElementById('genderSelect').value      = '';
        document.getElementById('posisiSelect').value      = '';
        document.getElementById('perkumpulanSelect').value = '';
        currentPage = 1;
        fetchData();
    }

    /* ---------- fetch ---------- */
    async function fetchData() {
        loading.classList.remove('hidden');
        grid.querySelectorAll('.member-card').forEach(c => c.remove());
        pagination.classList.add('hidden');
        countLabel.classList.add('hidden');

        const p = getParams();
        resetBtn.classList.toggle('hidden', !hasFilter(p));

        const qs   = new URLSearchParams(p).toString();
        const res  = await fetch(`${API_URL}?${qs}`);
        const json = await res.json();

        loading.classList.add('hidden');
        renderCards(json.data, json.total);
        renderPagination(json.current_page, json.last_page, json.total);
    }

    /* ---------- render cards ---------- */
    function badgeClass(status) {
        return {
            '2':  'bg-green-400/20 text-green-200 border-green-400/30',
            '1':  'bg-blue-400/20 text-blue-200 border-blue-400/30',
            '0':  'bg-yellow-400/20 text-yellow-200 border-yellow-400/30',
        }[String(status)] || 'bg-white/10 text-red-200 border-white/20';
    }

    function cardHtml(p) {
        const photoLayer = p.foto_url
            ? `<img src="${p.foto_url}" alt="${esc(p.nama)}"
                    class="absolute inset-0 w-full h-full object-cover object-top"
                    style="clip-path: polygon(50% 0%, 100% 18%, 100% 68%, 50% 100%, 0% 68%, 0% 18%)"
                    onerror="this.style.display='none'">`
            : `<div class="absolute inset-0 bg-white/15"
                    style="clip-path: polygon(50% 0%, 100% 18%, 100% 68%, 50% 100%, 0% 68%, 0% 18%)"></div>`;

        const noAnggota = p.no_anggota
            ? `<div class="text-white text-[10px] font-mono font-bold tracking-wider">${esc(p.no_anggota)}</div>` : '';

        const posisi = p.posisi
            ? `<div class="text-red-300 text-xs italic mt-0.5">${esc(p.posisi)}</div>` : '';

        const perkumpulan = p.nama_perkumpulan
            ? `<div class="text-red-300/70 text-[10px] mt-1 truncate">
                   <i class="fas fa-shield-halved mr-1"></i>${esc(p.nama_perkumpulan)}
               </div>` : '';

        return `
        <div class="member-card relative rounded-2xl overflow-hidden shadow-xl select-none"
             style="background: linear-gradient(180deg, #921212 0%, #7a0e0e 50%, #6b0c0c 100%); min-height: 380px; width:300px">
            <div class="relative flex justify-between items-start px-4 pt-4">
                <div class="flex items-center gap-2">
                    <img src="${LOGO}" alt="Logo" class="w-7 h-7 object-contain drop-shadow">
                    <div class="leading-tight">
                        <div class="text-white text-[10px] font-extrabold tracking-widest">PERBASI</div>
                        <div class="text-red-300 text-[8px] tracking-wide -mt-0.5">KAB. PEMALANG</div>
                    </div>
                </div>
                <div class="text-right">
                    ${noAnggota}
                    <div class="text-red-300 text-[8px] mt-0.5">${esc(p.tahun)}</div>
                </div>
            </div>
            <div class="relative flex justify-center mt-6 mb-6">
                <div class="relative" style="width:150px; height:192px;">
                    ${photoLayer}
                    <img src="${SHIELD3}" alt="" class="absolute inset-0 w-full h-full pointer-events-none" draggable="false">
                </div>
            </div>
            <div class="relative px-4 pb-4 text-center">
                <div class="text-white font-extrabold text-sm leading-tight">${esc(p.nama)}</div>
                ${posisi}
                ${perkumpulan}
                <span class="inline-block mt-2 px-3 py-0.5 rounded-full text-[10px] font-bold border ${badgeClass(p.status)}">
                    ${esc(p.status_label)}
                </span>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r from-transparent via-white/30 to-transparent"></div>
        </div>`;
    }

    function renderCards(data, total) {
        if (!data || data.length === 0) {
            grid.insertAdjacentHTML('beforeend',
                `<div class="member-card col-span-full py-20 text-center text-gray-400">
                    <i class="fas fa-user-tie text-5xl mb-4 opacity-30 block"></i>
                    <p class="text-lg font-medium">Tidak ada pengurus yang ditemukan.</p>
                </div>`);
            countLabel.classList.add('hidden');
            return;
        }

        countLabel.textContent = `Menampilkan ${data.length} dari ${total} pengurus`;
        countLabel.classList.remove('hidden');
        data.forEach(p => grid.insertAdjacentHTML('beforeend', cardHtml(p)));
    }

    /* ---------- pagination ---------- */
    function renderPagination(current, last, total) {
        if (last <= 1) { pagination.classList.add('hidden'); return; }

        pagination.innerHTML = '';
        pagination.classList.remove('hidden');

        const btn = (label, page, active = false, disabled = false) => {
            const base = 'px-3 py-1.5 rounded-lg text-sm font-medium transition border';
            const cls  = disabled  ? `${base} border-gray-200 text-gray-300 cursor-not-allowed` :
                         active    ? `${base} border-navy-700 bg-navy-700 text-white` :
                                     `${base} border-gray-300 text-gray-600 hover:bg-gray-100`;
            return `<button class="${cls}" ${disabled ? 'disabled' : ''} onclick="goPage(${page})">${label}</button>`;
        };

        let html = btn('&laquo;', current - 1, false, current === 1);

        const delta = 2;
        for (let i = 1; i <= last; i++) {
            if (i === 1 || i === last || (i >= current - delta && i <= current + delta)) {
                html += btn(i, i, i === current);
            } else if (i === current - delta - 1 || i === current + delta + 1) {
                html += `<span class="px-2 py-1.5 text-gray-400">…</span>`;
            }
        }

        html += btn('&raquo;', current + 1, false, current === last);
        pagination.innerHTML = html;
    }

    /* ---------- public helpers ---------- */
    window.goPage = function (page) {
        currentPage = page;
        fetchData();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    window.resetFilters = resetFilters;

    function esc(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* ---------- event listeners ---------- */
    document.getElementById('searchInput').addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { currentPage = 1; fetchData(); }, 400);
    });

    ['genderSelect','posisiSelect','perkumpulanSelect'].forEach(id => {
        document.getElementById(id).addEventListener('change', () => { currentPage = 1; fetchData(); });
    });

    /* ---------- init ---------- */
    fetchData();
})();
</script>
@endpush

@endsection
