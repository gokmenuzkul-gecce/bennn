# Protected API notes

This document records the public contract, private provider boundary, quota assumptions, and operational reason for each hosted PROMEX API. Provider credentials belong only in the private Service Hub environment; never in customer packages, browser code, source control, or this document.

## Sports: The Odds API

### Public service endpoints

- `GET /api/service/sports/odds`
- `GET /api/service/sports/scores`
- Required installation entitlement: `sportsbook_hub`
- Authentication: domain-bound installation HMAC, timestamp, and one-use request ID.
- Browser access: prohibited. Customer Laravel calls these endpoints server-to-server.

The physical implementation is replaceable under `api/sports/`; `api/sports_odds.php` and `api/sports_scores.php` are stable compatibility entry points used by the router.

### Customer application provider choice

Liteback stores `sportsbook_api_provider` as either `promex` (the default) or `custom`. The PROMEX option is always visible, including before activation, but it only runs when the installation has an active `sportsbook_hub` entitlement. Its connectivity test and scheduled sync use the domain-bound installation signature; no shared authority secret or upstream provider key is shipped to the customer.

Custom mode uses the operator's locally stored The Odds API key and does not require a PROMEX sports entitlement. The settings screen hides custom key fields while PROMEX is selected and explains that managed API access is included with an active license.

### Product scope: PRE-MATCH odds

The licensed PROMEX sports feed supplies **PRE-MATCH** fixtures and head-to-head prices for events that have not started. The connectivity button checks the installation license and reports how many pre-match odds records are currently available; it does not import them into Battle Odds. The `sports:sync-odds` command performs that import.

The Hub refreshes its shared pre-match dataset on a deliberately slower schedule, currently once per day. Each Laravel installation may poll the Hub more often, but it receives the same shared dataset until the next provider refresh. This design keeps the 500-credit upstream plan within budget while allowing every licensed installation to use the feed.

Pre-match prices can change, particularly near kickoff or after team, injury, or lineup news, but they normally change much less rapidly than in-play prices. The current refresh model is intended for social/demo pre-match sportsbook use and is not presented as real-time or live betting data.

Frequent live/in-play odds are outside this service. They will be offered later as a separate higher-frequency service with its own endpoint, entitlement, refresh policy, quota, and commercial terms.

### Endpoint and sport discovery model

Customer applications call one stable endpoint, `GET /api/service/sports/odds`; they never call separate upstream league URLs. The Hub internally cycles its configured provider sport keys and combines the results. Laravel discovers every sport and league present in that normalized response, creates new backend entries as enabled, updates feed-owned metadata and odds, and leaves entries missing from a later response in place.

The command `sports:sync:all` imports every sport currently present in the licensed feed by default. Its Liteback runner presents checkboxes so an operator can limit a manual run. Every included category and league is created or updated as enabled. An operator may disable one afterward, but a later full feed run enables included entries again; persistent disable memory is a possible future enhancement.

The Liteback **Clear Active Site Odds** action provides a safe clean-screen reset for testing or live operations. It hides active fixtures, games, markets, and outcomes with reversible status/lock updates while preserving bets and historical records. The next licensed full sync reactivates records present in the current feed.

As checked on 2026-09-26, the upstream catalog contained 68 active non-outright sport keys. At one region and one market, fetching all 68 every day would consume roughly 2,040 credits per 30-day month, before score calls, which is incompatible with the current 500-credit plan. Therefore “all” means all sports included in the subscribed PROMEX feed; the initial plan currently aggregates five configured sports. Broader coverage is a Hub-side plan/configuration change and requires no customer application update.

### Provider and configuration

Provider: The Odds API v4. The real key is configured only as `PROMEX_SPORTS_API_KEY` in the Service Hub environment. For local work, the ignored `clients377live/private/runtime.env` supplies the values without requiring Apache changes. Production may use the same private-file mechanism or server-managed environment variables. `api/sports/provider.conf.example` lists all supported settings without secrets.

Defaults:

- Sports: UEFA Champions League, EPL, La Liga, NBA, MMA.
- Region: `eu`.
- Market: `h2h`.
- Odds format: decimal.
- Odds refresh: 86,400 seconds (daily).
- Completed-score refresh: 86,400 seconds (daily).
- Stale-if-error retention: 604,800 seconds (seven days).
- Local monthly credit guard: 450.

Private `runtime.env` changes are loaded on the next request. Server-managed environment changes may require a PHP/web-service reload.

### Quota decision

The plan has 500 monthly credits. According to the provider's v4 documentation, odds cost one credit per requested region per market. Completed scores using `daysFrom=3` cost two credits per sport.

With five sports and daily refreshes:

- Odds: `5 × 30 × 1 = 150` credits/month.
- Completed scores: `5 × 30 × 2 = 300` credits/month.
- Planned total: about 450 credits/month, leaving about 50 credits of headroom.

The Hub records the provider's quota response headers and maintains a monthly local guard. Refreshing manually, adding regions/markets/sports, clearing private caches, or bypassing the Hub can consume the reserve faster.

### Cache and failure behavior

Redis is the primary shared cache. An atomic private-file copy under `private/cache/sports/` survives Redis outages; no response JSON is written under the public `/api` path. A refresh lock prevents concurrent requests from multiplying provider calls.

Fresh cache is served immediately. If refresh fails, the last valid response is served with `stale: true` for the configured stale window. If the monthly guard is reached, stale data is served instead of consuming more provider credits. With neither cache nor provider availability, the endpoint returns HTTP 503.

### Settlement safety

Odds do not prove results and must never settle wagers. `/sports/scores` uses the provider's scores endpoint and returns verified provider game results separately. The existing Laravel settlement command currently fabricates random final scores and must not be enabled for real wagers. Connecting settlement to the protected scores feed requires a separate test covering event-ID matching, completion state, idempotency, void/refund rules, and payout recovery.

### Deployment and testing

1. Configure the provider variables privately on `clients.377.live`.
2. Reload the web/PHP service.
3. Call either endpoint without installation headers; expect `401 invalid_authentication`.
4. Call with an active installation entitled to `sportsbook_hub`; expect HTTP 200 and `source: promex_sports_hub`.
5. Repeat immediately; expect `cache_status: cache` and no additional provider quota use.
6. Test a valid installation without `sportsbook_hub`; expect `403 feature_not_entitled`.

Official protocol reference: <https://the-odds-api.com/liveapi/guides/v4/>
