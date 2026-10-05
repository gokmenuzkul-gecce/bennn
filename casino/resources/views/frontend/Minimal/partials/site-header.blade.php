@php
    $hBrandName = settings('app_name') ?: 'Casino Gecce';
    $hTagline = settings('brand_tagline') ?: 'Sosyal Oyun';
    $hLogo = settings('brand_logo_path');
    $hBrandMark = $hLogo
        ? \Illuminate\Support\Facades\Storage::url($hLogo)
        : '/minimal/brand-logo.png?v=' . (@filemtime(base_path('../minimal/brand-logo.png')) ?: '1');
    $hStaff = Auth::check() && (in_array((int)Auth::user()->role_id, [2, 3, 4, 5, 6]) || Auth::user()->hasRole('admin') || Auth::user()->hasRole('manager') || (env('ADMIN_PHONE') && Auth::user()->phone == env('ADMIN_PHONE')));

    // Primary horizontal navigation: left block is the product, right block is engagement.
    $hLeft = [
        ['label' => 'Ana Sayfa', 'url' => route('frontend.game.list'), 'active' => Route::is('frontend.game.list') && !request()->is('categories/*')],
        ['label' => 'Slot', 'url' => route('frontend.game.list.category', ['category1' => 'slots']), 'active' => request()->is('categories/slots*')],
        ['label' => 'Canlı Casino', 'url' => route('frontend.game.list.category', ['category1' => 'evolution']), 'active' => request()->is('categories/evolution*')],
        ['label' => 'Spor Bahisleri', 'url' => route('frontend.sports.index'), 'active' => Route::is('frontend.sports*') && request('live') != 1],
        ['label' => 'Canlı Bahis', 'url' => route('frontend.sports.index', ['live' => 1]), 'active' => Route::is('frontend.sports*') && request('live') == 1],
    ];
    $hRight = [
        ['label' => 'Bonuslar', 'url' => route('frontend.bonuses'), 'active' => Route::is('frontend.bonuses')],
        ['label' => 'VİP', 'url' => route('frontend.vip.index'), 'active' => Route::is('frontend.vip*')],
        ['label' => 'Kazananlar', 'url' => route('frontend.winners'), 'active' => Route::is('frontend.winners')],
        ['label' => 'Yorumlar', 'url' => route('frontend.reviews'), 'active' => Route::is('frontend.reviews')],
    ];

    // Extra destinations that live in the drawer (kept off the crowded top bar).
    $hDrawerExtra = array_values(array_filter([
        settings('enable_lotto', '1') == '1' ? ['label' => 'Jackpot Bölgesi', 'url' => route('frontend.lotto.index'), 'icon' => 'auto_awesome'] : null,
        settings('enable_predictions', '1') == '1' ? ['label' => 'Tahmin Piyasaları', 'url' => route('frontend.predictions.index'), 'icon' => 'query_stats'] : null,
        ['label' => 'Kripto Ticareti', 'url' => route('frontend.crypto.index'), 'icon' => 'candlestick_chart'],
        ['label' => 'Hisse Ticareti', 'url' => route('frontend.stocks.index'), 'icon' => 'show_chart'],
        ['label' => 'Ortaklık Programı', 'url' => route('frontend.affiliates.index'), 'icon' => 'groups'],
        ['label' => 'Yardım Merkezi', 'url' => route('frontend.help'), 'icon' => 'help'],
    ]));
@endphp

