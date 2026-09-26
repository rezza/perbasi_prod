{{--
    Info strip (date, start time, check-in deadline, location) overlaid on the
    bottom of a cover. Renders nothing unless the news is linked to a session
    with check-in enabled. The parent must be `relative` (and `overflow-hidden`
    if the cover has rounded corners).

    Params:
      $info : session summary (array or object with jam_mulai, jam_checkin,
              checkin_enabled, lokasi) or null
      $size : 'sm' (cards) or 'lg' (hero / detail cover). Default 'sm'
      $pos  : positioning classes. Default 'absolute inset-x-0 bottom-0'
--}}
@php
    $i = is_array($info ?? null) ? (object) $info : ($info ?? null);
    $start = $checkin = null;
    if ($i && !empty($i->checkin_enabled) && !empty($i->jam_mulai)) {
        try {
            $start   = \Carbon\Carbon::parse($i->jam_mulai)->locale('id');
            $checkin = !empty($i->jam_checkin) ? \Carbon\Carbon::parse($i->jam_checkin)->locale('id') : null;
        } catch (\Throwable $e) {
            $start = null;
        }
    }
    $lokasi = trim($i->lokasi ?? '');
    $lg     = ($size ?? 'sm') === 'lg';
    $pos    = $pos ?? 'absolute inset-x-0 bottom-0';
@endphp
@if($start)
<div class="{{ $pos }} max-w-7xl mx-auto z-10 rounded-lg bg-[#101B3D]/95 text-white {{ $lg ? 'px-4 py-3 sm:px-6 sm:py-4' : 'px-3 py-2' }}">
    {{-- One row. When space runs out the date wraps to 2 lines and the location clamps at 2 lines. --}}
    <div class="{{ $lg ? 'max-w-7xl mx-auto text-xs sm:text-sm gap-x-3 sm:gap-x-5' : 'text-[10px] leading-tight gap-x-2' }} flex items-center">
        <div class="flex items-center gap-1.5 min-w-0 flex-[2]">
            <i class="far fa-calendar-alt shrink-0 {{ $lg ? 'text-base sm:text-2xl' : 'text-xs' }}"></i>
            <span class="font-medium">{{ $start->translatedFormat($lg ? 'l, d F Y' : 'D, d M Y') }}</span>
        </div>

        <span class="self-stretch w-px bg-white/25 shrink-0"></span>
        <div class="flex items-center gap-1.5 shrink-0">
            <i class="far fa-clock {{ $lg ? 'text-base sm:text-2xl' : 'text-xs' }}"></i>
            <div>
                <div class="font-bold whitespace-nowrap">{{ $start->format('H:i') }} WIB</div>
                <div class="text-white/70 whitespace-nowrap {{ $lg ? 'text-[10px] sm:text-xs' : 'text-[8px]' }}">{{ $lg ? 'Mulai Pertandingan' : 'Mulai' }}</div>
            </div>
        </div>

        @if($checkin)
            <span class="self-stretch w-px bg-white/25 shrink-0"></span>
            <div class="flex items-center gap-1.5 shrink-0">
                <i class="far fa-user {{ $lg ? 'text-base sm:text-2xl' : 'text-xs' }}"></i>
                <div>
                    <div class="font-bold whitespace-nowrap">{{ $checkin->format('H:i') }} WIB</div>
                    <div class="text-white/70 whitespace-nowrap {{ $lg ? 'text-[10px] sm:text-xs' : 'text-[8px]' }}">{{ $lg ? 'Check In Maksimal' : 'Check In Maks.' }}</div>
                </div>
            </div>
        @endif

        @if($lokasi !== '')
            <span class="self-stretch w-px bg-white/25 shrink-0 {{ $lg ? 'sm:hidden' : '' }}"></span>
            <div class="flex items-center gap-1.5 min-w-0 flex-[2] {{ $lg ? 'sm:border sm:border-white/40 sm:rounded-xl sm:px-3 sm:py-2' : '' }}">
                <i class="fas fa-map-marker-alt shrink-0 {{ $lg ? 'text-base sm:text-xl' : 'text-xs' }}"></i>
                <span class="line-clamp-2 break-words">{{ $lokasi }}</span>
            </div>
        @endif
    </div>
</div>
@endif
