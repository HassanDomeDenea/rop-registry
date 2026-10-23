<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dilatation and lens are no longer recorded as fields of their own. Whatever was
     * written in them is kept as a line in the notes of the same eye.
     */
    public function up(): void
    {
        DB::table('visits')
            ->where(fn ($query) => $query->whereNotNull('right_dilatation')->orWhereNotNull('right_lens')
                ->orWhereNotNull('left_dilatation')->orWhereNotNull('left_lens'))
            ->orderBy('id')
            ->chunkById(100, function ($visits): void {
                foreach ($visits as $visit) {
                    $changes = [];

                    foreach (['right', 'left'] as $eye) {
                        $lines = array_filter([
                            $visit->{"{$eye}_notes"},
                            filled($visit->{"{$eye}_dilatation"}) ? 'Dilatation: '.$visit->{"{$eye}_dilatation"} : null,
                            filled($visit->{"{$eye}_lens"}) ? 'Lens: '.$visit->{"{$eye}_lens"} : null,
                        ], filled(...));

                        $changes["{$eye}_notes"] = $lines === [] ? null : implode("\n", $lines);
                    }

                    DB::table('visits')->where('id', $visit->id)->update($changes);
                }
            });

        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['right_dilatation', 'right_lens', 'left_dilatation', 'left_lens']);
        });
    }

    /**
     * The columns come back empty; their former values stay in the notes.
     */
    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->string('right_dilatation')->nullable()->after('examiner');
            $table->string('right_lens')->nullable()->after('right_dilatation');
            $table->string('left_dilatation')->nullable()->after('right_notes');
            $table->string('left_lens')->nullable()->after('left_dilatation');
        });
    }
};
