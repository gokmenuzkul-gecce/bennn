<?php
declare(strict_types=1);
set_time_limit(0);
$root = realpath($argv[1]); $practice = realpath($argv[2]);
if (!str_contains(str_replace('\\', '/', $root), '/.skills-backup/prepack-qa-')) throw new RuntimeException('Isolated QA directory required.');
require $root . '/casino/vendor/autoload.php';
// Isolated installer test only: live authentication is covered by Hub auth/hosted-fetch tests.
class UpdaterTestLicense {
    public const PROMEX_PUBLIC_KEY = 'isolated-test-no-live-verification';
    public const DEFAULT_SERVER = 'https://example.invalid/api/service';
    public static bool $active = true;
    public static function isLicensed(): bool { return self::$active; }
    public static function canPlayGame(string $game): bool { return self::$active; }
    public static function getStatus(): array { return ['status' => self::$active ? 'active' : 'unverified', 'plan' => 'QA fixture']; }
}
class_alias(UpdaterTestLicense::class, 'VanguardLTE\\Services\\LicenseService');
$app = require $root . '/casino/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class); $kernel->bootstrap();
use VanguardLTE\Services\PatchManager;
use VanguardLTE\Services\ManualBackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$check = function ($condition, $name) { if (!$condition) throw new RuntimeException('FAIL: ' . $name); echo 'PASS: ' . $name . PHP_EOL; };
$manager = new PatchManager();
$check($manager->demo(), 'Local practice gate enabled');
$check(VanguardLTE\Services\DeliveryGatewaySettings::provider('whatsapp') === 'promex'
    && !(new VanguardLTE\Services\WhatsAppService())->isDevelopmentMode(), 'Fresh prepack defaults to real PROMEX delivery, not simulation');
$check((bool) DB::table('users')->where('id', 1)->value('must_change_password'), 'Fresh admin must change password');
$check(Schema::hasTable('cache_locks'), 'Clean installer applies missing cache lock migration');
$controller = new VanguardLTE\Http\Controllers\Web\Liteback\MaintenanceController();
$admin = VanguardLTE\User::find(1); Illuminate\Support\Facades\Auth::setUser($admin);
$profileHtml = app(VanguardLTE\Http\Controllers\Web\Liteback\ProfileController::class)
    ->editPassword(app(VanguardLTE\Services\AccountPhoneVerification::class))->render();
