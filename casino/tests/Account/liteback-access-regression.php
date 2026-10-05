<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use VanguardLTE\Http\Middleware\StaffOnly;
use VanguardLTE\User;

$checks = [];
$middleware = app(StaffOnly::class);

$call = static function (?User $user) use ($middleware): int {
    $request = Request::create('/liteback', 'GET');
    $request->setUserResolver(static fn () => $user);

    $guard = auth()->guard();
    $property = new \ReflectionProperty($guard, 'user');
    $property->setAccessible(true);
    $previous = $property->getValue($guard);
    $property->setValue($guard, $user);

    try {
        $response = $middleware->handle($request, static fn () => response('console', 200));

        return $response->getStatusCode();
    } catch (HttpException $e) {
        return $e->getStatusCode();
    } finally {
        $property->setValue($guard, $previous);
    }
};

$userWithRole = static function (int $roleId): User {
    $user = new User();
    $user->role_id = $roleId;

    return $user;
};

$checks['players (role 1) are rejected from the operator console'] = $call($userWithRole(1)) === 403;
$checks['cashiers (role 2) may reach the operator console'] = $call($userWithRole(2)) === 200;
$checks['managers (role 3) may reach the operator console'] = $call($userWithRole(3)) === 200;
$checks['distributors (role 4) may reach the operator console'] = $call($userWithRole(4)) === 200;
$checks['agents (role 5) may reach the operator console'] = $call($userWithRole(5)) === 200;
$checks['admins (role 6) may reach the operator console'] = $call($userWithRole(6)) === 200;
$checks['guests are left to the auth middleware'] = $call(null) === 200;

$routes = file_get_contents(__DIR__ . '/../../routes/web.php');
$checks['the liteback group is guarded by the staff_only middleware'] = is_string($routes)
    && preg_match("/Route::prefix\\('liteback'\\)\\s*\\n\\s*->middleware\\(\\['auth', 'checker', 'staff_only'\\]\\)/", $routes) === 1;

$kernel = file_get_contents(__DIR__ . '/../../app/Http/Kernel.php');
$checks['the staff_only middleware is registered in the HTTP kernel'] = is_string($kernel)
    && str_contains($kernel, "'staff_only' => 'VanguardLTE\\Http\\Middleware\\StaffOnly'");

$middlewareSource = file_get_contents(__DIR__ . '/../../app/Http/Middleware/StaffOnly.php');
$checks['the gate allows every staff role and blocks players'] = is_string($middlewareSource)
    && str_contains($middlewareSource, '[2, 3, 4, 5, 6]')
    && str_contains($middlewareSource, 'abort(403)');

foreach ($checks as $name => $passed) {
    if (!$passed) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    echo 'PASS: ' . $name . PHP_EOL;
}

echo 'PASS: ' . count($checks) . ' liteback access checks' . PHP_EOL;
