@extends('frontend.Minimal.layouts.clean')

@section('page-title', 'Kazananlar - ' . (settings('app_name') ?: 'Casino Gecce'))

@section('content')
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="inline-flex items-center gap-2 bg-amber-500/10 border border-amber-500/25 px-3 py-1 rounded-full mb-2">
                <span class="material-symbols-outlined text-amber-400 text-base">emoji_events</span>
                <span class="text-[11px] font-bold text-amber-400 font-mono-jet uppercase tracking-wider">CANLI KAZANÇ AKIŞI</span>
            </div>
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-white tracking-tight uppercase">Kazananlar</h1>
            <p class="text-on-surface-muted text-xs sm:text-sm mt-1">Oyunculardan gelen gerçek ödemeler. Liste oynandıkça otomatik güncellenir.</p>
        </div>
        <div class="grid grid-cols-3 gap-3">
            <div class="bg-[#121622] border border-white/[0.08] rounded-2xl px-4 py-3 text-center">
                <div class="text-[10px] uppercase font-bold text-on-surface-subtle tracking-widest">Toplam Ödeme</div>
                <div class="text-lg font-black text-primary font-mono-jet">{{ number_format($stats['total_paid'], 2) }} ₺</div>
            </div>
            <div class="bg-[#121622] border border-white/[0.08] rounded-2xl px-4 py-3 text-center">
                <div class="text-[10px] uppercase font-bold text-on-surface-subtle tracking-widest">Kayıt</div>
                <div class="text-lg font-black text-white font-mono-jet">{{ number_format($stats['payout_count']) }}</div>
            </div>
            <div class="bg-[#121622] border border-white/[0.08] rounded-2xl px-4 py-3 text-center">
                <div class="text-[10px] uppercase font-bold text-on-surface-subtle tracking-widest">En Yüksek</div>
                <div class="text-lg font-black text-accent-gold font-mono-jet">{{ number_format($stats['biggest'], 2) }} ₺</div>
            </div>
        </div>
    </div>

    @if($rows->isEmpty() && $draws->isEmpty())
        <div class="text-center py-16 bg-[#121622] rounded-3xl border border-white/[0.06]">
            <span class="material-symbols-outlined text-4xl text-on-surface-subtle mb-2">emoji_events</span>
            <p class="text-on-surface-muted text-sm font-medium">Henüz kaydedilmiş bir kazanç yok.</p>
            <p class="text-xs text-on-surface-subtle mt-1">İlk ödemeler gerçekleştiğinde kazananlar burada görünecek.</p>
        </div>
    @else
        <div class="bg-[#121622] rounded-2xl border border-white/[0.08] overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm">
                    <thead class="text-on-surface-subtle uppercase text-[10px] font-mono-jet">
                        <tr class="border-b border-white/[0.06]">
                            <th class="text-left p-4">Oyuncu</th>
                            <th class="text-left p-4">Oyun</th>
                            <th class="text-right p-4">Bahis</th>
                            <th class="text-right p-4">Kazanç</th>
                            <th class="text-right p-4">Zaman</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $r)
                            <tr class="border-b border-white/[0.04] hover:bg-white/[0.02] transition-colors">
                                <td class="p-4 font-bold text-white">{{ $r['player'] }}</td>
                                <td class="p-4 text-on-surface-muted">{{ $r['game'] }}</td>
                                <td class="p-4 text-right font-mono-jet text-on-surface-muted">{{ number_format($r['bet'], 2) }} ₺</td>
                                <td class="p-4 text-right font-mono-jet font-bold text-primary">{{ number_format($r['win'], 2) }} ₺</td>
                                <td class="p-4 text-right text-xs text-on-surface-subtle">{{ \Illuminate\Support\Carbon::parse($r['at'])->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-8 text-center text-on-surface-muted">Henüz oyun kazancı kaydedilmedi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($draws->isNotEmpty())
            <div class="bg-[#121622] rounded-2xl border border-white/[0.08] p-5">
                <h2 class="text-sm font-bold uppercase text-white mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-lg">auto_awesome</span> Jackpot Kazananları
                </h2>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
                    @foreach($draws as $d)
                        <div class="bg-white/[0.03] border border-white/[0.06] rounded-xl p-3 text-center">
                            <div class="text-[10px] font-mono-jet text-on-surface-subtle uppercase">Tur {{ $d['round'] }}</div>
                            <div class="text-xl font-black text-primary font-mono-jet">{{ $d['winners'] }}</div>
                            <div class="text-[10px] text-on-surface-subtle">kazanan</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
