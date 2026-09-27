<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crypto_assets', function (Blueprint $table) {
            $table->id();
            $table->string('provider_id', 120)->unique();
            $table->string('symbol', 30)->index();
            $table->string('name', 120);
            $table->unsignedInteger('market_rank')->nullable()->index();
            $table->decimal('price_usd', 28, 10)->nullable();
            $table->decimal('change_24h', 12, 6)->nullable();
            $table->timestamp('provider_updated_at')->nullable();
            $table->boolean('is_enabled')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('crypto_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crypto_asset_id')->constrained('crypto_assets')->cascadeOnDelete();
            $table->string('interval', 16)->index();
            $table->string('round_code', 100)->unique();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->decimal('open_price_usd', 28, 10)->nullable();
            $table->decimal('close_price_usd', 28, 10)->nullable();
            $table->string('status', 16)->default('scheduled')->index();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->unique(['crypto_asset_id', 'interval', 'starts_at'], 'crypto_round_asset_interval_start_unique');
        });

        Schema::create('crypto_price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crypto_round_id')->constrained('crypto_rounds')->cascadeOnDelete();
            $table->foreignId('crypto_asset_id')->constrained('crypto_assets')->cascadeOnDelete();
            $table->string('checkpoint', 10); // open or close
            $table->decimal('price_usd', 28, 10);
            $table->timestamp('observed_at');
            $table->timestamp('provider_updated_at')->nullable();
            $table->string('payload_hash', 64);
            $table->longText('provider_payload');
            $table->timestamps();
            $table->unique(['crypto_round_id', 'checkpoint']);
        });

        Schema::create('crypto_positions', function (Blueprint $table) {
            $table->id();
            // This application retains legacy unsigned INT user IDs, rather than Laravel's default unsigned BIGINT.
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('crypto_asset_id')->constrained('crypto_assets')->cascadeOnDelete();
            $table->foreignId('crypto_round_id')->constrained('crypto_rounds')->cascadeOnDelete();
            $table->string('requested_interval', 16)->default('hourly');
            $table->string('direction', 8); // long or short
            $table->decimal('leverage', 4, 2)->default(1);
            $table->decimal('stake', 18, 2);
            $table->decimal('entry_price_usd', 28, 10)->nullable();
            $table->decimal('exit_price_usd', 28, 10)->nullable();
            $table->decimal('return_percent', 12, 6)->nullable();
            $table->decimal('payout_amount', 18, 2)->default(0);
            $table->string('status', 16)->default('queued')->index();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crypto_positions');
        Schema::dropIfExists('crypto_price_snapshots');
        Schema::dropIfExists('crypto_rounds');
        Schema::dropIfExists('crypto_assets');
    }
};
