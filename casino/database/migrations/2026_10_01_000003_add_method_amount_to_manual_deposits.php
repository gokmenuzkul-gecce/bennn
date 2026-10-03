<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('manual_deposits', 'method')) {
            Schema::table('manual_deposits', function (Blueprint $table) {
                $table->string('method', 40)->nullable()->after('payment_intent_id');
            });
        }

        if (!Schema::hasColumn('manual_deposits', 'amount')) {
            Schema::table('manual_deposits', function (Blueprint $table) {
                $table->decimal('amount', 16, 2)->nullable()->after('method');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('manual_deposits', 'amount')) {
            Schema::table('manual_deposits', function (Blueprint $table) {
                $table->dropColumn('amount');
            });
        }

        if (Schema::hasColumn('manual_deposits', 'method')) {
            Schema::table('manual_deposits', function (Blueprint $table) {
                $table->dropColumn('method');
            });
        }
    }
};
