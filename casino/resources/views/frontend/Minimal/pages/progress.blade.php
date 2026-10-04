@extends('frontend.Minimal.layouts.clean')

@section('page-title', 'Seviye Programı - ' . (settings('app_name') ?: 'Casino Gecce'))

@section('content')
<div class="space-y-6">
    <div>
        <div class="inline-flex items-center gap-2 bg-emerald-500/10 border border-emerald-500/25 px-3 py-1 rounded-full mb-2">
            <span class="material-symbols-outlined text-emerald-400 text-base">trending_up</span>
            <span class="text-[11px] font-bold text-emerald-400 font-mono-jet uppercase tracking-wider">SADAKAT PROGRAMI</span>
        </div>
        <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-white tracking-tight uppercase">Seviye Ödülleri</h1>
        <p class="text-on-surface-muted text-xs sm:text-sm mt-1">Operatör tarafından tanımlanan gerçek seviye basamakları.</p>
    </div>

    @if(!$progress || (is_countable($progress) && count($progress) === 0))
        <div class="text-center py-16 bg-[#121622] rounded-3xl border border-white/[0.06]">
            <span class="material-symbols-outlined text-4xl text-on-surface-subtle mb-2">trending_up</span>
            <p class="text-on-surface-muted text-sm font-medium">Seviye programı şu anda aktif değil.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($progress as $step)
                <article class="bg-[#121622] rounded-2xl border border-white/[0.08] p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-10 h-10 rounded-2xl bg-emerald-500/15 text-emerald-400 flex items-center justify-center font-black font-mono-jet">{{ $step->rating }}</div>
                            <div>
                                <div class="text-sm font-extrabold text-white">Seviye {{ $step->rating }}</div>
                                <div class="text-[10px] text-on-surface-subtle uppercase font-mono-jet">{{ $step->type === 'one_pay' ? 'Tek Seferlik' : 'Kümülatif' }}</div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-xl font-black text-emerald-400 font-mono-jet">{{ rtrim(rtrim(number_format((float)$step->bonus, 2, '.', ''), '0'), '.') }}₺</div>
                            <div class="text-[10px] text-on-surface-subtle">ödül</div>
                        </div>
                    </div>
                    <dl class="grid grid-cols-2 gap-2 text-xs">
                        @foreach(['sum' => 'Gereken', 'spins' => 'Freespin', 'percent' => 'Yüzde %', 'wager' => 'Çevrim'] as $key => $label)
                            @if(isset($step->{$key}))
                                <div class="flex justify-between bg-white/[0.03] rounded-lg px-3 py-2">
                                    <dt class="text-on-surface-subtle">{{ $label }}</dt>
                                    <dd class="font-mono-jet font-bold text-white">{{ $step->{$key} }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
