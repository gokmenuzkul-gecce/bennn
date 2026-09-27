# Development checks

Run from the repository root with compatible PHP 8.3+, Composer dependencies installed under `casino/vendor`, and Node.js:

```sh
php casino/tests/Installer/cleanup-regression.php
php casino/tests/Licensing/regression.php
node casino/tests/Licensing/bridge-regression.cjs
php casino/tests/Licensing/packager-regression.php
```

On Windows, test the real access rules against an installed Apache distribution in an isolated loopback-only instance:

```sh
python casino/tests/Installer/apache-access-regression.py C:/path/to/apache
```

The public legacy protocol adapter is `js/promex-legacy-bridge.js`. Historical
mock-websocket script URLs map to this adapter through the supplied Apache rules.
Licensing and hosted-service authorization are enforced separately by PHP/the Hub.

```sh
node casino/tests/Licensing/bridge-regression.cjs
```

Validate a completed customer release ZIP against the current checkout:

```sh
php tools/packaging/verify_prepack.php /path/to/release.zip
```

The verifier rejects backups, private runtime files and stale security files. Build tooling, test fixtures, dependencies for compiling WASM, and authority private keys must not be shipped in customer packages. The local database-export/repack workflow remains private and is not part of these checks.

Protected CEDAR assets and server engines are not in this checkout. Tests that
explicitly require private build tooling or those assets are internal checks, not
standalone community tests. Customer code remains inspectable; hosted access is
controlled through domain-bound credentials and service entitlements.

New first-party HTML games load `js/promex-html-game.js`, which establishes the iframe runtime in the required order and exposes the small `PromexHtmlGame.request()` API. Vendor slot packages keep their existing historical socket-script references.
