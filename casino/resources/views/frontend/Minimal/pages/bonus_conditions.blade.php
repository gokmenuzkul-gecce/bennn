@extends('frontend.Minimal.layouts.clean')

@section('page-title', 'Bonus Şartları - ' . (settings('app_name') ?: 'Casino Gecce'))

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <div class="inline-flex items-center gap-2 bg-accent-gold/10 border border-accent-gold/25 px-3 py-1 rounded-full mb-2">
            <span class="material-symbols-outlined text-accent-gold text-base">gavel</span>
            <span class="text-[11px] font-bold text-accent-gold font-mono-jet uppercase tracking-wider">ŞARTLAR VE KOŞULLAR</span>
        </div>
        <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-white tracking-tight uppercase">Bonus Şartları</h1>
        <p class="text-on-surface-muted text-xs sm:text-sm mt-1">Aşağıdaki çevrim ve kullanım koşulları geçerli bonuslara uygulanır.</p>
    </div>

    @if(empty($bonuses))
        <div class="text-center py-16 bg-[#121622] rounded-3xl border border-white/[0.06]">
            <span class="material-symbols-outlined text-4xl text-on-surface-subtle mb-2">gavel</span>
            <p class="text-on-surface-muted text-sm font-medium">Şu anda yayınlanmış bonus şartı bulunmuyor.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($bonuses as $bonus)
                @php $data = $bonus['data'] ?? null; @endphp
                @if(is_object($data))
                <div class="bg-[#121622] rounded-2xl border border-white/[0.08] p-5">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-extrabold text-white uppercase tracking-tight">{{ ucfirst(str_replace('_', ' ', $bonus['type'] ?? 'Bonus')) }}</h2>
                        @if(isset($data->wager))
                            <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-primary/15 text-primary uppercase">{{ $data->wager }}x Çevrim</span>
                        @endif
                    </div>
                    <ul class="text-xs text-on-surface-muted space-y-1.5 list-disc list-inside">
                        @if(isset($data->bonus))<li>Bonus tutarı: <strong class="text-white">{{ number_format((float)$data->bonus, 2) }} ₺</strong></li>@endif
                        @if(isset($data->sum))<li>Gereken toplam: <strong class="text-white">{{ number_format((float)$data->sum, 2) }} ₺</strong></li>@endif
                        @if(isset($data->min) || isset($data->max))<li>Bahis aralığı: <strong class="text-white">{{ $data->min ?? '-' }} - {{ $data->max ?? '-' }}</strong></li>@endif
                        @if(isset($data->percent))<li>Katkı yüzdesi: <strong class="text-white">%{{ $data->percent }}</strong></li>@endif
                        @if(isset($data->spins))<li>Freespin: <strong class="text-white">{{ $data->spins }}</strong></li>@endif
                        @if(isset($data->days_active))<li>Geçerlilik: <strong class="text-white">{{ $data->days_active }} gün</strong></li>@endif
                        <li>Bonus, çevrim tamamlanmadan çekilemez ve iptal edilirse bonus bakiyesi kaldırılır.</li>
                    </ul>
                </div>
                @endif
            @endforeach
        </div>
    @endif

    <div class="text-center">
        <a href="{{ route('frontend.bonuses') }}" class="inline-flex items-center gap-2 text-xs font-bold text-primary hover:text-primary-light no-underline">
            <span class="material-symbols-outlined text-base">arrow_back</span> Aktif bonuslara dön
        </a>
    </div>
</div>
@endsection
