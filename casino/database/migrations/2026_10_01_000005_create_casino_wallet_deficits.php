<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-provider player deficit ("negative internal balance").
 *
 * The site ledger stores `users.balance` as an unsigned-in-practice decimal that
 * the rest of the app treats as spendable, so it can never go below zero. The
 * 01.tech contract, however, requires a win rollback to be applied even when the
 * player no longer has the funds: the casino must carry the shortfall
 * internally, keep returning a visible balance of 0, and let the next credits
 * offset the deficit before the player sees any money again.
 *
 * This table holds that shortfall per (provider, user). It is only meaningful to
 * the wallet that produces it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('casino_wallet_deficits')) {
            Schema::create('casino_wallet_deficits', function (Blueprint $table) {
                $table->id();
                $table->string('provider_key', 40);
                $table->unsignedBigInteger('user_id');
                $table->decimal('amount', 16, 2)->default(0);
                $table->timestamps();
                $table->unique(['provider_key', 'user_id'], 'casino_deficit_provider_user_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('casino_wallet_deficits');
    }
};
