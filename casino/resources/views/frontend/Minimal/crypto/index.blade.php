@extends('frontend.Minimal.layouts.clean')

@section('page-title', 'Kripto Ticaret Simülatörü')

@section('content')
<main class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-9">
    <div class="glass-panel rounded-2xl p-5 sm:p-7 mb-6 border border-cyan-400/15">
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5">
            <div>
                <div class="text-cyan-300 text-xs font-bold tracking-[.18em] uppercase mb-2">Piyasa fiyatlı • Yalnızca TRY</div>
                <h1 class="text-2xl sm:text-4xl font-extrabold tracking-tight">Kripto Ticaret Simülatörü</h1>
                <p class="text-sm text-on-surface-muted mt-2 max-w-2xl">Sanal bir alış veya satış pozisyonu açın. Her giriş ve çıkış, sunucuda önbelleğe alınmış lisanslı bir piyasa anlık görüntüsüyle belirlenir; hiçbir istemci fiyatı sonucu etkileyemez.</p>
            </div>
            <div class="text-left lg:text-right"><div class="text-xs uppercase tracking-wider text-on-surface-subtle">Next {{ $interval === 'manual' ? 'manual hourly' : $interval }} batch</div><div class="font-mono-jet text-cyan-300 font-bold mt-1" data-batch="{{ $nextBatch->toIso8601String() }}">{{ $nextBatch->format('D, H:i') }} UTC</div></div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-6">
        <section>
            <div class="flex gap-2 overflow-x-auto pb-1 mb-4" id="interval-tabs">
                @foreach(['hourly' => 'Hourly', 'daily' => 'Daily', 'weekly' => 'Weekly', 'manual' => 'Manuel kuyruk'] as $key => $label)
                    <a href="{{ route('frontend.crypto.index', ['interval' => $key]) }}" class="shrink-0 rounded-xl px-4 py-2 text-xs font-bold uppercase tracking-wider no-underline {{ $interval === $key ? 'bg-cyan-500 text-slate-950' : 'bg-surface-card border border-white/[.08] text-on-surface-muted hover:text-white' }}">{{ $label }}</a>
                @endforeach
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-5 text-sm">
                <div class="rounded-2xl border border-emerald-400/20 bg-emerald-400/[.06] p-4"><div class="font-bold text-emerald-300">1. Bir yön seçin</div><p class="text-xs text-on-surface-muted mt-1"><strong class="text-white">Alış</strong> “Fiyatın yükseleceğini düşünüyorum.” anlamına gelir. <strong class="text-white">Satış</strong> “Fiyatın düşeceğini düşünüyorum.” anlamına gelir.</p></div>
                <div class="rounded-2xl border border-cyan-400/20 bg-cyan-400/[.06] p-4"><div class="font-bold text-cyan-200">2. Gruba katılın</div><p class="text-xs text-on-surface-muted mt-1">Emriniz gösterilen UTC saatini bekler. O gruptaki herkes aynı resmi başlangıç fiyatını alır.</p></div>
                <div class="rounded-2xl border border-amber-400/20 bg-amber-400/[.06] p-4"><div class="font-bold text-amber-300">3. Sonucunuzu görün</div><p class="text-xs text-on-surface-muted mt-1">Kapanış saatinde TRY ödemeniz fiyat hareketini takip eder. Asla kripto satın almaz, sahip olmaz veya çekmezsiniz.</p></div>
            </div>

            @if($assets->isEmpty())
                <div class="glass-card rounded-2xl p-8 text-center text-on-surface-muted">Kripto kataloğu henüz yüklenmedi. Bir operatör bunu Liteback → Kripto Ticareti'nden yenileyebilir.</div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    @foreach($assets as $asset)
                        <article class="glass-card rounded-2xl p-4 crypto-card" data-asset-id="{{ $asset->id }}" data-symbol="{{ $asset->symbol }}">
                            <div class="flex items-start justify-between gap-3"><div><div class="font-bold">{{ $asset->name }}</div><div class="text-xs text-on-surface-subtle font-mono-jet">{{ $asset->symbol }}/USD · #{{ $asset->market_rank ?? '—' }}</div></div><span class="text-[10px] px-2 py-1 rounded-full bg-cyan-400/10 text-cyan-300 font-bold">AÇIK</span></div>
                            <div class="font-mono-jet text-xl font-bold mt-5" data-price>${{ number_format($asset->price_usd, $asset->price_usd < 1 ? 6 : 2) }}</div>
                            <div class="mt-1 text-xs {{ ($asset->change_24h ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400' }}" data-change>{{ ($asset->change_24h ?? 0) >= 0 ? '+' : '' }}{{ number_format($asset->change_24h ?? 0, 2) }}% 24h</div>
                            <button type="button" class="choose-asset mt-5 w-full rounded-xl border border-cyan-400/25 py-2.5 text-xs font-bold uppercase tracking-wider text-cyan-200 hover:bg-cyan-400/10" data-id="{{ $asset->id }}" data-name="{{ $asset->name }}" data-symbol="{{ $asset->symbol }}">Trade {{ $asset->symbol }}</button>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <aside class="glass-card rounded-2xl p-5 h-fit lg:sticky lg:top-5">
            <div class="flex items-center justify-between"><h2 class="font-bold">Yeni pozisyon</h2><span id="selected-symbol" class="font-mono-jet text-cyan-300 text-sm">Varlık seçin</span></div>
            <p class="text-xs text-on-surface-muted mt-2">{{ $interval === 'manual' ? 'Manual orders queue into the next hourly batch; the official entry is captured at that boundary.' : 'This position queues for the next UTC batch so every player receives the same official entry snapshot.' }} This is a TRY simulator, not a real crypto purchase.</p>
            <form id="position-form" class="mt-5 space-y-4">
                @csrf
                <input type="hidden" name="asset_id" id="asset-id">
                <input type="hidden" name="interval" value="{{ $interval }}">
                <div><label class="text-xs font-bold uppercase tracking-wider text-on-surface-muted">Sizce ne olacak?</label><div class="grid grid-cols-2 gap-2 mt-2"><button type="button" class="direction rounded-xl py-3 text-sm font-bold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30" data-value="long"><span class="block">Fiyat yükselecek</span><span class="block text-[10px] opacity-75 mt-0.5">Alış</span></button><button type="button" class="direction rounded-xl py-3 text-sm font-bold bg-white/[.03] text-on-surface-muted border border-white/[.08]" data-value="short"><span class="block">Fiyat düşecek</span><span class="block text-[10px] opacity-75 mt-0.5">Satış</span></button></div><input type="hidden" name="direction" value="long"></div>
                <div><label class="text-xs font-bold uppercase tracking-wider text-on-surface-muted" for="stake">Maliyet (TRY)</label><input id="stake" name="stake" type="number" min="1" max="1000000" value="100" class="mt-2 w-full rounded-xl bg-black/20 border-white/[.10] text-white" required></div>
                <div><label class="text-xs font-bold uppercase tracking-wider text-on-surface-muted">Kaldıraç</label><div class="grid grid-cols-3 gap-2 mt-2">@foreach([1,2,3] as $x)<button type="button" class="leverage rounded-xl py-2 text-xs font-bold {{ $x === 1 ? 'bg-cyan-500 text-slate-950' : 'bg-white/[.03] text-on-surface-muted border border-white/[.08]' }}" data-value="{{ $x }}">{{ $x }}×</button>@endforeach</div><input type="hidden" name="leverage" value="1"><p class="text-[11px] text-on-surface-subtle mt-1.5">Daha yüksek kaldıraç hem kazançları hem de kayıpları büyütür. Yeniyseniz 1× ile başlayın.</p></div>
                <div class="rounded-xl bg-white/[.035] p-3 text-xs text-on-surface-muted"><div class="flex justify-between"><span>Maksimum kayıp</span><span id="max-loss">100 TRY</span></div><div class="flex justify-between mt-1"><span>Maksimum kâr</span><span id="max-profit">100 TRY</span></div><div class="border-t border-white/[.07] mt-3 pt-3 leading-relaxed" id="position-preview">Fiyat seçtiğiniz yönde 1× ile %1 hareket ederse, bu 100 TRY'lik pozisyon yaklaşık 1 TRY değişir.</div></div>
                <button id="place-position" type="submit" class="w-full rounded-xl bg-cyan-400 hover:bg-cyan-300 py-3 font-extrabold text-slate-950 uppercase tracking-wider text-xs disabled:opacity-50" disabled>Bir varlık seçin</button>
                <p id="position-message" class="text-xs text-center min-h-[1.25rem]"></p>
            </form>
        </aside>
    </div>

    <section class="mt-8"><h2 class="font-bold text-lg mb-4">Son pozisyonlarınız</h2><div class="glass-card rounded-2xl overflow-x-auto"><table class="w-full text-sm min-w-[690px]"><thead class="text-left text-[11px] uppercase tracking-wider text-on-surface-subtle border-b border-white/[.07]"><tr><th class="p-4">Varlık / yön</th><th class="p-4">Grup</th><th class="p-4">Maliyet</th><th class="p-4">Giriş → çıkış</th><th class="p-4">Sonuç</th><th class="p-4">Durum</th></tr></thead><tbody>@forelse($positions as $position)<tr class="border-b border-white/[.05]"><td class="p-4 font-bold">{{ $position->asset->symbol }} <span class="{{ $position->direction === 'long' ? 'text-emerald-400' : 'text-rose-400' }} text-xs uppercase">{{ $position->direction }} {{ $position->leverage }}×</span></td><td class="p-4 text-xs font-mono-jet">{{ $position->round->starts_at->format('M d H:i') }} UTC</td><td class="p-4">{{ number_format($position->stake, 2) }}</td><td class="p-4 font-mono-jet text-xs">{{ $position->entry_price_usd ? '$'.number_format($position->entry_price_usd, 4) : 'Queued' }} → {{ $position->exit_price_usd ? '$'.number_format($position->exit_price_usd, 4) : '—' }}</td><td class="p-4 {{ ($position->return_percent ?? 0) >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ $position->return_percent === null ? '—' : number_format($position->return_percent, 2).'%' }}</td><td class="p-4 text-xs uppercase font-bold">{{ str_replace('_', ' ', $position->status) }}</td></tr>@empty<tr><td colspan="6" class="p-7 text-center text-on-surface-muted">Henüz kripto pozisyonu yok.</td></tr>@endforelse</tbody></table></div></section>
