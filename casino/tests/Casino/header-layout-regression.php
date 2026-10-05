<?php

declare(strict_types=1);

/**
 * Guards the Minimal header layout.
 *
 * The primary navigation must sit in its own bar above the brand banner, not
 * overlaid across the middle of the artwork, and the banner must be tall
 * enough (and framed on the model) that she stays in frame instead of being
 * cropped out by a short strip.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
$read = static function (string $path) use ($root): string {
    $value = file_get_contents($root . '/' . $path);
    if (!is_string($value)) {
        throw new RuntimeException('Unable to read ' . $path);
    }
    return $value;
};

$blade = $read('resources/views/frontend/Minimal/layouts/clean.blade.php');
$header = $read('resources/views/frontend/Minimal/partials/site-header.blade.php');
$modals = $read('resources/views/frontend/Minimal/partials/modals.blade.php');

$checks = [
    'header partial renders a dedicated nav bar' => str_contains($header, 'class="site-header-nav"'),

    'nav bar is emitted before the banner art' => strpos($header, 'site-header-nav') !== false
        && strpos($header, 'site-header-nav') < strpos($header, 'class="site-header-banner"'),

    'hamburger sits inside the top nav bar' => str_contains($header, 'id="btn-open-site-drawer"')
        && strpos($header, 'site-header-nav') < strpos($header, 'id="btn-open-site-drawer"')
        && strpos($header, 'id="btn-open-site-drawer"') < strpos($header, 'class="site-header-banner"'),

    'nav bar is a flex row with the brand centred' => str_contains($blade, '.site-header-nav {')
        && str_contains($blade, 'grid-template-columns: 1fr auto 1fr;'),

    'banner spans the full width and scales with the site width' => str_contains($blade, 'aspect-ratio: 1345 / 458;')
        && str_contains($blade, 'max-height: 360px;'),

    'banner crops around the subject rather than the vertical centre' => str_contains($blade, 'object-position: center 38%;'),

    'mobile keeps a readable strip without losing the subject' => str_contains($blade, 'aspect-ratio: 16 / 9;')
        && str_contains($blade, 'object-position: center 42%;'),

    'desktop reveals the horizontal link rows' => str_contains($blade, '.site-nav-left .site-nav-link { display: inline-block; }')
        && str_contains($blade, '.site-nav-right { display: flex; }'),

    'auth buttons live in the top-right nav cluster' => str_contains($header, 'site-nav-cta site-nav-cta--ghost open-modal" data-target="modal-login"')
        && str_contains($header, 'site-nav-cta site-nav-cta--primary open-modal" data-target="modal-register"')
        && strpos($header, 'site-nav-right') < strpos($header, 'data-target="modal-login"')
        && strpos($header, 'data-target="modal-login"') < strpos($header, 'class="site-header-banner"'),

    'register CTA reads "Kayıt Ol", not the old "Ücretsiz Kayıt Ol"' => str_contains($header, '>Kayıt Ol</button>')
        && !str_contains($header, 'Ücretsiz Kayıt Ol'),

    'no duplicate account row is emitted below the banner' => !str_contains($blade, 'Desktop top-right account area')
        && substr_count($blade, 'id="account-menu-toggle"') === 0,

    'guest nav offers a top-right admin login button' => str_contains($header, 'site-nav-cta--admin open-modal" data-target="modal-admin-login"')
        && strpos($header, 'site-nav-right') < strpos($header, 'data-target="modal-admin-login"')
        && strpos($header, 'data-target="modal-admin-login"') < strpos($header, 'class="site-header-banner"'),

    'admin login modal exists with its own form' => str_contains($modals, 'id="modal-admin-login"')
        && str_contains($modals, 'id="admin-login-form"')
        && str_contains($modals, "route('frontend.auth.login.post')"),

    'admin form posts to the operator console' => str_contains($modals, 'name="to" value="{{ url(\'/liteback\') }}"')
        && str_contains($modals, "window.location.href = '/liteback';"),

    'signed-in admins get a console link in the nav' => str_contains($header, "(int) Auth::user()->role_id === 6")
        && str_contains($header, "route('liteback.users.index')"),
];

foreach ($checks as $name => $passed) {
    if (!$passed) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    echo 'PASS: ' . $name . PHP_EOL;
}

echo 'PASS: ' . count($checks) . ' minimal header layout checks' . PHP_EOL;
