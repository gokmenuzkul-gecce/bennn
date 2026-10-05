<?php

declare(strict_types=1);

/**
 * Guards the Minimal header layout.
 *
 * The primary navigation lives in its own full-width bar; the decorative
 * banner art that used to sit underneath it was removed, so the bar must be
 * the header's only band and the drawer must follow it.
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

    'the decorative banner art has been removed from the header' => !str_contains($header, 'site-header-banner')
        && !str_contains($header, 'header-banner.jpg'),

    'banner CSS is gone from the layout' => !str_contains($blade, '.site-header-banner'),

    'centre brand is the supplied logo image, not the old wordmark' => str_contains($header, 'class="site-brand-center"')
        && str_contains($header, '$hBrandMark')
        && !str_contains($header, 'site-brand-wordmark')
        && !str_contains($blade, '.site-brand-wordmark'),

    'brand logo carries a subtle animation with reduced-motion fallback' => str_contains($blade, '@keyframes brandFloat')
        && str_contains($blade, '@keyframes brandGlow')
        && str_contains($blade, 'animation:')
        && str_contains($blade, 'brandFloat 7s ease-in-out .7s infinite')
        && str_contains($blade, 'prefers-reduced-motion: reduce'),

    'brand logo has a one-time intro and an orbiting hover ring' => str_contains($blade, '@keyframes brandIntro')
        && str_contains($blade, 'brandIntro .7s cubic-bezier')
        && str_contains($blade, '@keyframes brandRing')
        && str_contains($blade, 'conic-gradient(from 0deg')
        && str_contains($blade, '.site-brand-ring')
        && str_contains($header, 'site-brand-ring'),

    'brand logo springs larger and glows on hover/focus' => str_contains($blade, 'scale(1.16)')
        && str_contains($blade, 'cubic-bezier(.34,1.56,.64,1)')
        && str_contains($blade, 'animation: brandRing 2.6s linear infinite')
        && str_contains($blade, 'animation: brandGlow 3.5s ease-in-out infinite'),

    'brand logo asset is present and cache-busted' => is_file($root . '/../minimal/brand-logo.png')
        && str_contains($header, 'brand-logo.png?v='),

    'hamburger sits inside the top nav bar' => str_contains($header, 'id="btn-open-site-drawer"')
        && strpos($header, 'site-header-nav') < strpos($header, 'id="btn-open-site-drawer"')
        && strpos($header, 'id="btn-open-site-drawer"') < strpos($header, 'id="site-drawer"'),

    'nav bar is a flex row with the brand centred' => str_contains($blade, '.site-header-nav {')
        && str_contains($blade, 'grid-template-columns: 1fr auto 1fr;'),

    'desktop reveals the horizontal link rows' => str_contains($blade, '.site-nav-left .site-nav-link { display: inline-block; }')
        && str_contains($blade, '.site-nav-right { display: flex; }'),

    'auth buttons live in the top-right nav cluster' => str_contains($header, 'site-nav-cta site-nav-cta--ghost open-modal" data-target="modal-login"')
        && str_contains($header, 'site-nav-cta site-nav-cta--primary open-modal" data-target="modal-register"')
        && strpos($header, 'site-nav-right') < strpos($header, 'data-target="modal-login"'),

    'register CTA reads "Kayıt Ol", not the old "Ücretsiz Kayıt Ol"' => str_contains($header, '>Kayıt Ol</button>')
        && !str_contains($header, 'Ücretsiz Kayıt Ol'),

    'no duplicate account row is emitted below the header' => !str_contains($blade, 'Desktop top-right account area')
        && substr_count($blade, 'id="account-menu-toggle"') === 0,

    'guest nav offers a top-right admin login button' => str_contains($header, 'site-nav-cta--admin open-modal" data-target="modal-admin-login"')
        && strpos($header, 'site-nav-right') < strpos($header, 'data-target="modal-admin-login"'),

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
