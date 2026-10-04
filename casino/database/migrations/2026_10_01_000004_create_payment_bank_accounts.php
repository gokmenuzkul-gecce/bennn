<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payment_bank_accounts')) {
            Schema::create('payment_bank_accounts', function (Blueprint $table) {
                $table->bigIncrements('id');
                // Method family the account belongs to: bank, havale or crypto.
                $table->string('method', 40)->default('bank');
                $table->string('bank', 255)->nullable();
                $table->string('holder', 255)->nullable();
                $table->string('iban', 64)->nullable();
                $table->string('network', 255)->nullable();
                $table->string('address', 255)->nullable();
                $table->string('memo', 255)->nullable();
                $table->boolean('active')->default(true);
                $table->unsignedInteger('position')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('manual_deposits', 'bank_account_id')) {
            Schema::table('manual_deposits', function (Blueprint $table) {
                $table->unsignedBigInteger('bank_account_id')->nullable()->after('method');
            });
        }

        $this->seedFromSettings();
    }

    public function down(): void
    {
        if (Schema::hasColumn('manual_deposits', 'bank_account_id')) {
            Schema::table('manual_deposits', function (Blueprint $table) {
                $table->dropColumn('bank_account_id');
            });
        }

        Schema::dropIfExists('payment_bank_accounts');
    }

    /**
     * Turn the single IBAN each method used to keep in settings into the first
     * row of the new list so existing operators keep their account.
     */
    private function seedFromSettings(): void
    {
        if (!Schema::hasTable('settings') || DB::table('payment_bank_accounts')->exists()) {
            return;
        }

        $map = [
            'bank' => ['payment_bank_transfer_bank', 'payment_bank_transfer_holder', 'payment_bank_transfer_iban'],
            'havale' => ['payment_havale_bank', 'payment_havale_holder', 'payment_havale_iban'],
            'crypto' => ['payment_crypto_network', null, 'payment_crypto_address', 'payment_crypto_memo'],
        ];

        $now = now();
        foreach ($map as $method => $keys) {
            $get = fn($key) => $key ? (string) DB::table('settings')->where('key', $key)->value('value') : '';
            $bank = $get($keys[0]);
            $holder = $get($keys[1]);
            $iban = $get($keys[2]);
            $memo = $get($keys[3] ?? '');

            if ($method === 'crypto') {
                if ($iban === '' && $bank === '') {
                    continue;
                }
                DB::table('payment_bank_accounts')->insert([
                    'method' => 'crypto', 'bank' => null, 'holder' => null,
                    'iban' => null, 'network' => $bank ?: null, 'address' => $iban ?: null,
                    'memo' => $memo ?: null, 'active' => 1, 'position' => 0,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                continue;
            }

            if ($iban === '') {
                continue;
            }

            DB::table('payment_bank_accounts')->insert([
                'method' => $method, 'bank' => $bank ?: null, 'holder' => $holder ?: null,
                'iban' => $iban, 'active' => 1, 'position' => 0,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
};
