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
- **Konteyner her oturum arasında sıfırlanır** (PHP + MariaDB + veritabanı gider; repo ve
  `install.sql` kalır). Soğuk başlatma: `bash /workspace/project/scripts/dev-bootstrap.sh`
  → paketleri kurar, MariaDB'yi başlatır, `install.sql`'i import eder, migrate + katalog
  senkronu yapar ve sunucuyu `$PORT`te (varsayılan 12000) ayağa kaldırır. Tekrar çalıştırmak güvenli.
- Sunucu: `php -S 0.0.0.0:12000 -t /workspace/project /workspace/project/index.php`
  (docroot repo kökü, `index.php` front controller). Log: `/tmp/promex-server.log`.
- Yayın adresi (dev): https://work-1-tlcfjicrlanlyelk.prod-runtime.all-hands.dev/ (port 12000).
- DB: MariaDB, veritabanı `promex`, kullanıcı/şifre `promex` (127.0.0.1).

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
- Güncel endpoint'ler (agregatör 2026'da iki markayı taşıdı): Pragmatic `apipk.lxgame.io`,
  Amatic `apiam.lxgame.io`; PGSoft `ggapi.loginxgamesapi.com`, Amusnet `api.gitamus.net` kalır.
  Kimlikler `.env`'de (`*_AGENT_ID`/`*_API_TOKEN`/`*_SECRET_KEY`); varsayılanlar
  `config/casino_providers.php` içinde. Callback `CASINO_CALLBACK_BASE` + `CASINO_CALLBACK_SLUG`.
- Canlı casino masası YOK: bu dört marka yalnızca slot/RNG masa (Rulet) veriyor; baccarat/live
  dealer yok. Gerçek canlı masa için **SoftAggregator** (40.000+ oyun, canlı + crash, TRY),
  Waija/Slotsgateway veya smpl core/Gregmorn yapılandırılmalı (kimlikleri bekliyor).
- **SoftAggregator**: Waija protokolüyle birebir aynı agregatör (slot + canlı + crash). Sınıflar
  Waija'yı miras alır (`SoftAggregatorClient extends WaijaClient`,
  `SoftAggregatorWalletService extends WaijaWalletService`); yalnız config bloğu, base URL
  (`https://api.softaggregator.com/api/v1`) ve callback yolu farklı. Callback:
  `{APP_URL}/webhooks/softaggregator/callbacks` (`key = md5(timestamp + salt_key)`).
  `php artisan casino:sync-catalog --provider=softaggregator`.
  - **Ön ödemeli (prepaid)**: komisyon USDT/USDC kredisinden düşer (slot %8, spor %9, canlı %11);
    kurulum/aylık ücret yok ama kredi 0 olunca oyunlar açılmaz, minimum yükleme 100 USD. Kredi
    bittiğinde `createPlayer`/`getGame` **"Insufficient operator credit"** döner.
  - **Yanıt şekli iki türlü**: çoğu stüdyo `{"response":"<url>"}` (düz string), ama Hub-hosted
    slotlar ve **tüm canlı dealer** `{"response":{"gameurl":"<url>"}}` (obje) döner. `WaijaClient::
    extractLaunchUrl()` ikisini de kabul eder; yalnız string beklemek canlı masaları kırar.
  - Canlı dealer (Live Casino ~226 masa) hesap ürün olarak aktif edilince `getGameList`'te
    görünür; sync otomatik alır.
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

