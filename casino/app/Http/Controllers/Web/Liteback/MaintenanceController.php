<?php

namespace VanguardLTE\Http\Controllers\Web\Liteback;

use Illuminate\Http\Request;
use VanguardLTE\Http\Controllers\Controller;
use VanguardLTE\Services\LicenseService;
use VanguardLTE\Services\ManualBackupService;
use VanguardLTE\Services\PatchManager;
use VanguardLTE\Services\UpdaterService;

final class MaintenanceController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless(auth()->check() && (int) auth()->user()->role_id === 6, 403);
            return $next($request);
        });
    }
    public function index(Request $request, PatchManager $manager)
    {
        $licensed = LicenseService::isLicensed(); $demo = $manager->demo();
        $state = $manager->state(); $version = UpdaterService::getCurrentVersion();
        $preview = null; $catalog = []; $catalogError = null;
        if (($licensed || $demo) && $request->session()->has('patch.token')) {
            try { $preview = $manager->preview($manager->package($request->session()->get('patch.token'))); }
            catch (\Throwable $e) { $catalogError = $e->getMessage(); }
        }
        if (($licensed || $demo) && $request->boolean('check')) {
            try { $catalog = $manager->catalog(); } catch (\Throwable $e) { $catalogError = $e->getMessage(); }
        }
        $backups = array_reverse(glob(storage_path('app/manual-backups/backup-*.zip')) ?: []);
        return view('liteback.maintenance.index', compact('licensed', 'demo', 'state', 'version', 'preview', 'catalog', 'catalogError', 'backups'));
    }
    public function fetch(Request $request, PatchManager $manager)
    {
        $request->validate(['slug' => 'required|string|max:96']);
        return $this->action(function () use ($request, $manager) {
            $stage = $manager->fetch($request->input('slug'));
            $request->session()->put('patch', ['token' => $stage['token'], 'sha256' => $stage['sha256']]);
            return 'Patch downloaded. Review it below.';
        });
    }
    public function install(Request $request, PatchManager $manager)
    {
        return $this->action(fn () => $manager->install((string) $request->session()->get('patch.token'), (string) $request->session()->get('patch.sha256')));
    }
    public function verifyLegacy(Request $request, PatchManager $manager)
    {
        return $this->action(fn () => $manager->verifyLegacy((string) $request->session()->get('patch.token')));
    }
    public function package(Request $request, PatchManager $manager)
    {
        $manager->authorize(); $file = $manager->package((string) $request->session()->get('patch.token'));
        abort_unless(str_starts_with($manager->preview($file)['manifest']['component'], 'legacy:'), 403);
        return response()->download($file, 'reviewed-patch.zip', ['Cache-Control' => 'no-store']);
    }
    public function backup(ManualBackupService $backup)
    {
        return $this->action(fn () => 'Backup ready: ' . $backup->create() . '. Download it below.');
    }
    public function downloadBackup(string $name)
    {
        abort_unless(preg_match('/^backup-[0-9]{8}-[0-9]{6}-[a-f0-9]{12}\.zip$/D', $name), 404);
        $path = storage_path('app/manual-backups/' . $name); abort_unless(is_file($path), 404);
        return response()->download($path, $name, ['Cache-Control' => 'no-store']);
    }
    private function action(callable $action)
    {
        try { return redirect()->route('liteback.maintenance.index')->with('success', $action()); }
        catch (\Throwable $e) { report($e); return redirect()->route('liteback.maintenance.index')->withErrors($e->getMessage()); }
    }
}
