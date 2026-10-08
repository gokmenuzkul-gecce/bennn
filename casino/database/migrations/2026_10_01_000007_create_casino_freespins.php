<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free spins campaigns we issued to the Aggregator.
 *
 * A row is written when the back office issues a campaign (casino_a8r.Freespins/
 * Issue) and updated when the Aggregator reports the total win
 * (a8r_casino.Freespins/Finish) or the campaign is cancelled. It is the audit
 * trail for who received how many spins on which game and for how much.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('casino_freespins')) {
            Schema::create('casino_freespins', function (Blueprint $table) {
                $table->id();
                $table->string('provider_key', 40)->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('issue_id', 191);
                $table->string('game_id', 64)->nullable();
                $table->string('game_provider', 64)->nullable();
                $table->unsignedInteger('quantity')->default(0);
                $table->decimal('bet_amount', 16, 2)->default(0);
                $table->timestamp('valid_until')->nullable();
                $table->string('status', 16)->default('issued');
                $table->decimal('win_amount', 16, 2)->default(0);
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
                $table->unique(['provider_key', 'issue_id'], 'casino_freespins_provider_issue_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('casino_freespins');
    }
};
