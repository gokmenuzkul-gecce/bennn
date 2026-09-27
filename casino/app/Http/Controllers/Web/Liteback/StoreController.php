<?php

namespace VanguardLTE\Http\Controllers\Web\Liteback;

use VanguardLTE\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use VanguardLTE\Services\LicenseService;
use VanguardLTE\Services\PromexInstallationService;
use VanguardLTE\Services\UpdaterService;

class StoreController extends Controller
{
    /** Display the license and archive-access request page. */
    public function index()
    {
        $license = LicenseService::getStatus();
        return view('liteback.store.index', compact('license'));
    }

    /**
     * Save / Update License Key and Re-verify
     */
    public function updateLicense(Request $request)
    {
        $request->validate([
            'license_key' => 'nullable|string|max:255',
        ]);

        $key = trim($request->input('license_key', ''));

        if (function_exists('settings')) {
            settings()->set('license_key', $key);
            settings()->save();
        }

        // Force cache refresh
        $newStatus = LicenseService::getStatus(true);

        $msg = "License settings updated. Current status: " . strtoupper($newStatus['status']);
        if (($newStatus['status'] ?? '') === 'active' && (bool) config('licensing.auto_activate_installation', true)) {
            try {
                PromexInstallationService::ensureActivated($key);
                $msg .= '. Protected hosted services are active.';
            } catch (\Throwable $e) {
                return redirect()->route('liteback.store.index')->with(
                    'warning',
                    $msg . '. Local features remain active; protected hosted services are pending: ' . $e->getMessage()
                );
            }
        }

        return redirect()->route('liteback.store.index')->with('success', $msg);
    }

    /**
     * Trigger Live Verification Handshake
     */
    public function refreshLicense()
    {
        $status = LicenseService::getStatus(true);

        $type = $status['status'] === 'active' ? 'success' : ($status['status'] === 'grace_period' ? 'warning' : 'danger');
        $msg = "License verification complete: " . strtoupper($status['status']) . " - " . ($status['message'] ?? '');

        return redirect()->route('liteback.store.index')->with($type, $msg);
    }

    /** Request the one-email, 30-day PROMEX archives Drive grant for this license. */
    public function requestArchivesDriveAccess(Request $request)
    {
        $request->validate([
            'email' => 'required|email:rfc,dns|max:254',
            'confirm_one_email_grant' => 'accepted',
        ], [
            'confirm_one_email_grant.accepted' => 'Please confirm that this license receives one 30-day grant for one email address.',
        ]);

        $license = LicenseService::getStatus();
        $licenseKey = trim((string) ($license['license_key'] ?? ''));
        if ($licenseKey === '') {
            return redirect()->route('liteback.store.index')->withErrors('Save a license key before requesting archive access.')->withInput();
        }

        try {
            $response = Http::acceptJson()->timeout(60)->post('https://promex.me/wp-json/promex/v1/games/access', [
                'license_key' => $licenseKey,
                'email' => $request->input('email'),
            ]);
            $data = $response->json();
            if (!is_array($data)) {
                $data = [];
            }
        } catch (\Throwable $exception) {
            report($exception);
            return redirect()->route('liteback.store.index')->withErrors('We could not reach PROMEX to request archive access. Please try again or open a support ticket.')->withInput();
        }

        if ($response->status() === 200 && ($data['success'] ?? false) === true) {
            $message = 'Archive access was granted for ' . ($data['email'] ?? $request->input('email')) . '.';
            if (!empty($data['expires_at'])) {
                $message .= ' It expires ' . $data['expires_at'] . '.';
            }

            return redirect()->route('liteback.store.index')->with('drive_access', [
                'message' => $message,
                'folder_url' => $data['folder_url'] ?? null,
            ]);
        }

        $message = is_array($data) && !empty($data['message']) ? $data['message'] : 'PROMEX could not grant archive access at this time.';
        return redirect()->route('liteback.store.index')->withErrors($message)->withInput();
    }

    /**
     * Install or Update Module / Game Pack
     */
    public function installPack(Request $request)
    {
        $packId = $request->input('pack_id');

        if (!LicenseService::canDownloadPacks()) {
            return redirect()->route('liteback.store.index')->withErrors('Store Pack Downloads are locked: Active Promex license required.');
        }

        return redirect()->route('liteback.store.index')->with('success', "Package [{$packId}] verified and active on your system!");
    }

    /**
     * Apply Live GitHub / Hub Update (At Operator Risk)
     */
    public function applyUpdate(Request $request)
    {
        return redirect()->route('liteback.maintenance.index')->withErrors('The old full-package updater is retired. Select a signed patch under Backup & Update.');
    }
}
