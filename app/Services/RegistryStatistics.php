<?php

namespace App\Services;

use App\Enums\DeliveryMode;
use App\Enums\EyeSide;
use App\Enums\ManagementPlan;
use App\Enums\Multiplicity;
use App\Enums\PatientStatus;
use App\Enums\PlusDisease;
use App\Enums\RespiratorySupport;
use App\Enums\Sex;
use App\Enums\Stage;
use App\Enums\TreatmentType;
use App\Enums\Zone;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\Visit;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Calculates the registry statistics for a cohort of patients.
 *
 * Patient counts, eye counts and treatment-session counts are kept separate, and every
 * distribution reports its denominator and the number of unknown / unrecorded values.
 */
class RegistryStatistics
{
    protected const GA_GROUPS = [[0, 27, '< 28'], [28, 29, '28–29'], [30, 31, '30–31'], [32, 33, '32–33'], [34, 36, '34–36'], [37, 99, '≥ 37']];

    protected const WEIGHT_GROUPS = [[0, 999, '< 1000'], [1000, 1499, '1000–1499'], [1500, 1999, '1500–1999'], [2000, 2499, '2000–2499'], [2500, 99999, '≥ 2500']];

    protected const NICU_GROUPS = [[0, 7, '0–7'], [8, 14, '8–14'], [15, 28, '15–28'], [29, 60, '29–60'], [61, 9999, '> 60']];

    /**
     * @return array<string, mixed>
     */
    public function calculate(?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $all = Patient::query()->with(['visits', 'treatments'])->get();

        $patients = $all->filter(function (Patient $patient) use ($from, $to): bool {
            if ($from === null && $to === null) {
                return true;
            }

            $seen = $this->firstSeen($patient);

            return $seen !== null
                && ($from === null || $seen->gte($from))
                && ($to === null || $seen->lte($to));
        })->values();

        $visits = $patients->flatMap(fn (Patient $patient) => $patient->visits);
        $treatments = $patients->flatMap(fn (Patient $patient) => $patient->treatments);
        $eyes = $this->eyes($patients);

        return [
            'cohort' => [
                'patients' => $patients->count(),
                'registry_total' => $all->count(),
                'excluded_undated' => ($from !== null || $to !== null)
                    ? $all->filter(fn (Patient $patient): bool => $this->firstSeen($patient) === null)->count()
                    : 0,
            ],
            'kpis' => $this->kpis($patients, $visits, $treatments, $eyes),
            'numeric' => $this->numericSummaries($patients, $treatments),
            'charts' => [
                ...$this->demographicCharts($patients),
                ...$this->ropCharts($patients, $eyes),
                ...$this->treatmentCharts($patients, $visits, $treatments),
            ],
            'crosstabs' => [
                $this->crosstab(__('ROP by gestational age (weeks)'), $patients, 'ga_weeks', self::GA_GROUPS),
                $this->crosstab(__('ROP by birth weight (g)'), $patients, 'birth_weight_g', self::WEIGHT_GROUPS),
            ],
            'monthly' => $this->monthly($patients, $visits, $treatments),
        ];
    }

    protected function firstSeen(Patient $patient): ?CarbonInterface
    {
        return $patient->first_visit_date ?? $patient->referral_date ?? $patient->dob;
    }

