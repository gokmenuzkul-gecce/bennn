<?php
/**
 * Localize game cover art.
 *
 * Games whose icon_url points at a provider CDN make the lobby issue 1,400+
 * cross-origin image requests, which is slow and depends on the vendor's
 * hotlink policy. Download each cover once, crop it to the lobby's 3:4 card
 * ratio, and store it under frontend/Default/ico/<game>.jpg so the DB can
 * serve a same-origin path.
 *
 * Usage: php scripts/localize_game_images.php [--force] [--limit=N] [--dry-run]
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$icoDir = '/workspace/project/frontend/Default/ico';
$force = in_array('--force', $argv, true);
$dryRun = in_array('--dry-run', $argv, true);
$limit = 0;
foreach ($argv as $arg) {
    if (strpos($arg, '--limit=') === 0) {
        $limit = (int) substr($arg, 8);
    }
}

$q = DB::table('games')
    ->where('view', 1)
    ->whereNotNull('icon_url')
    ->where('icon_url', '!=', '')
    ->where('icon_url', 'like', 'http%');

if (!$force) {
    // Skip games whose local cover already exists; file presence is the source of truth.
    $games = $q->get(['id', 'name', 'icon_url'])->filter(function ($g) use ($icoDir) {
        $p = $icoDir . '/' . $g->name . '.jpg';
        return !(is_file($p) && @getimagesize($p));
    })->values();
} else {
    $games = $q->get(['id', 'name', 'icon_url']);
}
if ($limit > 0) {
    $games = $games->take($limit);
}

echo 'Islenecek oyun: ' . count($games) . PHP_EOL;

function fetchBytes(string $url, int $timeout = 12): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body !== false && $code >= 200 && $code < 300 && strlen($body) > 256) {
            return $body;
        }
        return null;
    }
    $body = @file_get_contents($url, false, stream_context_create([
        'http' => ['timeout' => $timeout, 'user_agent' => 'Mozilla/5.0'],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]));
    return ($body !== false && strlen($body) > 256) ? $body : null;
}

/** Crop-to-fill $bytes into a 3:4 JPEG at $dest. Returns true on success. */
function saveCard(string $bytes, string $dest): bool
{
    $img = @imagecreatefromstring($bytes);
    if (!$img) {
        return false;
    }
    $w = imagesx($img);
    $h = imagesy($img);
    if ($w < 1 || $h < 1) {
        imagedestroy($img);
        return false;
    }

    $targetW = 300;
    $targetH = 400;
    $targetRatio = $targetW / $targetH;      // 0.75
    $srcRatio = $w / $h;

    if ($srcRatio > $targetRatio) {
        // too wide -> crop sides
        $cropH = $h;
        $cropW = (int) round($h * $targetRatio);
        $srcX = (int) round(($w - $cropW) / 2);
        $srcY = 0;
    } else {
        // too tall -> crop top/bottom
        $cropW = $w;
        $cropH = (int) round($w / $targetRatio);
        $srcX = 0;
        $srcY = (int) round(($h - $cropH) / 2);
    }

    $dst = imagecreatetruecolor($targetW, $targetH);
    imagecopyresampled($dst, $img, 0, 0, $srcX, $srcY, $targetW, $targetH, $cropW, $cropH);
    // Progressive + optimised so the phone can paint a preview before the whole
    // file lands; quality 80 is visually lossless at card size.
    imageinterlace($dst, true);
    $ok = imagejpeg($dst, $dest, 80);
    imagedestroy($dst);
    imagedestroy($img);
    return (bool) $ok;
}

if (!is_dir($icoDir)) {
    @mkdir($icoDir, 0775, true);
}

$done = 0;
$failed = 0;
$skipped = 0;
$failedUrls = [];

foreach ($games as $g) {
    $dest = $icoDir . '/' . $g->name . '.jpg';
    if (!$force && is_file($dest) && @getimagesize($dest)) {
        $skipped++;
        continue;
    }
    if ($dryRun) {
        echo "  [dry] {$g->name} <- {$g->icon_url}" . PHP_EOL;
        continue;
    }
    $bytes = fetchBytes($g->icon_url);
    if ($bytes === null || !saveCard($bytes, $dest)) {
        $failed++;
        $failedUrls[] = $g->name . ' <- ' . $g->icon_url;
        continue;
    }
    $done++;
    if ($done % 50 === 0) {
        echo "  ...$done indirildi" . PHP_EOL;
    }
}

echo PHP_EOL . "Indirildi: $done | Atlandi: $skipped | Basarisiz: $failed" . PHP_EOL;
if ($failedUrls) {
    echo "Basarisiz URL'ler (ilk 20):" . PHP_EOL;
    foreach (array_slice($failedUrls, 0, 20) as $u) {
        echo "  $u" . PHP_EOL;
    }
}
