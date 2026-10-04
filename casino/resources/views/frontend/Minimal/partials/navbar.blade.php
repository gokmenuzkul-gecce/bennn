@php
    $brandName = settings('app_name') ?: 'Casino Gecce';
    $brandTagline = settings('brand_tagline') ?: 'Sosyal Oyun';
    $brandLogoPath = settings('brand_logo_path');
    $cedarBrand = settings('cedar_display_name') ?: 'CEDAR';
    $coinLabel = settings('coin_display_name') ?: 'TRY';
    $navCasino = settings('nav_label_casino') ?: 'Casino Slotları';
    $navCedarGames = settings('nav_label_cedar_games') ?: 'CEDAR Games';
    $navCedarGamesBadge = settings('nav_badge_cedar_games') ?: 'HOT';
    $navCedarSlots = settings('nav_label_cedar_slots') ?: 'CEDAR Slots';
    $navCedarSlotsBadge = settings('nav_badge_cedar_slots');
    $navSports = settings('nav_label_sportsbook') ?: 'Battle Odds';
    $navLotto = settings('nav_label_lotto') ?: 'Jackpot Bölgesi';
    $navPredictions = settings('nav_label_predictions') ?: 'Gelecek Oyu';
@endphp

<!-- Left Sidebar Navigation (Desktop) -->
<aside class="hidden lg:flex flex-col w-sidebar-width h-screen sticky top-0 bg-[#0b0f17]/90 border-r border-white/[0.07] backdrop-blur-2xl z-50 p-5 flex-shrink-0">
    <!-- Brand Logo -->
    <div class="mb-7 flex items-center gap-3 px-2">
        @if($brandLogoPath)
            <img src="{{ asset('storage/' . $brandLogoPath) }}" alt="{{ $brandName }}" class="w-10 h-10 rounded-xl object-contain bg-primary/10 shadow-lg shadow-primary/20 flex-shrink-0">
        @else
            <img src="/minimal/promex-emblem.png" alt="{{ $brandName }}" class="w-10 h-10 rounded-xl object-contain shadow-lg shadow-emerald-500/30 flex-shrink-0">
        @endif
        <a href="{{ route('frontend.game.list') }}" class="flex flex-col no-underline">
            <span class="text-base font-extrabold tracking-tight text-white leading-tight">{{ strtoupper($brandName) }}</span>
            <span class="text-[10px] font-bold tracking-widest text-emerald-400 uppercase">{{ $brandTagline }}</span>
        </a>
    </div>

    <!-- Main Navigation Links -->
    <nav class="flex-1 space-y-1.5 overflow-y-auto custom-scrollbar pr-1">
        <div class="text-[10px] font-bold uppercase tracking-wider text-on-surface-subtle px-3 py-1">Oyun Merkezi</div>

        <!-- Casino Lobby -->
        @if(settings('enable_casino_slots', '1') == '1')
        <a class="flex items-center gap-3.5 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all group no-underline {{ Route::is('frontend.game.list*') && !request()->is('categories/cedar_games*') && !request()->is('categories/cedar_remakes*') ? 'bg-primary/10 text-primary border border-primary/20 shadow-sm' : 'text-on-surface-muted hover:text-white hover:bg-white/[0.04]' }}" href="{{ route('frontend.game.list') }}">
            <span class="material-symbols-outlined text-xl {{ Route::is('frontend.game.list*') && !request()->is('categories/cedar_games*') && !request()->is('categories/cedar_remakes*') ? 'text-primary' : 'text-on-surface-subtle group-hover:text-white' }} transition-colors" style="font-variation-settings: 'FILL' 1;">casino</span>
            <span>{{ $navCasino }}</span>
        </a>
        @endif

        <!-- CEDAR Originals -->
        @if(settings('enable_cedar_originals', '1') == '1')
        <a class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all group no-underline {{ request()->is('categories/cedar_games*') ? 'bg-accent-gold/10 text-accent-gold border border-accent-gold/25 shadow-sm' : 'text-on-surface-muted hover:text-white hover:bg-white/[0.04]' }}" href="/categories/cedar_games">
            <div class="flex items-center gap-3.5">
                <span class="material-symbols-outlined text-xl {{ request()->is('categories/cedar_games*') ? 'text-accent-gold' : 'text-accent-gold/80 group-hover:text-accent-gold' }} transition-colors" style="font-variation-settings: 'FILL' 1;">rocket_launch</span>
                <span>{{ $navCedarGames }}</span>
            </div>
            @if($navCedarGamesBadge)<span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-accent-gold/20 text-accent-gold uppercase tracking-wider">{{ $navCedarGamesBadge }}</span>@endif
        </a>
        @endif

        @if(settings('enable_cedar_remakes', '1') == '1')
        <a class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all group no-underline {{ request()->is('categories/cedar_remakes*') ? 'bg-primary/10 text-primary border border-primary/25 shadow-sm' : 'text-on-surface-muted hover:text-white hover:bg-white/[0.04]' }}" href="/categories/cedar_remakes">
            <div class="flex items-center gap-3.5">
                <span class="material-symbols-outlined text-xl text-primary" style="font-variation-settings: 'FILL' 1;">park</span>
                <span>{{ $navCedarSlots }}</span>
            </div>
            @if($navCedarSlotsBadge)<span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-primary/20 text-primary uppercase tracking-wider">{{ $navCedarSlotsBadge }}</span>@endif
        </a>
        @endif

        <!-- Sportsbook / Battle Odds -->
        @if(settings('enable_sportsbook', '1') == '1')
        <a class="flex items-center gap-3.5 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all group no-underline {{ Route::is('frontend.sports*') ? 'bg-secondary/10 text-secondary border border-secondary/20 shadow-sm' : 'text-on-surface-muted hover:text-white hover:bg-white/[0.04]' }}" href="{{ route('frontend.sports.index') }}">
            <span class="material-symbols-outlined text-xl {{ Route::is('frontend.sports*') ? 'text-secondary' : 'text-on-surface-subtle group-hover:text-white' }} transition-colors" style="font-variation-settings: 'FILL' 1;">sports_soccer</span>
            <span>{{ $navSports }}</span>
        </a>
        @endif

        <!-- Lotto Jackpot Zone -->
        @if(settings('enable_lotto', '1') == '1')
        <a class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all group no-underline {{ Route::is('frontend.lotto*') ? 'bg-primary/10 text-primary border border-primary/20 shadow-sm' : 'text-on-surface-muted hover:text-white hover:bg-white/[0.04]' }}" href="{{ route('frontend.lotto.index') }}">
            <div class="flex items-center gap-3.5">
                <span class="material-symbols-outlined text-xl {{ Route::is('frontend.lotto*') ? 'text-primary' : 'text-on-surface-subtle group-hover:text-white' }} transition-colors" style="font-variation-settings: 'FILL' 1;">auto_awesome</span>
                <span>{{ $navLotto }}</span>
            </div>
            <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
        </a>
        @endif

        <!-- Future Vote Prediction Markets -->
        @if(settings('enable_predictions', '1') == '1')
        <a class="flex items-center gap-3.5 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all group no-underline {{ Route::is('frontend.predictions*') ? 'bg-secondary/10 text-secondary border border-secondary/20 shadow-sm' : 'text-on-surface-muted hover:text-white hover:bg-white/[0.04]' }}" href="{{ route('frontend.predictions.index') }}">
            <span class="material-symbols-outlined text-xl {{ Route::is('frontend.predictions*') ? 'text-secondary' : 'text-on-surface-subtle group-hover:text-white' }} transition-colors" style="font-variation-settings: 'FILL' 1;">query_stats</span>
            <span>{{ $navPredictions }}</span>
        </a>
        @endif

        <a class="flex items-center gap-3.5 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all group no-underline {{ Route::is('frontend.crypto*') ? 'bg-cyan-400/10 text-cyan-300 border border-cyan-400/20 shadow-sm' : 'text-on-surface-muted hover:text-white hover:bg-white/[0.04]' }}" href="{{ route('frontend.crypto.index') }}">
            <span class="material-symbols-outlined text-xl {{ Route::is('frontend.crypto*') ? 'text-cyan-300' : 'text-on-surface-subtle group-hover:text-white' }} transition-colors" style="font-variation-settings: 'FILL' 1;">candlestick_chart</span>
            <span>Kripto Ticareti</span>
        </a>
        <a class="flex items-center gap-3.5 px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all group no-underline {{ Route::is('frontend.stocks*') ? 'bg-cyan-400/10 text-cyan-300 border border-cyan-400/20 shadow-sm' : 'text-on-surface-muted hover:text-white hover:bg-white/[0.04]' }}" href="{{ route('frontend.stocks.index') }}">
            <span class="material-symbols-outlined text-xl {{ Route::is('frontend.stocks*') ? 'text-cyan-300' : 'text-on-surface-subtle group-hover:text-white' }} transition-colors" style="font-variation-settings: 'FILL' 1;">show_chart</span>
            <span>Hisse Ticareti</span>
        </a>

        <!-- 3-Tier Affiliate & Referral Program -->
        <a class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all group no-underline {{ Route::is('frontend.affiliates*') ? 'bg-amber-500/10 text-amber-400 border border-amber-500/25 shadow-sm' : 'text-on-surface-muted hover:text-white hover:bg-white/[0.04]' }}" href="{{ route('frontend.affiliates.index') }}">
            <div class="flex items-center gap-3.5">
                <span class="material-symbols-outlined text-xl {{ Route::is('frontend.affiliates*') ? 'text-amber-400' : 'text-amber-400/80 group-hover:text-amber-400' }} transition-colors" style="font-variation-settings: 'FILL' 1;">groups</span>
                <span>Ortaklık</span>
            </div>
            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-400 uppercase tracking-wider">KAZAN</span>
        </a>

        <!-- VIP Kulübü ve Sadakat Kasası -->
        <a class="flex items-center justify-between px-3.5 py-2.5 rounded-xl text-sm font-semibold transition-all group no-underline {{ Route::is('frontend.vip*') ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/25 shadow-sm' : 'text-on-surface-muted hover:text-white hover:bg-white/[0.04]' }}" href="{{ route('frontend.vip.index') }}">
            <div class="flex items-center gap-3.5">
                <span class="material-symbols-outlined text-xl {{ Route::is('frontend.vip*') ? 'text-emerald-400' : 'text-emerald-400/80 group-hover:text-emerald-400' }} transition-colors" style="font-variation-settings: 'FILL' 1;">workspace_premium</span>
                <span>VIP Kulübü</span>
            </div>
            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-400 uppercase tracking-wider">KASA</span>
        </a>
    </nav>

    <!-- Bottom Utility Bar (wallet + profile now live in the top-right account menu) -->
    <div class="mt-auto space-y-3 pt-4 border-t border-white/[0.07]">
        <!-- Help is public; the compact admin shortcut appears beside it for staff. -->
        <div class="flex gap-2">
            <a href="{{ route('frontend.help') }}" class="flex items-center justify-center w-11 rounded-xl bg-primary/10 border border-primary/30 text-primary hover:text-white hover:bg-primary/20 transition-all no-underline shadow-md shadow-primary/10" title="Oyuncu Yardımı" aria-label="Oyuncu Yardımı">
                <span class="material-symbols-outlined text-xl">help</span>
            </a>
            @if(Auth::check() && (in_array((int)Auth::user()->role_id, [2, 3, 4, 5, 6]) || Auth::user()->hasRole('admin') || Auth::user()->hasRole('manager') || (env('ADMIN_PHONE') && Auth::user()->phone == env('ADMIN_PHONE'))))
            <a href="{{ route('liteback.users.index') }}" target="_blank" class="flex-1 flex items-center justify-between px-3 py-2.5 rounded-xl bg-gradient-to-r from-amber-500/20 via-amber-600/10 to-[#121622] border border-amber-500/40 text-amber-300 hover:text-white hover:border-amber-400 hover:bg-amber-500/25 transition-all text-xs font-bold no-underline shadow-md shadow-amber-500/10 group">
                <span class="flex items-center gap-2"><span class="material-symbols-outlined text-lg text-amber-400 group-hover:rotate-12 transition-transform">admin_panel_settings</span><span class="tracking-wide uppercase">Yönetici</span></span>
                <span class="text-[9px] font-mono-jet font-bold px-2 py-0.5 rounded bg-amber-400 text-black uppercase flex items-center gap-0.5"><span>LITEBACK</span><span class="material-symbols-outlined text-[11px]">north_east</span></span>
            </a>
            @endif
        </div>
    </div>