<!-- Top navigation bar (sits above the banner art, never over the middle of it) -->
<div class="site-header-nav">
    <!-- Left: hamburger (opens drawer) + primary product links -->
    <div class="site-nav-left">
        <button type="button" class="site-burger" id="btn-open-site-drawer" aria-label="Menüyü aç" aria-controls="site-drawer">
            <span class="material-symbols-outlined">menu</span>
        </button>
        @foreach($hLeft as $item)
            <a href="{{ $item['url'] }}" class="site-nav-link {{ $item['active'] ? 'is-active' : '' }}">{{ $item['label'] }}</a>
        @endforeach
    </div>

    <!-- Center: brand mark -->
    <a href="{{ route('frontend.game.list') }}" class="site-brand-center" aria-label="{{ $hBrandName }}">
        <span class="site-brand-halo" aria-hidden="true"></span>
        <img src="{{ $hBrandMark }}" alt="{{ $hBrandName }}" decoding="async" fetchpriority="high">
    </a>

    <!-- Right: engagement links + auth / account cluster -->
    <div class="site-nav-right">
        @foreach($hRight as $item)
            <a href="{{ $item['url'] }}" class="site-nav-link {{ $item['active'] ? 'is-active' : '' }}">{{ $item['label'] }}</a>
        @endforeach
        @guest
            <button type="button" class="site-nav-cta site-nav-cta--ghost open-modal" data-target="modal-login">Giriş Yap</button>
            <button type="button" class="site-nav-cta site-nav-cta--primary open-modal" data-target="modal-register">Kayıt Ol</button>
            <button type="button" class="site-nav-cta site-nav-cta--admin open-modal" data-target="modal-admin-login" title="Operatör girişi">
                <span class="material-symbols-outlined">shield_person</span>
                Admin Girişi
            </button>
        @else
            @if((int) Auth::user()->role_id === 6)
            <a href="{{ route('liteback.users.index') }}" class="site-nav-cta site-nav-cta--admin" title="Yönetim konsolu">
                <span class="material-symbols-outlined">admin_panel_settings</span>
                Yönetim Konsolu
            </a>
            @endif
            <div class="site-nav-account" id="account-menu-wrap">
                <button type="button" id="account-menu-toggle" aria-haspopup="true" aria-expanded="false" class="site-nav-cta site-nav-cta--ghost">
                    <span class="site-nav-avatar">{{ strtoupper(substr(Auth::user()->username ?? 'U', 0, 1)) }}</span>
                    <span class="site-nav-username">{{ Auth::user()->username ?? Auth::user()->email }}</span>
                    <span class="material-symbols-outlined site-nav-caret">expand_more</span>
                </button>
                <div id="account-menu" class="hidden site-nav-menu">
                    <div class="site-nav-menu-head">
                        <p class="site-nav-menu-eyebrow">Hesap</p>
                        <p class="site-nav-menu-name">{{ Auth::user()->username ?? Auth::user()->email }}</p>
                        <div class="site-nav-menu-balance">
                            <span class="font-mono-jet" id="account-menu-balance">{{ number_format(Auth::user()->balance, 2) }}</span>
                            <span class="site-nav-menu-currency">{{ strtoupper(settings('default_currency') ?: 'TRY') }}</span>
                        </div>
                    </div>
                    <nav class="site-nav-menu-list">
                        <button type="button" class="account-menu-item open-modal" data-target="modal-profile"><span class="material-symbols-outlined">person</span> Profilim</button>
                        <button type="button" class="account-menu-item open-modal" data-target="modal-deposit"><span class="material-symbols-outlined">add_card</span> Bakiye Yükle</button>
                        @if(settings('enable_cashout', '1') == '1')
                        <button type="button" class="account-menu-item open-modal" data-target="modal-cashout"><span class="material-symbols-outlined">payments</span> Para Çekme</button>
                        @endif
                        <a href="{{ route('frontend.vip.index') }}" class="account-menu-item no-underline"><span class="material-symbols-outlined">workspace_premium</span> VIP Kulübü</a>
                        <a href="{{ route('frontend.affiliates.index') }}" class="account-menu-item no-underline"><span class="material-symbols-outlined">groups</span> Ortaklık</a>
                        <div class="site-nav-menu-divider"></div>
                        <a href="{{ route('frontend.auth.logout') }}" class="account-menu-item account-menu-item--danger no-underline"><span class="material-symbols-outlined">logout</span> Çıkış Yap</a>
                    </nav>
                </div>
            </div>
        @endguest
    </div>
