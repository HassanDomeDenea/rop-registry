<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Audit;
use App\Models\Patient;
use App\Models\ReviewItem;
use App\Models\Treatment;
use App\Models\Visit;
use BackedEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Combines two records of the same baby, e.g. after the paper notebooks were imported
 * twice under slightly different names.
 *
 * The kept record receives every visit, treatment, attachment, review item and history
 * entry of the duplicate. Details missing from the kept record are taken from the
 * duplicate; where both records disagree, the administrator chooses. The duplicate is
 * then moved to the recycle bin, so its own details stay viewable.
 */
class PatientMerger
{
    /**
     * The details the administrator may take from either record, each with its columns.
     * Columns that only make sense together, like weeks and days of gestation, move as one.
     *
     * @var array<string, list<string>>
     */
    public const FIELDS = [
        'file_number' => ['file_number'],
        'name' => ['name'],
        'mother_name' => ['mother_name'],
        'sex' => ['sex'],
        'dob' => ['dob'],
        'gestational_age' => ['ga_weeks', 'ga_days'],
        'birth_weight_g' => ['birth_weight_g'],
        'multiplicity' => ['multiplicity'],
        'delivery_mode' => ['delivery_mode'],
        'nicu_days' => ['nicu_days'],
        'respiratory_support' => ['respiratory_support', 'support_days', 'o2_days', 'cpap_days'],
        'systemic_illness' => ['systemic_illness'],
        'referral_date' => ['referral_date'],
        'referring_doctor' => ['referring_doctor'],
        'phone' => ['phone'],
        'phone_alt' => ['phone_alt'],
        'address' => ['address'],
        'status' => ['status'],
    ];

    /**
     * The records that belong to a patient and follow it into the kept record.
     *
     * @var list<class-string<Visit|Treatment|Attachment|ReviewItem>>
     */
    protected const CHILDREN = [Visit::class, Treatment::class, Attachment::class, ReviewItem::class];

    public function __construct(protected PatientSummarizer $summarizer) {}

    /**
     * Merge the duplicate into the kept record.
     *
     * @param  array<string, 'kept'|'duplicate'>  $choices  The record to take each disagreeing detail from.
     */
    public function merge(Patient $kept, Patient $duplicate, array $choices = []): Patient
    {
        return DB::transaction(function () use ($kept, $duplicate, $choices): Patient {
            $snapshot = $this->snapshot($duplicate);

            foreach (self::FIELDS as $field => $columns) {
                $takeDuplicate = $this->isBlank($kept, $columns) || ($choices[$field] ?? 'kept') === 'duplicate';

                if ($takeDuplicate && ! $this->isBlank($duplicate, $columns)) {
                    foreach ($columns as $column) {
                        $kept->setAttribute($column, $duplicate->getAttribute($column));
                    }
                }
            }

            $illnesses = array_values(array_unique([...($kept->illnesses ?? []), ...($duplicate->illnesses ?? [])]));

            $kept->fill([
                'illnesses' => $illnesses === [] ? $kept->illnesses : $illnesses,
                'notes' => $this->combine($kept->notes, $duplicate->notes),
                'source_notes' => $this->combine($kept->source_notes, $duplicate->source_notes),
                // Source notes of the duplicate that were never reviewed must show again on the merged record.
                'source_notes_reviewed_at' => $duplicate->source_notes === null || $duplicate->source_notes_reviewed_at !== null
                    ? $kept->source_notes_reviewed_at
                    : null,
                // A record confirmed against a report confirms the merged one.
                'unverified' => $kept->unverified && $duplicate->unverified,
            ])->save();

            foreach (self::CHILDREN as $model) {
                $model::query()->withoutGlobalScopes()->where('patient_id', $duplicate->id)->update(['patient_id' => $kept->id]);
            }

            Audit::query()->where('patient_id', $duplicate->id)->update(['patient_id' => $kept->id]);

            $this->recordMerge($kept, $duplicate, $snapshot);

            $duplicate->delete();

            $this->summarizer->refresh($kept);

            return $kept;
        });
    }

    /**
     * Determine whether the record has none of the given details.
     *
     * @param  list<string>  $columns
     */
    protected function isBlank(Patient $patient, array $columns): bool
    {
        foreach ($columns as $column) {
            $value = $patient->getAttribute($column);

            if ($value instanceof BackedEnum) {
                $value = $value->value;
            }

            if (filled($value) && $value !== 'unknown') {
                return false;
            }
        }

        return true;
    }

    /**
     * Join two free texts, leaving out the second one when it repeats the first.
     */
    protected function combine(?string $first, ?string $second): ?string
    {
        $first = filled($first) ? trim($first) : null;
        $second = filled($second) ? trim($second) : null;

        if ($first === null || $second === null || str_contains($first, $second)) {
            return $first ?? $second;
        }

        return $first."\n\n".$second;
    }

    /**
     * Get the recorded details of the duplicate, as they were before the merge.
     *
     * @return array<string, mixed>
     */
    protected function snapshot(Patient $patient): array
    {
        $excluded = ['id', 'name_key', 'created_at', 'updated_at', 'deleted_at', ...Patient::SUMMARY_COLUMNS];

        return array_filter(Arr::except($patient->getAttributes(), $excluded), fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * Write the merge into the history of the kept record, with the details of the duplicate.
     *
     * @param  array<string, mixed>  $snapshot
     */
    protected function recordMerge(Patient $kept, Patient $duplicate, array $snapshot): void
    {
        if (! Audit::$enabled) {
            return;
        }

        Audit::query()->create([
            'user_id' => Auth::id(),
            'event' => 'merged',
            'auditable_type' => $kept->getMorphClass(),
            'auditable_id' => $kept->id,
            'patient_id' => $kept->id,
            'label' => $duplicate->file_number === null ? $duplicate->name : "{$duplicate->name} #{$duplicate->file_number}",
            'old_values' => $snapshot ?: null,
            'new_values' => null,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
