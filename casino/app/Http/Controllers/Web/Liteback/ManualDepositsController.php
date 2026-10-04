<?php

namespace VanguardLTE\Http\Controllers\Web\Liteback;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use VanguardLTE\Http\Controllers\Controller;
use VanguardLTE\User;

class ManualDepositsController extends Controller
{
    public function index()
    {
        $deposits = DB::table('manual_deposits')
            ->join('users', 'manual_deposits.user_id', '=', 'users.id')
            ->join('payment_intents', 'manual_deposits.payment_intent_id', '=', 'payment_intents.id')
            ->leftJoin('payment_bank_accounts', 'manual_deposits.bank_account_id', '=', 'payment_bank_accounts.id')
            ->select(
                'manual_deposits.*',
                'users.username',
                'users.email',
                'payment_intents.amount as intent_amount',
                'payment_intents.currency',
                'payment_bank_accounts.bank as account_bank',
                'payment_bank_accounts.holder as account_holder',
                'payment_bank_accounts.iban as account_iban',
                'payment_bank_accounts.network as account_network',
                'payment_bank_accounts.address as account_address'
            )
            ->orderByRaw('CASE WHEN ' . DB::getTablePrefix() . 'manual_deposits.status = 0 THEN 0 ELSE 1 END')
            ->orderBy('manual_deposits.created_at', 'desc')
            ->paginate(20);

        $pendingCount = DB::table('manual_deposits')->where('status', 0)->count();

        return view('liteback.payments.manual', compact('deposits', 'pendingCount'));
    }

    public function approve($id)
    {
        $deposit = DB::table('manual_deposits')->where('id', $id)->first();
        if (!$deposit) {
            return redirect()->back()->withErrors('Deposit record not found.');
        }

        if ($deposit->status != 0) {
            return redirect()->back()->withErrors('This deposit has already been processed.');
        }

        $intent = DB::table('payment_intents')->where('id', $deposit->payment_intent_id)->first();
        if (!$intent) {
            return redirect()->back()->withErrors('Matching payment intent not found.');
        }

        $user = User::find($deposit->user_id);
        if (!$user) {
            return redirect()->back()->withErrors('User not found.');
        }

        $amount = (float) ($deposit->amount ?: $intent->amount);

        DB::transaction(function () use ($deposit, $intent, $user, $amount) {
            $rate = (float) (function_exists('settings') ? settings('coins_per_dollar', 100) : 100);
            $coinsCredited = $amount * $rate;
            $newBalance = (float) $user->balance + $coinsCredited;

            DB::table('users')->where('id', $user->id)->update([
                'balance' => $newBalance,
                'updated_at' => now(),
            ]);

            DB::table('transactions')->insert([
                'user_id' => $user->id,
                'admin_id' => auth()->id(),
                'direction' => 'payment',
                'amount' => $coinsCredited,
                'balance_before' => $user->balance,
                'balance_after' => $newBalance,
                'source' => 'manual',
                'note' => 'Manual Deposit of ' . number_format($amount, 2) . ' ' . $intent->currency . ' (' . number_format($coinsCredited, 0) . ' coins) approved by admin ' . auth()->user()->username,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('manual_deposits')->where('id', $deposit->id)->update([
                'status' => 1, // Approved
                'admin_note' => 'Approved by ' . auth()->user()->username,
                'updated_at' => now(),
            ]);

            DB::table('payment_intents')->where('id', $intent->id)->update([
                'status' => 'paid',
                'updated_at' => now(),
            ]);
        });

        return redirect()->back()->with('success', 'Deposit approved and user balance updated.');
    }

    public function reject(Request $request, $id)
    {
        $deposit = DB::table('manual_deposits')->where('id', $id)->first();
        if (!$deposit) {
            return redirect()->back()->withErrors('Deposit record not found.');
        }

        if ($deposit->status != 0) {
            return redirect()->back()->withErrors('This deposit has already been processed.');
        }

        $request->validate([
            'admin_note' => 'nullable|string|max:500',
        ]);

        DB::table('manual_deposits')->where('id', $id)->update([
            'status' => 2, // Rejected
            'admin_note' => $request->input('admin_note') ?? 'Rejected by ' . auth()->user()->username,
            'updated_at' => now(),
        ]);

        DB::table('payment_intents')->where('id', $deposit->payment_intent_id)->update([
            'status' => 'rejected',
            'updated_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Deposit request rejected.');
    }
}
