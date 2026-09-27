<?php
require __DIR__ . '/../../vendor/autoload.php';
use VanguardLTE\Support\InstallerPhpCli;
$root = dirname(__DIR__, 3);
$good = InstallerPhpCli::check($root, PHP_BINARY);
if (!$good['ok'] || !is_file($good['binary'])) throw new RuntimeException('Working CLI rejected');
$bad = InstallerPhpCli::check($root, $root . '/missing-php-executable');
if ($bad['ok']) throw new RuntimeException('Missing CLI accepted');
echo "PASS: working CLI accepted and resolved; unavailable CLI blocked\n";
