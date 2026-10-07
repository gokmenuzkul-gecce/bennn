# Agregatör ticari modelleri — "krediden düş" vs "kârdan pay"

Tarih: 2026-10. Amaç: **ön ödeme / prepaid kredi istemeyen**, çalıştıkça **GGR üzerinden
revenue-share** alan bir oyun agregatörü bulmak.

## İki model

| Model | Nasıl çalışır | Örnek |
|---|---|---|
| **Prepaid float** (mevcut SoftAggregator) | Hesaba USDT/USDC **kredi yükle**; komisyon gerçek-para turu kapandıkça bu bakiyeden düşülür. `createPlayer`/`getGame` kredi yokken **çalışmaz** (`Insufficient operator credit`). Harcanmayan kredi senin kalır ama **önce para koyman gerekir**. | SoftAggregator, çoğu self-service agregatör |
| **Postpaid fatura** (istenen) | Oyunlar **peşin para olmadan** açılır; ay sonunda GGR üzerinden **fatura** kesilir (havale/kripto). | Hub88, Slotegrator, SOFTSWISS |

SoftAggregator'ın kendi sayfası bunu açıkça yazıyor
(<https://softaggregator.com/no-setup-fee-casino-api>): *"You fund a prepaid balance and pay a
revenue share on the GGR the games actually produce."* Yani setup/monthly yok ama **prepaid float
var** — istenen model bu değil.

## Adaylar (postpaid / rev-share)

| Sağlayıcı | Ödeme modeli | Setup | Aylık min. | Rev-share | Onboarding | Canlı + TRY | Lisans şartı |
|---|---|---|---|---|---|---|---|
| **Hub88** ⭐ | **Postpaid fatura** (aylık konsolide, backoffice'ten ödeme) | Yayınlanmamış | Yayınlanmamış | Pazarlık (hacme göre) | **Self-serve** "1-Click Onboarding" (~3 gün) | 26.000+ oyun, 150+ tedarikçi, canlı ✅ | **"İş tipine göre lisans atlanabilir"** — satışa bildir |
| Slotegrator | Rev-share (fatura) | Muhtemelen var (quote-only) | **Yok** (SSS: *"We do NOT charge minimum monthly payments... only required to pay the revenue share"*) | Pazarlık (%15-25) | Sales-led, 1-4 hafta | 30.000+ oyun, canlı ✅ | Lisans gerekli |
| SOFTSWISS | Rev-share (fatura) | Belirsiz | Belirsiz | Pazarlık | Sales-led, 2-4 hafta | 40.000+ oyun, canlı ✅ | Lisans gerekli; **sweepstakes/sosyal destekli** |
| Groove | Rev-share | Belirsiz | Belirsiz | Pazarlık | Sales-led, ~günler | 4.000+ GC/SC oyunu, canlı ✅ | **"No gambling license required"** (sweepstakes/sosyal) |

Kaynaklar: Hub88 `docs.hub88.io` (Invoice Payments, 1-Click Onboarding), `slotegrator.pro/faq.html`,
`softswiss.com` sweepstakes duyurusu, `groovetech.com/sweepstakes-game-aggregation`.

## Kritik gerçekler

- **Postpaid = sales-led + due diligence.** Postpaid fatura veren her sağlayıcı önce şirket/legal
  entity ve (çoğunlukla) **oyun lisansı** ister. Hub88 istisna: *"Depending on your business type,
  adding gaming licences can be omitted — inform our Sales team in advance."*
- **Self-service + postpaid birlikte yok gibi.** Self-service olanlar (SoftAggregator) prepaid;
  postpaid olanlar (Hub88/Slotegrator/SOFTSWISS) satış görüşmesi ister.
- **Hub88 teknik protokolü farklı** (Waija/SoftAggregator değil): RSA public/private key imzası,
  `POST /operator/generic/v2/game/url`, cüzdan `POST /user/balance`, `/transaction/bet`,
  `/transaction/win`, `/transaction/rollback`. Yeni bir client gerekir; mevcut generic wallet
  rotası (`webhooks/aggregator/{slug}/wallet/{operation?}`) bet/win/rollback desenini zaten destekliyor.
- **Sosyal/sweepstakes senaryosu:** Site "Sosyal Oyun Lobisi" markalı ama gerçek bakiye + TRY
  kullanıyor. Gerçek-para lisansı yoksa **Groove** (sweepstakes, lisans yok) veya **Hub88**
  (lisans opsiyonel) en uygun; gerçek-para lisansı varsa Hub88/Slotegrator/SOFTSWISS.

## Öneri

1. **Hub88** (birincil): postpaid fatura, self-serve onboarding, canlı dahil 26k+ oyun, MGA lisanslı.
   `sales@hub88.io` — *"Postpaid invoicing on revenue share with no prepaid balance, licences
   omittable for our business type — confirm."*
2. **Slotegrator** (yedek): aylık minimum yok, sadece rev-share.
3. **Groove** (sosyal/sweepstakes ise): lisans gerekmiyor.

Not: SoftAggregator'ı tamamen bırakmak şart değil — prepaid float "ücret" değil, harcanmayan
kısım sende kalır; komisyon tur başına düşülür. Yine de peşin para konulmasını istemiyorsan
Hub88 doğru adres.
