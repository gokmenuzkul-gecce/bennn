<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('cedar_arcade_commands', function (Blueprint $t) {
            $t->bigIncrements('id'); $t->unsignedBigInteger('user_id'); $t->string('game',100);
            $t->string('command_id',32); $t->string('input_hash',64); $t->longText('input');
            $t->longText('result')->nullable(); $t->decimal('reserved',18,2)->default(0);
            $t->timestamps(); $t->unique(['user_id','game','command_id'],'cedar_arcade_command_unique');
        });
    }
    public function down(): void { Schema::dropIfExists('cedar_arcade_commands'); }
};
