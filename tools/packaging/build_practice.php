<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/build_patch.php';
$output = $argv[1] ?? (__DIR__ . '/../../localscripts/dist/updater-practice-' . gmdate('Ymd-His'));
if (file_exists($output)) throw new RuntimeException('Choose a new output directory.');
mkdir($output, 0700, true);
$options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
if ($config = getenv('OPENSSL_CONF')) $options['config'] = $config;
$key = openssl_pkey_new($options);
if (!$key || !openssl_pkey_export($key, $private, null, $options)) throw new RuntimeException('Cannot generate practice key. Configure OPENSSL_CONF for this PHP installation.');
file_put_contents($output . '/practice-public.pem', openssl_pkey_get_details($key)['key']);
// Private fixture key exists only in process memory and is discarded after building.
$work = $output . '/snapshots'; mkdir($work . '/v0', 0700, true);
for ($i = 1; $i <= 3; $i++) {
    $version = $work . '/v' . $i; mkdir($version . '/public', 0700, true);
    file_put_contents($version . '/public/promex-update-demo.txt', "Practice step {$i}\n");
    $files = ['public/promex-update-demo.txt']; $migrations = [];
    if ($i === 2) {
        $path = 'casino/database/migrations/2099_01_01_000001_promex_update_demo.php';
        mkdir($version . '/casino/database/migrations', 0700, true);
        file_put_contents($version . '/' . $path, '<?php return new class extends \\Illuminate\\Database\\Migrations\\Migration { public function up(): void { \\Illuminate\\Support\\Facades\\Schema::create("promex_update_demo", function ($table) { $table->id(); $table->string("message"); }); } public function down(): void { \\Illuminate\\Support\\Facades\\Schema::dropIfExists("promex_update_demo"); } };');
        $files[] = $path; $migrations[] = $path;
    }
    $recipe = ['id' => 'practice-' . $i, 'component' => 'demo', 'from' => '0.0.' . ($i-1), 'to' => '0.0.' . $i,
        'title' => 'Practice step ' . $i, 'notes' => $i === 2 ? 'File change and a dedicated empty practice database table.' : 'Changes only the practice text file.',
        'requires' => $i > 1 ? ['practice-' . ($i-1)] : [], 'files' => $files, 'delete' => [], 'migrations' => $migrations];
    buildPromexPatch($work . '/v' . ($i-1), $version, $recipe, $private, $output . '/practice-' . $i . '.zip');
}
$kit = new ZipArchive(); $kit->open($output . '/practice-kit.zip', ZipArchive::CREATE | ZipArchive::EXCL);
foreach (['practice-public.pem', 'practice-1.zip', 'practice-2.zip', 'practice-3.zip'] as $name) $kit->addFile($output . '/' . $name, $name);
$kit->addFromString('START-HERE.txt', "See PATCHES.md in your prepack. Local .test installation only. Set APP_ENV=local, PROMEX_PATCH_DEMO=true and PROMEX_PATCH_DEMO_PUBLIC_KEY_FILE to the absolute path of practice-public.pem; run php artisan config:clear. Open Liteback > Backup & Update. Upload practice-3 first to see it blocked, then install 1, 2, 3 in order. Step 2 creates an empty practice table. Core VERSION remains 2.0.0. Use a fresh prepack/database for your later live demo.\n");
if (!$kit->close()) throw new RuntimeException('Cannot finalize practice kit.');
echo "Practice bundle: {$output}\nPrivate fixture signing key discarded.\n";
