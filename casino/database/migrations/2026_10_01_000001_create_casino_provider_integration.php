<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('games', 'provider_key')) {
            Schema::table('games', function (Blueprint $table) {
                $table->string('provider_key', 40)->nullable()->after('source_type');
                $table->string('provider_game_id', 64)->nullable()->after('provider_key');
                $table->index(['provider_key', 'provider_game_id'], 'games_provider_idx');
            });
        }

        if (!Schema::hasTable('casino_provider_players')) {
            Schema::create('casino_provider_players', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('provider_key', 40)->index();
                $table->string('user_code', 64);
                $table->timestamps();
                $table->unique(['user_id', 'provider_key'], 'casino_player_user_provider_unique');
                $table->unique(['provider_key', 'user_code'], 'casino_player_provider_code_unique');
            });
        }

        if (!Schema::hasTable('casino_wallet_transactions')) {
            Schema::create('casino_wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->string('provider_key', 40)->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('transaction_id', 96);
                $table->string('round_id', 96)->nullable();
                $table->string('game_id', 64)->nullable();
                $table->string('operation', 24);
                $table->string('reference_transaction_id', 96)->nullable();
                $table->decimal('bet_amount', 16, 2)->default(0);
                $table->decimal('win_amount', 16, 2)->default(0);
                $table->decimal('amount', 16, 2)->default(0);
                $table->decimal('balance_after', 16, 2)->default(0);
                $table->string('status', 16)->default('committed');
                $table->timestamps();
                $table->unique(['provider_key', 'transaction_id'], 'casino_tx_provider_tx_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('casino_wallet_transactions');
        Schema::dropIfExists('casino_provider_players');

        if (Schema::hasColumn('games', 'provider_key')) {
            Schema::table('games', function (Blueprint $table) {
                $table->dropIndex('games_provider_idx');
                $table->dropColumn(['provider_key', 'provider_game_id']);
            });
        }
    }
};
