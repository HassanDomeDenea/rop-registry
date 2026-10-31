<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An edited picture points at its new file; these keep the one first received.
     */
    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->string('original_path')->nullable()->after('caption');
            $table->string('original_mime_type')->nullable()->after('original_path');
            $table->unsignedBigInteger('original_size')->nullable()->after('original_mime_type');
            $table->timestamp('edited_at')->nullable()->after('original_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropColumn(['original_path', 'original_mime_type', 'original_size', 'edited_at']);
        });
    }
};