    /**
     * Summarise every eye of the cohort across all of its recorded visits.
     *
     * @param  Collection<int, Patient>  $patients
     * @return Collection<int, array{rop: bool|null, stage: Stage|null, zone: Zone|null, plus: PlusDisease|null, treated: bool}>
     */
    protected function eyes(Collection $patients): Collection
    {
        $zoneOrder = [Zone::ZoneOne->value => 1, Zone::PosteriorZoneTwo->value => 2, Zone::ZoneTwo->value => 3, Zone::ZoneThree->value => 4];
        $plusOrder = [PlusDisease::Plus->value => 3, PlusDisease::PrePlus->value => 2, PlusDisease::None->value => 1];

        return $patients->flatMap(function (Patient $patient) use ($zoneOrder, $plusOrder): array {
            return collect(Visit::EYES)->map(function (string $eye) use ($patient, $zoneOrder, $plusOrder): array {
                $visits = $patient->visits;
                $side = EyeSide::from($eye);

                $hasRop = $visits->contains(fn (Visit $visit): bool => $visit->eyeHasRop($eye));
                $excluded = $visits->contains(fn (Visit $visit): bool => $visit->eyeExcludesRop($eye));

                return [
                    'rop' => $hasRop ? true : ($excluded ? false : null),
                    'stage' => $visits->pluck("{$eye}_stage")
                        ->filter(fn (?Stage $stage): bool => $stage?->severity() !== null)
                        ->sortByDesc(fn (Stage $stage): int => (int) $stage->severity())
                        ->first(),
                    'zone' => $visits->pluck("{$eye}_zone")
                        ->filter(fn (?Zone $zone): bool => $zone !== null && isset($zoneOrder[$zone->value]))
                        ->sortBy(fn (Zone $zone): int => $zoneOrder[$zone->value])
                        ->first(),
                    'plus' => $visits->pluck("{$eye}_plus")
                        ->filter(fn (?PlusDisease $plus): bool => $plus !== null && isset($plusOrder[$plus->value]))
                        ->sortByDesc(fn (PlusDisease $plus): int => $plusOrder[$plus->value])
                        ->first(),
                    'treated' => $patient->treatments->contains(
                        fn (Treatment $treatment): bool => $treatment->eye === $side || $treatment->eye === EyeSide::Both,
                    ),
                ];
            })->all();
        })->values();
    }

    /**
     * @param  Collection<int, Patient>  $patients
     * @param  Collection<int, Visit>  $visits
     * @param  Collection<int, Treatment>  $treatments
     * @param  Collection<int, array<string, mixed>>  $eyes
     * @return list<array{key: string, label: string, value: int|string, hint: string|null}>
     */
    protected function kpis(Collection $patients, Collection $visits, Collection $treatments, Collection $eyes): array
    {
        $total = $patients->count();
        $withRop = $patients->where('any_rop', true)->count();
        $knownRop = $patients->whereNotNull('any_rop')->count();
        $exams = $visits->filter(fn (Visit $visit): bool => $visit->kind->isExamination());
        $treated = $patients->filter(fn (Patient $patient): bool => $patient->treatments->isNotEmpty())->count();

        return [
            ['key' => 'patients', 'label' => __('Patients'), 'value' => $total, 'hint' => null],
            ['key' => 'examinations', 'label' => __('Examinations'), 'value' => $exams->count(), 'hint' => __(':count with a recorded date', ['count' => $exams->whereNotNull('visit_date')->count()])],
            ['key' => 'rop', 'label' => __('Patients with documented ROP'), 'value' => $withRop, 'hint' => $knownRop > 0 ? __(':percent of :count with a known ROP status', ['percent' => $this->percent($withRop, $knownRop), 'count' => $knownRop]) : null],
            ['key' => 'eyes', 'label' => __('Affected eyes'), 'value' => $eyes->where('rop', true)->count(), 'hint' => __('of :count eyes', ['count' => $eyes->count()])],
            ['key' => 'type_one', 'label' => __('Type 1 ROP (treatment criteria)'), 'value' => $patients->where('type_one', true)->count(), 'hint' => __('patients')],
            ['key' => 'treated', 'label' => __('Patients treated'), 'value' => $treated, 'hint' => $withRop > 0 ? __(':percent of patients with ROP', ['percent' => $this->percent($treated, $withRop)]) : null],
            ['key' => 'injections', 'label' => __('Injection sessions'), 'value' => $treatments->filter(fn (Treatment $treatment): bool => $treatment->type->isInjection())->count(), 'hint' => __(':count patients', ['count' => $patients->where('had_injection', true)->count()])],
            ['key' => 'laser', 'label' => __('Laser sessions'), 'value' => $treatments->where('type', TreatmentType::Laser)->count(), 'hint' => __(':count patients', ['count' => $patients->where('had_laser', true)->count()])],
        ];
    }

