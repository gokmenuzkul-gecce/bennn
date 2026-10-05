@extends('frontend.Minimal.layouts.clean')

@section('page-title', 'Casino Gecce - Sosyal Oyun Lobisi')

@section('content')

@php
    $cedarBrand = settings('cedar_display_name', 'CEDAR');
    $coinLabel = settings('coin_display_name', 'TRY');
    $navCedarGames = settings('nav_label_cedar_games', 'CEDAR Games');
    $navSports = settings('nav_label_sportsbook', 'Battle Odds');
    $navPredictions = settings('nav_label_predictions', 'Gelecek Oyu');
@endphp

<!-- ===== MODERN HERO SLIDER ===== -->
<div class="hero-slider" id="hero-slider">
    <div class="hero-track">

        <!-- Slide 1: Crash / Slots -->
        <div class="hero-slide is-active" style="background:linear-gradient(120deg,#0c3b2c 0%,#11304f 55%,#141b28 100%);">
            <div class="absolute inset-0 pointer-events-none" style="background:radial-gradient(circle at 82% 40%,rgba(16,185,129,0.35),transparent 55%);"></div>
            <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6 w-full">
                <div class="space-y-3 max-w-xl">
                    <div class="inline-flex items-center gap-2 bg-emerald-400/10 border border-emerald-400/30 px-3 py-1 rounded-full backdrop-blur-sm">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 pulse-dot"></span>
                        <span class="text-[11px] font-bold text-emerald-300 uppercase tracking-widest">RESMİ SOSYAL CASINO</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold tracking-tight leading-[1.05] text-white">
                        1.000+ Slot &amp;<br><span class="text-gradient">{{ $cedarBrand }} Originals</span>
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-300/90 leading-relaxed max-w-lg">
                        Yüksek tempolu arcade slotları, heyecan verici {{ $cedarBrand }} Space Crash ve %100 ücretsiz {{ $coinLabel }} ile başla.
                    </p>
                    <div class="flex flex-wrap items-center gap-3 pt-1">
                        <a href="/categories/cedar_games" class="btn-glow inline-flex items-center gap-2 bg-gradient-to-r from-emerald-500 to-teal-500 text-white px-6 py-3 text-xs uppercase tracking-wider shadow-lg shadow-emerald-500/30 no-underline">
                            <span class="material-symbols-outlined text-lg">rocket_launch</span>
                            <span>{{ $cedarBrand }} Crash Oyna</span>
                        </a>
                        <button type="button" class="open-modal inline-flex items-center gap-2 bg-white/[0.07] hover:bg-white/[0.14] text-white border border-white/15 px-5 py-3 rounded-xl text-xs font-bold uppercase tracking-wider transition-all backdrop-blur-sm" data-target="{{ Auth::check() ? 'modal-profile' : 'modal-login' }}">
                            {{ Auth::check() ? 'Ücretsiz Dolum Al' : 'Giriş Yap / Kayıt Ol' }}
                        </button>
                    </div>
                </div>
                <div class="hidden sm:flex flex-col items-center justify-center p-6 rounded-2xl bg-black/40 border border-white/10 backdrop-blur-md text-center min-w-[210px]">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">{{ $cedarBrand }} CRASH ÇARPANI</span>
                    <span class="font-mono-jet text-5xl font-extrabold text-amber-400 tracking-tighter drop-shadow-[0_0_18px_rgba(245,158,11,0.6)]">48.72x</span>
                    <span class="text-[10px] text-emerald-400 font-bold mt-1 tracking-wider">SON EN İYİ TUR</span>
                    <a href="/game/CedarCrash" class="btn-glow mt-4 text-xs font-bold text-white bg-emerald-500 hover:bg-emerald-400 px-5 py-2 no-underline uppercase w-full">Başlat</a>
                </div>
            </div>
        </div>

        <!-- Slide 2: Sportsbook -->
        <div class="hero-slide" style="background:linear-gradient(120deg,#073a4c 0%,#0d3d5e 55%,#141b28 100%);">
            <div class="absolute inset-0 pointer-events-none" style="background:radial-gradient(circle at 80% 45%,rgba(6,182,212,0.38),transparent 55%);"></div>
            <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6 w-full">
                <div class="space-y-3 max-w-xl">
                    <div class="inline-flex items-center gap-2 bg-cyan-400/10 border border-cyan-400/30 px-3 py-1 rounded-full backdrop-blur-sm">
                        <span class="w-2 h-2 rounded-full bg-cyan-400 pulse-dot"></span>
                        <span class="text-[11px] font-bold text-cyan-300 uppercase tracking-widest">CANLI ORANLAR</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold tracking-tight leading-[1.05] text-white">
                        Battle Odds<br><span class="text-transparent bg-clip-text bg-gradient-to-r from-cyan-300 to-sky-400">Spor Bahisleri</span>
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-300/90 leading-relaxed max-w-lg">
                        Global maçlarda tekli ve kombine bahisleri %100 ücretsiz {{ $coinLabel }} ile oyna. Anlık oranlar, hızlı kupon.
                    </p>
                    <div class="flex flex-wrap items-center gap-3 pt-1">
                        <a href="{{ route('frontend.sports.index') }}" class="btn-glow inline-flex items-center gap-2 bg-gradient-to-r from-cyan-500 to-sky-500 text-white px-6 py-3 text-xs uppercase tracking-wider shadow-lg shadow-cyan-500/30 no-underline">
                            <span class="material-symbols-outlined text-lg">sports_soccer</span>
                            <span>Oranları Gör</span>
                        </a>
                    </div>
                </div>
                <div class="hidden sm:flex flex-col gap-2 p-5 rounded-2xl bg-black/40 border border-white/10 backdrop-blur-md min-w-[230px]">
                    <div class="flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase tracking-wider"><span>ŞAMPİYONLAR LİGİ</span><span class="text-cyan-400">CANLI</span></div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-bold text-white">REAL MADRID</span>
                        <span class="font-mono-jet text-xs font-bold text-cyan-300 bg-cyan-400/10 px-2 py-1 rounded-lg">2.10</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-bold text-white">MANCHESTER CITY</span>
                        <span class="font-mono-jet text-xs font-bold text-cyan-300 bg-cyan-400/10 px-2 py-1 rounded-lg">3.45</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Slide 3: Jackpot / VIP -->
        <div class="hero-slide" style="background:linear-gradient(120deg,#3d2109 0%,#2f1b4f 55%,#141b28 100%);">
            <div class="absolute inset-0 pointer-events-none" style="background:radial-gradient(circle at 80% 45%,rgba(245,158,11,0.35),transparent 55%);"></div>
            <div class="relative z-10 flex flex-col md:flex-row items-start md:items-center justify-between gap-6 w-full">
                <div class="space-y-3 max-w-xl">
                    <div class="inline-flex items-center gap-2 bg-amber-400/10 border border-amber-400/30 px-3 py-1 rounded-full backdrop-blur-sm">
                        <span class="w-2 h-2 rounded-full bg-amber-400 pulse-dot"></span>
                        <span class="text-[11px] font-bold text-amber-300 uppercase tracking-widest">MEGA JACKPOT</span>
                    </div>
                    <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold tracking-tight leading-[1.05] text-white">
                        Jackpot Bölgesi<br><span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-300 to-orange-400">ve VIP Kulübü</span>
                    </h1>
                    <p class="text-xs sm:text-sm text-slate-300/90 leading-relaxed max-w-lg">
                        Çoklu çekilişler, otomatik rakebak ve 6 kademeli VIP ayrıcalıklarla kazançlarını katla.
                    </p>
                    <div class="flex flex-wrap items-center gap-3 pt-1">
                        <a href="{{ route('frontend.lotto.index') }}" class="btn-glow inline-flex items-center gap-2 bg-gradient-to-r from-amber-500 to-orange-500 text-white px-6 py-3 text-xs uppercase tracking-wider shadow-lg shadow-amber-500/30 no-underline">
                            <span class="material-symbols-outlined text-lg">auto_awesome</span>
                            <span>Jackpot Oyna</span>
                        </a>
                        <a href="{{ route('frontend.vip.index') }}" class="inline-flex items-center gap-2 bg-white/[0.07] hover:bg-white/[0.14] text-white border border-white/15 px-5 py-3 rounded-xl text-xs font-bold uppercase tracking-wider transition-all no-underline backdrop-blur-sm">
                            VIP Kulübü
                        </a>
                    </div>
                </div>
                <div class="hidden sm:flex flex-col items-center justify-center p-6 rounded-2xl bg-black/40 border border-amber-400/20 backdrop-blur-md text-center min-w-[210px]">
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">GÜNCEL JACKPOT</span>
                    <span class="font-mono-jet text-3xl font-extrabold text-amber-400 tracking-tighter drop-shadow-[0_0_18px_rgba(245,158,11,0.6)]">₡ 2.480.000</span>
                    <span class="text-[10px] text-amber-300/80 font-bold mt-1 tracking-wider">SONRAKİ ÇEKİLİŞ • SAATLİK</span>
                </div>
            </div>
        </div>

    </div>

    <button type="button" class="hero-nav prev" id="hero-prev" aria-label="Önceki"><span class="material-symbols-outlined">chevron_left</span></button>
    <button type="button" class="hero-nav next" id="hero-next" aria-label="Sonraki"><span class="material-symbols-outlined">chevron_right</span></button>
    <div class="hero-dots" id="hero-dots">
        <button type="button" class="hero-dot is-active" data-slide="0" aria-label="Slayt 1"></button>
        <button type="button" class="hero-dot" data-slide="1" aria-label="Slayt 2"></button>
        <button type="button" class="hero-dot" data-slide="2" aria-label="Slayt 3"></button>
    </div>
