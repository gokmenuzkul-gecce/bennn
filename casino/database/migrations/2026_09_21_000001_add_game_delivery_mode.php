<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('games', 'delivery_mode')) {
            Schema::table('games', function (Blueprint $table) {
                $table->string('delivery_mode', 24)->default('LOCAL')->after('source_type');
            });
        }
        DB::table('games')->where('name', 'Cedarcules')->where('source_type', 'cedar_game')
            ->update(['delivery_mode' => 'PROMEX_REMOTE']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('games', 'delivery_mode')) {
            Schema::table('games', fn (Blueprint $table) => $table->dropColumn('delivery_mode'));
        }
    }
};