</div>

<!-- Hamburger drawer (opened from the header corner) -->
<div class="site-drawer-overlay" id="site-drawer-overlay"></div>
<aside class="site-drawer" id="site-drawer" aria-hidden="true">
    <div class="flex items-center justify-between px-4 py-4 border-b border-white/[0.08]">
        <a href="{{ route('frontend.game.list') }}" class="flex items-center gap-2.5 no-underline min-w-0">
            @if($hLogo)
                <img src="{{ \Illuminate\Support\Facades\Storage::url($hLogo) }}" alt="{{ $hBrandName }}" class="w-9 h-9 rounded-lg object-contain flex-shrink-0">
            @else
                <img src="/minimal/promex-emblem.png" alt="{{ $hBrandName }}" class="w-9 h-9 rounded-lg object-contain flex-shrink-0">
            @endif
            <span class="font-extrabold text-sm text-white truncate">{{ $hBrandName }}</span>
        </a>
        <button type="button" class="text-on-surface-subtle hover:text-white p-2 rounded-xl bg-white/[0.04] transition-colors" id="btn-close-site-drawer" aria-label="Menüyü kapat">
            <span class="material-symbols-outlined text-xl">close</span>
        </button>
    </div>

    <nav class="flex-1 px-2.5 py-3 space-y-0.5">
        <div class="site-drawer-heading">Menü</div>
        @foreach($hLeft as $item)
            <a href="{{ $item['url'] }}" class="site-drawer-link {{ $item['active'] ? 'text-primary' : '' }}">
                <span class="material-symbols-outlined">{{ $loop->first ? 'home' : 'sports_esports' }}</span>
                {{ $item['label'] }}
            </a>
        @endforeach
        <div class="site-drawer-heading">Keşfet</div>
        @foreach($hRight as $item)
            <a href="{{ $item['url'] }}" class="site-drawer-link {{ $item['active'] ? 'text-primary' : '' }}">
                <span class="material-symbols-outlined">{{ $item['label'] === 'Bonuslar' ? 'redeem' : ($item['label'] === 'VİP' ? 'workspace_premium' : ($item['label'] === 'Kazananlar' ? 'emoji_events' : 'reviews')) }}</span>
                {{ $item['label'] }}
            </a>
        @endforeach
        <div class="site-drawer-heading">Daha Fazla</div>
        @foreach($hDrawerExtra as $item)
            <a href="{{ $item['url'] }}" class="site-drawer-link">
                <span class="material-symbols-outlined">{{ $item['icon'] }}</span>
                {{ $item['label'] }}
            </a>
        @endforeach
        @if($hStaff)
            <div class="site-drawer-heading">Yönetim</div>
            <a href="{{ route('liteback.users.index') }}" target="_blank" class="site-drawer-link text-amber-400">
                <span class="material-symbols-outlined text-amber-400">admin_panel_settings</span>
                Liteback Konsolu
            </a>
        @endif
    </nav>

    <div class="p-4 border-t border-white/[0.08] space-y-2">
        @auth
            <button type="button" class="open-modal w-full py-2.5 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white text-xs font-bold uppercase tracking-wider open-modal" data-target="modal-deposit">
                Bakiye Yükle
            </button>
            <a href="{{ route('frontend.auth.logout') }}" class="block w-full py-2.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-center text-xs font-bold text-rose-400 no-underline border border-rose-500/20 transition-colors">
                Çıkış Yap
            </a>
        @else
            <button type="button" class="open-modal w-full py-2.5 rounded-xl bg-primary/10 hover:bg-primary/20 text-center text-xs font-bold text-primary border border-primary/20 transition-colors" data-target="modal-login">
                Giriş Yap
            </button>
            <button type="button" class="open-modal w-full py-2.5 rounded-xl bg-primary text-white text-center text-xs font-bold shadow-md shadow-primary/20 transition-colors" data-target="modal-register">
                Kayıt Ol
            </button>
        @endauth
    </div>
</aside>
