# V2.0.0 backup and incremental updates

## Release policy (2026-09-27)

Routine updates are signed, incremental batches only. The former full-package
`/api/service/version` endpoint is retired (HTTP410, full_updates_retired).
Use `/api/service/updates` and its authenticated download route. Full fresh-install
prepacks are separate purchase/install artifacts, not the update mechanism.
Manual operator backup/restore remains available; no automatic full backup/update.

Legacy component identity is `legacy:ExactFolderName`. An existing game folder
without recorded patch history is automatically version2.0.0; an absent folder is
0.0.0. No version file needs to be injected into every game. This is already enforced
by PatchPackage::eligibility. It is a version convention, not proof of file identity:
patches still require matching before-hashes and declared prerequisite patches.
After successful manual replacement verification, only that game's version advances.

Our one-time VPS legacy asset baseline uses games/ → /var/www/html/games, with
private file-hash manifests under localscripts/VPS/legacy-baselines/. Back up changed
remote files; upload differences, preserve remote-only files, exclude generated ZIPs,
backup files and unfinished downloads. This does not switch customer games to CDN.
Customers continue hosting their own games/GameName assets. Future approved game
changes become signed legacy patches, published through the licensed batch catalog.

The product baseline is 2.0.0. Historical Git tags are not moved.
Legacy assets are operator-owned under `games/`. Cedar presentation and protected
math stay on the Hub/CDN. The prepack includes PHP dependencies for installation;
ordinary delta patches and manual backups exclude `vendor/`.

## Fresh installation and manual backup

1. Extract the prepack into a new empty Laragon site folder. Use a new empty MySQL
   database, not the development site's database. Visit `/install`.
2. Leave License blank for a community installation, or activate a license valid
   for this hostname. Login with `admin` / `123456` and choose your own password.
3. Open Liteback → Backup & Update, the final navigation item below Store & License.
   Without a license the page links to free GitHub updates. Manual backup is available.
4. Test Create backup and download the ZIP. It includes core code and a SQL export,
   plus patch history when present. Separately retain your `.env` (especially
   APP_KEY), uploads and legacy games. Database encrypted settings need the original
   APP_KEY. Restore and backup remain operator responsibilities.

## Practice patches (local only)

Use a separate test database. Copy the supplied `practice-public.pem` somewhere
outside the site's public directory. In the test site's `casino/.env` set:

```dotenv
APP_ENV=local
PROMEX_PATCH_DEMO=true
PROMEX_PATCH_DEMO_PUBLIC_KEY_FILE="C:/path/to/practice-public.pem"
```

Run `php artisan config:clear` from the test site's `casino` folder. APP_URL must
end in `.test`. An active license and valid Hub installation credential are also
required. The Hub's private `updates/practice-domains.json` allowlist must include
the authenticated installation domain. Practice mode never bypasses licensing.

1. Click Check available patches. Fetch Practice step 3 first: review must block it.
2. Fetch Practice step 1: review, then install. Adds a practice text file.
3. Fetch Practice step 2: review, then install. Changes that file and creates
   the empty `promex_update_demo` table (with the installation's DB prefix).
4. Fetch Practice step 3: install, then fetch it again. Duplicate is blocked.
5. View Installed Patches. The core version remains 2.0.0 throughout.

Practice patches use the same verifier, lock, installer, migration and history code.
For live installation extract a fresh prepack and use a fresh database. Do not
carry the practice settings or practice database into the demo site.

## Real patch workflow

Commit and review the baseline before publishing a new `v2.0.0` tag. Existing
`v2.0` history is separate and must not be moved. A Git tag identifies source;
it does not stamp each file. Operator-supplied legacy games need separate source
snapshots because `games/` is Git-ignored.

Export the before/after source snapshots of reviewed commits into separate folders.
Use `git diff --name-status BASE TARGET` to select intended changed files. Create
a recipe (all fields required):

```json
{
  "id": "patch-core-2-0-1",
  "component": "core",
  "from": "2.0.0",
  "to": "2.0.1",
  "title": "First core patch",
  "notes": "Describe the user-visible changes.",
  "requires": [],
  "files": ["VERSION", "casino/app/Example.php"],
  "delete": [],
  "migrations": []
}
```

Run `php tools/packaging/build_patch.php BEFORE AFTER recipe.json PRIVATE_KEY patch-core-2-0-1.zip`.
Use the authorized release signing key matching the installation's trust anchor;
never commit or package that private key. The builder computes before/after SHA-256
hashes and signs the manifest. Existing output files are not overwritten.
Core patches include the resulting VERSION file. New migrations must be listed
in both files and migrations; historical migrations cannot be edited or removed.
Database-only feature patches may contain only their new migration.

Publish reviewed core source/tags/releases to GitHub; publish signed `patch-*.zip`
artifacts to the Hub's private `updates/stable/` directory. Use `updates/practice/`
only for allowlisted local tests. `/api/service/updates?channel=stable` lists releases;
`/api/service/updates/download?channel=stable&patch=patch-SLUG` fetches a package.
Both endpoints require replay-resistant, domain-bound active-license authentication
and send no-store headers. No public ZIP URLs or customer upload route are provided.
The operator clicks Check available patches, Download & review, then Install.
Only legacy patches retain a browser download for the agreed manual replacement flow.
Hosting access is controlled, but customers can still copy files on their own server.

Components: `core`, `feature:slug`, `cedar:slug`, `legacy:GameName`.
Core uses VERSION; optional components start at 0.0.0; existing legacy folders
start at 2.0.0. Prerequisites list exact successful patch IDs. File hashes additionally
block overwriting a locally customized/wrong baseline file. Cedar patches contain
only local integrations/registration, never protected game assets.

Legacy patches contain files under `files/games/GameName/`. The operator downloads,
copies them into the matching local folder and removes explicitly listed obsolete
files. Verify my manual replacement checks every packaged hash and removal before
recording the new version. Other games keep their existing versions.

## Failure and recovery

Installation obtains an exclusive lock, rechecks the signed archive and prerequisites,
records installing, enables maintenance mode, writes the declared changes and runs
only declared migrations in fresh PHP processes. It checks Laravel route boot before
recording success and reopening. No automatic backup, restore or Cedar catalog sync.

If a mutation fails, the site stays in maintenance mode and the failed/installing
history blocks later patches. Restore the matching code and database manually.
Restore `patch-history.json` from the backup to
`casino/storage/app/patch-manager/history.json`; if the baseline backup has none,
remove the newer history only after restoring that baseline. Then run
`php artisan optimize:clear` and `php artisan up`. Do not mark a failed patch successful
by editing its history. Terminated processes leave installing state for the same reason.

MVP limits: single application server, MySQL/MariaDB manual backup, 128 MB compressed /
256 MB expanded patches, synchronous requests (hosting upload/execution limits apply).
Quiesce external workers/cron for the maintenance window. Composer dependency changes
require a separately planned deployment; this MVP cannot install vendor changes.
No automatic full-site restore or browser recovery wizard is provided.

On shared hosting, configure `PROMEX_PHP_BINARY` to the host's PHP CLI executable
and `PROMEX_MYSQLDUMP_BINARY` to its database dump executable if the defaults are
unavailable. Back up during a quiet period; the manual backup does not pause player
activity or external workers.