</aside>

<!-- Sleek Mobile Top Header -->
<header class="lg:hidden h-16 px-4 flex items-center justify-between bg-[#0e121b]/95 border-b border-white/[0.08] backdrop-blur-xl w-full sticky top-0 z-40">
    <a href="{{ route('frontend.game.list') }}" class="flex items-center gap-2.5 no-underline min-w-0">
        @if($brandLogoPath)
            <img src="{{ asset('storage/' . $brandLogoPath) }}" alt="{{ $brandName }}" class="w-8 h-8 rounded-lg object-contain shadow-md shadow-primary/20 flex-shrink-0">
        @else
            <img src="/minimal/promex-emblem.png" alt="{{ $brandName }}" class="w-8 h-8 rounded-lg object-contain shadow-md shadow-primary/20 flex-shrink-0">
        @endif
        <span class="hidden min-[380px]:inline font-extrabold text-sm tracking-tight text-white truncate">{{ strtoupper($brandName) }}</span>
    </a>
    
    <div class="flex items-center gap-2 flex-shrink-0">
        <a href="{{ route('frontend.help') }}" class="bg-primary/10 border border-primary/30 hover:bg-primary/20 text-primary p-2 rounded-xl flex items-center justify-center no-underline transition-all" title="Oyuncu Yardımı" aria-label="Oyuncu Yardımı">
            <span class="material-symbols-outlined text-base">help</span>
        </a>
        @if(Auth::check() && (in_array((int)Auth::user()->role_id, [2, 3, 4, 5, 6]) || Auth::user()->hasRole('admin') || Auth::user()->hasRole('manager') || (env('ADMIN_PHONE') && Auth::user()->phone == env('ADMIN_PHONE'))))
        <a href="{{ route('liteback.users.index') }}" target="_blank" class="bg-amber-500/20 border border-amber-500/40 hover:bg-amber-500/30 text-amber-400 p-2 rounded-xl flex items-center justify-center no-underline transition-all shadow-sm shadow-amber-500/10" title="Liteback Yönetici Konsolunu Aç">
            <span class="material-symbols-outlined text-base">admin_panel_settings</span>
        </a>
        @endif
        @auth
        <!-- Balance Badge -->
        <div class="flex items-center gap-1 bg-[#161c2b] border border-white/[0.08] px-2.5 py-1.5 rounded-xl">
            <span class="font-mono-jet text-xs font-bold text-primary" id="user-coin-balance-mobile">{{ number_format(Auth::user()->balance, 0) }}</span>
            <span class="text-[10px] font-bold text-primary-light">₺</span>
        </div>
        <!-- Quick Deposit Button -->
        <button type="button" class="open-modal btn-glow bg-gradient-to-r from-emerald-500 to-teal-500 text-white text-xs font-bold px-2.5 py-1.5 rounded-xl uppercase tracking-wide shadow-md shadow-primary/20 flex items-center gap-1" data-target="modal-deposit">
            <span class="material-symbols-outlined text-sm">add</span>
            <span>Yükle</span>
        </button>
        <!-- Logout -->
        <a href="{{ route('frontend.auth.logout') }}" class="bg-white/[0.04] hover:bg-white/[0.1] border border-white/[0.08] text-accent-rose p-2 rounded-xl flex items-center justify-center no-underline transition-all" title="Çıkış Yap" aria-label="Çıkış Yap">
            <span class="material-symbols-outlined text-base">logout</span>
        </a>
        @else
        <!-- Guest: sign in / sign up -->
        <button type="button" class="open-modal bg-white/[0.06] hover:bg-white/[0.12] border border-white/[0.12] text-white text-xs font-bold px-2.5 py-2 rounded-xl transition-all" data-target="modal-login">
            Giriş Yap
        </button>
        <button type="button" class="open-modal bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-white text-xs font-bold px-2.5 py-2 rounded-xl shadow-md shadow-emerald-500/25 transition-all whitespace-nowrap" data-target="modal-register">
            Kayıt Ol
        </button>
        @endauth
    </div>
