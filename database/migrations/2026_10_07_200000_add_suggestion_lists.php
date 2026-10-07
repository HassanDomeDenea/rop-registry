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
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('list');
            $table->string('label');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['list', 'label']);
        });

        Schema::table('patients', function (Blueprint $table) {
            // The illnesses ticked from the list; "systemic_illness" keeps the free-text remainder.
            $table->json('illnesses')->nullable()->after('cpap_days');
        });

        foreach (SuggestionLists::DEFAULT_ILLNESSES as $position => $label) {
            DB::table('suggestions')->insert([
                'list' => SuggestionList::Illness->value,
                'label' => $label,
                'position' => $position + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(SuggestionLists::class)->adoptExistingRecords();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('illnesses');
        });

        Schema::dropIfExists('suggestions');
    }
};
