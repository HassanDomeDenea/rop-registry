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
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('file_number')->nullable()->index();
            $table->string('name')->index();
            $table->date('dob')->nullable();
            $table->string('sex')->default('unknown');
            $table->unsignedInteger('birth_weight_g')->nullable();
            $table->unsignedTinyInteger('ga_weeks')->nullable();
            $table->unsignedTinyInteger('ga_days')->nullable();
            $table->string('multiplicity')->nullable();
            $table->string('delivery_mode')->nullable();
            $table->date('referral_date')->nullable();
            $table->string('referring_doctor')->nullable();
            $table->unsignedSmallInteger('nicu_days')->nullable();
            $table->string('respiratory_support')->nullable();
            $table->unsignedSmallInteger('support_days')->nullable();
            $table->unsignedSmallInteger('o2_days')->nullable();
            $table->unsignedSmallInteger('cpap_days')->nullable();
            $table->text('systemic_illness')->nullable();
            $table->string('phone')->nullable();
            $table->string('phone_alt')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->text('source_notes')->nullable();
            $table->string('status')->default('active')->index();

            // Summary columns, recalculated whenever visits or treatments change.
            $table->unsignedSmallInteger('exams_count')->default(0);
            $table->date('first_visit_date')->nullable();
            $table->date('last_visit_date')->nullable();
            $table->date('next_appointment_date')->nullable()->index();
            $table->boolean('any_rop')->nullable();
            $table->string('highest_stage')->nullable();
            $table->boolean('any_plus')->default(false);
            $table->boolean('type_one')->default(false);
            $table->boolean('had_injection')->default(false);
            $table->boolean('had_laser')->default(false);
            $table->date('last_injection_date')->nullable();
            $table->boolean('treatment_pending')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('kind')->default('examination');
            $table->date('visit_date')->nullable()->index();
            $table->string('examiner')->nullable();

            foreach (['right', 'left'] as $eye) {
                $table->string("{$eye}_dilatation")->nullable();
                $table->string("{$eye}_lens")->nullable();
                $table->string("{$eye}_plus")->nullable();
                $table->string("{$eye}_zone")->nullable();
                $table->string("{$eye}_stage")->nullable();
                $table->boolean("{$eye}_a_rop")->nullable();
                $table->string("{$eye}_rop_type")->nullable();
                $table->string("{$eye}_rop_status")->nullable();
                $table->text("{$eye}_notes")->nullable();
            }

            $table->text('assessment')->nullable();
            $table->string('management_plan')->nullable();
            $table->text('management_notes')->nullable();
            $table->date('next_visit_date')->nullable();
            $table->unsignedInteger('fee')->nullable();
            $table->text('notes')->nullable();
            $table->string('source_reference')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('eye');
            $table->date('performed_date')->nullable()->index();
            $table->string('agent')->nullable();
            $table->string('performed_by')->nullable();
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->string('source_reference')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('caption')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('review_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('field')->nullable();
            $table->text('issue');
            $table->string('source_reference')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');
            $table->morphs('auditable');
            $table->foreignId('patient_id')->nullable()->index();
            $table->string('label')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audits');
        Schema::dropIfExists('review_items');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('treatments');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('patients');
    }
};