### Waija (Slotsgateway) agregatörü (slot + canlı, 150-250+ satıcı)
- Docs: <https://documentation.waija.com>. Tek kimlik seti arkasında çok satıcı.
- Dışa çağrılar **form-encoded POST**'tur (`application/x-www-form-urlencoded`) ve
  `api_login` / `api_password` / `method` taşır — doküman "JSON" yazsa da referans SDK
  (`slotsgateway/slotsgateway-php-client`, `sendRequest` → Guzzle `form_params`) form gönderir.
  Akış: `createPlayer` → `getGameList` (katalog, satır `id_hash` = launch id) → `getGame`
  (oynanabilir `response` URL'i). `WaijaProvider::launchUrl` önce `createPlayer` çağırır
  (idempotent; oyuncu varsa Waija içeride `playerExists`e yönlendirir). Para birimi wire'da
  BÜYÜK harftir (`strtoupper`). `WAIJA_REQUEST_FORMAT=form` ile JSON'a çevrilebilir.
- Cüzdan callback'i: Waija tek URL'e **GET** atar, `action=balance|debit|credit`.
  İmza `key = md5(timestamp + saltkey)`; `timestamp` son **30 saniye** içinde olmalı.
  **Her yanıt HTTP 200**; hata gövdede: `{error, balance}` — `0` ok, `1` yetersiz bakiye,
  `2` işlem hatası (bilinmeyen oyuncu / kötü veya eski imza).
- **Bakiye ve tutarlar wire'da integer CENT'tir** (`$2.50 → 250`); site defteri ondalık tutar,
  bu yüzden sınırda ×100 ölçeklenir.
- Rollback ayrı bir action değildir: `rb=1` ile debit/credit olarak gelir, normal hareket gibi
  uygulanır. `type=bonus_fs` debit'inde **nakit düşülmez** (ücretsiz spin Waija tarafından ödenir).
  `call_id` idempotency anahtarıdır.
- Dosyalar: `app/Casino/Waija/{WaijaClient,WaijaWalletService}.php`,
  `WaijaWebhookController` → `GET|POST /webhooks/waija/callbacks` (CSRF'ten muaf),
  bağdaştırıcı `app/Casino/Providers/WaijaProvider.php`, kayıt `CasinoProviderRegistry`,
  config bloğu `config/casino_providers.php` → `waija`.
- Demo (fun-play) launch: `WaijaClient::demo()` / `WaijaProvider::demoLaunch()` →
  `getGameDemo` (oyuncu ve cüzdan gerektirmez). Hesap kilitliyken gerçek para yerine
  bununla test edilir. `playerExists` **deprecated**; onun yerine `createPlayer` kullanılır
  (oyuncu varsa Waija içeride yönlendirir), bu yüzden hiç çağrılmaz.
- Kimlik bilgileri `.env`'de (`WAIJA_BASE_URL`, `WAIJA_API_LOGIN`, `WAIJA_API_PASSWORD`,
  `WAIJA_SALT_KEY`, `WAIJA_PLAYER_PASSWORD`, `WAIJA_PLAYER_NICKNAME`, `WAIJA_REQUEST_FORMAT`,
  `WAIJA_CURRENCY=TRY`, `WAIJA_CALLBACK_PATH`, `WAIJA_SIGNATURE_WINDOW=30`, `WAIJA_BRANDED`).
  Prod base: `https://api-eu-1.waija.com/api/system/operator`. Waija backoffice'te sunucu IP
  allowlist'i ZORUNLUDUR; eklenmemişse API `401 {"error":"Unauthorized","message":"No valid
  authentication method found."}` döner. **Sandbox çıkış IP'si her konteyner sıfırlamasında
  değişir**; `php artisan casino:integration-status` güncel IP'yi gösterir, değişince
  backoffice'ten yeniden allowlist'e eklenmelidir.
- `isConfigured()` yalnızca base_url + api_login + api_password ister; **salt_key gerekmez**
  (o sadece gelen callback imzası içindir → `canVerifyCallbacks()`). Salt'ı zorunlu tutmak
  katalog senkronunu ve launch'ları sessizce devre dışı bırakıyordu.
- Waija `error` alanı başarıda `0`, auth hatalarında `"Unauthorized"` (string) döner.
  `(int)"Unauthorized" === 0` olduğundan ham cast hatayı yutar → `errorCode()`/`failed()`
  ile normalize edilir; HTTP ≥ 400 da hata sayılır.
- Katalog senkronizasyonu: `CasinoCatalogSyncService::fetchWaija`; `gameid` = `id_hash`.
- Testler: `tests/Casino/waija-integration-regression.php` (29 statik kontrol),
  `tests/Casino/waija-wallet-e2e.php` (17 cüzdan kontrolü),
  `tests/Casino/waija-webhook-e2e.php` (12 gerçek rota kontrolü, GET + cents).

### Testler
- `php scripts/verify_casino_wallet.php` — 15 cüzdan kontrolü (kendi verisini temizler).
- `php tests/Casino/provider-integration-regression.php` — 19 entegrasyon kontrolü.
- `php tests/Casino/catalog-sync-regression.php` — 33 katalog senkronizasyon + lobi performans + logo + kapak + mobil navigasyon kontrolü.
- `php tests/Casino/embed-aspect-regression.php` — 10 gömülü en-boy oranı kontrolü.
- `php tests/Casino/game-player-overlay-regression.php` — 7 oyun oynatıcı overlay kontrolü.
- `php tests/Account/account-security-regression.php` — 17 hesap güvenliği + tek-hash kontrolü.
- Spor regresyonları: `tests/Sports/*.php`.

## Oyun oynatıcı overlay (containing block)
`#game-player` (`games/list.blade.php`) `position: fixed` ve `<main class="motion-intro">` içinde
render edilir. `.motion-intro` giriş animasyonu **`backwards`** dolgu kullanmalıdır; `both`/
`forwards` son keyframe'i tutar ve `transform: none` bile **identity matrix** olarak hesaplandığından
`<main>` fixed overlay'ler için containing block olur. Sonuç: oyuncu sayfanın tepesine değil, uzun
sayfa kolonunun dibine (~2600px) yerleşir ve telefonda oyun hiç açılmıyormuş gibi görünür.
Kural: fixed torun taşıyan animasyonlu bir kapsayıcıya asla `both`/`forwards` dolgu verme.
Kaynak `resources/css/tailwind.css`, servis edilen derlenmiş dosya `../minimal/css/tailwind.css`
(`npm run css:build`). Regresyon: `tests/Casino/game-player-overlay-regression.php`.

## Şifre hash'leme (tek hash kuralı)
`app/User.php:setPasswordAttribute` gelen değeri **zaten `bcrypt`** yapar. Bu yüzden şifre atayan
hiçbir yer ön-hash (`bcrypt()` / `Hash::make()`) çağırmamalıdır; aksi halde çift hash oluşur ve
hesap hiçbir giriş formundan açılamaz. `MultiAuthController` (OTP kaydı) ve `Liteback/UserController`
(admin kullanıcı oluşturma) düz metni doğrudan atar. Regresyon: `tests/Account/account-security-regression.php`.

## Lobi performansı ve launch (telefon)

Lobi katalogu **kademeli** basar: ilk ekran kadarı (`$initialCards = 120`) sunucuda render edilir,
kalanı `#games-rest-data` içindeki JSON'dan istemcide `CHUNK = 120`'lik parçalarla eklenir
(`appendChunk`, scroll sentinel + "Daha Fazla Yükle"). Telefonda DOM 6252 → 120 kart, HTML
13.3 MB → ~1.9 MB, DOMContentLoaded 3.4 s → ~1.3 s. Arama (`#home-game-search`) artık kartları
her seferinde DOM'dan okur (`allCards()`), böylece hydrate edilen kartları da filtreler.
- **gzip:** Prod'da nginx yapar (`deploy/nginx-casino.conf.example` → `gzip on` + tipler).
  nginx olmayan ortamlarda (`php -S`, `PHP_SAPI === 'cli-server'`) `Http/Middleware/GzipResponse`
  devreye girer; nginx arkasında `Content-Encoding` zaten set olduğundan **no-op**'tur (çift
  sıkıştırma olmaz).