</div>

<!-- ===== LIVE WINS TICKER ===== -->
@php
    $liveWins = [
        ['Ahmad_K', 'Book of Ra', '₺142.000', '710x', '#10b981', '#06b6d4'],
        ['Maya_L', 'Cedar Space Crash', '₺38.400', '38.4x', '#f43f5e', '#fb7185'],
        ['Charbel99', 'Beetle Mania', '₺88.500', '442x', '#a855f7', '#e879f9'],
        ['Georges_B', 'Jackpot Lotto', '₺12.750', '3-Match', '#f59e0b', '#fbbf24'],
        ['Selin_T', 'Gates of Olympus', '₺64.200', '642x', '#0ea5e9', '#22d3ee'],
        ['Omar_R', 'Sweet Bonanza', '₺27.900', '279x', '#22c55e', '#4ade80'],
        ['Leyla_K', 'Fruit Blast', '₺9.450', '94x', '#fb923c', '#fdba74'],
        ['Karim_D', 'Buffalo Thunder', '₺53.100', '531x', '#3b82f6', '#60a5fa'],
        ['Nora_S', 'Book of Dead', '₺18.300', '183x', '#14b8a6', '#2dd4bf'],
        ['Tarek_M', 'Sun of Egypt', '₺31.600', '316x', '#8b5cf6', '#a78bfa'],
    ];