    /**
     * @param  Collection<int, Patient>  $patients
     * @param  Collection<int, Treatment>  $treatments
     * @return list<array<string, mixed>>
     */
    protected function numericSummaries(Collection $patients, Collection $treatments): array
    {
        $firstExamPma = $patients->map(fn (Patient $patient): ?int => $patient->postmenstrualAgeInDays($patient->first_visit_date));
        $firstExamAge = $patients->map(fn (Patient $patient): ?int => $patient->chronologicalAgeInDays($patient->first_visit_date));
        $treatmentPma = $patients->map(function (Patient $patient): ?int {
            $first = $patient->treatments->whereNotNull('performed_date')->min('performed_date');

            return $first === null ? null : $patient->postmenstrualAgeInDays($first);
        });

        return [
            $this->numeric(__('Gestational age (weeks)'), $patients->map(fn (Patient $patient): ?float => $patient->gestationalAgeInDays() === null ? null : $patient->gestationalAgeInDays() / 7)),
            $this->numeric(__('Birth weight (g)'), $patients->pluck('birth_weight_g'), 0),
            $this->numeric(__('NICU stay (days)'), $patients->pluck('nicu_days')),
            $this->numeric(__('Respiratory support (days)'), $patients->pluck('support_days')),
            $this->numeric(__('Age at first examination (days)'), $firstExamAge),
            $this->numeric(__('PMA at first examination (weeks)'), $firstExamPma->map(fn (?int $days): ?float => $days === null ? null : $days / 7)),
            $this->numeric(__('PMA at first treatment (weeks)'), $treatmentPma->map(fn (?int $days): ?float => $days === null ? null : $days / 7)),
            $this->numeric(__('Examinations per patient'), $patients->pluck('exams_count')),
        ];
    }

    /**
     * @param  Collection<int, int|float|null>  $values
     * @return array{label: string, n: int, missing: int, mean: float|null, median: float|null, min: float|null, max: float|null}
     */
    protected function numeric(string $label, Collection $values, int $precision = 1): array
    {
        $known = $values->filter(fn (mixed $value): bool => $value !== null)->map(fn (mixed $value): float => (float) $value)->sort()->values();

        return [
            'label' => $label,
            'n' => $known->count(),
            'missing' => $values->count() - $known->count(),
            'mean' => $known->isEmpty() ? null : round((float) $known->avg(), $precision),
            'median' => $known->isEmpty() ? null : round((float) $known->median(), $precision),
            'min' => $known->isEmpty() ? null : round((float) $known->first(), $precision),
            'max' => $known->isEmpty() ? null : round((float) $known->last(), $precision),
        ];
    }

    /**
     * @param  Collection<int, Patient>  $patients
     * @return array<string, array<string, mixed>>
     */
    protected function demographicCharts(Collection $patients): array
    {
        return [
            'sex' => $this->enumChart(__('Sex'), $patients->pluck('sex'), Sex::cases(), __('patients'), [Sex::Unknown]),
            'gestational_age' => $this->groupChart(__('Gestational age (weeks)'), $patients->pluck('ga_weeks'), self::GA_GROUPS, __('patients')),
            'birth_weight' => $this->groupChart(__('Birth weight (g)'), $patients->pluck('birth_weight_g'), self::WEIGHT_GROUPS, __('patients')),
            'multiplicity' => $this->enumChart(__('Multiplicity'), $patients->pluck('multiplicity'), Multiplicity::cases(), __('patients'), [Multiplicity::Unknown]),
            'delivery_mode' => $this->enumChart(__('Delivery mode'), $patients->pluck('delivery_mode'), DeliveryMode::cases(), __('patients')),
            'respiratory_support' => $this->enumChart(__('Respiratory support'), $patients->pluck('respiratory_support'), RespiratorySupport::cases(), __('patients')),
            'nicu_stay' => $this->groupChart(__('NICU stay (days)'), $patients->pluck('nicu_days'), self::NICU_GROUPS, __('patients')),
            'status' => $this->enumChart(__('Follow-up status'), $patients->pluck('status'), PatientStatus::cases(), __('patients')),
        ];
    }

