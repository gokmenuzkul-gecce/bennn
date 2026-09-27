<?php
declare(strict_types=1);
$root = realpath($argv[1] ?? '');
if (!$root || !str_contains(str_replace('\\', '/', $root), '/.skills-backup/prepack-qa-')) throw new RuntimeException('Isolated QA directory required.');
require $root . '/casino/vendor/autoload.php';
$app = require $root . '/casino/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Auth::setUser(VanguardLTE\User::findOrFail(1));
$request = Illuminate\Http\Request::create('/game/DayofDead/server', 'POST', ['action' => 'doInit']);
$request->setLaravelSession(app('session')->driver());
// Legacy engine exits with its protocol body; the harness checks that body.
(new VanguardLTE\Http\Controllers\Web\Frontend\GamesController())->server($request, 'DayofDead');
