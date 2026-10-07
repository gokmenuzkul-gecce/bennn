<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('games', 'game_type')) {
            Schema::table('games', function (Blueprint $table) {
                // Aggregator's normalised type: slots, live, crash, table, ...
                // The lobby badge and the live/slot split key off this instead
                // of the legacy gamebank bank column, which cannot tell a live
                // table from a slot.
                $table->string('game_type', 24)->nullable()->after('provider_game_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('games', 'game_type')) {
            Schema::table('games', function (Blueprint $table) {
                $table->dropColumn('game_type');
            });
        }
    }
};
