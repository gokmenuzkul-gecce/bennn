<?php
declare(strict_types=1);
require __DIR__ . '/../../vendor/autoload.php';

use Symfony\Component\HttpFoundation\File\UploadedFile;
use VanguardLTE\Services\PatchPackage;

$kit = dirname(__DIR__, 3) . '/localscripts/dist/updater-practice-20260927-082517';
$key = file_get_contents($kit . '/practice-public.pem');
$upload = new class($kit . '/practice-3.zip', 'practice-3.zip', 'application/zip', UPLOAD_ERR_OK, true) extends UploadedFile {
    public function getRealPath(): string|false { return false; }
};
$manifest = PatchPackage::inspect($upload->getPathname(), $key);
try {
    PatchPackage::eligibility($manifest, [], dirname(__DIR__, 3));
    throw new LogicException('Out-of-order practice patch was accepted.');
} catch (RuntimeException $e) {
    echo 'PASS: Unresolved realpath still reads signed ZIP; eligibility blocks: ' . $e->getMessage() . PHP_EOL;
}
foreach (['', $kit . '/missing.zip'] as $path) {
    try {
        PatchPackage::inspect($path, $key);
        throw new LogicException('Missing path was accepted.');
    } catch (RuntimeException $e) {
        if (!str_contains($e->getMessage(), 'missing or unreadable')) throw $e;
        echo 'PASS: Missing upload receives actionable error.' . PHP_EOL;
    }
}
