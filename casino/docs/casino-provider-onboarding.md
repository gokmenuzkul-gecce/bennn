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

### B. OroPlay (canlı casino + slot + mini oyun) — ŞU AN ÖLÜ
Yapılandırılan host `api.oroplay.com` **DNS'te yok** (çözülmüyor).

- [ ] **Doğru API base URL** (staging + prod)
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

### D. smpl core (alternatif agregatör)
- [ ] Base URL (staging + prod)
- [ ] `merchant_id`
- [ ] `merchant_key`
- [ ] Callback URL'imizi kayıt → `https://<SITE_ALAN_ADI>/webhooks/smplcore/callbacks`

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
| OroPlay | Ölü | `api.oroplay.com` çözülmüyor — doğru host gerekli |
| Gregmorn Hub | Kimlik bekliyor | Kod hazır, operatör login/secret/IP allowlist gerekli |
| smpl core | Placeholder | merchant id/key gerekli |

Canlı masaların görünmesi için **OroPlay doğru host** veya **Gregmorn operatör
kimlikleri** gerekli; ikisi de canlı masa (live dealer) içeriği sağlıyor.
