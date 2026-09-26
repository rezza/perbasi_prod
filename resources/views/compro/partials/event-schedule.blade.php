{{--
    Jadwal Event: one card per event of a banner/berita (a news can carry many
    events, each with its own registration + check-in). Each card decides its
    own CTA from the visitor's registration/check-in state.

    Params:
      $schedules : list of arrays from EventPendaftaran::getMemberSchedulesByNewsId()
                   (+ `role` / `prefill` filled by ComproController::scheduleInfo())
--}}
@php
    $schedules = $schedules ?? [];
    $nowTs = now();
    $loggedIn = (bool) session('jwt_token');
@endphp
@if(count($schedules) > 0)
<div class="mb-8">
    <h2 class="text-lg font-bold text-navy-900 mb-3">
        {{ count($schedules) > 1 ? 'Jadwal Event ('.count($schedules).')' : 'Jadwal Event' }}
    </h2>
    <div class="space-y-4">
        @foreach($schedules as $s)
            @php
                try { $tgl = \Carbon\Carbon::parse($s['tanggal_mulai'])->locale('id'); } catch (\Throwable $ex) { $tgl = null; }
                $isSelesai = $s['status'] === 'selesai';
                $reg = $s['my_registration'];
                $regStatus = $reg ? (int) $reg['status'] : null;
                $myCheckin = $s['my_checkin'];
                $checkinOpen = false;
                if ($s['checkin_enabled'] && !empty($s['jam_checkin'])) {
                    try { $checkinOpen = $nowTs->greaterThanOrEqualTo(\Carbon\Carbon::parse($s['tanggal_mulai'].' '.$s['jam_checkin'])); } catch (\Throwable $ex) {}
                }
                $panelId = 'checkin-panel-'.$s['id'];
            @endphp
            <div class="rounded-xl border {{ $isSelesai ? 'border-gray-200 bg-gray-50' : 'border-navy-900/10 bg-white shadow-sm' }} p-5">
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div>
                        <div class="font-bold text-navy-900 text-base">{{ $s['nama_event'] }}</div>
                        <div class="text-sm text-gray-500 mt-1 flex flex-wrap gap-x-4 gap-y-1">
                            <span><i class="far fa-calendar mr-1"></i>{{ $tgl ? $tgl->translatedFormat('l, d F Y') : $s['tanggal_mulai'] }}</span>
                            @if(!empty($s['jam_mulai']))
                                <span><i class="far fa-clock mr-1"></i>Mulai {{ $s['jam_mulai'] }} WIB</span>
                            @endif
                            @if($s['checkin_enabled'] && !empty($s['jam_checkin']))
                                <span><i class="fas fa-user-check mr-1"></i>Check In {{ $s['jam_checkin'] }} WIB</span>
                            @endif
                            @if(!empty($s['lokasi']))
                                <span><i class="fas fa-map-marker-alt mr-1"></i>{{ $s['lokasi'] }}</span>
                            @endif
                            @if(!empty($s['biaya_pendaftaran']) && (float) $s['biaya_pendaftaran'] > 0)
                                <span><i class="fas fa-tag mr-1"></i>Rp {{ number_format((float) $s['biaya_pendaftaran'], 0, ',', '.') }}</span>
                            @endif
                        </div>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @forelse($s['perkumpulan'] as $p)
                                <span class="text-xs bg-navy-900/5 text-navy-900 px-2 py-0.5 rounded-full">{{ $p['nama'] }}</span>
                            @empty
                                <span class="text-xs text-gray-400">Terbuka untuk semua perkumpulan</span>
                            @endforelse
                        </div>
                    </div>

                    <div class="shrink-0">
                        @if($isSelesai)
                            <span class="inline-block bg-gray-200 text-gray-600 font-semibold px-4 py-2 rounded-lg text-sm">Selesai</span>
                        @elseif($regStatus === null)
                            <a href="{{ route('compro.event.form', $s['id']) }}" class="inline-block bg-brandred-600 hover:bg-brandred-700 text-white font-semibold px-5 py-2 rounded-lg shadow transition text-sm">Daftar Sekarang</a>
                        @elseif($regStatus === 0)
                            <a href="{{ route('compro.event.form', $s['id']) }}" class="inline-block bg-yellow-500 text-white font-semibold px-5 py-2 rounded-lg text-sm">Menunggu Persetujuan</a>
                        @elseif($regStatus === -1)
                            <a href="{{ route('compro.event.form', $s['id']) }}" class="inline-block bg-brandred-600 text-white font-semibold px-5 py-2 rounded-lg shadow text-sm">Ditolak · Daftar Ulang</a>
                        @elseif(!$s['checkin_enabled'])
                            <a href="{{ route('compro.event.form', $s['id']) }}" class="inline-block bg-green-600 text-white font-semibold px-5 py-2 rounded-lg text-sm"><i class="fas fa-check-circle mr-1"></i>Terdaftar</a>
                        @elseif($myCheckin)
                            <span class="inline-block bg-green-500 text-white font-semibold px-4 py-2 rounded-lg text-sm">
                                <i class="fas fa-check-circle mr-1"></i>Sudah Check In ({{ (int) ($myCheckin['status_kehadiran'] ?? 0) === 1 ? 'Hadir' : 'Tidak Hadir' }})
                            </span>
                        @elseif(!$checkinOpen)
                            <span class="inline-block bg-gray-200 text-gray-500 font-semibold px-4 py-2 rounded-lg text-sm">Check In dibuka {{ $s['jam_checkin'] }} WIB</span>
                        @else
                            <button type="button" onclick="document.getElementById('{{ $panelId }}').classList.toggle('hidden')"
                                class="bg-navy-900 hover:bg-navy-800 text-white font-semibold px-5 py-2 rounded-lg shadow transition text-sm">
                                <i class="fas fa-user-check mr-1"></i>Check In
                            </button>
                        @endif
                    </div>
                </div>

                @if($regStatus === 1 && $s['checkin_enabled'] && !$myCheckin && $checkinOpen && !$isSelesai)
                    <div id="{{ $panelId }}" class="hidden mt-4 bg-gray-50 rounded-lg p-4">
                        <form method="POST" action="{{ route('compro.event.checkin.submit', $s['id']) }}">
                            @csrf
                            @if(($s['role'] ?? null) === 'pemain')
                                <div class="mb-3">
                                    <label class="block text-xs font-semibold mb-1">Posisi</label>
                                    <input type="text" name="posisi" value="{{ old('posisi', $s['prefill']['posisi'] ?? '') }}" class="w-full border rounded-lg px-3 py-2 text-sm" required>
                                </div>
                                <div class="mb-3">
                                    <label class="block text-xs font-semibold mb-1">Nomor Punggung</label>
                                    <input type="number" name="no_punggung" value="{{ old('no_punggung', $s['prefill']['no_punggung'] ?? '') }}" class="w-full border rounded-lg px-3 py-2 text-sm" required>
                                </div>
                            @endif
                            <div class="mb-3">
                                <label class="block text-xs font-semibold mb-1">Konfirmasi Kehadiran</label>
                                <div class="flex gap-3">
                                    <label class="flex items-center gap-1 text-sm"><input type="radio" name="status_kehadiran" value="1" checked> Ya, Hadir</label>
                                    <label class="flex items-center gap-1 text-sm"><input type="radio" name="status_kehadiran" value="0"> Tidak Hadir</label>
                                </div>
                            </div>
                            <button type="submit" class="bg-brandred-600 text-white font-semibold px-6 py-2 rounded-lg text-sm">Kirim Check In</button>
                        </form>
                    </div>
                @endif

                @if($s['checkin_enabled'])
                    <details class="mt-4">
                        <summary class="cursor-pointer text-sm font-semibold text-navy-900 select-none">
                            <i class="fas fa-users mr-1"></i> Daftar Hadir
                        </summary>
                        <div class="mt-3">
                            @include('compro.partials.attendance-list', [
                                'attendanceUrl' => route('compro.event.checkin.attendance', $s['id']),
                                'lazy' => true,
                            ])
                        </div>
                    </details>
                @endif
            </div>
        @endforeach
    </div>
    @unless($loggedIn)
        <p class="text-xs text-gray-500 mt-3">
            <a href="{{ route('compro.login') }}" class="text-brandred-600 font-semibold">Masuk</a> untuk mendaftar atau check in.
        </p>
    @endunless
</div>
@endif