@endphp
<div class="wins-bar">
    <div class="wins-label">
        <span class="wins-live"></span>
        <span>Canlı Kazançlar</span>
    </div>
    <div class="marquee-mask overflow-hidden flex-1">
        <div class="marquee-track" style="gap:12px;">
            @foreach([0, 1] as $pass)
            <div class="flex items-center" style="gap:12px;" @if($pass) aria-hidden="true" @endif>
                @foreach($liveWins as $w)
                <div class="win-item" style="--c1: {{ $w[4] }}; --c2: {{ $w[5] }};">
                    <span class="win-avatar">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($w[0], 0, 1)) }}</span>
                    <span class="win-meta">
                        <span class="win-name">{{ $w[0] }}</span>
                        <span class="win-game"><i></i>{{ $w[1] }}</span>
                    </span>
                    <span class="win-amount {{ \Illuminate\Support\Str::endsWith($w[3], 'x') ? '' : 'is-multi' }}">{{ $w[2] }}</span>
                </div>
                @endforeach
            </div>
            @endforeach
        </div>
    </div>
</div>

<!-- ===== PROVIDERS / OYUN SAĞLAYICILARI ===== -->
<section class="space-y-4">
    <div class="marquee-mask overflow-hidden">
        <div class="marquee-track">
            @php
                $providerColors = ['#10b981','#06b6d4','#f59e0b','#a855f7','#f43f5e','#3b82f6','#22c55e','#eab308'];
                $logoDir = public_path('frontend/Default/provider-logos');
                // Surface every licensed brand we have cover art for, independent of the
                // games currently loaded, so the marquee is always complete.
                $providerList = \VanguardLTE\Category::where('parent', 0)
                    ->orderBy('position', 'ASC')
                    ->get()
                    ->reject(fn($c) => in_array($c->href, ['cedar_games','cedar_cards','cedar_remakes','slots'], true))
                    ->filter(fn($c) => is_file($logoDir . '/' . $c->href . '.svg'))
                    ->values();
                if ($providerList->isEmpty() && is_iterable($categories)) {
                    $providerList = collect($categories)
                        ->reject(fn($c) => in_array($c->href, ['cedar_games','cedar_cards','cedar_remakes','slots'], true))
                        ->filter(fn($c) => is_file($logoDir . '/' . $c->href . '.svg'))
                        ->values();
                }
            @endphp
            @foreach($providerList as $i => $cat)
                @php($logo = '/frontend/Default/provider-logos/' . $cat->href . '.svg')
                <a href="{{ route('frontend.game.list.category', $cat->href) }}" class="provider-tile" style="--pc: {{ $providerColors[$i % count($providerColors)] }};" title="{{ $cat->title }}">
                    <img src="{{ $logo }}" alt="{{ $cat->title }}" class="provider-logo" loading="eager" decoding="async">
                </a>
            @endforeach
            @foreach($providerList as $i => $cat)
                @php($logo = '/frontend/Default/provider-logos/' . $cat->href . '.svg')
                <a href="{{ route('frontend.game.list.category', $cat->href) }}" class="provider-tile" style="--pc: {{ $providerColors[$i % count($providerColors)] }};" aria-hidden="true" tabindex="-1" title="{{ $cat->title }}">
                    <img src="{{ $logo }}" alt="" class="provider-logo" loading="eager" decoding="async">
                </a>
            @endforeach
        </div>
    </div>
