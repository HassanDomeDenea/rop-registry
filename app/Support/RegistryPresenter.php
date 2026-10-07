<?php

namespace App\Support;

use App\Models\Attachment;
use App\Models\Audit;
use App\Models\Patient;
use App\Models\ReviewItem;
use App\Models\Treatment;
use App\Models\Visit;
use Illuminate\Support\Carbon;

/**
 * Shapes registry models into the arrays consumed by the Inertia pages.
 */
class RegistryPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function patientRow(Patient $patient): array
    {
        $today = Carbon::today();

        return [
            ...$patient->only([
                'id', 'file_number', 'name', 'sex', 'birth_weight_g', 'ga_weeks', 'ga_days', 'multiplicity',
                'status', 'phone', 'exams_count', 'any_rop', 'highest_stage', 'any_plus', 'type_one',
                'had_injection', 'had_laser', 'treatment_pending',
            ]),
            'dob' => $patient->dob?->format('Y-m-d'),
            'referral_date' => $patient->referral_date?->format('Y-m-d'),
            'first_visit_date' => $patient->first_visit_date?->format('Y-m-d'),
            'last_visit_date' => $patient->last_visit_date?->format('Y-m-d'),
            'next_appointment_date' => $patient->next_appointment_date?->format('Y-m-d'),
            'last_injection_date' => $patient->last_injection_date?->format('Y-m-d'),
            'days_until_appointment' => $patient->next_appointment_date === null
                ? null
                : (int) $today->diffInDays($patient->next_appointment_date, false),
            'days_since_injection' => $patient->last_injection_date === null
                ? null
                : (int) $patient->last_injection_date->diffInDays($today, false),
            'pma_days_today' => $patient->postmenstrualAgeInDays($today),
            'age_days_today' => $patient->chronologicalAgeInDays($today),
            'open_review_items_count' => $patient->getAttribute('open_review_items_count'),
            'deleted_at' => $patient->deleted_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function patient(Patient $patient): array
    {
        return [
            ...self::patientRow($patient),
            ...$patient->only([
                'delivery_mode', 'referring_doctor', 'nicu_days', 'respiratory_support', 'support_days',
                'o2_days', 'cpap_days', 'systemic_illness', 'phone_alt', 'address', 'notes', 'source_notes',
            ]),
            'created_at' => $patient->created_at?->toIso8601String(),
            'updated_at' => $patient->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function visit(Visit $visit, Patient $patient): array
    {
        return [
            ...$visit->attributesToArray(),
            'pma_days' => $patient->postmenstrualAgeInDays($visit->visit_date),
            'age_days' => $patient->chronologicalAgeInDays($visit->visit_date),
            'right_suggested_type' => $visit->eyeRopType('right')?->value,
            'left_suggested_type' => $visit->eyeRopType('left')?->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function treatment(Treatment $treatment, Patient $patient): array
    {
        return [
            ...$treatment->attributesToArray(),
            'pma_days' => $patient->postmenstrualAgeInDays($treatment->performed_date),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function attachment(Attachment $attachment): array
    {
        return [
            ...$attachment->only(['id', 'patient_id', 'visit_id', 'original_name', 'mime_type', 'size', 'caption']),
            'is_image' => $attachment->isImage(),
            'url' => route('attachments.show', $attachment),
            'created_at' => $attachment->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function reviewItem(ReviewItem $item): array
    {
        return [
            ...$item->only(['id', 'patient_id', 'field', 'issue', 'source_reference', 'resolution']),
            'resolved_at' => $item->resolved_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function audit(Audit $audit): array
    {
        return [
            ...$audit->only(['id', 'event', 'auditable_type', 'auditable_id', 'patient_id', 'label', 'old_values', 'new_values']),
            'user' => $audit->user?->name,
            'patient_name' => $audit->relationLoaded('patient') ? $audit->patient?->name : null,
            'created_at' => $audit->created_at?->toIso8601String(),
        ];
    }
}
