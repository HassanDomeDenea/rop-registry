<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Images received from the camera that are not assigned to a patient yet.
        Schema::create('captures', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch')->index();
            $table->string('source');
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('hash', 64)->unique();
            $table->timestamps();
        });

        Schema::table('attachments', function (Blueprint $table) {
            // Lets the inbox recognise a camera file that was already attached to a patient.
            $table->string('hash', 64)->nullable()->index()->after('size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropIndex(['hash']);
            $table->dropColumn('hash');
        });

        Schema::dropIfExists('captures');
    }
};
