<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('games', 'icon_url')) {
            Schema::table('games', function (Blueprint $table) {
                $table->string('icon_url', 512)->nullable()->after('custom_path');
            });
        }

        if (!Schema::hasColumn('games', 'launch_code')) {
            Schema::table('games', function (Blueprint $table) {
                // Numeric aggregator game id the vendor expects in userauth.
                $table->string('launch_code', 64)->nullable()->after('provider_game_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('games', 'icon_url')) {
            Schema::table('games', function (Blueprint $table) {
                $table->dropColumn('icon_url');
            });
        }

        if (Schema::hasColumn('games', 'launch_code')) {
            Schema::table('games', function (Blueprint $table) {
                $table->dropColumn('launch_code');
            });
        }
    }
};
