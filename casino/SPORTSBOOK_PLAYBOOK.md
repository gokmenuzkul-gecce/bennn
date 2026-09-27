# Battle Odds Operator Playbook

This guide covers the simple Battle Odds feed backed by `sports_matches`. It is separate from the older advanced league/market tables and commands.

## What the PROMEX feed provides

PROMEX supplies upcoming **PRE-MATCH** fixtures with decimal head-to-head prices. It is suitable for ordinary social/demo bets placed before an event starts.

The feed is not real-time and does not include live/in-play odds. Pre-match prices can still change near kickoff or after important team news, but they normally move less often than live prices. A separate higher-frequency live-odds service is planned for operators who need it.

## First import

1. Open **Liteback → System & API Keys**.
2. Select **PROMEX Licensed API**. This option is included with an active license carrying the sportsbook entitlement.
3. Click **Test PROMEX API**. A successful result verifies the installation and reports how many pre-match odds are available; it does not write matches to the database.
4. Under **Manual Operations**, run **Sync Sports Odds Now**, or execute:

   ```bash
   php artisan sports:sync-odds
   ```

5. Open Battle Odds and confirm the imported fixtures appear.

The full-sync runner automatically creates or updates and enables categories and leagues found in the licensed response. Rows absent from a later response are retained. The manual runner also shows checkboxes for limiting one run to selected available sports. Operators may disable an entry afterward, but the next full run enables included entries again.

Operators may instead choose **My own The Odds API key**. That mode calls the operator's provider account and does not use the PROMEX sportsbook entitlement.

The customer app uses one PROMEX endpoint. The Hub handles the separate upstream sport endpoints and returns one normalized response. The current 500-credit plan includes five Hub-configured sports; increasing coverage is done centrally after adjusting the upstream quota plan.

## Refresh model

Laravel runs `sports:sync-odds` every 30 minutes and imports the newest dataset available from the selected provider. The licensed PROMEX dataset itself currently refreshes once per day to keep shared upstream usage within the 500-credit monthly plan. Repeated customer imports do not force additional upstream refreshes.

This slower schedule is intentional for pre-match social gaming. Do not describe it as live odds, and do not use it where second-by-second price movement is required.

## Results and settlement

Odds are prices, not proof of an event result. The protected scores endpoint is separate. The existing random-score settlement command is not safe for real wagering and must remain disabled until result matching, idempotency, void/refund behavior, and payout recovery are fully tested.

## Troubleshooting

### Clear the active odds UI

Use **Clear Active Site Odds** on the Sports dashboard to hide all current Battle Odds fixtures and advanced sportsbook games, markets, and outcomes. This action changes visibility/status only: it does not delete bets, bet items, completed history, categories, leagues, or teams. Run `sports:sync:all` afterward to reactivate the odds currently available from the licensed feed.

Do not use a database truncate or the legacy destructive sports reset for this purpose.

- If the test button fails, confirm the installation license is active and includes sportsbook access.
- If the test passes but Battle Odds is empty, run the import; the test button never imports data.
- If import fails in Custom mode, verify the operator's API key and provider quota.
- Never clear `sports_bets` during fixture testing. Back up `sports_matches` before resetting imported fixtures.
