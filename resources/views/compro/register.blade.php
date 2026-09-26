@extends('compro.layout')

@section('title', 'Daftar Akun - PERBASI Kabupaten Pemalang')

@section('content')

<section class="bg-navy-900 pt-16">
    <div class="text-white py-20 text-center">
        <h1 class="text-4xl md:text-5xl font-black">Daftar Akun</h1>
        <p class="mt-3 text-gray-300">Buat akun member untuk mendaftar event PERBASI</p>
    </div>
</section>

<section class="max-w-md mx-auto px-4 -mt-8 pb-16">
    <div class="bg-white rounded-2xl shadow-xl p-8">
        <form method="POST" action="{{ route('compro.register') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nama Lengkap</label>
                <input type="text" name="nama" value="{{ old('nama') }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" name="password" required minlength="6"
                       class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Provinsi</label>
                <select id="provinsiSelect" required
                        class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-navy-700">
                    <option value="">Memuat...</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Kota / Kabupaten</label>
                <select id="kotaSelect" name="id_kota" required disabled
                        class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-navy-700">
                    <option value="">Pilih provinsi dahulu</option>
                </select>
            </div>

            <button type="submit"
                    class="w-full bg-brandred-600 hover:bg-brandred-700 text-white font-semibold py-2.5 rounded-lg shadow transition">
                Daftar
            </button>
        </form>

        <p class="text-center text-sm text-gray-600 mt-6">
            Sudah punya akun?
            <a href="{{ route('compro.login') }}" class="text-brandred-600 font-semibold hover:underline">Masuk</a>
        </p>
    </div>
</section>

@push('scripts')
<script>
(async function () {
    const provinsiSelect = document.getElementById('provinsiSelect');
    const kotaSelect = document.getElementById('kotaSelect');

    const PROVINSI_URL = '{{ url("/api/helper/provinsi") }}';
    const KOTA_URL      = '{{ url("/api/helper/kota") }}';

    async function loadProvinsi() {
        try {
            const res = await fetch(PROVINSI_URL, { method: 'POST' });
            const json = await res.json();
            provinsiSelect.innerHTML = '<option value="">Pilih Provinsi</option>' +
                (json.data || []).map(p => `<option value="${p.id}">${p.name}</option>`).join('');
        } catch (e) {
            provinsiSelect.innerHTML = '<option value="">Gagal memuat provinsi</option>';
        }
    }

    provinsiSelect.addEventListener('change', async function () {
        kotaSelect.disabled = true;
        kotaSelect.innerHTML = '<option value="">Memuat...</option>';
        if (!this.value) {
            kotaSelect.innerHTML = '<option value="">Pilih provinsi dahulu</option>';
            return;
        }
        try {
            const body = new URLSearchParams({ id_provinsi: this.value });
            const res = await fetch(KOTA_URL, { method: 'POST', body });
            const json = await res.json();
            kotaSelect.innerHTML = '<option value="">Pilih Kota/Kabupaten</option>' +
                (json.data || []).map(k => `<option value="${k.id}">${k.type} ${k.name}</option>`).join('');
            kotaSelect.disabled = false;
        } catch (e) {
            kotaSelect.innerHTML = '<option value="">Gagal memuat kota</option>';
        }
    });

    loadProvinsi();
})();
</script>
@endpush

@endsection