$check(str_contains($profileHtml, 'Phone verification is optional') && str_contains($profileHtml, 'No license is required to complete password setup'), 'Fresh account screen explains password-first setup and optional phone verification');
$check(class_exists(VanguardLTE\Games\DayofDead\SlotSettings::class) && class_exists(VanguardLTE\Games\DayofDead\Server::class), 'Packaged legacy PHP engines autoload');
// Legacy server files set their own request timeout while being loaded.
set_time_limit(0);
$check(view()->exists('frontend.games.list.DayofDead'), 'Packaged legacy launch view exists');
$launchRequest = Illuminate\Http\Request::create('/game/DayofDead');
$launchRequest->setLaravelSession(app('session')->driver());
$launch = (new VanguardLTE\Http\Controllers\Web\Frontend\GamesController())->go($launchRequest, 'DayofDead');
$launchHtml = $launch instanceof Illuminate\Contracts\View\View ? $launch->render() : $launch->getContent();
$check(str_contains($launchHtml, '/games/DayofDead/'), 'DayofDead real controller launch renders operator-local game assets');
$check($kernel->call('view:cache') === 0, 'All packaged view namespaces compile');
$request = Illuminate\Http\Request::create('/liteback/maintenance');
$request->setLaravelSession(app('session')->driver());
view()->share('errors', new Illuminate\Support\ViewErrorBag());
$html = $controller->index($request, $manager)->render();
$check(str_contains($html, 'Local practice mode') && str_contains($html, 'Backup &amp; Update'), 'Practice page renders with final sidebar link');
config(['patches.demo' => false]);
UpdaterTestLicense::$active = false;
$html = $controller->index($request, $manager)->render();
$check(str_contains($html, 'Get free GitHub updates') && !str_contains($html, '1. Upload &amp; review'), 'Unlicensed page shows GitHub and hides managed controls');
config(['patches.demo' => true]);
UpdaterTestLicense::$active = true;
$middleware = $controller->getMiddleware()[0]['middleware'];
$admin->role_id = 1;
try { $middleware($request, fn () => true); $deniedRole = false; } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) { $deniedRole = $e->getStatusCode() === 403; }
$check($deniedRole, 'Non-administrator denied maintenance operations'); $admin->role_id = 6;
$check($manager->preview($practice . '/practice-3.zip')['blocked'] !== null, 'Cannot skip practice prerequisites');
$backup = (new ManualBackupService())->create();
$zip = new ZipArchive(); $zip->open(storage_path('app/manual-backups/' . $backup));
$check($zip->locateName('database.sql') !== false && $zip->locateName('VERSION') !== false, 'Manual backup contains SQL and core');
for ($i=0; $i<$zip->numFiles; $i++) if (preg_match('#(^|/)(vendor|games|\.env)(/|$)#', $zip->getNameIndex($i))) throw new RuntimeException('Forbidden backup content');
$zip->close(); $check(true, 'Manual backup excludes vendor games and environment');
foreach ([1,2,3] as $step) {
    $stage = $manager->stage($practice . '/practice-' . $step . '.zip');
    $check($stage['blocked'] === null, 'Practice step ' . $step . ' eligible');
    $manager->install($stage['token'], $stage['sha256']);
    $check($manager->state()['versions']['demo'] === '0.0.' . $step, 'Practice step ' . $step . ' applied and recorded');
}
$check(Schema::hasTable('promex_update_demo'), 'Actual declared migration ran in isolated database');
$check(trim(file_get_contents($root . '/public/promex-update-demo.txt')) === 'Practice step 3', 'Actual file replacements completed');
$check(trim(file_get_contents($root . '/VERSION')) === '2.0.0', 'Core baseline unchanged by practice');
$check($manager->preview($practice . '/practice-3.zip')['blocked'] !== null, 'Duplicate install blocked');
$check(!is_file(storage_path('framework/down')), 'Successful install reopens site');
// Server-side authorization remains independent of whether the UI shows buttons.
config(['patches.demo' => false]);
UpdaterTestLicense::$active = false;
try { $manager->stage($practice . '/practice-1.zip'); $denied = false; } catch (RuntimeException) { $denied = true; }
$check($denied, 'Unlicensed patch staging rejected server-side');
config(['patches.demo' => true]);
try { $manager->stage($practice . '/practice-1.zip'); $denied = false; } catch (RuntimeException) { $denied = true; }
$check($denied, 'Practice mode cannot bypass licensing');
UpdaterTestLicense::$active = true;
$check($kernel->call('schedule:list') === 0, 'Scheduler enumerates in fresh installation');
// A real migration failure must leave failed history and maintenance mode, with no automatic restore.
require __DIR__ . '/../../../tools/packaging/build_patch.php';
$fixture = $root . '/failure-fixture'; mkdir($fixture . '/before', 0700, true); mkdir($fixture . '/after/casino/database/migrations', 0700, true);
$path = 'casino/database/migrations/2099_01_01_000002_promex_update_demo.php';
file_put_contents($fixture . '/after/' . $path, '<?php return new class extends \\Illuminate\\Database\\Migrations\\Migration { public function up(): void { throw new \\RuntimeException("Intentional isolated QA failure"); } };');
$options = ['private_key_bits' => 2048]; if (getenv('OPENSSL_CONF')) $options['config'] = getenv('OPENSSL_CONF');
$key = openssl_pkey_new($options); openssl_pkey_export($key, $private, null, $options);
file_put_contents($fixture . '/public.pem', openssl_pkey_get_details($key)['key']); config(['patches.demo_public_key' => $fixture . '/public.pem']);
$recipe = ['id' => 'practice-failure', 'component' => 'demo', 'from' => '0.0.3', 'to' => '0.0.4', 'title' => 'Failure fixture', 'notes' => 'Isolated test', 'requires' => ['practice-3'], 'files' => [$path], 'delete' => [], 'migrations' => [$path]];
buildPromexPatch($fixture . '/before', $fixture . '/after', $recipe, $private, $fixture . '/failure.zip');
$stage = $manager->stage($fixture . '/failure.zip');
try { $manager->install($stage['token'], $stage['sha256']); $failed = false; } catch (RuntimeException) { $failed = true; }
$state = $manager->state(); $last = end($state['history']);
$check($failed && $last['status'] === 'failed' && $state['versions']['demo'] === '0.0.3', 'Migration failure recorded without advancing component version');
$check(is_file(storage_path('framework/down')) && is_file($root . '/' . $path), 'Failed mutation leaves site down and changes for operator recovery');
$check($manager->preview($fixture . '/failure.zip')['blocked'] !== null, 'Failed installation blocks subsequent patches');
echo "ALL INSTALLED FLOW CHECKS PASSED\n";
