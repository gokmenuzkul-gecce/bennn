# Install PROMEX Gaming Suite 2.0.0

## Requirements

- PHP 8.3 or later compatible with the locked dependencies, including PDO MySQL,
  cURL, OpenSSL, mbstring, XML/DOM, ZIP, BCMath and Composer-required extensions.
- MySQL or MariaDB and a fresh, empty database.
- HTTPS; Apache 2.4 with mod_rewrite and AllowOverride enabled for supplied routing.
- Writable installer configuration and Laravel storage/bootstrap cache directories.

PHP constraints do not certify every future PHP release. Website PHP and CLI PHP
must satisfy dependencies for managed updates. CLI readiness is advisory during
installation; manual updates remain possible without it.

## Complete installation prepack

1. Extract the approved prepack into a fresh site directory. It includes vendor
   dependencies and sanitized install.sql. No Composer command is required.
2. Configure HTTPS and point Apache at the extracted root. Enable the supplied
   .htaccess protections before exposing the installer.
3. Open `/install`, enter the empty database connection and complete the checks.
4. Leave the license blank for community use, or activate a domain license.
5. Change the temporary administrator password immediately. Complete installer
   cleanup; resolve permission errors and retry cleanup before going public.
6. Configure branding, delivery, providers and authorized games.

Keep APP_ENV=production and APP_DEBUG=false. Never enable development OTP preview
on a public installation. License activation alone does not prove delivery;
verify your delivery configuration.

Nginx does not read .htaccess. Its configuration must reproduce the root routing
and asset layout and deny internal application directories, SQL, archives,
environment files, storage, keys and tools. Do not expose the repository root
with a generic unrestricted PHP handler.

## GitHub source checkout

GitHub source ZIPs and Git clones are not dependency-bundled prepacks. Developers
install dependencies before using the same fresh-database installer:

```sh
git clone https://github.com/promexdotme/laravel-social-gaming.git
cd laravel-social-gaming/casino
composer install --no-dev --optimize-autoloader
```

Keep platform checks enabled. Root install.sql is a curated public template, not
a production backup. Do not substitute `migrate --seed` for the installer: generic
seeders do not reproduce the curated baseline.

## Games, services and updates

Legacy assets are supplied separately and hosted under your own
`games/ExactGameName/`. There is no legacy CDN or reverse proxy. Customer handlers
and views are included; protected CEDAR assets and engines remain on the licensed Hub.

Managed services default to https://clients.377.live. Do not carry local .test
service URLs or Laragon PHP paths to production. If needed, configure
PROMEX_PHP_BINARY with that host's compatible PHP CLI executable.

Activate optional services under **Store & License**. **Backup & Update** fetches
signed incremental patches for eligible installations; community operators apply
GitHub changes and relevant migrations manually. See [PATCHES.md](PATCHES.md).
Backups and restoration remain your responsibility.

Never run the installer or import install.sql over an existing installation.
Preserve its .env/APP_KEY, database, storage, uploads, games and installation lock.