</header>

<!-- Mobile Slide-Up Bottom Sheet Overlay -->
<div id="mobile-sheet-overlay" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-[90] hidden transition-opacity duration-300"></div>

<!-- Mobile Slide-Up Sheet (Menu & User Profile Hub) -->
<div id="mobile-bottom-sheet" class="fixed left-0 right-0 bottom-0 max-h-[85vh] bg-[#121622]/98 border-t border-white/10 rounded-t-3xl backdrop-blur-2xl z-[100] transform translate-y-full transition-transform duration-300 flex flex-col p-5 shadow-2xl overflow-y-auto custom-scrollbar">
    <!-- Drag Bar -->
    <div class="w-12 h-1.5 bg-white/20 rounded-full mx-auto mb-4 flex-shrink-0 cursor-pointer" id="sheet-drag-handle"></div>

    <!-- User Header in Sheet -->
    <div class="flex items-center justify-between mb-4 pb-4 border-b border-white/[0.08]">
        <div class="flex items-center gap-3 cursor-pointer open-modal flex-1" data-target="{{ Auth::check() ? 'modal-profile' : 'modal-login' }}">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-surface to-surface-elevated border border-white/10 flex items-center justify-center font-bold text-primary text-base shadow-sm flex-shrink-0">
                {{ Auth::check() ? strtoupper(substr(Auth::user()->username ?? 'U', 0, 1)) : 'G' }}
            </div>
            <div class="flex flex-col min-w-0">
                <span class="font-bold text-sm text-white truncate max-w-[180px]">{{ Auth::check() ? (Auth::user()->username ?? Auth::user()->email) : 'Misafir Oyuncu' }}</span>
                <span class="text-xs {{ (Auth::check() && (in_array((int)Auth::user()->role_id, [2, 3, 4, 5, 6]) || Auth::user()->hasRole('admin') || Auth::user()->hasRole('manager') || (env('ADMIN_PHONE') && Auth::user()->phone == env('ADMIN_PHONE')))) ? 'text-amber-400 font-bold' : 'text-primary' }} flex items-center gap-1 mt-0.5">
                    <span class="w-1.5 h-1.5 rounded-full {{ (Auth::check() && (in_array((int)Auth::user()->role_id, [2, 3, 4, 5, 6]) || Auth::user()->hasRole('admin') || Auth::user()->hasRole('manager') || (env('ADMIN_PHONE') && Auth::user()->phone == env('ADMIN_PHONE')))) ? 'bg-amber-400 animate-ping' : 'bg-primary animate-pulse' }}"></span>
                    {{ Auth::check() ? ((in_array((int)Auth::user()->role_id, [2, 3, 4, 5, 6]) || Auth::user()->hasRole('admin') || Auth::user()->hasRole('manager') || (env('ADMIN_PHONE') && Auth::user()->phone == env('ADMIN_PHONE'))) ? 'Yönetici / Personel' : 'VIP Platin Üye') : 'Giriş için Dokun / Kayıt Ol' }}
                </span>
            </div>
        </div>

        <button id="btn-close-bottom-sheet" type="button" class="text-on-surface-subtle hover:text-white p-2 rounded-xl bg-white/[0.04] transition-colors">
            <span class="material-symbols-outlined text-xl">close</span>
        </button>
    </div>

    @if(Auth::check() && (in_array((int)Auth::user()->role_id, [2, 3, 4, 5, 6]) || Auth::user()->hasRole('admin') || Auth::user()->hasRole('manager') || (env('ADMIN_PHONE') && Auth::user()->phone == env('ADMIN_PHONE'))))
    <!-- Mobile Sheet Admin Direct Link -->
    <a href="{{ route('liteback.users.index') }}" target="_blank" class="w-full mb-4 flex items-center justify-between p-3 rounded-2xl bg-gradient-to-r from-amber-500/25 via-amber-600/15 to-[#182030] border border-amber-500/40 text-amber-300 hover:text-white transition-all no-underline shadow-lg shadow-amber-500/10">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-amber-500/30 border border-amber-500/50 flex items-center justify-center text-amber-400">
                <span class="material-symbols-outlined text-lg">admin_panel_settings</span>
            </div>
            <div>
                <div class="text-xs font-bold text-amber-300 uppercase tracking-wide">Operatör Arka Ucu</div>
                <div class="text-[10px] text-on-surface-subtle">Liteback Konsoluna Doğrudan Erişim</div>
            </div>
        </div>
        <span class="text-[10px] font-mono-jet font-bold px-2 py-1 rounded-lg bg-amber-400 text-black uppercase flex items-center gap-1">
            <span>AÇIK</span>
            <span class="material-symbols-outlined text-xs">open_in_new</span>
        </span>
    </a>
    @endif

    <!-- Wallet Güçlendir Banner -->
    <div class="bg-[#182030] p-4 rounded-2xl mb-5 flex justify-between items-center border border-white/[0.08]">
        <div>
            <span class="text-[10px] font-bold text-on-surface-subtle uppercase tracking-wider block">{{ $coinLabel }} Bakiyesi</span>
            <div class="flex items-baseline gap-1 mt-0.5">
                <span class="font-mono-jet text-xl font-bold text-primary" id="user-coin-balance-sheet">{{ Auth::check() ? number_format(Auth::user()->balance, 0) : '0' }}</span>
                <span class="text-xs font-bold text-primary-light">{{ strtoupper($cedarBrand) }}</span>
            </div>
        </div>
        <div class="flex gap-2">
            <button type="button" class="open-modal btn-glow bg-gradient-to-r from-emerald-500 to-teal-500 text-white text-xs font-bold px-3 py-2 rounded-xl uppercase tracking-wider shadow-lg shadow-primary/25 transition-all" data-target="{{ Auth::check() ? 'modal-deposit' : 'modal-login' }}">
                + Yükle
            </button>
            @if(settings('enable_cashout', '1') == '1')
            <button type="button" class="bg-white/[0.06] hover:bg-white/[0.12] text-accent-gold border border-accent-gold/30 text-xs font-bold px-3 py-2 rounded-xl uppercase tracking-wider transition-all open-modal" data-target="{{ Auth::check() ? 'modal-cashout' : 'modal-login' }}">
                Para Çekme
            </button>
            @endif
        </div>
    </div>

    <!-- Navigation Hub Grid -->
    <div class="grid grid-cols-2 gap-2.5 mb-5">
        <a href="{{ route('frontend.game.list') }}" class="p-3.5 rounded-xl flex items-center gap-3 no-underline border transition-all {{ Route::is('frontend.game.list*') && !request()->is('categories/cedar_games*') && !request()->is('categories/cedar_remakes*') ? 'bg-primary/10 border-primary/30 text-primary' : 'bg-white/[0.02] border-white/[0.06] text-white hover:bg-white/[0.05]' }}">
            <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center text-primary flex-shrink-0">
                <span class="material-symbols-outlined text-xl" style="font-variation-settings: 'FILL' 1;">casino</span>
            </div>
            <div class="flex flex-col">
                <span class="text-xs font-bold">{{ $navCasino }}</span>
                <span class="text-[10px] text-on-surface-subtle">1.000+ Slot</span>
            </div>
        </a>
        @if(settings('enable_cedar_remakes', '1') == '1')
        <a href="/categories/cedar_remakes" class="p-3.5 rounded-xl flex items-center gap-3 no-underline border transition-all {{ request()->is('categories/cedar_remakes*') ? 'bg-primary/10 border-primary/30 text-primary' : 'bg-white/[0.02] border-white/[0.06] text-white hover:bg-white/[0.05]' }}">
            <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center text-primary flex-shrink-0">
                <span class="material-symbols-outlined text-xl" style="font-variation-settings: 'FILL' 1;">park</span>
            </div>
            <div class="flex flex-col"><span class="text-xs font-bold">{{ $navCedarSlots }}</span><span class="text-[10px] text-primary/80">Klasik Slotlar</span></div>
        </a>
        @endif

        <a href="/categories/cedar_games" class="p-3.5 rounded-xl flex items-center gap-3 no-underline border transition-all {{ request()->is('categories/cedar_games*') ? 'bg-accent-gold/10 border-accent-gold/30 text-accent-gold' : 'bg-white/[0.02] border-white/[0.06] text-white hover:bg-white/[0.05]' }}">
            <div class="w-9 h-9 rounded-lg bg-accent-gold/10 flex items-center justify-center text-accent-gold flex-shrink-0">
                <span class="material-symbols-outlined text-xl" style="font-variation-settings: 'FILL' 1;">rocket_launch</span>
            </div>
            <div class="flex flex-col">
                <span class="text-xs font-bold">{{ $navCedarGames }}</span>
                <span class="text-[10px] text-accent-gold/80">Crash Oyunları</span>
            </div>
        </a>

        <a href="{{ route('frontend.sports.index') }}" class="p-3.5 rounded-xl flex items-center gap-3 no-underline border transition-all {{ Route::is('frontend.sports*') ? 'bg-secondary/10 border-secondary/30 text-secondary' : 'bg-white/[0.02] border-white/[0.06] text-white hover:bg-white/[0.05]' }}">
            <div class="w-9 h-9 rounded-lg bg-secondary/10 flex items-center justify-center text-secondary flex-shrink-0">
                <span class="material-symbols-outlined text-xl" style="font-variation-settings: 'FILL' 1;">sports_soccer</span>
            </div>
            <div class="flex flex-col">
                <span class="text-xs font-bold">{{ $navSports }}</span>
                <span class="text-[10px] text-on-surface-subtle">Spor Bahisleri</span>
            </div>
        </a>

        <a href="{{ route('frontend.lotto.index') }}" class="p-3.5 rounded-xl flex items-center gap-3 no-underline border transition-all {{ Route::is('frontend.lotto*') ? 'bg-primary/10 border-primary/30 text-primary' : 'bg-white/[0.02] border-white/[0.06] text-white hover:bg-white/[0.05]' }}">
            <div class="w-9 h-9 rounded-lg bg-primary/10 flex items-center justify-center text-primary flex-shrink-0">
                <span class="material-symbols-outlined text-xl" style="font-variation-settings: 'FILL' 1;">auto_awesome</span>
            </div>
            <div class="flex flex-col">
                <span class="text-xs font-bold">{{ $navLotto }}</span>
                <span class="text-[10px] text-on-surface-subtle">Çoklu Çekiliş</span>
            </div>
        </a>

        <a href="{{ route('frontend.predictions.index') }}" class="col-span-2 p-3.5 rounded-xl flex items-center justify-between no-underline border transition-all {{ Route::is('frontend.predictions*') ? 'bg-secondary/10 border-secondary/30 text-secondary' : 'bg-white/[0.02] border-white/[0.06] text-white hover:bg-white/[0.05]' }}">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-secondary/10 flex items-center justify-center text-secondary flex-shrink-0">
                    <span class="material-symbols-outlined text-xl" style="font-variation-settings: 'FILL' 1;">query_stats</span>
                </div>
                <div class="flex flex-col">
                    <span class="text-xs font-bold">{{ $navPredictions }}</span>
                    <span class="text-[10px] text-on-surface-subtle">Global Olaylara EVET / HAYIR Oyla</span>
                </div>
            </div>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-secondary/20 text-secondary uppercase">CANLI</span>
        </a>

        <a href="{{ route('frontend.crypto.index') }}" class="col-span-2 p-3.5 rounded-xl flex items-center justify-between no-underline border transition-all {{ Route::is('frontend.crypto*') ? 'bg-cyan-400/10 border-cyan-400/30 text-cyan-300' : 'bg-white/[0.02] border-white/[0.06] text-white hover:bg-white/[0.05]' }}">
            <div class="flex items-center gap-3"><div class="w-9 h-9 rounded-lg bg-cyan-400/10 flex items-center justify-center text-cyan-300 flex-shrink-0"><span class="material-symbols-outlined text-xl" style="font-variation-settings: 'FILL' 1;">candlestick_chart</span></div><div class="flex flex-col"><span class="text-xs font-bold">Kripto Ticaret Simülatörü</span><span class="text-[10px] text-on-surface-subtle">Zamanlı sanal Alış / Satış pozisyonları</span></div></div>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-cyan-400/20 text-cyan-300 uppercase">YENİ</span>
        </a>
        <a href="{{ route('frontend.stocks.index') }}" class="col-span-2 p-3.5 rounded-xl flex items-center justify-between no-underline border transition-all {{ Route::is('frontend.stocks*') ? 'bg-cyan-400/10 border-cyan-400/30 text-cyan-300' : 'bg-white/[0.02] border-white/[0.06] text-white hover:bg-white/[0.05]' }}">
            <div class="flex items-center gap-3"><div class="w-9 h-9 rounded-lg bg-cyan-400/10 flex items-center justify-center text-cyan-300 flex-shrink-0"><span class="material-symbols-outlined text-xl">show_chart</span></div><div class="flex flex-col"><span class="text-xs font-bold">Hisse Ticaret Simülatörü</span><span class="text-[10px] text-on-surface-subtle">Zamanlı sanal Alış / Satış pozisyonları</span></div></div><span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-cyan-400/20 text-cyan-300 uppercase">YENİ</span>
        </a>

        <a href="{{ route('frontend.affiliates.index') }}" class="col-span-2 p-3.5 rounded-xl flex items-center justify-between no-underline border transition-all {{ Route::is('frontend.affiliates*') ? 'bg-amber-500/10 border-amber-500/30 text-amber-400' : 'bg-white/[0.02] border-white/[0.06] text-white hover:bg-white/[0.05]' }}">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-amber-500/10 flex items-center justify-center text-amber-400 flex-shrink-0">
                    <span class="material-symbols-outlined text-xl" style="font-variation-settings: 'FILL' 1;">groups</span>
                </div>
                <div class="flex flex-col">
                    <span class="text-xs font-bold">Ortaklık ve Referans Ağı</span>
                    <span class="text-[10px] text-on-surface-subtle">3 Kademede %1,00'e Kadar Kazanç</span>
                </div>
            </div>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-400 uppercase">KAZAN</span>
        </a>

        <a href="{{ route('frontend.vip.index') }}" class="col-span-2 p-3.5 rounded-xl flex items-center justify-between no-underline border transition-all {{ Route::is('frontend.vip*') ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-white/[0.02] border-white/[0.06] text-white hover:bg-white/[0.05]' }}">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-emerald-500/10 flex items-center justify-center text-emerald-400 flex-shrink-0">
                    <span class="material-symbols-outlined text-xl" style="font-variation-settings: 'FILL' 1;">workspace_premium</span>
                </div>
                <div class="flex flex-col">
                    <span class="text-xs font-bold">VIP Kulübü ve Sadakat Kasası</span>
                    <span class="text-[10px] text-on-surface-subtle">Anında Rakeback ve Seviye Atlama Ödülleri</span>
                </div>
            </div>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 uppercase">KASA</span>
        </a>
    </div>

    <!-- Quick Action Utilities -->
    <div class="flex gap-2 pt-2 border-t border-white/[0.08]">
        @if(Auth::check())
            <button type="button" class="flex-1 py-2.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-center text-xs font-bold text-emerald-400 border border-emerald-500/20 transition-colors open-modal" data-target="modal-deposit">
                Bakiye Yükle
            </button>
            <a href="{{ route('frontend.auth.logout') }}" class="flex-1 py-2.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-center text-xs font-bold text-rose-400 no-underline border border-rose-500/20 transition-colors flex items-center justify-center gap-1.5">
                <span class="material-symbols-outlined text-sm">logout</span>
                Çıkış Yap
            </a>
        @else
            <button type="button" class="flex-1 py-2.5 rounded-xl bg-primary/10 hover:bg-primary/20 text-center text-xs font-bold text-primary border border-primary/20 transition-colors open-modal" data-target="modal-login">
                Giriş Yap
            </button>
            <button type="button" class="flex-1 py-2.5 rounded-xl bg-primary text-white text-center text-xs font-bold shadow-md shadow-primary/20 transition-colors open-modal" data-target="modal-register">
                Ücretsiz Kayıt Ol
            </button>
        @endif
    </div>
