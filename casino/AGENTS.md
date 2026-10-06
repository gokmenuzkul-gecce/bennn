# AGENTS.md — Casino Gecce (Laravel)

## Proje
- Kök: `/workspace/project/casino` (Laravel, namespace `VanguardLTE\`).
- Frontend teması: `resources/views/frontend/Minimal` (ayar `frontend`).
- Admin paneli: `/liteback` (`resources/views/liteback`).
- Ödeme/API servisleri: `app/Services` (OddsApiService, PolymarketService), `app/Sports/Services/SportsOddsSyncService`.

## Marka
- Görünen marka adı **Casino Gecce** (`app_name` + `brand_tagline` ayarları, varsayılanlar).
  Eski "Promex Gaming Suite" / "Social Gaming" metinleri kaldırıldı.
- "promex" hâlâ **teknik kimlik** olarak geçer ve DEĞİŞTİRİLMEMELİDİR: spor sağlayıcı anahtarı
  (`PromexLicensedProvider`, `sportsbook_api_provider=promex`), `js/promex-html-game.js`,
  `js/promex-legacy-bridge.js`, `PromexInstallationService`. Bunlar lisans/entegrasyon sözleşmesidir.

## Çalıştırma
- Yerel sunucu: `php artisan serve --host=0.0.0.0 --port=8000` (pid dosyası yoksa `ps aux | grep artisan`).
- View derleme: `php artisan view:clear && php artisan view:cache` (blade sözdizimi kontrolü için).
- Yayın adresi (dev): https://work-1-tlcfjicrlanlyelk.prod-runtime.all-hands.dev/

## Yerelleştirme (tr)
- Varsayılan dil `tr`: `.env APP_LOCALE=tr`, `config/app.php` `'locale' => env('APP_LOCALE','tr')`.
- Dil dosyaları: `resources/lang/tr/{app,auth,pagination,passwords,log,validation}.php`.
- `app/Http/Middleware/SelectLanguage.php`: sıra → config varsayılanı → kullanıcı dili → `language` cookie.
- Blade metinleri çeviri anahtarı yerine doğrudan Türkçe metinle değiştirildi (tasarım/kod korunarak).

### Çeviri kuralı (önemli)
Naif `str.replace` KULLANMA. `Play`, `All`, `in`, `on`, `Status` gibi kısa kelimeler CSS sınıflarına,
JS koduna ve `material-symbols` ikon adlarına sızar ve sayfayı bozar.
Bunun yerine:
1. Yalnızca HTML metin düğümlerini (`>...<`) ve güvenli öznitelikleri (`placeholder`, `aria-label`,
   `title`, `alt`) tam eşleşmeyle değiştir.
2. Uzun, boşluklu ifadeleri (≥8 karakter, boşluk içeren) her yerde değiştirmek güvenlidir.
3. İkon adlarını (`material-symbols-outlined` içeriği) ve marka adlarını (CEDAR, PROMEX, Liteback,
   Battle Odds) ASLA çevirme.
4. Değişiklikten sonra `git diff` ile `class="..."` içinde Türkçe karakter olup olmadığını kontrol et;
   `php artisan view:cache` ile tüm şablonların derlendiğini doğrula.

## Sağlayıcı katmanı
- Sağlayıcı bağdaştırıcıları: `app/Sports/Services` + `liteback/sports/providers` sayfası.
- PROMEX lisanslı akış varsayılan; özel anahtar (The Odds API) opsiyonel.

## Kumarhane sağlayıcı entegrasyonu (seamless wallet)
- Dört gerçek marka: Pragmatic, PGSoft, Amatic, Amusnet — tek agregatör protokolü.
- Bağdaştırıcılar: `app/Casino/Providers/{AbstractCasinoProvider,PragmaticProvider,...}.php`,
  kayıt defteri `CasinoProviderRegistry` (`findBySlug`, `callbackUrl`, `catalog`).
- İmza: HMAC-SHA256, alanlar sabit sırayla birleştirilir, para alanları 2 ondalığa yuvarlanır
  (`SIGN_ORDERS`, `MONEY_FIELDS`). Doğrulamada `hash_equals` + `strtoupper`.
- Cüzdan: `app/Casino/Wallet/CasinoWalletService.php` — GetBalance/Withdraw/Deposit/BetWin/
  RollbackTransaction; `casino_wallet_transactions` üzerinden idempotent (tekrar eden işlem reddi).
- Webhook: `POST /webhooks/aggregator/{slug}/wallet/{Operation}` →
  `CasinoWalletController`. CSRF'ten muaf (`VerifyCsrfToken`). Dönüş kodları:
  0 başarı, 3 geçersiz imza, 5 kullanıcı yok, 6 yetersiz bakiye, 9 zaten geri alındı, 11 tekrar.
- Oyun başlatma: `CasinoGameLaunchService` → `GamesController::go` içinde `provider_key` dolu
  oyunlar için userAuth URL'i üretir (lisans kontrolünden önce). `AbstractCasinoProvider::launchUrl`
  agregatöre **POST** yapar (`Authorization: Bearer`, JSON gövde) ve dönen `url`'i kullanır; userID
  yalnızca alfanümerik olmalı (alt çizgi temizlenir), gameID ise satıcının **sayısal** id'si
  (`games.launch_code`).
- Site içi gömülü oynatma: lobi kartı `play-game` düğmesiyle `GET /game/{name}/launch`
  (`GamesController::launch_json`) çağırır ve dönen oturum URL'ini `#game-player` modalındaki
  iframe'e yükler; "Yeni Sekme" ile ayrı sekmeye açılabilir. `CasinoGameLaunchService::canEmbed`
  gömülebilirliği belirler: Amatic `X-Frame-Options: SAMEORIGIN` gönderdiği için
  `config/casino_providers.php` içinde `embeddable=false` ve modalda yeni sekme önerilir; diğer üç
  sağlayıcı gömülü açılır (Amusnet önce kendi POST formunu çalıştırır, sonuç iframe'e gömülebilir).
  Agregatör olmayan (Cedar/yerel) oyunlar doğrudan `game/{name}` bağlantısını korur.
- En-boy oranı: aggregator PG Soft için çağırandan bağımsız olarak **telefon** build'ini döndürür
  (dikey 9:16). `CasinoProvider::embedAspect()` sağlayıcı başına tercih edilen oranı verir
  (`config/casino_providers.php` → `embed_aspect`, PG Soft için `PGSOFT_EMBED_ASPECT=9:16`, diğerleri
  `auto`). Lobideki `launch_json` bu değeri `aspect` olarak döndürür; oyuncu iframe'i
  `game-player-frame-wrap` içinde orana göre ölçeklenir (`ResizeObserver`) — aksi halde oyun geniş
  panelde yarım bir dikey şerit olarak görünür.
- Katalog senkronizasyonu: `CasinoCatalogSyncService` + `php artisan casino:sync-catalog`
  (`--provider=`, `--link-only`). Eski `games` satırlarını normalize başlıkla eşleştirir, eksikleri
  içe aktarır; `provider_key`, `provider_game_id` (sembol), `launch_code` (sayısal id), `icon_url`
  (gerçek kapak) yazar ve `categories`/`game_categories` ile lobi filtresine bağlar. Toplu içe aktarma
  `Game::withoutEvents` kullanır (admin denetim abonesini atlar).
- Kırık yer tutucular: yerel klasörü olmayan ve sağlayıcıya bağlı olmayan `source_type='default'`
  satırlar `view=0` yapılır (tıklanınca 404 veren eski oyunlar gizlenir).
- Kimlik bilgileri `.env`'de (`PRAGMATIC_*`, `PGSOFT_*`, `AMATIC_*`, `AMUSNET_*`); config yalnızca
  `env()` okur, sır tutmaz. Operatör Liteback'ten (`/liteback/casino/providers`) override edebilir.

## Gregmorn Hub agregatörü (çok sağlayıcılı slot + canlı casino)
- Doküman: <https://docs.gregmorn.org> (HTTP Basic, login `gregmorn`). Stage ve prod ayrı
  login/secret/IP allowlist kullanır. Gregmorn Hub tek kimlik bilgisiyle birçok sağlayıcıyı
  (slotlar + canlı masa) sunar ve her oyunu site cüzdanına bağlar.
- Protokol (legacy loginxgames'ten farklı): `POST /auth/login` (form-encoded) kısa ömürlü JWT verir;
  `GET /users/{user_id}/getUserGames/{currency}` kataloğu döner; `POST /games/openGame` (JSON +
  `X-Signature`) oynanabilir oturum URL'i verir. `X-Signature` = ham gövde üzerinde hex HMAC-SHA256,
  hesap secret'ı ile.
- Cüzdan callback'leri: Hub `getBalance` / `writeBet` / `rollback` komutlarını tek URL'e JSON olarak
  POST eder; yanıt her zaman HTTP 200 + `{balance,currency,duration,error,login,status}`.
  `writeBet` stake'i düşüp kazancı ekler (delta = win - bet); `transactionId` idempotency anahtarıdır;
  `rollback` aynı `transactionId`'li writeBet'i geri alır.
- Dosyalar: `app/Casino/Gregmorn/{GregmornClient,GregmornWalletService}.php`,
  `GregmornWebhookController` → `POST /webhooks/gregmorn/callbacks` (CSRF'ten muaf),
  bağdaştırıcı `app/Casino/Providers/GregmornProvider.php`, kayıt `CasinoProviderRegistry`,
  config bloğu `config/casino_providers.php` → `gregmorn`.
- Kimlik bilgileri `.env`'de (`GREGMORN_OFFICE_BASE_URL`, `GREGMORN_CLIENT_BASE_URL`, `GREGMORN_LOGIN`,
  `GREGMORN_PASSWORD`, `GREGMORN_SECRET_KEY`, `GREGMORN_USER_ID`, `GREGMORN_CURRENCY=TRY`).
  Operatör Liteback'ten override edebilir. `GREGMORN_USER_ID` boşsa `/auth/login` yanıtındaki
  `user.id` kullanılır. Hub operatör API'si IP allowlist ister; allowlist yoksa `/auth/login` 401
  döner (doküman girişi bundan bağımsızdır).
- Katalog senkronizasyonu: `CasinoCatalogSyncService::fetchGregmorn` tüm listeyi (slot + canlı masa)
  çeker; `gameid` Hub'ın kendi id'sidir (ör. `integration_a:provider_a:game_001`).
- Testler: `tests/Casino/gregmorn-integration-regression.php` (18 statik kontrol),
  `tests/Casino/gregmorn-wallet-e2e.php` (gerçek DB'de 9 cüzdan kontrolü).
- Admin: `/liteback/casino/providers` (durum + test + aç/kapat), `/liteback/casino/transactions`.
- Callback slug `gregmorn`; callback tabanı `CASINO_CALLBACK_BASE`.

### Testler
- `php scripts/verify_casino_wallet.php` — 15 cüzdan kontrolü (kendi verisini temizler).
- `php tests/Casino/provider-integration-regression.php` — 19 entegrasyon kontrolü.
- `php tests/Casino/catalog-sync-regression.php` — 16 katalog senkronizasyon kontrolü.
- `php tests/Casino/embed-aspect-regression.php` — 10 gömülü en-boy oranı kontrolü.
- Spor regresyonları: `tests/Sports/*.php`.

## Modern tasarım sistemi (frontend/Minimal)
Tüm stiller `layouts/clean.blade.php` içindeki `<style>` bloğunda; tema `tailwind.config` ile uyumlu.
- **Arka plan:** `.casino-bg` — sabit (fixed) katman; koyu degrade + ızgara (`::before`, `gridDrift`) + 4 adet
  hareketli ışık küresi (`.orb-1..4`, `floatA/B/C`). `prefers-reduced-motion` desteği var.
- **Slayt:** `.hero-slider` / `.hero-track` / `.hero-slide.is-active` + `.hero-dot` / `.hero-nav`.
  Ana sayfa hero'su 3 slaytlı (Slot-Crash, Spor, Jackpot-VIP). JS `games/list.blade.php` `@section('scripts')`
  içinde: otomatik geçiş 5.5s, nokta/ok kontrolleri, hover'da durur, dokunmatik kaydırma.
- **Sağlayıcı butonları:** `.provider-tile` (gerçek sağlayıcı adları `categories` tablosundan, `--pc` ile
  renk teması) + `.marquee-mask` / `.marquee-track` sonsuz kayan şerit. Liste iki kez basılır (kusursuz döngü).
- **Oyun kartları:** `.game-card` (hover lift + parlama) + `.card-shine` + `.btn-glow` CTA.
- **Yardımcılar:** `.text-gradient`, `.section-title-bar`, `.pulse-dot`.

### Kural
Yeni bölüm eklerken bu sınıfları kullan; satır içi stillerle yeni renk paleti icat etme.
Slayt/şerit eklemek için `.hero-slide` ve `.provider-tile` kalıplarını kopyala.
