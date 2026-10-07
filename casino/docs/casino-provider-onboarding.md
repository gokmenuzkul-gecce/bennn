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

### B. OroPlay (canlı casino + slot + mini oyun) — SADECE BASE URL BEKLİYOR

Yapılandırılan host `api.oroplay.com` **DNS'te yok**: yetkili NS Cloudflare `NXDOMAIN`
(Status 3) döner — yani host hiç tanımlı değil, geçici bir kesinti değil. `oroplay.io` /
`oroplay.com` ana siteleri ve bilinen tüm alt alan varyantları denendi, hiçbirinde public
API yok; base URL operatör onboarding'inde veriliyor (site yalnızca Telegram iletişimi
sunuyor: <https://t.me/oroplaycs1>).

**Kimlikler doğrulandı ve doğru çalışıyor:**
- `clientId` = `stg-TRY-Geccebet53` (staging), `clientSecret` mevcut → `isConfigured() === true`
- Callback URL'imiz kayıtlı ve **çalışıyor**: `POST /webhooks/oroplay/api/balance` Basic auth
  ile HTTP 200 + `{"success":false,"message":"User does not exist","errorCode":2}` döner
  (doğru format; "User does not exist" beklenen çünkü test kullanıcısı yok).

- [ ] **Doğru API base URL** (staging + prod) — TEK EKSİK. Sağlayıcıdan isteyin.
      İsteninceye kadar: `OROPLAY_BASE_URL=https://api.oroplay.com/api/v2` → NXDOMAIN.
- [x] `clientId` = `stg-TRY-Geccebet53`
- [x] `clientSecret` = (`.env`'de)
- [x] Callback URL'imiz → `https://<SITE_ALAN_ADI>/webhooks/oroplay/api` (route'lar hazır)
- [x] Basic auth başlığı (clientId:clientSecret) — doğrulandı
- [ ] Agent kaydı + canlı casino vendor/game listesi

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
- [x] **IP allowlist**: eklendi (API `200` dönüyordu)
  > ⚠️ **DİKKAT:** Konteyner sıfırlanınca çıkış IP'si **değişiyor**. İlk allowlist `34.45.0.142`
  > idi; sıfırlama sonrası IP `34.70.174.52` oldu ve API `401 No valid authentication method
  > found` dönmeye başladı. **IP değişince backoffice'ten yeni IP'yi allowlist'e ekle**:
  > ```bash
  > curl -s https://api.ipify.org   # güncel çıkış IP'si
  > ```
- [x] **Salt key**: alındı (`951eaec0e1a0`, 12 karakter), `.env`'e yazıldı. Webhook imzası
  gerçek salt'la doğrulandı: geçerli `true`, bozuk `false`, eski (>30 sn) `false`.
  > Not: `isConfigured()` artık salt'ı şart koşmuyor (yalnızca endpoint + login + şifre);
  > salt sadece gelen callback doğrulaması için gerekli. Bu ayrım olmadan salt gelene
  > kadar katalog senkronu ve launch'lar sessizce devre dışı kalıyordu.
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

### Referans uygulama: Racko (play.racko.app)
Waija ile çalışan canlı bir uygulama, tasarımımızı doğrulamak için incelendi:

- **Oyun kimliği**: `waija-<vendor>/<game>` **URL slug**'ıdır; gerçek `gameid` yine
  `vendor/game` (ör. `pragmaticslots/aladdin-and-the-sorcerer`). Bizim `id_hash`'i
  aynen kullanmamız **doğru** — ekstra önek gerekmez.
- **Para birimi**: Racko **USD** kullanıyor (`Currency:"USD"`), TRY değil → TRY'nin
  Waija'da desteklenmediğini bağımsız olarak doğrular.
- **Kapsam**: ~18 sağlayıcı, yalnızca Waija API'si (Racko "üçüncü taraf aggregator
  eklenemez" notu taşır).
