<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('stock_assets', function (Blueprint $table) {
            $table->id(); $table->string('provider_id', 32)->unique(); $table->string('symbol', 16)->unique(); $table->string('name');
            $table->string('exchange', 100)->nullable(); $table->decimal('price_usd', 18, 6)->nullable(); $table->decimal('change_percent', 12, 6)->nullable();
            $table->timestamp('provider_updated_at')->nullable(); $table->boolean('is_enabled')->default(false)->index(); $table->timestamps();
        });
        Schema::create('stock_rounds', function (Blueprint $table) {
            $table->id(); $table->foreignId('stock_asset_id')->constrained('stock_assets')->cascadeOnDelete(); $table->string('interval', 16); $table->string('round_code', 100)->unique();
            $table->timestamp('starts_at')->index(); $table->timestamp('ends_at')->index(); $table->decimal('open_price_usd', 18, 6)->nullable(); $table->decimal('close_price_usd', 18, 6)->nullable();
            $table->string('status', 16)->default('scheduled')->index(); $table->timestamp('opened_at')->nullable(); $table->timestamp('settled_at')->nullable(); $table->timestamps();
            $table->unique(['stock_asset_id', 'interval', 'starts_at'], 'stock_round_asset_interval_start_unique');
        });
        Schema::create('stock_price_snapshots', function (Blueprint $table) {
            $table->id(); $table->foreignId('stock_round_id')->constrained('stock_rounds')->cascadeOnDelete(); $table->foreignId('stock_asset_id')->constrained('stock_assets')->cascadeOnDelete();
            $table->string('checkpoint', 8); $table->decimal('price_usd', 18, 6); $table->timestamp('observed_at'); $table->timestamp('provider_updated_at')->nullable(); $table->char('payload_hash', 64); $table->longText('provider_payload'); $table->timestamps();
            $table->unique(['stock_round_id', 'checkpoint']);
        });
        Schema::create('stock_positions', function (Blueprint $table) {
            $table->id(); $table->unsignedInteger('user_id'); $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete(); $table->foreignId('stock_asset_id')->constrained('stock_assets')->cascadeOnDelete(); $table->foreignId('stock_round_id')->constrained('stock_rounds')->cascadeOnDelete();
            $table->string('requested_interval', 16); $table->string('direction', 8); $table->decimal('leverage', 4, 2)->default(1); $table->decimal('stake', 18, 2); $table->decimal('entry_price_usd', 18, 6)->nullable(); $table->decimal('exit_price_usd', 18, 6)->nullable();
            $table->decimal('return_percent', 12, 6)->nullable(); $table->decimal('payout_amount', 18, 2)->default(0); $table->string('status', 16)->default('queued')->index(); $table->timestamp('settled_at')->nullable(); $table->timestamps(); $table->index(['user_id', 'created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('stock_positions'); Schema::dropIfExists('stock_price_snapshots'); Schema::dropIfExists('stock_rounds'); Schema::dropIfExists('stock_assets'); }
};