</section>

<!-- Games Grid Section -->
<section class="space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div class="flex items-center gap-3">
            <span id="games-count-label" class="font-mono-jet text-xs text-primary font-bold bg-primary/10 border border-primary/20 px-3 py-1 rounded-lg">
                {{ count($games) }} OYUN ÇEVRİMİÇİ
            </span>
        </div>
    </div>

    <!-- Game Search -->
    <div class="relative">
        <span class="material-symbols-outlined absolute left-3.5 top-1/2 -translate-y-1/2 text-on-surface-subtle text-xl pointer-events-none">search</span>
        <input type="text" id="home-game-search" autocomplete="off"
               placeholder="Oyun ara... (ör. Gates of Olympus, Sweet Bonanza)"
               class="w-full bg-surface-card border border-white/[0.08] focus:border-primary/50 focus:ring-1 focus:ring-primary/30 text-white placeholder:text-on-surface-subtle rounded-2xl pl-11 pr-11 py-3 text-sm outline-none transition-all">
        <button type="button" id="home-game-search-clear" class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-on-surface-subtle hover:text-white transition-colors">
            <span class="material-symbols-outlined text-lg">close</span>
        </button>
        <span id="home-game-search-spinner" class="hidden absolute right-3.5 top-1/2 -translate-y-1/2 text-xs font-mono-jet text-primary animate-pulse">...</span>
    </div>

    <!-- Category Filter Pills -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1.5 custom-scrollbar max-w-full">
        <a href="{{ route('frontend.game.list.category', 'all') }}" class="px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap uppercase tracking-wider no-underline {{ ($category1 ?? 'all') == 'all' ? 'bg-primary text-white shadow-md shadow-primary/25' : 'bg-surface-card text-on-surface-muted hover:text-white border border-white/[0.06] hover:border-white/15' }}">
            Tüm Oyunlar
        </a>
        <a href="/categories/cedar_games" class="px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap uppercase tracking-wider no-underline {{ request()->is('categories/cedar_games*') ? 'bg-accent-gold text-black shadow-md shadow-accent-gold/25' : 'bg-surface-card text-accent-gold hover:text-yellow-300 border border-accent-gold/20' }}">
            🚀 {{ $navCedarGames }}
        </a>
        <a href="/categories/cedar_remakes" class="px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap uppercase tracking-wider no-underline {{ request()->is('categories/cedar_remakes*') ? 'bg-primary text-white shadow-md shadow-primary/25' : 'bg-surface-card text-primary hover:text-primary-light border border-primary/20' }}">
            🌲 CEDAR Slotları
        </a>
        @if(is_iterable($categories))
            @foreach($categories as $cat)
                @continue(in_array($cat->href, ['cedar_games', 'cedar_cards', 'cedar_remakes'], true))
                <a href="{{ route('frontend.game.list.category', $cat->href) }}" class="px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap uppercase tracking-wider no-underline {{ ($category1 ?? '') == $cat->href ? 'bg-primary text-white shadow-md shadow-primary/25' : 'bg-surface-card text-on-surface-muted hover:text-white border border-white/[0.06] hover:border-white/15' }}">
                    {{ $cat->title }}
                </a>
            @endforeach
        @endif
    </div>

    <!-- Games Grid: 3-col mobile, 4-col tablet, 6-col laptop, 7-col wide -->
    <div id="games-grid" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 xl:grid-cols-7 gap-2.5 sm:gap-3">
        @forelse($games as $game)
            @php($isCedarProvider = str_starts_with($game->name, 'Cedar') || $game->name === 'RoyalSteps')
            <div class="game-card group aspect-[3/4] flex flex-col justify-end" data-title="{{ \Illuminate\Support\Str::lower($game->title . ' ' . $game->name) }}">
                <span class="card-shine"></span>
                <!-- Cover Image -->
                <img src="{{ game_cover($game) }}"
                     onerror="this.src='/frontend/Default/ico/DayofDead.jpg'"
                     alt="{{ $game->title }}"
                     class="absolute inset-0 w-full h-full object-cover group-hover:scale-110 transition-transform duration-700"
                     loading="lazy">

                <!-- Badge -->
                <span class="absolute top-1.5 left-1.5 z-10 text-[8px] font-mono-jet font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-md backdrop-blur-md {{ $isCedarProvider ? 'bg-amber-400/20 text-amber-300 border border-amber-400/30' : 'bg-black/50 text-emerald-300 border border-emerald-400/25' }}">
                    {{ $isCedarProvider ? strtoupper($cedarBrand) . ' ORİJİNAL' : strtoupper(substr($game->name, -2) === 'AM' ? 'AMATIC' : (substr($game->name, -3) === 'PGD' ? 'PGD' : 'SLOT')) }}
                </span>

                <!-- Dark Gradient Overlay -->
                <div class="absolute inset-0 bg-gradient-to-t from-black via-black/45 to-transparent opacity-90 group-hover:opacity-95 transition-opacity"></div>

                <!-- Game Info & Action -->
                <div class="relative z-10 p-2 space-y-1.5">
                    <h4 class="text-[10px] sm:text-[11px] font-bold text-white leading-tight truncate" title="{{ $game->title }}">
                        {{ $game->title }}
                    </h4>
                    @if(!empty($game->provider_key))
                        <button type="button"
                                class="btn-glow block w-full text-center bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white py-1.5 rounded-lg font-bold text-[10px] uppercase tracking-wider shadow-md shadow-emerald-500/30 play-game"
                                data-game="{{ $game->name }}"
                                data-title="{{ $game->title }}">
                            Oyna
                        </button>
                    @else
                        <a href="{{ route('frontend.game.go', $game->name) }}"
                           class="btn-glow block w-full text-center bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white py-1.5 rounded-lg font-bold text-[10px] uppercase tracking-wider no-underline shadow-md shadow-emerald-500/30">
                            Oyna
                        </a>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-16 glass-card rounded-3xl">
                <span class="material-symbols-outlined text-4xl text-on-surface-subtle mb-2">sentiment_dissatisfied</span>
                <p class="text-on-surface-muted text-sm font-medium">Bu kategoride oyun bulunamadı.</p>
                <a href="{{ route('frontend.game.list') }}" class="btn-glow mt-3 inline-block bg-gradient-to-r from-emerald-500 to-teal-500 text-white text-xs font-bold px-5 py-2.5 rounded-xl no-underline uppercase">
                    Tüm Oyunları Gör
                </a>
            </div>
        @endforelse
    </div>