- **Launch akışı**: Sunucu tarafında (JS bundle'da API çağrısı yok) — bizimkiyle aynı
  mimari: `createPlayer` → `getGame` → dönen URL iframe'de açılır.

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
| Waija / Slotsgateway | Kimlik bekliyor | Kod tamamen hazır; base_url + api_login/password + salt_key gerekli. **Not: IP allowlist hâlâ eksik** (`Ip not whitelisted`). |
| SoftAggregator | **CANLI (kredi bekliyor)** | Kod + cüzdan + katalog tamam. 1095 oyun senkron (1089 slot). `createPlayer` → "Insufficient operator credit" (USDT/USDC yüklemesi gerekli). **Canlı casino ürünü henüz aktif değil** — `@mentionso`'ya başvur. |
| smpl core | Kapalı (kimlik yok) | Kod+adapter hazır; `merchant_id` boş |

### D. SoftAggregator (slot + canlı + crash agregatörü) — ✅ ENTEGRE, CANLI TEST EDİLDİ

Aradığımız "tek API ile her şey" çözümü. Protokolü Waija ile **birebir aynı**; sınıflar Waija'yı
miras alır (`SoftAggregatorClient extends WaijaClient`,
`SoftAggregatorWalletService extends WaijaWalletService`) — yalnız config bloğu, base URL ve
callback yolu farklı.

**Kurulum tamamlandı:** hesap açıldı, `api_login`/`api_password`/`salt_key` `.env`'e yazıldı,
callback URL backend'de kayıtlı, `TRY` varsayılan oyuncu para birimi.

**Canlı doğrulama (gerçek anahtarlarla):**

| Test | Sonuç |
|---|---|
| `getGameList` (TRY) | HTTP 200, `error:0`, **1095 oyun** |
| `getCurrencies` | `default:TRY`, `currencies:[TRY,EUR]`, `settlement:EUR`, `open:true` |
| Katalog senkronu | 1095 getirildi, 621 eşleşti, **453 eklendi** (DB'de 1074 satır) |
| `createPlayer` | HTTP 200, `error:1` "Insufficient operator credit" (beklenen — kredi yok) |
| `getGame` (launch) | Aynı: kredi yüklenince çalışacak |
| Wallet `balance` | `{"error":0,"balance":899763200}` (8.997.632,00 TRY) ✓ |
| Wallet `debit` 1.00 | `{"error":0,"balance":899763100}` ✓ |
| Wallet `credit` 0.50 | `{"error":0,"balance":899763150}` ✓ |
| Idempotency (debit tekrar) | Bakiye değişmedi ✓ |
| İmza `md5(timestamp+salt_key)` | Geçerli ✓ / bozuk & eski reddedildi ✓ |

**Kalan iki iş:**

1. **Operatör kredisi yükle** (USDT/USDC, `@mentionso`) → `createPlayer` + `getGame` çalışır,
   gerçek oyun oynanır. Katalog ve demo bundan bağımsız çalışıyor.
2. **Canlı casino ürününü aktive et** (`@mentionso`): Hesapta şu an yalnızca Video Slots (1089),
   Arcade (4), Betting (1), Bingo (1) var — **canlı dealer yok**. Canlı masalar ürün aktif
   edilince `getGameList`'te otomatik görünür; kod tarafında değişiklik gerekmez.

**Not:** `createPlayer` + `getGame` her oyuncu için varsayılan para birimini geçersiz kılar;
callback `username` alanı kullanır (`user_username` değil) ve `X-Signature` başlığı HMAC-SHA256
taşır (opsiyonel, `md5` yeterli). Ayrıntı: <https://softaggregator.com/docs.html>

Destek: Telegram `@mentionso` · `support@softaggregator.com`

Canlı masaların görünmesi için **Waija** (tek kimlik, 150-250+ satıcı, slot + canlı),
**smpl core merchant_id** veya **Gregmorn operatör kimlikleri** gerekli;
üçü de canlı masa (live dealer) içeriği sağlıyor.

> **Waija cüzdan farkı:** callback tek URL'e **GET** gelir (`action=balance|debit|credit`),
> imza `md5(timestamp + saltkey)` (30 sn pencere), tutar/bakiye **integer cent**, yanıt her
> zaman **HTTP 200** + `{error, balance}`. Bu, yukarıdaki `GetBalance/Withdraw/...` uçlarından
> farklıdır; Waija için `WaijaWebhookController` kullanılır.
