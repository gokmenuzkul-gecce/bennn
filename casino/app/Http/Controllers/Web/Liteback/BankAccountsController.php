<?php

namespace VanguardLTE\Http\Controllers\Web\Liteback;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use VanguardLTE\Http\Controllers\Controller;

/**
 * Operator-managed pool of payment accounts.
 *
 * Each row is one bank/IBAN (or crypto wallet) the player may be asked to pay
 * into. The player deposit screen picks a random active row for the chosen
 * method, so the operator can rotate any number of accounts without a deploy.
 */
class BankAccountsController extends Controller
{
    private const METHODS = ['bank', 'havale', 'crypto'];

    public function index(Request $request)
    {
        $method = $request->query('method');
        if (!in_array($method, self::METHODS, true)) {
            $method = null;
        }

        $accounts = DB::table('payment_bank_accounts')
            ->when($method, fn($q) => $q->where('method', $method))
            ->orderBy('method')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $counts = DB::table('payment_bank_accounts')
            ->select('method', DB::raw('COUNT(*) as total'), DB::raw('SUM(active) as active'))
            ->groupBy('method')
            ->get()
            ->keyBy('method');

        return view('liteback.payments.bank-accounts', [
            'accounts' => $accounts,
            'counts' => $counts,
            'methods' => self::METHODS,
            'activeFilter' => $method,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        DB::table('payment_bank_accounts')->insert([
            'method' => $data['method'],
            'bank' => $data['bank'] ?? null,
            'holder' => $data['holder'] ?? null,
            'iban' => $data['iban'] ?? null,
            'network' => $data['network'] ?? null,
            'address' => $data['address'] ?? null,
            'memo' => $data['memo'] ?? null,
            'active' => $request->boolean('active', true) ? 1 : 0,
            'position' => (int) DB::table('payment_bank_accounts')->max('position') + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Ödeme hesabı eklendi. Oyuncular bu hesaba rastgele yönlendirilecek.');
    }

    public function update(Request $request, $id)
    {
        $account = DB::table('payment_bank_accounts')->where('id', $id)->first();
        if (!$account) {
            return redirect()->back()->withErrors('Ödeme hesabı bulunamadı.');
        }

        $data = $this->validated($request);

        DB::table('payment_bank_accounts')->where('id', $id)->update([
            'method' => $data['method'],
            'bank' => $data['bank'] ?? null,
            'holder' => $data['holder'] ?? null,
            'iban' => $data['iban'] ?? null,
            'network' => $data['network'] ?? null,
            'address' => $data['address'] ?? null,
            'memo' => $data['memo'] ?? null,
            'active' => $request->boolean('active') ? 1 : 0,
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Ödeme hesabı güncellendi.');
    }

    public function destroy($id)
    {
        $deleted = DB::table('payment_bank_accounts')->where('id', $id)->delete();

        return redirect()->back()->with(
            $deleted ? 'success' : 'error',
            $deleted ? 'Ödeme hesabı silindi.' : 'Ödeme hesabı bulunamadı.'
        );
    }

    public function toggle($id)
    {
        $account = DB::table('payment_bank_accounts')->where('id', $id)->first();
        if (!$account) {
            return redirect()->back()->withErrors('Ödeme hesabı bulunamadı.');
        }

        DB::table('payment_bank_accounts')->where('id', $id)->update([
            'active' => $account->active ? 0 : 1,
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', $account->active ? 'Hesap pasife alındı.' : 'Hesap aktife alındı.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'method' => 'required|in:bank,havale,crypto',
            'bank' => 'nullable|string|max:255',
            'holder' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:64',
            'network' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'memo' => 'nullable|string|max:255',
        ]);

        $errors = [];
        if (in_array($data['method'], ['bank', 'havale'], true) && trim((string) ($data['iban'] ?? '')) === '') {
            $errors['iban'] = 'Banka ve Havale hesapları için IBAN zorunludur.';
        }
        if ($data['method'] === 'crypto' && trim((string) ($data['address'] ?? '')) === '') {
            $errors['address'] = 'Kripto hesapları için cüzdan adresi zorunludur.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }
}
