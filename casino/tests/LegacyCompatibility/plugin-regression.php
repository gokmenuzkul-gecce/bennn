<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../../vendor/autoload.php';

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use VanguardLTE\Services\LegacyCompatibilityService;

$root = sys_get_temp_dir() . '/promex-legacy-' . bin2hex(random_bytes(8));
$front = $root . '/games';
$back = $root . '/app/Games';
$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    ++$checks;
    echo "PASS: {$label}\n";
};

try {
    mkdir($front . '/ReadyGame', 0700, true);
    mkdir($front . '/FrontOnly', 0700, true);
    mkdir($front . '/HistoricalHtm', 0700, true);
    mkdir($front . '/Bad-Name', 0700, true);
    mkdir($back . '/ReadyGame', 0700, true);
    mkdir($back . '/HistoricalHtm', 0700, true);
    file_put_contents($front . '/ReadyGame/index.html', '<script src="/js/mock-websocket.js"></script>');
    file_put_contents($front . '/FrontOnly/index.html', '<html></html>');
    file_put_contents($front . '/HistoricalHtm/index.htm', '<html></html>');
    file_put_contents($front . '/Bad-Name/index.html', '<html></html>');
    file_put_contents($back . '/ReadyGame/Server.php', '<?php');
    file_put_contents($back . '/HistoricalHtm/Server.php', '<?php');

    $service = new LegacyCompatibilityService($front, $back);
    $found = [];
    foreach ($service->discover() as $row) $found[$row['name']] = $row;
    $check(isset($found['ReadyGame']) && $found['ReadyGame']['backend'] && $found['ReadyGame']['bridge_hint'],
        'scanner reports a matching frontend/backend and adapter hint');
    $check(isset($found['FrontOnly']) && !$found['FrontOnly']['backend'],
        'scanner reports incomplete content without publishing it');
    $check(($found['HistoricalHtm']['entry'] ?? null) === 'index.htm',
        'scanner recognizes historical index.htm packages');
    $check(!isset($found['Bad-Name']), 'scanner rejects unsafe folder names');
    $check(!array_key_exists('content', $found['ReadyGame']), 'scanner returns metadata only');

    $container = new Container();
    Container::setInstance($container);
    Facade::setFacadeApplication($container);
    $db = new Manager($container);
    $db->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'w_']);
    $db->setAsGlobal();
    $db->bootEloquent();
    $container->instance('db', $db->getDatabaseManager());
    $container->bind('db.schema', fn () => $db->schema());
    $container->make('config')->set('legacy.enabled_by_default', false);
    $store = new class {
        public array $values = ['legacy_compatibility_enabled' => '1'];
        public function get($key, $default = null) { return $this->values[$key] ?? $default; }
        public function set($key, $value): void { $this->values[$key] = $value; }
        public function save(): void {}
    };
    $container->instance('anlutro\LaravelSettings\SettingStore', $store);
    $db->schema()->create('games', function (Blueprint $table): void {
        $table->id(); $table->string('name'); $table->string('title'); $table->string('label')->nullable();
        $table->string('source_type')->default('default'); $table->string('delivery_mode')->default('LOCAL');
        $table->text('custom_path')->nullable(); $table->timestamp('legacy_rights_attested_at')->nullable();
        $table->unsignedBigInteger('legacy_rights_attested_by')->nullable(); $table->integer('view')->default(0);
        $table->integer('device')->default(2); $table->integer('shop_id')->default(1); $table->integer('original_id')->default(0);
        $table->timestamps();
    });
    $db->schema()->create('categories', function (Blueprint $table): void {
        $table->id(); $table->string('title'); $table->integer('parent'); $table->integer('position');
        $table->string('href'); $table->integer('original_id'); $table->integer('shop_id');
    });
    $db->schema()->create('game_categories', function (Blueprint $table): void {
        $table->integer('game_id'); $table->integer('category_id');
        $table->unique(['game_id', 'category_id']);
    });
    DB::table('games')->insert(['name' => 'Template', 'title' => 'Template', 'created_at' => now(), 'updated_at' => now()]);
    $imported = $service->import('ReadyGame', true, null, 99);
    $registered = DB::table('games')->where('name', 'ReadyGame')->first();
    $check(($imported[0]['status'] ?? '') === 'created' && $registered->source_type === LegacyCompatibilityService::SOURCE_TYPE
        && (int) $registered->view === 0 && (int) $registered->legacy_rights_attested_by === 99,
        'explicit attested import registers the game disabled with an admin audit');
    $rejected = false;
    try { $service->import('FrontOnly', false); } catch (RuntimeException) { $rejected = true; }
    $check($rejected && DB::table('games')->where('name', 'FrontOnly')->doesntExist(),
        'import without rights attestation is rejected');

    $project = dirname(__DIR__, 3);
    $controller = (string) file_get_contents($project . '/casino/app/Http/Controllers/Web/Liteback/GameController.php');
    $frontend = (string) file_get_contents($project . '/casino/app/Http/Controllers/Web/Frontend/GamesController.php');
    $provider = (string) file_get_contents($project . '/casino/app/Http/Controllers/Web/Frontend/ProviderCompatibilityController.php');
    $routes = (string) file_get_contents($project . '/casino/routes/web.php');
    $adapter = (string) file_get_contents($project . '/casino/app/Console/Commands/InjectMockWebSocket.php');
    $htaccess = (string) file_get_contents($project . '/.htaccess');
    $check(str_contains($controller, 'rights_attested') && str_contains($controller, 'setPluginEnabled')
        && str_contains($controller, 'applyVisibility'), 'admin activation and bulk paths use the plugin gate');
    $check(str_contains($frontend, 'LegacyCompatibilityService::SOURCE_TYPE')
        && str_contains($frontend, 'assertPlayable($legacyGame)')
        && str_contains($provider, 'LegacyCompatibilityService'), 'launch, spin and compatibility routes enforce plugin state');
    $check(!str_contains($frontend, 'requiresCompatibility((string) $game'),
        'existing default legacy rows retain licensed local launch without mandatory plugin migration');
    $check(str_contains($routes, 'legacy-plugin') && str_contains($routes, 'legacy-import'),
        'plugin enable and explicit import routes are registered');
    $check(str_contains($adapter, "{game* : Registered Legacy Compatibility game names}")
        && str_contains($adapter, '.promex-original') && !str_contains($adapter, '--dir=')
        && str_contains($htaccess, 'RewriteRule ^js/mock-websocket\\.js$ js/promex-legacy-bridge.js'),
        'adapter injection is explicit, recoverable, and uses the same-origin bridge alias');
    $check(configFileDefaultIsOff($project . '/casino/config/legacy.php'), 'clean-install configuration defaults the plugin off');

    echo "PASS: {$checks} Legacy Compatibility plugin checks\n";
} finally {
    Facade::clearResolvedInstances();
    removeFixtureTree($root);
}

function configFileDefaultIsOff(string $path): bool
{
    $source = (string) file_get_contents($path);
    return str_contains($source, "env('PROMEX_LEGACY_COMPATIBILITY', false)");
}

function removeFixtureTree(string $root): void
{
    if (!is_dir($root)) return;
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($root);
}
