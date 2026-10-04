# CASINO GECCE

### Your brand. Your platform. A growing world of social games.

Build a self-hosted social-gaming experience with a customizable player lobby,
the Liteback operator console, sports and prediction modules, virtual trading,
and an optional licensed CEDAR ecosystem.

**V2.0.0 · Laravel 13 · PHP 8.3 minimum · Source available for own use**

[Product and licensing](https://promex.me/platforms/promex-gaming-suite/) ·
[GitHub releases](https://github.com/promexdotme/casino-gecce/releases) ·
[Backup and updates](PATCHES.md) · [Source-use terms](LICENSE)

> Features can require configuration, provider accounts or licensed
> services; source availability does not mean every hosted service is enabled.

## Make it your own

Customize your platform name, tagline and logo for your own operation. Give players
a responsive, dark-themed lobby and manage the experience through Liteback.
Player and operator Help pages support English, French, Spanish, Russian, Turkish,
Arabic and Hebrew; this does not imply every interface string is translated.

## One operator console, many experiences

- **Players and virtual balances:** user administration, balance controls,
  transaction visibility and operator tools.
- **Sportsbook:** sports/event management, odds configuration, betslips and
  settlement workflows. Live feeds require a configured provider/service.
- **Prediction markets:** event-based virtual predictions and market management.
- **Jackpot Zone:** lotto games, ticket purchases, scheduled draws and results.
- **Crypto and stock simulations:** virtual positions with market-data-backed
  experiences—not brokerage or exchange accounts.
- **VIP and referrals:** player progression, bonus/rakeback controls and referral
  features for community engagement.
- **Payments and top-ups:** configurable Stripe, PayPal and BTCPay integrations,
  plus manual-deposit review. Provider accounts, approvals and fees are separate.
- **Delivery settings:** connect your own transactional email through Brevo,
  Resend, Postmark or a compatible custom API. WhatsApp OTP supports PROMEX/custom
  routing; the PROMEX route depends on entitlement, configuration and rollout.

## CEDAR: a protected game ecosystem

CEDAR brings together original arcade experiences, card games and slots through
the protected PROMEX Service Hub.

Protected CEDAR assets and engines stay on our infrastructure. They are excluded
from public source and customer installation packages.
Access depends on an active license and its game/service entitlements.

Central fixes can improve shared services without replacing every customer's
application. Games needing local registration, handlers or database changes get
a separate integration patch with declared prerequisites.

**Roadmap:** expand the CEDAR catalog with new games and updates, followed by a
planned daily game-release cadence after the patch system and release pipeline
pass testing. This is a plan, not a current daily-release or game-count guarantee.

## Licensing is optional. Managed services make it worthwhile.

You can install the community application without buying a PROMEX service license.
Use and customize the covered source for your own operation under the restricted
[own-use terms](LICENSE), configure supported services and apply GitHub updates
manually. This is **source-available software, not MIT/GNU or unrestricted open source**.

Choose a PROMEX annual license when you want the connected service experience:

| Community / self-managed | With an eligible active PROMEX license |
| --- | --- |
| Install without a paid service license | Connect a domain-bound installation to the Hub |
| Operate and customize your own platform | Access entitled protected CEDAR games and services |
| Apply file/database updates manually | Fetch signed patches inside Backup & Update |
| Configure supported providers yourself | Use available PROMEX services included in your plan |
| Maintain your own update procedure | Review patch files, prerequisites and history |

Licensing does not replace hosting, backups, provider accounts or operating
responsibilities. Not every service is necessarily included in every plan.

### Annual launch offer

**$99 USD for an annual license purchased through October 31, 2026.**
**From November 1, 2026, the annual purchase price becomes $239 USD**, alongside
the planned expansion of games and updates.

This offer does not promise a lifetime renewal-price lock. Check the product's
checkout terms for renewal pricing, taxes and the services included.

[Get the annual PROMEX license](https://promex.me/platforms/promex-gaming-suite/)

## Your platform—not a product to resell

The source-use policy permits personal use and operation of your own business's
platform, including your own branding. It does not permit selling or sublicensing
the platform, white-labelling installations for clients, or using covered code to
create a product offered to other operators. An annual hosted-service license
does not grant resale rights. See [LICENSE](LICENSE) for scope and third-party exceptions.

## Legacy games stay on your domain

Legacy slots are operator-hosted under `/games/GameName/`. Obtain and host only
assets you are authorized to use. Legacy assets are not included in the core
package and are not provided through a PROMEX legacy-games CDN. There is no
`.htaccess` or reverse-proxy source switch.

A legacy patch targets one exact game folder and version. Download the reviewed
patch, replace its declared files, then verify the replacement in the backend.
Updating one game does not change every other game's version.

## Smaller updates, clearer control

**Check available patches → Download & review → Install**

- Signed packages contain selected changed files and new migrations when needed,
  rather than a complete application archive every time.
- Core updates enforce their required sequence. Optional features and individual
  games have independent versions and explicit prerequisites.
- Wrong baselines, locally modified files and duplicate installs are checked.
- Installed Patches records results. Customer ZIP uploads are not part of this flow.

An update that fails after mutation can leave the site in maintenance mode for
operator recovery. There is no automatic backup or restore. Read [PATCHES.md](PATCHES.md)
before your first update.

## Backups stay under your control

The manual backup tool exports core code, SQL and patch history. It excludes vendor,
legacy games, CEDAR assets, uploads and environment secrets. Retain your original
`.env`/APP_KEY, uploads, games and matching dependencies separately.

Use a quiet backup window and test recovery. A core backup alone is not a complete,
ready-to-restore website.

## Installation

### Operators

Use a verified installation prepack from an approved release. Prepacks include
PHP dependencies: no Composer command is needed after extraction. GitHub's
automatically generated source ZIP is not an installation prepack.

1. Prepare a fresh site/database with compatible PHP and MySQL or MariaDB.
2. Configure HTTPS; make the application URL match the browser address.
3. Extract the package and open `/install` on a correctly configured web server.
4. Complete the environment/database checks. Leave the license blank for community
   setup, or activate a license for that domain.
5. Change the temporary administrator password immediately and finish installer cleanup.
6. Configure branding, delivery and providers; add only authorized games/services.

The installer checks PHP CLI readiness for managed updates, migrations and cache
cleanup. This is advisory: missing CLI does not block initial installation.
Manual updates remain an alternative with release-specific file/database instructions.

Website PHP and CLI can differ. Configure `PROMEX_PHP_BINARY` when the host's
default CLI is unsuitable. Never copy a Laragon PHP path onto a live server.

### Developers

The application lives in `casino/`. Composer targets PHP `^8.3`; the current
lockfile selects Laravel 13. Keep platform checks enabled and test your runtime;
the version constraint is not certification of every future PHP release.

A Git clone is a source checkout, not necessarily a complete prepack. Do not assume
the general seeder reproduces the curated installer database. Use the setup procedure
for the approved release. Apache `.htaccess` protections do not automatically apply
to Nginx: keep internal code/configuration, SQL, storage and secrets inaccessible.

## Release and rights notice

The product baseline is **2.0.0**, distinct from the historical `v2.0` tag.
The root `VERSION` file records the core version; a release tag identifies the
complete source snapshot. Dependency versions and historical migrations keep
their original identifiers. Existing unversioned legacy game folders use the
2.0.0 baseline; subsequent patches advance only the targeted component.

The licensor of covered PROMEX code is **PROMEX DOT ME INC**. The own-use license
does not revoke rights already granted under earlier licenses or replace
third-party terms. See [LICENSE](LICENSE).

The product is intended for social-gaming and virtual-currency experiences.
Content rights, operating permissions, provider eligibility and compliance remain
the operator's responsibility. No gaming authorization, financial return or
production certification is implied.

---

Built by [PROMEX](https://promex.me) ·
[Product and licensing](https://promex.me/platforms/promex-gaming-suite/) ·
[Project source](https://github.com/promexdotme/casino-gecce)