    /**
     * @param  Collection<int, Patient>  $patients
     * @param  Collection<int, array<string, mixed>>  $eyes
     * @return array<string, array<string, mixed>>
     */
    protected function ropCharts(Collection $patients, Collection $eyes): array
    {
        $affected = $eyes->where('rop', true);

        return [
            'rop' => $this->chart(__('Documented ROP'), __('patients'), $patients->count(), $patients->whereNull('any_rop')->count(), [
                ['label' => __('ROP documented'), 'value' => $patients->where('any_rop', true)->count()],
                ['label' => __('No ROP documented'), 'value' => $patients->whereStrict('any_rop', false)->count()],
            ]),
            'highest_stage' => $this->enumChart(__('Highest documented stage'), $patients->pluck('highest_stage'), $this->stagedCases(), __('patients')),
            'eye_rop' => $this->chart(__('Eyes with documented ROP'), __('eyes'), $eyes->count(), $eyes->whereNull('rop')->count(), [
                ['label' => __('Affected'), 'value' => $affected->count()],
                ['label' => __('Not affected'), 'value' => $eyes->whereStrict('rop', false)->count()],
            ]),
            'eye_stage' => $this->enumChart(__('Highest stage per affected eye'), $affected->pluck('stage'), $this->stagedCases(), __('eyes')),
            'eye_zone' => $this->enumChart(__('Most posterior zone per affected eye'), $affected->pluck('zone'), [Zone::ZoneOne, Zone::PosteriorZoneTwo, Zone::ZoneTwo, Zone::ZoneThree], __('eyes')),
            'eye_plus' => $this->enumChart(__('Worst plus status per eye'), $eyes->pluck('plus'), [PlusDisease::None, PlusDisease::PrePlus, PlusDisease::Plus], __('eyes')),
            'laterality' => $this->chart(__('Laterality among patients with ROP'), __('patients'), $patients->where('any_rop', true)->count(), 0, $this->laterality($patients)),
        ];
    }

    /**
     * @param  Collection<int, Patient>  $patients
     * @return list<array{label: string, value: int}>
     */
    protected function laterality(Collection $patients): array
    {
        $counts = ['both' => 0, 'one' => 0, 'unattributed' => 0];

        foreach ($patients->where('any_rop', true) as $patient) {
            $affected = collect(Visit::EYES)
                ->filter(fn (string $eye): bool => $patient->visits->contains(fn (Visit $visit): bool => $visit->eyeHasRop($eye)))
                ->count();

            $counts[match ($affected) {
                2 => 'both',
                1 => 'one',
                default => 'unattributed',
            }]++;
        }

        return [
            ['label' => __('Both eyes'), 'value' => $counts['both']],
            ['label' => __('One eye'), 'value' => $counts['one']],
            ['label' => __('Eye not attributed'), 'value' => $counts['unattributed']],
        ];
    }