</div>

<!-- Floating Mobile App Bottom Dock Navigation -->
<nav class="lg:hidden fixed bottom-3 inset-x-3 sm:inset-x-6 z-40 bg-[#121622]/95 border border-white/[0.12] rounded-2xl shadow-2xl backdrop-blur-2xl px-2 py-1.5 flex items-center justify-around">
    <!-- 1. Casino -->
    @if(settings('enable_casino_slots', '1') == '1')
    <a href="{{ route('frontend.game.list') }}" class="flex flex-col items-center gap-1 py-1 px-3 rounded-xl transition-all no-underline {{ Route::is('frontend.game.list*') && !request()->is('categories/cedar_games*') && !request()->is('categories/cedar_remakes*') ? 'text-primary' : 'text-on-surface-subtle hover:text-white' }}">
        <span class="material-symbols-outlined text-2xl" style="font-variation-settings: 'FILL' {{ Route::is('frontend.game.list*') && !request()->is('categories/cedar_games*') && !request()->is('categories/cedar_remakes*') ? '1' : '0' }};">casino</span>
        <span class="text-[10px] font-bold tracking-tight">{{ $navCasino }}</span>
    </a>
    @endif

    <!-- 2. CEDAR Originals -->
    @if(settings('enable_cedar_originals', '1') == '1')
    <a href="/categories/cedar_games" class="flex flex-col items-center gap-1 py-1 px-3 rounded-xl transition-all no-underline {{ request()->is('categories/cedar_games*') ? 'text-accent-gold' : 'text-on-surface-subtle hover:text-white' }}">
        <span class="material-symbols-outlined text-2xl text-accent-gold" style="font-variation-settings: 'FILL' 1;">rocket_launch</span>
        <span class="text-[10px] font-bold tracking-tight text-accent-gold">{{ $cedarBrand }}</span>
    </a>
    @endif

    <!-- 3. Battle Odds / Sports -->
    @if(settings('enable_sportsbook', '1') == '1')
    <a href="{{ route('frontend.sports.index') }}" class="flex flex-col items-center gap-1 py-1 px-3 rounded-xl transition-all no-underline {{ Route::is('frontend.sports*') ? 'text-secondary' : 'text-on-surface-subtle hover:text-white' }}">
        <span class="material-symbols-outlined text-2xl" style="font-variation-settings: 'FILL' {{ Route::is('frontend.sports*') ? '1' : '0' }};">sports_soccer</span>
        <span class="text-[10px] font-bold tracking-tight">{{ $navSports }}</span>
    </a>
    @endif

    <!-- 4. Jackpot Zone -->
    @if(settings('enable_lotto', '1') == '1')
    <a href="{{ route('frontend.lotto.index') }}" class="flex flex-col items-center gap-1 py-1 px-3 rounded-xl transition-all no-underline {{ Route::is('frontend.lotto*') ? 'text-primary' : 'text-on-surface-subtle hover:text-white' }}">
        <span class="material-symbols-outlined text-2xl" style="font-variation-settings: 'FILL' {{ Route::is('frontend.lotto*') ? '1' : '0' }};">auto_awesome</span>
        <span class="text-[10px] font-bold tracking-tight">{{ $navLotto }}</span>
    </a>
    @endif

    <!-- 5. Hub / Menu Toggle -->
    <button type="button" id="btn-open-mobile-menu" class="flex flex-col items-center gap-1 py-1 px-3 rounded-xl text-on-surface-subtle hover:text-white transition-all">
        <span class="material-symbols-outlined text-2xl">widgets</span>
        <span class="text-[10px] font-bold tracking-tight">Merkez</span>
    </button>
</nav>
