# Casino Sağlayıcı Onboarding — Ne Alıyoruz / Ne Veriyoruz

Bu dosya, kumarhane sağlayıcılarına (agregatörler ve markalar) gönderilecek
bilgileri ve onlardan almamız gerekenleri tek yerde toplar.

> ÖNEMLİ: Aşağıdaki `<SITE_ALAN_ADI>` kısmını **kalıcı, public HTTPS alan adınla**
> değiştir. Şu an `CASINO_CALLBACK_BASE` geçici bir sandbox host
> (`work-1-....prod-runtime.all-hands.dev`) gösteriyor; satıcı portallarına
> kaydettirmeden önce gerçek alan adını kullan.

Hazırlık durumunu ve güncel callback URL'lerini görmek için:

```bash
php artisan casino:integration-status --test
```

---

## 1) Bizden sağlayıcıya VERİLECEKLER (hepsine ortak)

| Bilgi | Değer |
|---|---|
| Callback / Wallet URL (agregatör markaları) | `https://<SITE_ALAN_ADI>/webhooks/aggregator/gregmorn/wallet` |
| Para birimi | `TRY` |
| Oyuncu kimliği formatı | `<prefix><site_user_id>` (kayıt anında üretilir, satıcı aynen geri gönderir) |
| Operasyonlar | `GetBalance`, `Withdraw`, `Deposit`, `BetWin`, `RollbackTransaction` |
| Yanıt gövdesi | JSON `{ code, message, balance }` (code 0 = başarı) |

---

## 2) Sağlayıcıdan BİZE gerekenler

### A. Mevcut agregatör (Pragmatic Play, PG Soft, Amatic, Amusnet)
Bu markalar çalışıyor (slot). Canlı masa için ek içerik gerekiyor.

- [ ] Endpoint host (`pk2api.loginxgamesapi.com` vb. — mevcut)
- [ ] `agentID` (mevcut: `stagingGeccebet53TRY`)
- [ ] API token (mevcut)
- [ ] Secret key (mevcut)
- [ ] **Callback URL'imizi kayıt** → yukarıdaki agregator URL
- [ ] **Canlı casino (live dealer) içeriği aktivasyonu** — şu an hesapta canlı masa yok

### B. OroPlay (canlı casino + slot + mini oyun) — BASE URL BEKLİYOR
Yapılandırılan host `api.oroplay.com` **DNS'te yok** (çözülmüyor). Sağlayıcının
gerçek sitesi `https://oroplay.io` (B2B iGaming API altyapısı); public API
subdomain'i yok, base URL operatör onboarding'inde veriliyor.

- [ ] **Doğru API base URL** (staging + prod) — sağlayıcıdan isteyin
- [ ] `clientId` (mevcut: `stg-TRY-Geccebet53`)
- [ ] `clientSecret` (mevcut)
- [ ] Agent kaydı + canlı casino vendor/game listesi
- [ ] Callback URL'imizi kayıt → `https://<SITE_ALAN_ADI>/webhooks/oroplay/api`
- [ ] Basic auth başlığı (clientId:clientSecret) teyidi

### C. Gregmorn Hub (çok sağlayıcılı slot + canlı masa) — KİMLİK BEKLİYOR
Doküman: <https://docs.gregmorn.org>. Entegrasyon kod tarafında hazır; operatör
hesabı olmadığı için `/auth/login` 401 dönüyor.

- [ ] **Operatör hesabı**: `login` + `password`
- [ ] **Secret key** (openGame imzası ve cüzdan callback imzası için)
- [ ] **User ID** (yoksa login yanıtındaki `user.id` kullanılır)
- [ ] **IP allowlist**'e bizim sunucu IP'mizi ekleme
- [ ] **Stage ve prod** için ayrı kimlikler (ayrı login/secret/IP listesi)
- [ ] Stage base: `office-api-dev.gregmorn.org` + `client-api-dev.gregmorn.org` teyidi
- [ ] Prod base: `office-api.gamble-hub.net` + `client-api.gamble-hub.net` teyidi
- [ ] Callback URL'imizi kayıt → `https://<SITE_ALAN_ADI>/webhooks/gregmorn/callbacks`
- [ ] Para birimi: `TRY` kataloğu aktif mi

