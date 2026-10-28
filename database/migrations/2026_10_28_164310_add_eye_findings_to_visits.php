<?php

use App\Enums\SuggestionList;
use App\Services\SuggestionLists;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each eye of a visit keeps the ids of the findings ticked from the "eye findings" list,
     * so that renaming an entry renames it on every visit and the counts stay together.
     */
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->json('right_findings')->nullable()->after('right_rop_status');
            $table->json('left_findings')->nullable()->after('left_rop_status');
        });

        Schema::table('suggestions', function (Blueprint $table) {
            // An entry that is still on saved records is hidden from the forms instead of deleted.
            $table->timestamp('hidden_at')->nullable()->after('position');
        });

        foreach (SuggestionLists::DEFAULT_EYE_FINDINGS as $position => $label) {
            DB::table('suggestions')->insertOrIgnore([
                'list' => SuggestionList::EyeFinding->value,
                'label' => $label,
                'position' => $position + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('suggestions')->where('list', SuggestionList::EyeFinding->value)->delete();

        Schema::table('suggestions', function (Blueprint $table) {
            $table->dropColumn('hidden_at');
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['right_findings', 'left_findings']);
        });
    }
};
