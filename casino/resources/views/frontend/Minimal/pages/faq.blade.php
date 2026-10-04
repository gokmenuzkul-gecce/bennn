@extends('frontend.Minimal.layouts.clean')

@section('page-title', 'Sıkça Sorulan Sorular - ' . (settings('app_name') ?: 'Casino Gecce'))

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div>
        <div class="inline-flex items-center gap-2 bg-secondary/10 border border-secondary/25 px-3 py-1 rounded-full mb-2">
            <span class="material-symbols-outlined text-secondary text-base">quiz</span>
            <span class="text-[11px] font-bold text-secondary font-mono-jet uppercase tracking-wider">DESTEK</span>
        </div>
        <h1 class="text-2xl sm:text-3xl md:text-4xl font-extrabold text-white tracking-tight uppercase">Sıkça Sorulan Sorular</h1>
        <p class="text-on-surface-muted text-xs sm:text-sm mt-1">Operatör tarafından yayınlanan güncel sorular ve yanıtlar.</p>
    </div>

    @if($faqs->isEmpty())
        <div class="text-center py-16 bg-[#121622] rounded-3xl border border-white/[0.06]">
            <span class="material-symbols-outlined text-4xl text-on-surface-subtle mb-2">quiz</span>
            <p class="text-on-surface-muted text-sm font-medium">Henüz yayınlanmış bir soru yok.</p>
            <p class="text-xs text-on-surface-subtle mt-1">Yardım için <a href="{{ route('frontend.help') }}" class="text-primary no-underline hover:underline">Yardım Merkezi</a>'ni ziyaret edin.</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($faqs as $faq)
                <details class="group bg-[#121622] rounded-2xl border border-white/[0.08] overflow-hidden">
                    <summary class="flex items-center justify-between gap-4 p-5 cursor-pointer list-none">
                        <span class="text-sm font-bold text-white">{{ $faq->question }}</span>
                        <span class="material-symbols-outlined text-on-surface-subtle group-open:rotate-180 transition-transform">expand_more</span>
                    </summary>
                    <div class="px-5 pb-5 text-sm text-on-surface-muted leading-relaxed border-t border-white/[0.06] pt-4">{{ $faq->answer }}</div>
                </details>
            @endforeach
        </div>
    @endif
</div>
@endsection
