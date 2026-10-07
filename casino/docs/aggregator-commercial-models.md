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

**SoftAggregator teyitli fiyatlandırma** (hesap açılış mesajından): **kurulum ücreti yok, aylık
ücret yok**; komisyon GGR üzerinden **slot %8, spor %9, canlı casino %11**; **sistem ön ödemeli**
(oyuncular oynadıkça komisyon krediden düşer, kredi 0 olunca oyun açılmaz); **minimum yükleme
100 USD** (USDT/USDC), her yüklemede geçerli, iade edilmez. Test için **2 USDT** kredi verildi —
`createPlayer`/`getGame`/bet/win/rollback bu kredi ile test edilebilir. Yani "ücret" yok ama
**peşin float** var: 100 USD'lik ilk yükleme olmadan gerçek oyun açılmaz.

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

## Bize uyar mı? (Hub88 değerlendirmesi)

**Model olarak tam uyar** — istediğin "peşin para yok, kârdan pay" mantığı Hub88'de var:

| Kriter | Hub88 | Uyum |
|---|---|---|
| Ödeme modeli | **Postpaid fatura** (aylık konsolide, backoffice'ten ödeme) | ✅ |
| Peşin kredi | **Yok** | ✅ |
| Onboarding | **Self-serve** "1-Click Onboarding" (~3 gün) | ✅ |
| Kapsam | 26.000+ oyun, 150+ tedarikçi, **canlı dealer dahil** | ✅ |
| Sosyal casino | **Evet** — ABD sosyal casino **Legendz** ile sweepstake debut (Ara 2024) | ✅ |
| Cüzdan | Seamless wallet (bizim mimari) | ✅ |
| Lisans | MGA B2B; **"iş tipine göre lisans atlanabilir"** — satışa bildir | ⚠️ |
| TRY | Dokümanlarda açık değil; entegrasyonda seçiliyor | ⚠️ doğrula |
| Fiyat | Rev-share oranı + min. hacim **yayınlanmamış** (pazarlık) | ⚠️ |

**3 engel / yapılacak:**

1. **Sales-led + due diligence**: SoftAggregator gibi anında kayıt yok; parent company, legal
   entity, registration no, marka bilgisi istenir. Şirket bilgileri hazır olmalı.
2. **Farklı teknik protokol**: RSA public/private key imzası, `POST /operator/generic/v2/game/url`,
   cüzdan `POST /user/balance`, `/transaction/bet`, `/transaction/win`, `/transaction/rollback`.
   Waija/SoftAggregator protokolü **değil** → yeni `Hub88Client` gerekir. Mevcut generic cüzdan
   rotası (`webhooks/aggregator/{slug}/wallet/{operation?}`) bet/win/rollback desenini zaten
   destekliyor, iş ~1-2 gün.
3. **TRY + min. hacim teyidi** gerekli.

**Sorulacak 3 soru** (`sales@hub88.io` veya HubConnect formu):
1. *"Postpaid invoicing on revenue share, with no prepaid balance required to enable game
   launch — please confirm in writing."*
2. *"Our business type is social/sweepstakes; can gaming licences be omitted, and is TRY
   supported for gameplay?"*
3. *"Is there a minimum volume commitment or monthly minimum?"*

**Sonuç:** Hub88 birincil aday. Onay gelene kadar SoftAggregator (slot katalogu, canlı için
`@mentionso`) çalışmaya devam eder; iki agregatör farklı `provider_key` ile yan yana sorunsuz
çalışır.

## Groove Technologies (eski GrooveGaming) — değerlendirme

**En güçlü tarafı: lisanssız sosyal/sweepstakes modelini açıkça destekliyor.**

| Kriter | Groove | Not |
|---|---|---|
| Şirket / düzenleyici | Groove Technologies N.V., **Curaçao** (OGL/2024/309/0142) | MGA değil → offshore/esnek |
| Oyun | 20.000+ oyun, 150+ stüdyo (bazı sayfalar 15.000+/200+) | Hub88 ile benzer |
| Canlı dealer | **Evet** (slot + canlı + masa + crash) | ✅ |
| **Sosyal/sweepstakes** | **Ayrı ürün: 4.000+ GC/SC hazır oyun, "no gambling license required"** | ✅✅ bize tam uyar |
| Para birimi | 200+ (fiat + kripto) | TRY doğrulanmalı |
| Cüzdan | Seamless (tek Unified Casino API + Marketing API) | ✅ |
| Protokol | `X-API-Key` + `X-Signature` (HMAC-SHA256), `/balance`, bet/win/rollback | Hub88'e benzer, **yeni client gerekir** |
| Onboarding | Sales-led, **<4 hafta** (sweepstakes ürünü günler içinde) | Anında kayıt yok |
| Fiyat | **On request** (rev-share + setup + min. hepsi pazarlık) | ⚠️ belirsiz |
| **Ön ödeme / postpaid** | **Yayınlanmamış** | ⚠️ **sorulacak** |

**Avantaj:** Groove'un sweepstakes kataloğu **lisans gerektirmiyor** ve Curacao düzenlemesinde.
"Sosyal Oyun Lobisi" markalı, gerçek-para lisansı olmayan bir site için Hub88'den **daha uygun**
olabilir.

**Dezavantaj / bilinmeyen:**
- **Prepaid mi postpaid mi belli değil** — Hub88 postpaid'i yazılı teyit ediyor, Groove etmiyor.
  Ödeme modelini mutlaka sor.
- Fiyat tamamen pazarlık (setup + rev-share + min. yayınlanmamış).
- Sweepstakes kataloğu ağırlıklı **ABD (Gold Coin/Sweeps Coin)** odaklı; TRY/TR pazarı için
  hangi katalog uygun, teyit lazım.

## Hub88 vs Groove — hangisi?

| | Groove | Hub88 |
|---|---|---|
| Operatör lisansı | **Sweepstakes ürününde gerekmez** | Opsiyonel (satış onayı) |
| Agregatör düzenleyicisi | Curaçao | Malta (MGA) |
| Oyun | 20.000+ / 150+ stüdyo | 26.000+ / 150+ tedarikçi |
| Sosyal/sweepstakes | **Ayrı ürün, 4K+ GC/SC** | Kanıtlı (Legendz) |
| Ödeme modeli | On request (belirsiz) | **Postpaid fatura (teyitli)** |
| Onboarding | Sales-led | Self-serve 1-click |

**Karar:** Site gerçekten **sosyal/sweepstakes** (gerçek para yok) ise → **Groove**. Gerçek-para
işi ise → **Hub88**. İkisinde de sales-led + due diligence var, ikisinde de yeni client gerekir.

### Groove'a sorulacak sorular (`sales@groovetech.com`)

1. *"Do you invoice postpaid on revenue share, or is a prepaid credit balance required to
   enable game launch?"*
2. *"Our site is a social/sweepstakes model in Turkish (TRY) — which catalogue applies, is TRY
   supported, and do we need a gaming licence?"*
3. *"Setup fee, monthly minimum and revenue-share percentage for our volume?"*

## Öneri

1. **Hub88** (birincil): postpaid fatura, self-serve onboarding, canlı dahil 26k+ oyun, MGA lisanslı,
   sosyal casino deneyimi. `sales@hub88.io`.
2. **Slotegrator** (yedek): aylık minimum yok, sadece rev-share.
3. **Groove** (sosyal/sweepstakes ise): lisans gerekmiyor.

Not: SoftAggregator'ı tamamen bırakmak şart değil — prepaid float "ücret" değil, harcanmayan
kısım sende kalır; komisyon tur başına düşülür. Yine de peşin para konulmasını istemiyorsan
Hub88 doğru adres.
