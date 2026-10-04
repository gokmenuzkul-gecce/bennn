@extends('frontend.Minimal.layouts.clean')

@section('page-title', 'Bonuslar - ' . (settings('app_name') ?: 'Casino Gecce'))

@php
    $typeMeta = [
        'welcome_bonus' => ['Hoş Geldin Bonusu', 'redeem', 'text-primary'],
        'happyhour'     => ['Mutlu Saat', 'schedule', 'text-accent-gold'],
        'progress'      => ['Seviye Ödülü', 'trending_up', 'text-emerald-400'],
        'invite'        => ['Davet Bonusu', 'group_add', 'text-cyan-300'],
        'sms_bonus'     => ['SMS Bonusu', 'sms', 'text-secondary'],
    ];
@endphp

@section('content')
<div class="space-y-6">
    <div>
        <div class="inline-flex items-center gap-2 bg-primary/10 border border-primary/25 px-3 py-1 rounded-full mb-2">
            <span class="material-symbols-outlined text-primary text-base">redeem</span>
            <span class="text-[11px] font-bold text-primary font-mono-jet uppercase tracking-wider">AKTİF KAMPANYALAR</span>
        </div>
        <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-white tracking-tight uppercase">Bonuslar</h1>
        <p class="text-on-surface-muted text-xs sm:text-sm mt-1">Operatörünüzün şu anda sunduğu gerçek bonuslar.</p>
    </div>

    @if(empty($bonuses))
        <div class="text-center py-16 bg-[#121622] rounded-3xl border border-white/[0.06]">
            <span class="material-symbols-outlined text-4xl text-on-surface-subtle mb-2">redeem</span>
            <p class="text-on-surface-muted text-sm font-medium">Şu anda aktif bonus bulunmuyor.</p>
            <p class="text-xs text-on-surface-subtle mt-1">Yeni kampanyalar eklendiğinde burada listelenecek.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($bonuses as $bonus)
                @php
                    $type = $bonus['type'] ?? '';
                    $data = $bonus['data'] ?? null;
                    $meta = $typeMeta[$type] ?? ['Bonus', 'card_giftcard', 'text-primary'];
                @endphp
                <article class="relative bg-[#121622] rounded-2xl border border-white/[0.08] p-5 overflow-hidden">
                    @if(!empty($bonus['is_first']))
                        <span class="absolute top-4 right-4 text-[10px] font-bold px-2 py-0.5 rounded-full bg-primary/20 text-primary uppercase tracking-wider">İlk</span>
                    @endif
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-11 h-11 rounded-2xl bg-white/[0.05] border border-white/[0.08] flex items-center justify-center {{ $meta[2] }}">
                            <span class="material-symbols-outlined text-2xl" style="font-variation-settings:'FILL' 1;">{{ $meta[1] }}</span>
                        </div>
                        <div>
                            <h2 class="text-sm font-extrabold text-white uppercase tracking-tight">{{ $meta[0] }}</h2>
                            <span class="text-[10px] text-on-surface-subtle font-mono-jet uppercase">{{ $type }}</span>
                        </div>
                    </div>

                    @if(is_object($data) && isset($data->bonus))
                        <div class="text-3xl font-black text-primary font-mono-jet mb-3">
                            {{ rtrim(rtrim(number_format((float)$data->bonus, 2, '.', ''), '0'), '.') }}<span class="text-lg">₺</span>
                        </div>
                    @endif

                    <dl class="grid grid-cols-2 gap-2 text-xs">
                        @foreach(['wager' => 'Çevrim', 'sum' => 'Min. Yükleme', 'min' => 'Min', 'max' => 'Maks', 'spins' => 'Freespin', 'percent' => 'Yüzde %'] as $key => $label)
                            @if(is_object($data) && isset($data->{$key}) && $data->{$key} !== null)
                                <div class="flex justify-between bg-white/[0.03] rounded-lg px-3 py-2">
                                    <dt class="text-on-surface-subtle">{{ $label }}</dt>
                                    <dd class="font-mono-jet font-bold text-white">{{ $data->{$key} }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                </article>
            @endforeach
        </div>
    @endif

    <div class="text-center">
        <a href="{{ route('frontend.bonus.conditions') }}" class="inline-flex items-center gap-2 text-xs font-bold text-primary hover:text-primary-light no-underline">
            <span class="material-symbols-outlined text-base">gavel</span> Bonus şartlarını okuyun
        </a>
    </div>
</div>
@endsection
