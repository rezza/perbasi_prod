@extends('compro.layout')

@php
    $needsPerkumpulan = in_array('mitra_perkumpulan', $event['required_fields'] ?? []);
    $showForm = empty($registration) || (int) ($registration['status'] ?? null) === -1;
    $existingData = $registration['data_persyaratan'] ?? [];
    $lockedValues = $lockedValues ?? [];

    // Section per requirement key; keys not listed fall back to Berkas (uploads) or Informasi Tambahan.
    $sections = [
        'data_diri'  => ['title' => 'Data Diri',          'icon' => 'fa-user',           'keys' => ['nama', 'nik', 'no_kk', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'pekerjaan', 'no_pelajar']],
        'kontak'     => ['title' => 'Kontak',             'icon' => 'fa-phone',          'keys' => ['no_hp', 'email', 'no_darurat', 'hubungan_nodarurat']],
        'alamat'     => ['title' => 'Alamat',             'icon' => 'fa-location-dot',   'keys' => ['alamat']],
        'berkas'     => ['title' => 'Berkas',             'icon' => 'fa-file-arrow-up',  'keys' => ['ktp', 'kk', 'foto_profil']],
        'tambahan'   => ['title' => 'Informasi Tambahan', 'icon' => 'fa-circle-info',    'keys' => []],
    ];
    $groupedDefs = array_fill_keys(array_keys($sections), []);
    foreach ($event['persyaratan_definitions'] as $def) {
        if ($def['key'] === 'mitra_perkumpulan') continue;
        $target = 'tambahan';
        foreach ($sections as $sectionKey => $section) {
            if (in_array($def['key'], $section['keys'], true)) { $target = $sectionKey; break; }
        }
        if ($target === 'tambahan' && $def['tipe_input'] === 'upload') $target = 'berkas';
        $groupedDefs[$target][] = $def;
    }
    $sectionHeadingClass = 'flex items-center gap-2 text-sm font-bold text-navy-900 uppercase tracking-wide pb-2 mb-4 border-b border-gray-200';
    $lockedInputClass = 'w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm bg-gray-100 text-gray-600 cursor-not-allowed';
@endphp

@section('title', ($event['nama_event'] ?? 'Pendaftaran Event') . ' - PERBASI Kabupaten Pemalang')

@section('content')

<section class="bg-navy-900 pt-16">
    <div class="text-white py-16">
        <div class="max-w-3xl mx-auto px-4">
            <span class="inline-block bg-brandred-600 text-white text-xs px-3 py-1 rounded-full font-semibold mb-3">PENDAFTARAN EVENT</span>
            <h1 class="text-3xl md:text-4xl font-black">{{ $event['nama_event'] ?? '-' }}</h1>
            <div class="mt-3 text-sm text-gray-300 space-y-1">
                @if(!empty($event['tanggal_mulai']))
                    <div><i class="fas fa-calendar mr-2"></i>
                        {{ \Carbon\Carbon::parse($event['tanggal_mulai'])->translatedFormat('d M Y') }}
                        @if(!empty($event['tanggal_selesai']) && $event['tanggal_selesai'] !== $event['tanggal_mulai'])
                            &mdash; {{ \Carbon\Carbon::parse($event['tanggal_selesai'])->translatedFormat('d M Y') }}
                        @endif
                    </div>
                @endif
                @if(!empty($event['lokasi']))
                    <div><i class="fas fa-location-dot mr-2"></i>{{ $event['lokasi'] }}</div>
                @endif
                @if(!empty($event['biaya_pendaftaran']))
                    <div><i class="fas fa-money-bill mr-2"></i>Rp {{ number_format((float) $event['biaya_pendaftaran'], 0, ',', '.') }}</div>
                @endif
            </div>
        </div>
    </div>
</section>