### D. smpl core — 35.000+ oyun (slot + CANLI CASINO) — ÖNERİLEN CANLI MASA KAYNAĞI
Docs: <https://smplcore.com/docs/getting-started>. Entegrasyon tamamen hazır
(client + cüzdan + webhook + provider adapter); **sadece kimlik eksik**.

- [ ] **`merchant_id`** (ŞU AN BOŞ — `SMPL_MERCHANT_ID`)
- [ ] **`merchant_key`** (HMAC-SHA1 imza anahtarı)
- [ ] Base API URL teyidi: stage `https://staging.smplcore.net/api/index.php/v1`, prod `<prod-url>`
- [ ] **Canlı casino (live dealer) kategorisinin hesapta aktif olduğu teyidi**
- [ ] Callback URL'imizi kayıt → `https://<SITE_ALAN_ADI>/webhooks/smplcore/callbacks`
- [ ] Webhook'lar: balance / bet / win / refund / rollback (tek URL, `action` gövdede)
- [ ] İmza: `X-Sign = HMAC-SHA1(http_build_query(payload), merchant_key)` + `X-Merchant-Id`, `X-Timestamp`, `X-Nonce`
- [ ] Para birimi: `TRY`

> smpl core ayrıca 35.000+ oyun ve canlı casino sağlıyor; doğru kaynak bu.

---

## 3) Cüzdan (seamless wallet) callback sözleşmesi

Satıcı, oyuncunun bahis/kazanç hareketlerini **bizim** cüzdanımıza aşağıdaki
uçlarla bildirir. Bakiye sitede kalır; oyun tarafında para tutulmaz.

| Uç | Ne zaman | Bizim yanıtımız |
|---|---|---|
| `GetBalance` | Oyun açılışında / sorguda | `{code:0, balance:<site bakiyesi>}` |
| `Withdraw` | Bahis (stake düşülür) | `{code:0, balance:<yeni bakiye>}` |
| `Deposit` | Kazanç / iade (eklenir) | `{code:0, balance:<yeni bakiye>}` |
| `BetWin` | Tur sonucu (bet + win net) | `{code:0, balance:<yeni bakiye>}` |
| `RollbackTransaction` | İptal/geri alma | `{code:0, balance:<yeni bakiye>}` |

İdempotency: aynı `transactionID` ikinci kez gelirse bakiye tekrar değişmez
(`code:11`, orijinal bakiye döner). Yetersiz bakiyede `code:6`.

Test: `php tests/Casino/wallet-e2e-check.php` (imzalı callback'lerle gerçek bakiye
hareketi; mevcut agregatör markalarında 45/55 geçiyor).

---

## 4) Şu anki durum özeti

| Sağlayıcı | Durum | Not |
|---|---|---|
| Pragmatic / PGSoft / Amatic / Amusnet | Çalışıyor (slot) | Launch OK, cüzdan bağlı |
| OroPlay | Base URL bekliyor | `api.oroplay.com` çözülmüyor; gerçek site `oroplay.io` |
| Gregmorn Hub | Kimlik bekliyor | Kod hazır, operatör login/secret/IP allowlist gerekli |
| smpl core | Kimlik bekliyor | Kod+adapter hazır; `merchant_id` boş (35.000+ oyun + canlı casino) |

Canlı masaların görünmesi için **smpl core merchant_id** (en hızlı yol),
**OroPlay doğru base URL** veya **Gregmorn operatör kimlikleri** gerekli;
üçü de canlı masa (live dealer) içeriği sağlıyor.