    /**
     * @param  Collection<int, Patient>  $patients
     * @param  Collection<int, Visit>  $visits
     * @param  Collection<int, Treatment>  $treatments
     * @return array<string, array<string, mixed>>
     */
    protected function treatmentCharts(Collection $patients, Collection $visits, Collection $treatments): array
    {
        $injected = $patients->where('had_injection', true);
        $lasered = $patients->where('had_laser', true);
        $both = $injected->where('had_laser', true)->count();
        $plannedInjection = $patients->filter(fn (Patient $patient): bool => $patient->visits->contains(fn (Visit $visit): bool => $visit->management_plan?->includesInjection() ?? false));
        $plannedLaser = $patients->filter(fn (Patient $patient): bool => $patient->visits->contains(fn (Visit $visit): bool => $visit->management_plan?->includesLaser() ?? false));

        return [
            'treated_patients' => $this->chart(__('Completed treatment'), __('patients'), $patients->count(), 0, [
                ['label' => __('Injection only'), 'value' => $injected->count() - $both],
                ['label' => __('Laser only'), 'value' => $lasered->count() - $both],
                ['label' => __('Injection and laser'), 'value' => $both],
                ['label' => __('No treatment recorded'), 'value' => $patients->count() - $injected->count() - $lasered->count() + $both],
            ]),
            'treatment_sessions' => $this->enumChart(__('Treatment sessions by type'), $treatments->pluck('type'), TreatmentType::cases(), __('sessions')),
            'treated_eyes' => $this->chart(__('Eyes treated per session type'), __('eyes'), 0, 0, collect(TreatmentType::cases())
                ->map(fn (TreatmentType $type): array => [
                    'label' => $type->label(),
                    'value' => (int) $treatments->where('type', $type)->sum(fn (Treatment $treatment): int => $treatment->eyesCount()),
                ])
                ->filter(fn (array $item): bool => $item['value'] > 0)
                ->values()
                ->all()),
            'plan_vs_performed' => $this->chart(__('Recommended versus performed'), __('patients'), $patients->count(), 0, [
                ['label' => __('Injection recommended'), 'value' => $plannedInjection->count()],
                ['label' => __('Injection performed'), 'value' => $injected->count()],
                ['label' => __('Laser recommended'), 'value' => $plannedLaser->count()],
                ['label' => __('Laser performed'), 'value' => $lasered->count()],
                ['label' => __('Referred to Baghdad'), 'value' => $patients->filter(fn (Patient $patient): bool => $patient->visits->contains('management_plan', ManagementPlan::Referred))->count()],
            ]),
            'management_plans' => $this->enumChart(__('Management plans recorded at visits'), $visits->pluck('management_plan'), ManagementPlan::cases(), __('visits')),
            'follow_up' => $this->chart(__('Examinations per patient'), __('patients'), $patients->count(), 0, collect([0, 1, 2, 3, 4])
                ->map(fn (int $count): array => ['label' => (string) $count, 'value' => $patients->where('exams_count', $count)->count()])
                ->push(['label' => '5+', 'value' => $patients->where('exams_count', '>=', 5)->count()])
                ->all()),
        ];
    }

    /**
     * Incidence of retinopathy and treatment within each range of a numeric attribute.
     *
     * @param  Collection<int, Patient>  $patients
     * @param  list<array{0: int, 1: int, 2: string}>  $groups
     * @return array{title: string, rows: list<array<string, mixed>>}
     */
    protected function crosstab(string $title, Collection $patients, string $attribute, array $groups): array
    {
        $rows = [];

        foreach ([...$groups, [null, null, __('Not recorded')]] as [$min, $max, $label]) {
            $group = $patients->filter(function (Patient $patient) use ($attribute, $min, $max): bool {
                $value = $patient->getAttribute($attribute);

                return $min === null ? $value === null : ($value !== null && $value >= $min && $value <= $max);
            });

            if ($min === null && $group->isEmpty()) {
                continue;
            }

            $known = $group->whereNotNull('any_rop')->count();
            $withRop = $group->where('any_rop', true)->count();

            $rows[] = [
                'label' => $label,
                'patients' => $group->count(),
                'known' => $known,
                'rop' => $withRop,
                'rop_percent' => $known > 0 ? round($withRop / $known * 100, 1) : null,
                'type_one' => $group->where('type_one', true)->count(),
                'treated' => $group->filter(fn (Patient $patient): bool => $patient->had_injection || $patient->had_laser)->count(),
            ];
        }

        return ['title' => $title, 'rows' => $rows];
    }

