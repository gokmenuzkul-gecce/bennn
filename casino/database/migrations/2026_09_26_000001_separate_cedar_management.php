<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach (['cedar_games' => 1, 'cedar_cards' => 2, 'cedar_remakes' => 3] as $href => $position) {
            DB::table('categories')->where('href', $href)->update(['position' => $position]);
        }

        // Retain history and files for recovery, but retire the superseded title everywhere.
        DB::table('games')->where('name', 'CedarHercules')->update(['view' => 0]);
        $retiredIds = DB::table('games')->where('name', 'CedarHercules')->pluck('id');
        if ($retiredIds->isNotEmpty()) {
            $cedarCategoryIds = DB::table('categories')->whereIn('href', ['cedar_games', 'cedar_cards', 'cedar_remakes'])->pluck('id');
            DB::table('game_categories')->whereIn('game_id', $retiredIds)->whereIn('category_id', $cedarCategoryIds)->delete();
        }
    }

    public function down(): void
    {
        DB::table('games')->where('name', 'CedarHercules')->update(['view' => 1]);
        $category = DB::table('categories')->where('href', 'cedar_remakes')->value('id');
        foreach (DB::table('games')->where('name', 'CedarHercules')->pluck('id') as $id) {
            if ($category) DB::table('game_categories')->updateOrInsert(['game_id' => $id, 'category_id' => $category], []);
        }
    }
};