</section>

<!-- ===== IN-SITE GAME PLAYER (keeps header + left menu visible) ===== -->
<div id="game-player" class="hidden fixed z-40 inset-x-0 bottom-0 top-16 lg:left-[260px] lg:top-[68px] px-2 sm:px-4 lg:px-12 pb-24 lg:pb-4 pointer-events-none items-center justify-center">
    <div class="pointer-events-auto w-full max-w-7xl h-[min(82vh,900px)] flex flex-col rounded-2xl overflow-hidden border border-white/10 bg-[#0b0e17]/95 backdrop-blur-xl shadow-2xl shadow-black/60">
        <div class="flex items-center justify-between gap-3 px-3 py-2 border-b border-white/[0.07] bg-[#0d1119]/80">
            <div class="flex items-center gap-2 min-w-0">
                <span class="material-symbols-outlined text-primary text-xl">casino</span>
                <h3 id="game-player-title" class="text-white font-bold text-sm sm:text-base truncate">Oyun</h3>
                <span id="game-player-provider" class="text-[10px] font-mono-jet uppercase tracking-wider text-emerald-300 bg-emerald-500/10 border border-emerald-400/25 px-2 py-0.5 rounded-md"></span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" id="game-player-newtab" class="btn-control text-[11px] font-bold text-white bg-white/10 hover:bg-white/20 border border-white/15 px-3 py-2 rounded-xl transition-colors uppercase tracking-wide">
                    Yeni Sekme
                </button>
                <button type="button" id="game-player-close" class="text-white bg-white/10 hover:bg-rose-500/80 border border-white/15 p-2 rounded-xl transition-colors" aria-label="Kapat">
                    <span class="material-symbols-outlined text-lg block">close</span>
                </button>
            </div>
        </div>
        <div id="game-player-stage" class="relative flex-1 min-h-0 bg-[#0b0e17] flex items-center justify-center overflow-hidden">
            <div id="game-player-frame-wrap" class="w-full h-full">
                <iframe id="game-player-frame" name="game-player-frame" class="w-full h-full border-0" allow="autoplay; fullscreen; screen-wake-lock" allowfullscreen></iframe>
            </div>
            <div id="game-player-loading" class="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-[#0b0e17]">
                <div class="w-10 h-10 border-2 border-primary/30 border-t-primary rounded-full animate-spin"></div>
                <p class="text-on-surface-muted text-xs font-medium">Oyun yükleniyor...</p>
            </div>
            <div id="game-player-error" class="hidden absolute inset-0 flex-col items-center justify-center gap-3 bg-[#0b0e17] px-6 text-center">
                <span class="material-symbols-outlined text-3xl text-rose-400">error</span>
                <p id="game-player-error-text" class="text-on-surface-muted text-sm"></p>
                <button type="button" id="game-player-error-newtab" class="hidden btn-glow bg-gradient-to-r from-emerald-500 to-teal-500 text-white text-xs font-bold px-5 py-2.5 rounded-xl uppercase">
                    Yeni Sekmede Aç
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<!-- ===== IN-SITE GAME LAUNCH ===== -->
<script>
(function () {
    var player = document.getElementById('game-player');
    if (!player) return;
    var frame = document.getElementById('game-player-frame');
    var loading = document.getElementById('game-player-loading');
    var errorBox = document.getElementById('game-player-error');
    var errorText = document.getElementById('game-player-error-text');
    var errorNewTab = document.getElementById('game-player-error-newtab');
    var titleEl = document.getElementById('game-player-title');
    var providerEl = document.getElementById('game-player-provider');
    var newTabBtn = document.getElementById('game-player-newtab');
    var closeBtn = document.getElementById('game-player-close');
    var currentUrl = null;

    var frameWrap = document.getElementById('game-player-frame-wrap');
    var currentAspect = 'auto';

    // Size the frame to the vendor's build. 'auto' stretches to the panel; a
    // "w:h" aspect (e.g. PG Soft's phone build) fits the game inside the panel
    // so it fills the height without overflowing or leaving half the frame blank.
    function applyAspect(aspect) {
        currentAspect = aspect || 'auto';
        fitFrame();
    }

    function fitFrame() {
        if (!currentAspect || currentAspect === 'auto') {
            frame.style.width = '100%';
            frame.style.height = '100%';
            return;
        }
        var parts = String(currentAspect).split(':');
        var w = parseFloat(parts[0]), h = parseFloat(parts[1]);
        if (parts.length !== 2 || !w || !h) {
            frame.style.width = '100%';
            frame.style.height = '100%';
            return;
        }
        var aw = frameWrap.clientWidth, ah = frameWrap.clientHeight;
        if (!aw || !ah) return;
        var scale = Math.min(aw / w, ah / h);
        frame.style.width = Math.floor(w * scale) + 'px';
        frame.style.height = Math.floor(h * scale) + 'px';
    }

    if (window.ResizeObserver) {
        new ResizeObserver(fitFrame).observe(frameWrap);
    } else {
        window.addEventListener('resize', fitFrame);
    }

    function showError(message, allowNewTab) {
        loading.classList.add('hidden');
        errorBox.classList.remove('hidden');
        errorBox.classList.add('flex');
        errorText.textContent = message;
        errorNewTab.classList.toggle('hidden', !allowNewTab);
    }

    function openPlayer(gameName, title) {
        titleEl.textContent = title || gameName;
        providerEl.textContent = '';
        currentUrl = null;
        errorBox.classList.add('hidden');
        errorBox.classList.remove('flex');
        loading.classList.remove('hidden');
        player.classList.remove('hidden');
        player.classList.add('flex');

        fetch('{{ url('/game') }}/' + encodeURIComponent(gameName) + '/launch', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, status: r.status, data: d }; }); })
            .then(function (res) {
                var data = res.data || {};
                if (!res.ok || !data.success) {
                    if (res.status === 401) {
                        // Not signed in: fall back to the lobby login modal.
                        player.classList.add('hidden');
                        player.classList.remove('flex');
                        var loginModal = document.querySelector('[data-target="modal-login"]');
                        if (loginModal) loginModal.click();
                        return;
                    }
                    showError(data.text || 'Oyun başlatılamadı.', false);
                    return;
                }
                providerEl.textContent = data.provider || '';
                applyAspect(data.aspect);
                currentUrl = data.url;
                if (!data.embedded) {
                    showError('Bu sağlayıcı site içinde açılamıyor; lütfen yeni sekmede açın.', true);
                    return;
                }
                // Amusnet answers with an auto-POST page; replay the POST from our
                // own origin so the browser lets the iframe follow it.
                if (data.form && data.form.action) {
                    submitFormToFrame(data.form);
                    return;
                }
                frame.src = data.url;
                frame.onload = function () { loading.classList.add('hidden'); };
            })
            .catch(function () { showError('Bağlantı hatası. Lütfen tekrar deneyin.', false); });
    }

    function submitFormToFrame(formData) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = formData.action;
        form.target = frame.getAttribute('name');
        form.style.display = 'none';
        Object.keys(formData.fields || {}).forEach(function (name) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = formData.fields[name];
            form.appendChild(input);
        });
        document.body.appendChild(form);
        frame.onload = function () { loading.classList.add('hidden'); };
        form.submit();
        setTimeout(function () { form.remove(); }, 0);
    }

    function closePlayer() {
        player.classList.add('hidden');
        player.classList.remove('flex');
        frame.src = 'about:blank';
        applyAspect('auto');
        currentUrl = null;
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.play-game');
        if (!btn) return;
        e.preventDefault();
        openPlayer(btn.dataset.game, btn.dataset.title);
    });

    closeBtn.addEventListener('click', closePlayer);
    player.addEventListener('click', function (e) { if (e.target === player) closePlayer(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !player.classList.contains('hidden')) closePlayer(); });

    function openNewTab() {
        if (currentUrl) window.open(currentUrl, '_blank', 'noopener');
    }
    newTabBtn.addEventListener('click', openNewTab);
    errorNewTab.addEventListener('click', openNewTab);
})();
</script>
<script>
(function () {
    var slider = document.getElementById('hero-slider');
    if (!slider) return;
    var slides = slider.querySelectorAll('.hero-slide');
    var dots = slider.querySelectorAll('.hero-dot');
    if (slides.length < 2) return;
    var current = 0, timer = null, DELAY = 5500;

    function show(i) {
        current = (i + slides.length) % slides.length;
        slides.forEach(function (s, idx) { s.classList.toggle('is-active', idx === current); });
        dots.forEach(function (d, idx) { d.classList.toggle('is-active', idx === current); });
    }
    function next() { show(current + 1); }
    function prev() { show(current - 1); }
    function restart() { clearInterval(timer); timer = setInterval(next, DELAY); }

    dots.forEach(function (d) {
        d.addEventListener('click', function () { show(parseInt(d.dataset.slide, 10)); restart(); });
    });
    var nb = document.getElementById('hero-next'), pb = document.getElementById('hero-prev');
    if (nb) nb.addEventListener('click', function () { next(); restart(); });
    if (pb) pb.addEventListener('click', function () { prev(); restart(); });

    slider.addEventListener('mouseenter', function () { clearInterval(timer); });
    slider.addEventListener('mouseleave', restart);

    // Touch swipe support
    var startX = null;
    slider.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; }, { passive: true });
    slider.addEventListener('touchend', function (e) {
        if (startX === null) return;
        var dx = e.changedTouches[0].clientX - startX;
        if (Math.abs(dx) > 45) { dx < 0 ? next() : prev(); restart(); }
        startX = null;
    }, { passive: true });

    restart();
})();
</script>