<section class="max-w-3xl mx-auto px-4 -mt-8 pb-16">

    {{-- Status card: shows current registration state when one exists --}}
    @if(!empty($registration))
        @php
            $statusColor = ['0' => 'yellow', '1' => 'green', '-1' => 'red'][(string) $registration['status']] ?? 'gray';
        @endphp
        <div class="bg-white rounded-2xl shadow-xl p-6 mb-6 border-l-4 border-{{ $statusColor }}-500">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-500">Status Pendaftaran</div>
                    <div class="text-lg font-bold text-{{ $statusColor }}-600">{{ $registration['status_text'] ?? '-' }}</div>
                </div>
                @if(!empty($registration['jumlah_pengajuan']) && $registration['jumlah_pengajuan'] > 1)
                    <span class="text-xs text-gray-500">Pengajuan ke-{{ $registration['jumlah_pengajuan'] }}</span>
                @endif
            </div>
            @if((int) $registration['status'] === -1 && !empty($registration['catatan']))
                <div class="mt-3 text-sm text-gray-700 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                    <span class="font-semibold">Catatan penolakan:</span> {{ $registration['catatan'] }}
                </div>
                <p class="mt-3 text-sm text-gray-600">Silakan lengkapi ulang data di bawah untuk mengajukan pendaftaran kembali.</p>
            @endif
        </div> 
    @endif

    @if($showForm)
    <div class="bg-white rounded-2xl shadow-xl p-6 md:p-8">
        <form id="eventForm" method="POST" action="{{ route('compro.event.submit', $eventId) }}" class="space-y-8">
            @csrf

            @if($needsPerkumpulan)
                <section>
                    <h2 class="{{ $sectionHeadingClass }}"><i class="fas fa-people-group text-brandred-600"></i>Keanggotaan</h2>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Mitra Perkumpulan</label>
                        <select name="id_perkumpulan" required
                                class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-navy-700">
                            <option value="">Pilih Perkumpulan</option>
                            @foreach($myPerkumpulan as $p)
                                <option value="{{ $p['id'] }}" {{ ($registration['id_perkumpulan'] ?? null) === $p['id'] ? 'selected' : '' }}>
                                    {{ $p['nama'] }}
                                </option>
                            @endforeach
                        </select>
                        @if(empty($myPerkumpulan))
                            <p class="text-xs text-red-600 mt-1">Anda belum terdaftar sebagai anggota perkumpulan manapun yang diizinkan pada event ini.</p>
                        @endif
                    </div>
                </section>
            @endif

            @foreach($groupedDefs as $sectionKey => $defs)
            @continue(empty($defs))
            <section class="space-y-5">
                <h2 class="{{ $sectionHeadingClass }}"><i class="fas {{ $sections[$sectionKey]['icon'] }} text-brandred-600"></i>{{ $sections[$sectionKey]['title'] }}</h2>

            @foreach($defs as $def)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $def['label'] }}</label>

                    @if(array_key_exists($def['key'], $lockedValues))
                        @php $lockedValue = $lockedValues[$def['key']]; @endphp

                        @if($def['tipe_input'] === 'upload')
                            <div class="{{ $lockedInputClass }}">
                                <a href="{{ asset('profil/'.$lockedValue) }}" target="_blank" rel="noopener"
                                   class="text-navy-700 underline">
                                    <i class="fas fa-paperclip mr-1"></i>Lihat berkas dari profil
                                </a>
                            </div>
                        @elseif($def['tipe_input'] === 'textarea')
                            <textarea rows="3" disabled class="{{ $lockedInputClass }}">{{ $lockedValue }}</textarea>
                        @else
                            @php
                                $displayValue = $def['key'] === 'jenis_kelamin'
                                    ? ['1' => 'Laki-laki', '0' => 'Perempuan'][$lockedValue] ?? $lockedValue
                                    : $lockedValue;
                            @endphp
                            <input type="text" value="{{ $displayValue }}" disabled class="{{ $lockedInputClass }}">
                        @endif
                        <p class="text-xs text-gray-500 mt-1">Diambil dari profil Anda. Ubah di halaman profil bila perlu.</p>

                    @elseif($def['tipe_input'] === 'upload')
                        <input type="file" data-key="{{ $def['key'] }}" accept="image/jpeg,image/png,application/pdf"
                               class="upload-input w-full border border-gray-300 rounded-lg px-4 py-2 text-sm">
                        <input type="hidden" name="data_persyaratan[{{ $def['key'] }}]"
                               value="{{ $existingData[$def['key']] ?? '' }}" class="upload-value">
                        <p class="upload-status text-xs text-gray-500 mt-1">
                            {{ !empty($existingData[$def['key']]) ? 'File tersimpan: '.$existingData[$def['key']] : 'JPG/PNG/PDF, maks 2MB' }}
                        </p>

                    @elseif($def['tipe_input'] === 'textarea')
                        <textarea name="data_persyaratan[{{ $def['key'] }}]" rows="3"
                                  class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700">{{ $existingData[$def['key']] ?? '' }}</textarea>

                    @elseif($def['tipe_input'] === 'date')
                        <input type="date" name="data_persyaratan[{{ $def['key'] }}]" value="{{ $existingData[$def['key']] ?? '' }}"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700">

                    @elseif($def['tipe_input'] === 'dropdown' && $def['key'] === 'jenis_kelamin')
                        <select name="data_persyaratan[{{ $def['key'] }}]"
                                class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-navy-700">
                            <option value="">Pilih</option>
                            <option value="Laki-laki" {{ ($existingData[$def['key']] ?? '') === 'Laki-laki' ? 'selected' : '' }}>Laki-laki</option>
                            <option value="Perempuan" {{ ($existingData[$def['key']] ?? '') === 'Perempuan' ? 'selected' : '' }}>Perempuan</option>
                        </select>

                    @elseif($def['tipe_input'] === 'number')
                        <input type="number" name="data_persyaratan[{{ $def['key'] }}]" value="{{ $existingData[$def['key']] ?? '' }}"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700">

                    @else
                        <input type="text" name="data_persyaratan[{{ $def['key'] }}]" value="{{ $existingData[$def['key']] ?? '' }}"
                               class="w-full border border-gray-300 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-navy-700">
                    @endif
                </div>
            @endforeach
            </section>
            @endforeach

            <button type="submit"
                    class="w-full bg-brandred-600 hover:bg-brandred-700 text-white font-semibold py-3 rounded-lg shadow transition">
                {{ !empty($registration) ? 'Daftar Ulang' : 'Daftar Sekarang' }}
            </button>
        </form>
    </div>
    @endif