</main>

<script>
(() => {
    const form = document.getElementById('position-form'), message = document.getElementById('position-message'), submit = document.getElementById('place-position');
    const setEstimate = () => { const v = Math.max(0, Number(document.getElementById('stake').value || 0)), leverage = Number(form.leverage.value || 1), direction = form.direction.value === 'short' ? 'falls' : 'rises', onePercent = (v * leverage * 0.01).toLocaleString(undefined, {maximumFractionDigits: 2}); document.getElementById('max-loss').textContent = v.toLocaleString() + ' TRY'; document.getElementById('max-profit').textContent = v.toLocaleString() + ' TRY'; document.getElementById('position-preview').textContent = `Fiyat %1 ${direction === 'falls' ? 'düşerse' : 'yükselirse'} ${leverage}× kaldıraçla bu pozisyon yaklaşık ${onePercent} TRY değişir. Ters yönde hareket kaybı aynı oranda artırır.`; };
    document.getElementById('stake').addEventListener('input', setEstimate);
    document.querySelectorAll('.choose-asset').forEach(button => button.addEventListener('click', () => { document.getElementById('asset-id').value = button.dataset.id; document.getElementById('selected-symbol').textContent = button.dataset.symbol + '/USD'; submit.disabled = false; document.querySelectorAll('.crypto-card').forEach(card => card.classList.remove('ring-1', 'ring-cyan-400')); button.closest('.crypto-card').classList.add('ring-1', 'ring-cyan-400'); }));
    document.querySelectorAll('.direction').forEach(button => button.addEventListener('click', () => { form.direction.value = button.dataset.value; document.querySelectorAll('.direction').forEach(x => x.className = 'direction rounded-xl py-3 text-sm font-bold bg-white/[.03] text-on-surface-muted border border-white/[.08]'); button.className = 'direction rounded-xl py-3 text-sm font-bold ' + (button.dataset.value === 'long' ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/15 text-rose-300 border border-rose-500/30'); setEstimate(); }));
    document.querySelectorAll('.leverage').forEach(button => button.addEventListener('click', () => { form.leverage.value = button.dataset.value; document.querySelectorAll('.leverage').forEach(x => x.className = 'leverage rounded-xl py-2 text-xs font-bold bg-white/[.03] text-on-surface-muted border border-white/[.08]'); button.className = 'leverage rounded-xl py-2 text-xs font-bold bg-cyan-500 text-slate-950'; setEstimate(); }));
    form.addEventListener('submit', async event => { event.preventDefault(); submit.disabled = true; message.className = 'text-xs text-center text-on-surface-muted'; message.textContent = 'Queueing position…'; try { const r = await fetch('{{ route('frontend.crypto.place') }}', {method:'POST', headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content, 'Accept':'application/json'}, body:new FormData(form)}), data = await r.json(); message.textContent = data.message || 'Unable to queue position.'; message.className = 'text-xs text-center ' + (data.success ? 'text-emerald-400' : 'text-rose-400'); if (data.success) setTimeout(() => location.reload(), 850); else submit.disabled = false; } catch (_) { message.textContent = 'Network error. Your position was not confirmed.'; message.className = 'text-xs text-center text-rose-400'; submit.disabled = false; }});
    // CDN-safe local cache only; random delay avoids a synchronized edge-cache revalidation burst.
    setTimeout(() => fetch('{{ route('frontend.crypto.cache') }}', {headers:{'Accept':'application/json'}}).then(r => r.ok ? r.json() : null).then(data => data && data.assets.forEach(asset => { const card = document.querySelector('[data-asset-id="' + asset.id + '"]'); if (!card) return; const price = card.querySelector('[data-price]'), change = card.querySelector('[data-change]'); if (price) price.textContent = '$' + Number(asset.price_usd).toLocaleString(undefined, {maximumFractionDigits: Number(asset.price_usd) < 1 ? 6 : 2}); if (change) { const value = Number(asset.change_24h || 0); change.textContent = (value >= 0 ? '+' : '') + value.toFixed(2) + '% 24h'; change.className = 'mt-1 text-xs ' + (value >= 0 ? 'text-emerald-400' : 'text-rose-400'); }})), 5000 + Math.floor(Math.random() * 15000));
})();
</script>
@endsection
