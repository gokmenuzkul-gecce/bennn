@php
    $fBrand = settings('app_name') ?: 'Promex Gaming Suite';
    $fTagline = settings('brand_tagline') ?: 'Sosyal Oyun';
    $fYear = date('Y');
    $fLinks = [
        'Oyun' => [
            ['label' => 'Tüm Oyunlar', 'route' => 'frontend.game.list'],
            ['label' => 'Canlı Bahis', 'route' => 'frontend.sports.index'],
            ['label' => 'Tahmin Piyasaları', 'route' => 'frontend.predictions.index'],
            ['label' => 'Piyango', 'route' => 'frontend.lotto.index'],
        ],
        'Kazanç' => [
            ['label' => 'VIP Kulübü', 'route' => 'frontend.vip.index'],
            ['label' => 'Bonuslar', 'route' => 'frontend.bonuses'],
            ['label' => 'Ortaklık Programı', 'route' => 'frontend.affiliates.index'],
            ['label' => 'Kripto Borsası', 'route' => 'frontend.crypto.index'],
        ],
        'Destek' => [
            ['label' => 'Yardım Merkezi', 'route' => 'frontend.help'],
            ['label' => 'Sıkça Sorulan Sorular', 'route' => 'frontend.faq'],
            ['label' => 'Bonus Şartları', 'route' => 'frontend.bonus.conditions'],
            ['label' => 'İletişim', 'route' => 'frontend.help'],
        ],
    ];
@endphp

<footer class="relative mt-12 border-t border-white/[0.07] bg-[#0b0e17]/80 overflow-hidden">
    {{-- Ambient glow --}}
    <div class="pointer-events-none absolute -top-24 left-1/4 h-48 w-48 rounded-full bg-emerald-500/10 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-24 right-1/4 h-48 w-48 rounded-full bg-teal-500/10 blur-3xl"></div>

    <div class="relative mx-auto w-full max-w-7xl px-4 sm:px-6 md:px-10 lg:px-12 py-10 md:py-14">
        <div class="grid grid-cols-1 gap-10 md:grid-cols-2 lg:grid-cols-5">
            {{-- Brand --}}
            <div class="lg:col-span-2 space-y-4">
                <div class="flex items-center gap-3">
                    <img src="/minimal/logo.svg" alt="{{ $fBrand }}" class="w-11 h-11 rounded-xl object-contain shadow-lg shadow-emerald-500/30 flex-shrink-0">
                    <div class="leading-tight">
                        <span class="block text-base font-extrabold tracking-tight text-white">{{ strtoupper($fBrand) }}</span>
                        <span class="block text-[10px] font-bold tracking-widest text-emerald-400 uppercase">{{ $fTagline }}</span>
                    </div>
                </div>
                <p class="max-w-sm text-[13px] leading-relaxed text-on-surface-muted">
                    Modern, güvenli ve hızlı oyun deneyimi. Dünyanın önde gelen sağlayıcılarından binlerce oyun,
                    canlı bahis ve tahmin piyasaları tek çatı altında.
                </p>
                <div class="flex items-center gap-2 pt-1">
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

            {{-- Link columns --}}
            @foreach($fLinks as $heading => $links)
                <div class="space-y-3">
                    <h4 class="text-[11px] font-extrabold uppercase tracking-widest text-white">{{ $heading }}</h4>
                    <ul class="space-y-2.5">
                        @foreach($links as $link)
                            <li>
                                <a href="{{ route($link['route']) }}"
                                   class="group inline-flex items-center gap-1.5 text-[13px] font-medium text-on-surface-muted no-underline transition-colors hover:text-emerald-400">
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
        <div class="mt-10 flex flex-col gap-4 border-t border-white/[0.07] pt-6 md:flex-row md:items-center md:justify-between">
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-[10px] font-bold uppercase tracking-widest text-on-surface-subtle">Ödeme:</span>
                @foreach(['Visa', 'Mastercard', 'Havale/EFT', 'Kripto', 'Papara'] as $pay)
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
                {{ $fTagline }} &middot; Sürüm 1.0 &middot; <span class="text-emerald-400/80 font-semibold">Güvenli &amp; Lisanslı Altyapı</span>
            </p>
        </div>
    </div>
</footer>
