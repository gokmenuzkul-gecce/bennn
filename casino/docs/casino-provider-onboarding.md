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

> **Kalıcı alan adı ZORUNLU.** Sağlayıcılar callback'i bize kaydedilen URL'e atar; bu host
> DNS'te çözülmeli ve HTTPS olmalı. Sandbox host'u (`*.prod-runtime.all-hands.dev`) yalnızca
> geliştirme içindir — portallara kaydettirmeyin. Alan adı hazır olunca tek komut yeter:
> `deploy/set-callback-domain.sh https://<SITE_ALAN_ADI>` (APP_URL + CASINO_CALLBACK_BASE'i
> yazar, cache temizler ve aşağıdaki URL'leri basar). Nginx şablonu:
> `deploy/nginx-casino.conf.example`.

| Bilgi | Değer |
|---|---|
| Callback / Wallet URL (agregatör markaları) | `https://<SITE_ALAN_ADI>/webhooks/aggregator/gregmorn/wallet` |
| Callback base (Gregmorn Hub) | `https://<SITE_ALAN_ADI>/webhooks/gregmorn` → `/api/balance`, `/api/transaction`, `/api/batch-transaction` |
| Callback (Waija) | `https://<SITE_ALAN_ADI>/webhooks/waija/callbacks` (GET; `action=balance\|debit\|credit`) |
| Callback (OroPlay) | `https://<SITE_ALAN_ADI>/webhooks/oroplay/api` → `/balance`, `/transaction`, `/batch-transactions` |
| Callback (smpl core) | `https://<SITE_ALAN_ADI>/webhooks/smplcore/callbacks` |
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
- [ ] **Callback base URL'imizi kayıt** → `https://<SITE_ALAN_ADI>/webhooks/gregmorn`
      (Hub kendi yollarını ekler: `/api/balance`, `/api/transaction`, `/api/batch-transaction`)
- [ ] Para birimi: `TRY` kataloğu aktif mi

### D. Waija / Slotsgateway (slot + canlı, 150-250+ satıcı) — KİMLİK BEKLİYOR
Doküman: <https://documentation.waija.com>. Entegrasyon kod tarafında tamamen hazır
(`WaijaClient`, `WaijaWalletService`, `WaijaWebhookController`, `WaijaProvider`).
Backoffice'ten gelen kimlikler girilince çalışır.

- [x] **Base API URL**: `https://api-eu-1.waija.com/api/system/operator` (backoffice'ten alındı)
- [x] **api_login** / **api_password**: backoffice'ten alındı, `.env`'e yazıldı
- [x] **IP allowlist**: `34.45.0.142` eklendi (API artık `200` dönüyor)
- [ ] **Salt key** (callback imzası `md5(timestamp + saltkey)`; backoffice → Spinshield detayı)
- [ ] **Para birimi kararı** — TRY Waija'da DESTEKLENMİYOR (aşağıya bak)
- [x] **Callback URL kaydı** → `https://work-1-tlcfjicrlanlyelk.prod-runtime.all-hands.dev/webhooks/waija/callbacks`
- [x] **Kumarhane URL'i** kayıtlı
- [ ] **Access & Limits**: oyun kilitlerini aç (şu an tüm oyunlar "disabled")
- [ ] (Opsiyonel) `WAIJA_PLAYER_PASSWORD` / `WAIJA_PLAYER_NICKNAME`

> **Doğrulanan canlı sonuçlar (2026-10):**
> - `getGameList` → `USD`/`EUR` **3704 oyun**, `GBP` 3687, `BRL` 2709, ama **`TRY` 0 oyun**
>   (tüm `list_type` 0-5 için). TRY Waija'nın desteklediği para birimleri arasında yok:
>   USD, EUR, GBP, BRL, AUD, CAD, NZD, TND.
> - `createPlayer` TRY ve USD için `error:0` dönüyor (oyuncu açılıyor).
> - `getGame` şu an her oyunda `error:1 "Game is disabled or does not exist."` →
>   hesapta oyun kilidi var; backoffice **Access & Limits**'ten açılmalı.
> - Katalog içeriği: ~3139 video-slot + ~565 canlı masa (Pragmatic 651+504, Evolution 401,
>   Spinomenal 481, Bgaming 258, Booming 251, Hacksaw 173, ...).
>
> Not: Waija istekleri **form-encoded** (`application/x-www-form-urlencoded`) gönderir; doküman
> "JSON" yazsa da referans SDK form kullanır. Para birimi wire'da BÜYÜK harftir (`TRY`).

> Not: Waija cüzdanı **integer cent** kullanır (`$2.50 → 250`); rollback `rb=1` ile normal
> hareket olarak gelir; `type=bonus_fs` debit'inde nakit düşülmez. Tüm yanıtlar HTTP 200.

### E. smpl core — 35.000+ oyun (slot + CANLI CASINO) — ÖNERİLEN CANLI MASA KAYNAĞI
Docs: <https://smplcore.com/docs/getting-started>. Entegrasyon tamamen hazır
(client + cüzdan + webhook + provider adapter); **sadece kimlik eksik**.

> **ÖNEMLİ:** smpl core, **Merchant ID + Merchant Key** ister (doküman: "Contact your sales
> manager to receive Merchant ID and Merchant Key"). Elimizdeki `stg-TRY-Geccebet53` /
> Client Secret **bu API'nin kimliği DEĞİL** — o, agregatörün (loginxgames) OAuth client'ı.
> O yüzden staging `/games` çağrısı **403 "RBAC: access denied"** döner. Canlı masa açmak için
> smpl core entegrasyon müdüründen ayrı **Merchant ID + Merchant Key** alınmalı; gelince
> `.env`'e `SMPL_MERCHANT_ID` / `SMPL_MERCHANT_KEY` yazılıp `php artisan casino:sync-catalog`
> çalıştırılır (istek imzası: HMAC-SHA1, `X-Merchant-Id`/`X-Timestamp`/`X-Nonce`/`X-Sign`).

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
| Gregmorn Hub | Kapalı (kimlik yok) | Kod hazır, operatör login/secret/IP allowlist gerekli |
| Waija / Slotsgateway | Kimlik bekliyor | Kod tamamen hazır; base_url + api_login/password + salt_key gerekli |
| smpl core | Kapalı (kimlik yok) | Kod+adapter hazır; `merchant_id` boş |

Canlı masaların görünmesi için **Waija** (tek kimlik, 150-250+ satıcı, slot + canlı),
**smpl core merchant_id** veya **Gregmorn operatör kimlikleri** gerekli;
üçü de canlı masa (live dealer) içeriği sağlıyor.

> **Waija cüzdan farkı:** callback tek URL'e **GET** gelir (`action=balance|debit|credit`),
> imza `md5(timestamp + saltkey)` (30 sn pencere), tutar/bakiye **integer cent**, yanıt her
> zaman **HTTP 200** + `{error, balance}`. Bu, yukarıdaki `GetBalance/Withdraw/...` uçlarından
> farklıdır; Waija için `WaijaWebhookController` kullanılır.