- **`content-visibility: auto`** (`.game-card`, `layouts/clean.blade.php`): ekran dışı kartlar
  çizilmeden atlanır; `contain-intrinsic-size` kaydırma zıplamasını önler. Tasarım/JS değişmez.
- **Kapak görselleri:** `scripts/optimize_game_images.py` büyük JPEG'leri kart oranına (300×400,
  progressive q80) çeker; 1128 dosya 48.6 → 28.2 MB. Yeni indirmeler `localize_game_images.php`
  içinde aynı boyutta üretilir.
- Kategori sayfaları (`/categories/live_casino`) zaten küçük ve hızlıdır.

### Mobil navigasyon
- Üst barda telefonda `.site-nav-mobile` kümesi: misafirde Giriş/Kayıt, üyede bakiye chip'i
  (`site-nav-wallet`, TRY) + avatar (`.js-open-mobile-sheet` → bottom sheet). Masaüstünde
  `.site-nav-right` tam link + CTA satırını gösterir; ikisi `@media (max-width:1023px)` ile ayrışır.
- Alt dock `.app-dock` (`partials/navbar.blade.php`): Casino / Canlı / Cedar / Spor / Merkez;
  aktif sekme rota ile `is-active` olur. Dokunma hedefleri ≥40px.

Canlı masa launch'ı (`SoftAggregatorProvider` → `WaijaClient::launch`):
- `device` **istek User-Agent'ından** türetilir (`detectDevice()`; `MobileDetect`'e
  `setUserAgent()` ile verilir — yapıcıya geçirmek çalışmaz). Telefona masaüstü build verilmesi
  canlı masaların açılmamasının/geç yüklenmesinin bir nedenidir. `country` config'ten (TR).
  İkisi de `getGame` **ve** `getGameDemo`'ya `launchContext()` ile eklenir.
- Agregatörün **saatlik launch limiti** ("Too many game launches") `launchErrorMessage()` ile
  Türkçe'ye çevrilir; oyuncuya "birkaç dakika sonra tekrar deneyin" gösterilir (ham İngilizce
  metin "oyun bozuk" gibi okunuyordu).
- Navbar "Canlı Casino" artık **konsolide `live_casino` hub'ına** gider (203 masa);
  `evolution` yalnız 19 masa gösteriyordu.

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

### Sağlayıcı logoları
`public/frontend/Default/provider-logos/*.svg` **elle düzenlenmez**; `scripts/gen_provider_logos.py`
üretir (`python3 scripts/gen_provider_logos.py`). Tasarım: cam efektli (yarı saydam dolgu + ince
gradyan kontur) yuvarlatılmış monogram rozeti + anlamsal ikon (slot/fiş/zar/elmas/yıldız/şimşek/taç/
kart) + beyaz wordmark; tamamen şeffaf arka plan. Renk paleti ve ikon `BRANDS`/`G` sözlüklerinde;
yeni sağlayıcı eklerken ikisine de kayıt gir. Script iki dizine yazar (kaynak + `casino/public/...`).
Logo ekleme/değiştirme sonrası `catalog-sync-regression.php` çalıştırılır (rozet deseni doğrulanır).
