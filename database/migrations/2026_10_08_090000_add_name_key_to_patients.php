<?php

use App\Support\DuplicateFinder;
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
        Schema::table('patients', function (Blueprint $table) {
            // The name without Arabic spelling variants, so that a search for "احمد" finds "أحمد".
            $table->string('name_key')->nullable()->index()->after('name');
        });

        $finder = new DuplicateFinder;

        DB::table('patients')->orderBy('id')->each(function (object $patient) use ($finder): void {
            DB::table('patients')->where('id', $patient->id)->update([
                'name_key' => $finder->normalize((string) $patient->name),
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex(['name_key']);
            $table->dropColumn('name_key');
        });
    }
};
