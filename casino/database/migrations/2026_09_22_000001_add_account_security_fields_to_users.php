<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (!Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('password');
            }
            if (!Schema::hasColumn('users', 'preferred_login_method')) {
                $table->string('preferred_login_method', 16)->default('phone')->after('must_change_password');
            }
        });

        DB::table('users')
            ->where(function ($query): void {
                $query->whereNull('phone')->orWhere('phone', '');
            })
            ->update(['preferred_login_method' => 'password']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $columns = [];
            if (Schema::hasColumn('users', 'must_change_password')) {
                $columns[] = 'must_change_password';
            }
            if (Schema::hasColumn('users', 'preferred_login_method')) {
                $columns[] = 'preferred_login_method';
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
