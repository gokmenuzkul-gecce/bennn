<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widen the 01.tech wallet ledger columns.
 *
 * The integration migration sized `transaction_id`/`round_id` at 96 characters
 * from the first protocol's limits, but a8r ids only have to stay under the
 * 255-character field limit. They are widened here so a longer id is never
 * silently truncated (which would break idempotency).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('casino_wallet_transactions', function (Blueprint $table) {
            $table->string('transaction_id', 191)->change();
            $table->string('round_id', 191)->nullable()->change();
            $table->string('reference_transaction_id', 191)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('casino_wallet_transactions', function (Blueprint $table) {
            $table->string('transaction_id', 96)->change();
            $table->string('round_id', 96)->nullable()->change();
            $table->string('reference_transaction_id', 96)->nullable()->change();
        });
    }
};