    /**
     * @param  Collection<int, Patient>  $patients
     * @param  Collection<int, Visit>  $visits
     * @param  Collection<int, Treatment>  $treatments
     * @return list<array{month: string, new_patients: int, examinations: int, treatments: int}>
     */
    protected function monthly(Collection $patients, Collection $visits, Collection $treatments): array
    {
        $newPatients = $patients->map(fn (Patient $patient): ?string => $this->firstSeen($patient)?->format('Y-m'))->filter()->countBy();
        $examinations = $visits->map(fn (Visit $visit): ?string => $visit->visit_date?->format('Y-m'))->filter()->countBy();
        $performed = $treatments->map(fn (Treatment $treatment): ?string => $treatment->performed_date?->format('Y-m'))->filter()->countBy();

        $months = $examinations->keys()->merge($performed->keys())->merge($newPatients->keys())->unique()->sort()->values();

        if ($months->isEmpty()) {
            return [];
        }

        $cursor = Date::parse($months->first().'-01');
        $end = Date::parse($months->last().'-01');
        $rows = [];

        while ($cursor->lte($end) && count($rows) < 120) {
            $key = $cursor->format('Y-m');

            $rows[] = [
                'month' => $key,
                'new_patients' => (int) $newPatients->get($key, 0),
                'examinations' => (int) $examinations->get($key, 0),
                'treatments' => (int) $performed->get($key, 0),
            ];

            $cursor = $cursor->addMonth();
        }

        return $rows;
    }

    /**
     * @return list<Stage>
     */
    protected function stagedCases(): array
    {
        return array_values(array_filter(Stage::cases(), fn (Stage $stage): bool => $stage->severity() !== null));
    }

    /**
     * @param  Collection<int, mixed>  $values
     * @param  array<int, BackedEnum>  $cases
     * @param  array<int, BackedEnum>  $unknownCases
     * @return array<string, mixed>
     */
    protected function enumChart(string $title, Collection $values, array $cases, string $unit, array $unknownCases = []): array
    {
        $items = [];
        $unknown = 0;

        foreach ($cases as $case) {
            $count = $values->filter(fn (mixed $value): bool => $value === $case)->count();

            if (in_array($case, $unknownCases, true)) {
                $unknown += $count;

                continue;
            }

            $items[] = ['label' => method_exists($case, 'label') ? $case->label() : (string) $case->value, 'value' => $count];
        }

        $known = array_sum(array_column($items, 'value'));

        return $this->chart($title, $unit, $values->count(), $values->count() - $known, $items);
    }

    /**
     * @param  Collection<int, int|null>  $values
     * @param  list<array{0: int, 1: int, 2: string}>  $groups
     * @return array<string, mixed>
     */
    protected function groupChart(string $title, Collection $values, array $groups, string $unit): array
    {
        $items = array_map(fn (array $group): array => [
            'label' => $group[2],
            'value' => $values->filter(fn (?int $value): bool => $value !== null && $value >= $group[0] && $value <= $group[1])->count(),
        ], $groups);

        return $this->chart($title, $unit, $values->count(), $values->filter(fn (?int $value): bool => $value === null)->count(), $items);
    }

    /**
     * @param  list<array{label: string, value: int}>  $items
     * @return array{title: string, unit: string, total: int, unknown: int, items: list<array{label: string, value: int}>}
     */
    protected function chart(string $title, string $unit, int $total, int $unknown, array $items): array
    {
        return ['title' => $title, 'unit' => $unit, 'total' => $total, 'unknown' => $unknown, 'items' => array_values($items)];
    }

    protected function percent(int $part, int $whole): string
    {
        return $whole === 0 ? '—' : round($part / $whole * 100, 1).'%';
    }
}
