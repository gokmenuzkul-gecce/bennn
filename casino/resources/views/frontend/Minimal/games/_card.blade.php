{{-- One lobby game card. Expects $meta from the lobby cardMeta() builder. --}}
<div class="game-card group aspect-[3/4] flex flex-col justify-end" data-title="{{ \Illuminate\Support\Str::lower($meta['title'] . ' ' . $meta['name']) }}">
    <span class="card-shine"></span>
    <img src="{{ $meta['icon'] }}"
         onerror="this.src='/frontend/Default/ico/DayofDead.jpg'"
         alt="{{ $meta['title'] }}"
         class="absolute inset-0 w-full h-full object-cover group-hover:scale-110 transition-transform duration-700"
         loading="lazy" decoding="async">

    <span class="absolute top-1.5 left-1.5 z-10 text-[8px] font-mono-jet font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-md backdrop-blur-md {{ $meta['badgeClass'] }}">
        {{ $meta['badge'] }}
    </span>

    <div class="absolute inset-0 bg-gradient-to-t from-black via-black/45 to-transparent opacity-90 group-hover:opacity-95 transition-opacity"></div>

    <div class="relative z-10 p-2 space-y-1.5">
        <h4 class="text-[10px] sm:text-[11px] font-bold text-white leading-tight truncate" title="{{ $meta['title'] }}">
            {{ $meta['title'] }}
        </h4>
        @if($meta['provider'])
            <button type="button"
                    class="btn-glow block w-full text-center bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white py-1.5 rounded-lg font-bold text-[10px] uppercase tracking-wider shadow-md shadow-emerald-500/30 play-game"
                    data-game="{{ $meta['name'] }}"
                    data-title="{{ $meta['title'] }}">
                Oyna
            </button>
        @else
            <a href="{{ route('frontend.game.go', $meta['name']) }}"
               class="btn-glow block w-full text-center bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white py-1.5 rounded-lg font-bold text-[10px] uppercase tracking-wider no-underline shadow-md shadow-emerald-500/30">
                Oyna
            </a>
        @endif
    </div>
</div>
