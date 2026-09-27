<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('games', 'legacy_rights_attested_at')) {
            Schema::table('games', function (Blueprint $table) {
                $table->timestamp('legacy_rights_attested_at')->nullable()->after('custom_path');
            });
        }
        if (!Schema::hasColumn('games', 'legacy_rights_attested_by')) {
            Schema::table('games', function (Blueprint $table) {
                $table->unsignedBigInteger('legacy_rights_attested_by')->nullable()->after('legacy_rights_attested_at');
            });
        }
    }

    public function down(): void
    {
        $columns = [];
        if (Schema::hasColumn('games', 'legacy_rights_attested_by')) $columns[] = 'legacy_rights_attested_by';
        if (Schema::hasColumn('games', 'legacy_rights_attested_at')) $columns[] = 'legacy_rights_attested_at';
        if ($columns) Schema::table('games', fn (Blueprint $table) => $table->dropColumn($columns));
    }
};