</section>

@push('scripts')
<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const eventId = @json($eventId);

    document.querySelectorAll('.upload-input').forEach(function (input) {
        input.addEventListener('change', async function () {
            const file = this.files[0];
            if (!file) return;

            const key = this.dataset.key;
            const wrapper = this.closest('div');
            const hidden = wrapper.querySelector('.upload-value');
            const status = wrapper.querySelector('.upload-status');
            const safeName = file.name.replace(/[^a-zA-Z0-9._-]/g, '_');
            const filename = `event_${eventId}_${key}_${Date.now()}_${safeName}`;

            status.textContent = 'Mengunggah...';

            const form = new FormData();
            form.append('uploaded_file', file);
            form.append('filename', filename);

            try {
                const res = await fetch('{{ route('compro.event.upload') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: form,
                });
                const json = await res.json();
                if (json.status === '00') {
                    hidden.value = filename;
                    status.textContent = 'Berhasil diunggah: ' + file.name;
                    status.classList.remove('text-red-600');
                } else {
                    status.textContent = json.message || 'Gagal mengunggah file';
                    status.classList.add('text-red-600');
                }
            } catch (e) {
                status.textContent = 'Gagal mengunggah file';
                status.classList.add('text-red-600');
            }
        });
    });
})();
</script>
@endpush

@endsection