<!-- ===== HOMEPAGE GAME SEARCH ===== -->
<script>
(function () {
    var input = document.getElementById('home-game-search');
    if (!input) return;
    var grid = document.getElementById('games-grid');
    var clearBtn = document.getElementById('home-game-search-clear');
    var spinner = document.getElementById('home-game-search-spinner');
    var counter = document.getElementById('games-count-label');
    var cards = grid ? Array.prototype.slice.call(grid.querySelectorAll('.game-card')) : [];
    var originalCount = cards.length;
    var timer = null;

    function applyFilter(query) {
        var q = query.trim().toLowerCase();
        var shown = 0;
        cards.forEach(function (card) {
            var match = q === '' || (card.dataset.title || '').indexOf(q) !== -1;
            card.classList.toggle('hidden', !match);
            if (match) shown++;
        });
        if (counter) counter.textContent = shown + ' OYUN ÇEVRİMİÇİ';
        if (clearBtn) clearBtn.classList.toggle('hidden', q === '');
    }

    function remoteSearch(query) {
        // Titles outside the current category still exist in the catalogue, so
        // fall back to the server search and append the extra matches.
        if (spinner) spinner.classList.remove('hidden');
        fetch('{{ route('frontend.search.json') }}?q=' + encodeURIComponent(query))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (spinner) spinner.classList.add('hidden');
                if (!data || !data.success || !grid) return;
                grid.querySelectorAll('.game-card.search-extra').forEach(function (n) { n.remove(); });
                (data.data || []).forEach(function (g) {
                    if (grid.querySelector('[data-title="' + g.title.toLowerCase() + '"]')) return;
                    var card = document.createElement('div');
                    card.className = 'game-card group aspect-[3/4] flex flex-col justify-end search-extra';
                    card.dataset.title = g.title.toLowerCase();
                    var playBtn = g.provider_key
                        ? '<button type="button" class="play-game btn-glow block w-full text-center bg-gradient-to-r from-emerald-500 to-teal-500 text-white py-1.5 rounded-lg font-bold text-[10px] uppercase tracking-wider" data-game="' + g.name + '" data-title="' + g.title + '">Oyna</button>'
                        : '<a href="' + g.link + '" class="btn-glow block w-full text-center bg-gradient-to-r from-emerald-500 to-teal-500 text-white py-1.5 rounded-lg font-bold text-[10px] uppercase tracking-wider no-underline">Oyna</a>';
                    card.innerHTML =
                        '<span class="card-shine"></span>' +
                        '<img src="' + g.icon + '" onerror="this.src=\'/frontend/Default/ico/DayofDead.jpg\'" alt="" class="absolute inset-0 w-full h-full object-cover" loading="lazy">' +
                        '<div class="absolute inset-0 bg-gradient-to-t from-black via-black/45 to-transparent opacity-90"></div>' +
                        '<div class="relative z-10 p-2 space-y-1.5">' +
                        '<h4 class="text-[10px] sm:text-[11px] font-bold text-white leading-tight truncate">' + g.title + '</h4>' +
                        playBtn +
                        '</div>';
                    grid.appendChild(card);
                });
            })
            .catch(function () { if (spinner) spinner.classList.add('hidden'); });
    }

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var value = input.value;
        applyFilter(value);
        if (value.trim().length < 2) return;
        timer = setTimeout(function () { remoteSearch(value.trim()); }, 350);
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            input.value = '';
            applyFilter('');
            if (grid) grid.querySelectorAll('.game-card.search-extra').forEach(function (n) { n.remove(); });
            if (counter) counter.textContent = originalCount + ' OYUN ÇEVRİMİÇİ';
            input.focus();
        });
    }
})();
</script>
@endsection
