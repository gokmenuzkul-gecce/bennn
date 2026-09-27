<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lotto_games', function (Blueprint $table) {
            if (!Schema::hasColumn('lotto_games', 'result_columns_json')) {
                $table->json('result_columns_json')->nullable()->after('draw_time');
            }
            if (!Schema::hasColumn('lotto_games', 'draw_source')) {
                $table->string('draw_source', 30)->default('local')->after('draw_time');
            }
        });

        Schema::table('lotto_draws', function (Blueprint $table) {
            if (!Schema::hasColumn('lotto_draws', 'round_code')) {
                $table->string('round_code', 80)->nullable()->after('lotto_game_id');
            }
            if (!Schema::hasColumn('lotto_draws', 'scheduled_for')) {
                $table->timestamp('scheduled_for')->nullable()->after('draw_date');
            }
            if (!Schema::hasColumn('lotto_draws', 'status')) {
                $table->string('status', 20)->default('scheduled')->after('scheduled_for');
            }
            if (!Schema::hasColumn('lotto_draws', 'draw_source')) {
                $table->string('draw_source', 30)->default('local')->after('status');
            }
            $table->unique(['lotto_game_id', 'round_code'], 'lotto_draws_game_round_unique');
        });

        Schema::table('lotto_tickets', function (Blueprint $table) {
            if (!Schema::hasColumn('lotto_tickets', 'lotto_draw_id')) {
                $table->unsignedBigInteger('lotto_draw_id')->nullable()->index()->after('lotto_game_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lotto_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('lotto_tickets', 'lotto_draw_id')) $table->dropColumn('lotto_draw_id');
        });
        Schema::table('lotto_draws', function (Blueprint $table) {
            foreach (['round_code', 'scheduled_for', 'status', 'draw_source'] as $column) {
                if (Schema::hasColumn('lotto_draws', $column)) $table->dropColumn($column);
            }
        });
        Schema::table('lotto_games', function (Blueprint $table) {
            foreach (['result_columns_json', 'draw_source'] as $column) {
                if (Schema::hasColumn('lotto_games', $column)) $table->dropColumn($column);
            }
        });
    }
};
