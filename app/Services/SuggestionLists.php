<?php

namespace App\Services;

use App\Enums\SuggestionList;
use App\Models\Suggestion;
use Illuminate\Support\Facades\DB;

/**
 * Fills the pick lists from records that were typed as free text, such as the imported workbook.
 */
class SuggestionLists
{
    /**
     * The illness list a new registry starts with.
     *
     * @var list<string>
     */
    public const DEFAULT_ILLNESSES = [
        'RDS', 'Jaundice', 'Sepsis', 'CHD', 'PDA', 'ASD', 'IUGR', 'Pneumothorax', 'Blood transfusion',
        'BPD', 'IVH', 'NEC', 'Apnea', 'Anemia',
    ];

    /**
     * Tick the listed illnesses found in the free text of every patient that has no checklist yet,
     * and add the referring doctors already on record to their list.
     */
    public function adoptExistingRecords(): void
    {
        $known = Suggestion::labels(SuggestionList::Illness);

        DB::table('patients')
            ->whereNull('illnesses')
            ->whereNotNull('systemic_illness')
            ->orderBy('id')
            ->each(function (object $patient) use ($known): void {
                [$illnesses, $other] = $this->splitIllnesses((string) $patient->systemic_illness, $known);

                if ($illnesses !== []) {
                    DB::table('patients')->where('id', $patient->id)->update([
                        'illnesses' => json_encode($illnesses, JSON_UNESCAPED_UNICODE),
                        'systemic_illness' => $other,
                    ]);
                }
            });

        DB::table('patients')
            ->whereNotNull('referring_doctor')
            ->selectRaw('referring_doctor, count(*) as patients')
            ->groupBy('referring_doctor')
            ->orderByDesc('patients')
            ->pluck('referring_doctor')
            // Names the transcriber could not read in full are not worth suggesting.
            ->reject(fn (mixed $doctor): bool => str_contains((string) $doctor, '['))
            ->each(fn (mixed $doctor) => Suggestion::remember(SuggestionList::ReferringDoctor, (string) $doctor));
    }

    /**
     * Separate the parts of a semicolon-separated text that match a listed illness from the rest.
     *
     * @param  list<string>  $known
     * @return array{0: list<string>, 1: string|null}
     */
    public function splitIllnesses(string $text, array $known): array
    {
        $labels = [];

        foreach ($known as $label) {
            $labels[$this->normalise($label)] = $label;
        }

        $matched = [];
        $rest = [];

        foreach (explode(';', $text) as $part) {
            $label = $labels[$this->normalise($part)] ?? null;

            if ($label === null) {
                $rest[] = trim($part);
            } elseif (! in_array($label, $matched, true)) {
                $matched[] = $label;
            }
        }

        $rest = array_filter($rest, fn (string $part): bool => $part !== '');

        return [$matched, $rest === [] ? null : implode('; ', $rest)];
    }

    /**
     * Ignore case and spacing, so "bloodtransfusion" matches "Blood transfusion".
     */
    protected function normalise(string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', '', $value));
    }
}
