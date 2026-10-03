@php
    $fBrand = settings('app_name') ?: 'Promex Gaming Suite';
    $fTagline = settings('brand_tagline') ?: 'Social Gaming';
    $fYear = date('Y');
    $fVersion = settings('app_version') ?: '1.0';

    $fColumns = [
        [
            'icon' => 'sports_esports',
            'heading' => 'Oyun',
            'links' => [
                ['label' => 'Tüm Oyunlar', 'route' => 'frontend.game.list'],
                ['label' => 'Canlı Bahis', 'route' => 'frontend.sports.index'],
                ['label' => 'Tahmin Piyasaları', 'route' => 'frontend.predictions.index'],
                ['label' => 'Piyango', 'route' => 'frontend.lotto.index'],
            ],
        ],
        [
            'icon' => 'workspace_premium',
            'heading' => 'Kazanç',
            'links' => [
                ['label' => 'VIP Kulübü', 'route' => 'frontend.vip.index'],
                ['label' => 'Bonuslar', 'route' => 'frontend.bonuses'],
                ['label' => 'Ortaklık Programı', 'route' => 'frontend.affiliates.index'],
                ['label' => 'Kripto Borsası', 'route' => 'frontend.crypto.index'],
            ],
        ],
        [
            'icon' => 'support_agent',
            'heading' => 'Destek',
            'links' => [
                ['label' => 'Yardım Merkezi', 'route' => 'frontend.help'],
                ['label' => 'Sıkça Sorulan Sorular', 'route' => 'frontend.faq'],
                ['label' => 'Bonus Şartları', 'route' => 'frontend.bonus.conditions'],
                ['label' => 'İletişim', 'route' => 'frontend.help'],
            ],
        ],
    ];

    // Route "İletişim" to the operator's mailbox when one is configured.
    $fContactEmail = settings('contact_email');
    if (!empty($fContactEmail)) {
        $fColumns[2]['links'][3]['route'] = null;
        $fColumns[2]['links'][3]['url'] = 'mailto:' . $fContactEmail;
    }

    // Payment chips reflect the gateways the operator has actually switched on.
    $fPayments = [];
    if (settings('payment_manual_enabled', '1')) {
        $fPayments[] = 'Havale/EFT';
    }
    if (settings('payment_stripe_enabled')) {
        $fPayments[] = 'Visa';
        $fPayments[] = 'Mastercard';
    }
    if (settings('payment_btcpay_enabled') || settings('payment_coinbase') || settings('payment_xto_enabled')) {
        $fPayments[] = 'Kripto';
    }
    if (settings('payment_paypal_enabled')) {
        $fPayments[] = 'PayPal';
    }
    $fPayments[] = 'Papara';
@endphp

<footer class="relative mt-12 border-t border-white/[0.07] bg-[#0b0e17]/80 overflow-hidden">
    {{-- Ambient glow --}}
    <div class="pointer-events-none absolute -top-24 left-1/4 h-48 w-48 rounded-full bg-emerald-500/10 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-24 right-1/4 h-48 w-48 rounded-full bg-teal-500/10 blur-3xl"></div>

    <div class="relative mx-auto w-full max-w-7xl px-4 sm:px-6 md:px-10 lg:px-12 py-10 md:py-12">
        {{-- Brand line --}}
        <div class="mb-8 flex flex-col items-center gap-4 text-center sm:flex-row sm:justify-between sm:text-left">
            <a href="{{ route('frontend.game.list') }}" class="flex items-center gap-3 no-underline">
                <img src="{{ settings('brand_logo_path') ? \Illuminate\Support\Facades\Storage::url(settings('brand_logo_path')) : '/minimal/logo.svg' }}"
                     alt="{{ $fBrand }}" class="w-11 h-11 rounded-xl object-contain shadow-lg shadow-emerald-500/30 flex-shrink-0">
                <div class="leading-tight">
                    <span class="block text-base font-extrabold tracking-tight text-white">{{ strtoupper($fBrand) }}</span>
                    <span class="block text-[10px] font-bold tracking-widest text-emerald-400 uppercase">{{ $fTagline }}</span>
                </div>
            </a>
            <div class="flex flex-wrap items-center justify-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-lg border border-white/[0.08] bg-white/[0.03] px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wider text-on-surface-muted">
                    <span class="material-symbols-outlined text-sm text-emerald-400" style="font-variation-settings: 'FILL' 1;">verified_user</span> SSL Güvenli
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-lg border border-white/[0.08] bg-white/[0.03] px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wider text-on-surface-muted">
                    <span class="material-symbols-outlined text-sm text-emerald-400" style="font-variation-settings: 'FILL' 1;">bolt</span> Anında Ödeme
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-lg border border-white/[0.08] bg-white/[0.03] px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wider text-on-surface-muted">
                    <span class="material-symbols-outlined text-sm text-emerald-400" style="font-variation-settings: 'FILL' 1;">support_agent</span> 7/24 Destek
                </span>
            </div>
        </div>

        {{-- Three side-by-side link boxes --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($fColumns as $column)
                <div class="rounded-2xl border border-white/[0.07] bg-white/[0.02] p-5 transition-colors hover:border-emerald-400/25">
                    <div class="mb-3.5 flex items-center gap-2">
                        <span class="material-symbols-outlined text-lg text-emerald-400" style="font-variation-settings: 'FILL' 1;">{{ $column['icon'] }}</span>
                        <h4 class="text-[11px] font-extrabold uppercase tracking-widest text-white">{{ $column['heading'] }}</h4>
                    </div>
                    <ul class="space-y-2.5">
                        @foreach($column['links'] as $link)
                            <li>
                                <a href="{{ !empty($link['url']) ? $link['url'] : route($link['route']) }}"
                                   class="group inline-flex items-center gap-2 text-[13px] font-medium text-on-surface-muted no-underline transition-colors hover:text-emerald-400">
                                    <span class="h-1 w-1 rounded-full bg-white/20 transition-colors group-hover:bg-emerald-400"></span>
                                    {{ $link['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>

        {{-- Payment / trust strip --}}
        <div class="mt-8 flex flex-col gap-4 border-t border-white/[0.07] pt-6 md:flex-row md:items-center md:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-subtle">Ödeme:</span>
                @foreach(array_values(array_unique($fPayments)) as $pay)
                    <span class="rounded-lg border border-white/[0.08] bg-white/[0.03] px-2.5 py-1 text-[10px] font-bold tracking-wider text-on-surface-muted">{{ $pay }}</span>
                @endforeach
            </div>
            <div class="flex items-center gap-3">
                <span class="inline-flex h-8 items-center rounded-lg border border-rose-500/30 bg-rose-500/10 px-2.5 text-[11px] font-extrabold text-rose-400">18+</span>
                <span class="text-[11px] leading-snug text-on-surface-subtle">Sorumlu oyun oynayın.<br>Oyun bağımlılığı ciddi bir sorundur.</span>
            </div>
        </div>

        {{-- Bottom bar --}}
        <div class="mt-6 flex flex-col items-center justify-between gap-3 border-t border-white/[0.07] pt-6 text-center md:flex-row md:text-left">
            <p class="text-[12px] text-on-surface-subtle">
                &copy; {{ $fYear }} <span class="font-bold text-on-surface-muted">{{ $fBrand }}</span>. Tüm hakları saklıdır.
            </p>
            <p class="text-[12px] text-on-surface-subtle">
                {{ $fTagline }} &middot; Sürüm {{ $fVersion }} &middot; <span class="text-emerald-400/80 font-semibold">Güvenli &amp; Lisanslı Altyapı</span>
            </p>
        </div>
    </div>
</footer>
