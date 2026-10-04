@extends('frontend.Minimal.layouts.clean')

@section('page-title', 'Yorumlar - ' . (settings('app_name') ?: 'Casino Gecce'))

@section('content')
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 bg-primary/10 border border-primary/25 px-3 py-1 rounded-full mb-2">
                <span class="material-symbols-outlined text-primary text-base">reviews</span>
                <span class="text-[11px] font-bold text-primary font-mono-jet uppercase tracking-wider">OYUNCU GERİ BİLDİRİMİ</span>
            </div>
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-white tracking-tight uppercase">Yorumlar</h1>
            <p class="text-on-surface-muted text-xs sm:text-sm mt-1">Oyunculardan gelen gerçek yorumlar.</p>
        </div>
        <div class="bg-[#121622] border border-white/[0.08] rounded-2xl px-5 py-3 text-center">
            <div class="text-[10px] uppercase font-bold text-on-surface-subtle tracking-widest">Yorum</div>
            <div class="text-2xl font-black text-primary font-mono-jet">{{ number_format($stats['count']) }}</div>
        </div>
    </div>

    @if($reviews->isEmpty())
        <div class="text-center py-16 bg-[#121622] rounded-3xl border border-white/[0.06]">
            <span class="material-symbols-outlined text-4xl text-on-surface-subtle mb-2">reviews</span>
            <p class="text-on-surface-muted text-sm font-medium">Henüz yayınlanmış bir yorum yok.</p>
            <p class="text-xs text-on-surface-subtle mt-1">Oyunculardan gelen yorumlar burada listelenir.</p>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($reviews as $r)
                <article class="bg-[#121622] rounded-2xl border border-white/[0.08] p-5 space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="w-9 h-9 rounded-xl bg-primary/15 text-primary flex items-center justify-center font-bold">{{ strtoupper(substr($r['player'], 0, 1)) }}</div>
                            <div>
                                <div class="text-sm font-bold text-white">{{ $r['player'] }}</div>
                                <div class="text-[10px] text-on-surface-subtle">{{ \Illuminate\Support\Carbon::parse($r['at'])->diffForHumans() }}</div>
                            </div>
                        </div>
                        <span class="material-symbols-outlined text-accent-gold text-lg" style="font-variation-settings:'FILL' 1;">star</span>
                    </div>
                    @if($r['title'])<h3 class="text-sm font-bold text-white pt-1">{{ $r['title'] }}</h3>@endif
                    <p class="text-sm text-on-surface-muted leading-relaxed">{{ $r['body'] }}</p>
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
