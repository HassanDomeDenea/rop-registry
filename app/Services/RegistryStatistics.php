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
use App\Models\Suggestion;
use App\Models\Treatment;
use App\Models\Visit;
use BackedEnum;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * Calculates the registry statistics for a cohort of patients.
 *
 * Patient counts, eye counts and treatment-session counts are kept separate, and every
 * distribution reports its denominator and the number of unknown / unrecorded values.
 *
 * @phpstan-type EyeSummary array{rop: bool|null, stage: Stage|null, zone: Zone|null, plus: PlusDisease|null, treated: bool}
 * @phpstan-type ChartItem array{label: string, value: int}
 */
class RegistryStatistics
{
    protected const GA_GROUPS = [[0, 27, '< 28'], [28, 29, '28–29'], [30, 31, '30–31'], [32, 33, '32–33'], [34, 36, '34–36'], [37, 99, '≥ 37']];

    protected const WEIGHT_GROUPS = [[0, 999, '< 1000'], [1000, 1499, '1000–1499'], [1500, 1999, '1500–1999'], [2000, 2499, '2000–2499'], [2500, 99999, '≥ 2500']];

    protected const NICU_GROUPS = [[0, 7, '0–7'], [8, 14, '8–14'], [15, 28, '15–28'], [29, 60, '29–60'], [61, 9999, '> 60']];

    /** Chronological age at the first examination, in days: screening is due by 4 to 6 weeks. */
    protected const FIRST_EXAM_GROUPS = [[0, 27, '< 4 weeks'], [28, 42, '4–6 weeks'], [43, 99999, '> 6 weeks']];

    /**
     * @return array<string, mixed>
     */
    public function calculate(?CarbonInterface $from, ?CarbonInterface $to, bool $includeUnverified = false): array
    {
        $all = Patient::query()
            ->when(! $includeUnverified, fn ($query) => $query->verified())
            ->with(['visits', 'treatments'])
            ->get();

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
                'unverified' => Patient::query()->where('unverified', true)->count(),
                'excluded_undated' => ($from !== null || $to !== null)
                    ? $all->filter(fn (Patient $patient): bool => $this->firstSeen($patient) === null)->count()
                    : 0,
            ],
            'kpis' => $this->kpis($patients, $visits, $treatments, $eyes),
            'numeric' => $this->numericSummaries($patients, $treatments),
            'charts' => [
                ...$this->demographicCharts($patients),
                ...$this->ropCharts($patients, $eyes),
                ...$this->findingCharts($patients),
                ...$this->treatmentCharts($patients, $visits, $treatments),
            ],
            'crosstabs' => [
                $this->crosstab($this->text('ROP by gestational age (weeks)'), $this->rangeGroups($patients, 'ga_weeks', self::GA_GROUPS)),
                $this->crosstab($this->text('ROP by birth weight (g)'), $this->rangeGroups($patients, 'birth_weight_g', self::WEIGHT_GROUPS)),
                $this->crosstab($this->text('ROP by respiratory support'), $this->enumGroups($patients, 'respiratory_support', RespiratorySupport::cases())),
                $this->crosstab($this->text('ROP by NICU stay (days)'), $this->rangeGroups($patients, 'nicu_days', self::NICU_GROUPS)),
                $this->crosstab($this->text('ROP by multiplicity'), $this->enumGroups($patients, 'multiplicity', Multiplicity::cases())),
                $this->crosstab($this->text('ROP by age at first examination'), $this->rangeGroups(
                    $patients,
                    fn (Patient $patient): ?int => $patient->chronologicalAgeInDays($patient->first_visit_date),
                    self::FIRST_EXAM_GROUPS,
                )),
            ],
            'doctors' => $this->crosstab($this->text('Patients by referring doctor'), $this->doctorGroups($patients)),
            'monthly' => $this->monthly($patients, $visits, $treatments),
            'fees' => $this->fees($visits),
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
     * @return Collection<int, EyeSummary>
     */
    protected function eyes(Collection $patients): Collection
    {
        $eyes = [];

        foreach ($patients as $patient) {
            foreach (EyeSide::cases() as $side) {
                if ($side === EyeSide::Both) {
                    continue;
                }

                $eyes[] = $this->eye($patient, $side);
            }
        }

        return collect($eyes);
    }

    /**
     * @return EyeSummary
     */
    protected function eye(Patient $patient, EyeSide $side): array
    {
        $eye = $side->value;
        $visits = $patient->visits;
        $isRight = $side === EyeSide::Right;

        $hasRop = $visits->contains(fn (Visit $visit): bool => $visit->eyeHasRop($eye));
        $excluded = $visits->contains(fn (Visit $visit): bool => $visit->eyeExcludesRop($eye));

        $stage = null;
        $zone = null;
        $plus = null;

        foreach ($visits as $visit) {
            $visitStage = $isRight ? $visit->right_stage : $visit->left_stage;
            $visitZone = $isRight ? $visit->right_zone : $visit->left_zone;
            $visitPlus = $isRight ? $visit->right_plus : $visit->left_plus;

            if ($visitStage?->severity() !== null && ($stage === null || $visitStage->severity() > $stage->severity())) {
                $stage = $visitStage;
            }

            if ($visitZone?->posteriority() !== null && ($zone === null || $visitZone->posteriority() < $zone->posteriority())) {
                $zone = $visitZone;
            }

            if ($visitPlus?->severity() !== null && ($plus === null || $visitPlus->severity() > $plus->severity())) {
                $plus = $visitPlus;
            }
        }

        return [
            'rop' => $hasRop ? true : ($excluded ? false : null),
            'stage' => $stage,
            'zone' => $zone,
            'plus' => $plus,
            'treated' => $patient->treatments->contains(
                fn (Treatment $treatment): bool => $treatment->eye === $side || $treatment->eye === EyeSide::Both,
            ),
        ];
    }

    /**
     * @param  Collection<int, Patient>  $patients
     * @param  Collection<int, Visit>  $visits
     * @param  Collection<int, Treatment>  $treatments
     * @param  Collection<int, EyeSummary>  $eyes
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
            ['key' => 'patients', 'label' => $this->text('Patients'), 'value' => $total, 'hint' => null],
            ['key' => 'examinations', 'label' => $this->text('Examinations'), 'value' => $exams->count(), 'hint' => $this->text(':count with a recorded date', ['count' => $exams->whereNotNull('visit_date')->count()])],
            ['key' => 'rop', 'label' => $this->text('Patients with documented ROP'), 'value' => $withRop, 'hint' => $knownRop > 0 ? $this->text(':percent of :count with a known ROP status', ['percent' => $this->percent($withRop, $knownRop), 'count' => $knownRop]) : null],
            ['key' => 'eyes', 'label' => $this->text('Affected eyes'), 'value' => $eyes->where('rop', true)->count(), 'hint' => $this->text('of :count eyes', ['count' => $eyes->count()])],
            ['key' => 'type_one', 'label' => $this->text('Type 1 ROP (treatment criteria)'), 'value' => $patients->where('type_one', true)->count(), 'hint' => $this->text('patients')],
            ['key' => 'treated', 'label' => $this->text('Patients treated'), 'value' => $treated, 'hint' => $withRop > 0 ? $this->text(':percent of patients with ROP', ['percent' => $this->percent($treated, $withRop)]) : null],
            ['key' => 'injections', 'label' => $this->text('Injection sessions'), 'value' => $treatments->filter(fn (Treatment $treatment): bool => $treatment->type->isInjection())->count(), 'hint' => $this->text(':count patients', ['count' => $patients->where('had_injection', true)->count()])],
            ['key' => 'laser', 'label' => $this->text('Laser sessions'), 'value' => $treatments->where('type', TreatmentType::Laser)->count(), 'hint' => $this->text(':count patients', ['count' => $patients->where('had_laser', true)->count()])],
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
            $this->numeric($this->text('Gestational age (weeks)'), $patients->map(fn (Patient $patient): ?float => $patient->gestationalAgeInDays() === null ? null : $patient->gestationalAgeInDays() / 7)),
            $this->numeric($this->text('Birth weight (g)'), $patients->pluck('birth_weight_g'), 0),
            $this->numeric($this->text('NICU stay (days)'), $patients->pluck('nicu_days')),
            $this->numeric($this->text('Days on O₂'), $this->supportDays($patients, RespiratorySupport::Oxygen, 'o2_days')),
            $this->numeric($this->text('Days on CPAP'), $this->supportDays($patients, RespiratorySupport::Cpap, 'cpap_days')),
            $this->numeric($this->text('Age at first examination (days)'), $firstExamAge),
            $this->numeric($this->text('PMA at first examination (weeks)'), $firstExamPma->map(fn (?int $days): ?float => $days === null ? null : $days / 7)),
            $this->numeric($this->text('PMA at first treatment (weeks)'), $treatmentPma->map(fn (?int $days): ?float => $days === null ? null : $days / 7)),
            $this->numeric($this->text('Examinations per patient'), $patients->pluck('exams_count')),
        ];
    }

    /**
     * The days on one type of support, among the patients who received it: alone it is kept in
     * `support_days`, together with the other type in its own column. O₂ and CPAP days may
     * overlap, so they are never added up; an earlier combined total counts as not recorded.
     *
     * @param  Collection<int, Patient>  $patients
     * @return Collection<int, int|null>
     */
    protected function supportDays(Collection $patients, RespiratorySupport $alone, string $column): Collection
    {
        return $patients
            ->filter(fn (Patient $patient): bool => in_array($patient->respiratory_support, [$alone, RespiratorySupport::OxygenAndCpap], true))
            ->map(fn (Patient $patient): ?int => $patient->respiratory_support === $alone ? $patient->support_days : $patient->getAttribute($column))
            ->values();
    }

    /**
     * @param  iterable<int, int|float|null>  $values
     * @return array{label: string, n: int, missing: int, mean: float|null, median: float|null, min: float|null, max: float|null}
     */
    protected function numeric(string $label, iterable $values, int $precision = 1): array
    {
        $values = collect($values);
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
            'sex' => $this->enumChart($this->text('Sex'), $patients->pluck('sex'), Sex::cases(), $this->text('patients'), [Sex::Unknown]),
            'gestational_age' => $this->groupChart($this->text('Gestational age (weeks)'), $patients->pluck('ga_weeks'), self::GA_GROUPS, $this->text('patients')),
            'birth_weight' => $this->groupChart($this->text('Birth weight (g)'), $patients->pluck('birth_weight_g'), self::WEIGHT_GROUPS, $this->text('patients')),
            'multiplicity' => $this->enumChart($this->text('Multiplicity'), $patients->pluck('multiplicity'), Multiplicity::cases(), $this->text('patients'), [Multiplicity::Unknown]),
            'delivery_mode' => $this->enumChart($this->text('Delivery mode'), $patients->pluck('delivery_mode'), DeliveryMode::cases(), $this->text('patients')),
            'respiratory_support' => $this->enumChart($this->text('Respiratory support'), $patients->pluck('respiratory_support'), RespiratorySupport::cases(), $this->text('patients')),
            'nicu_stay' => $this->groupChart($this->text('NICU stay (days)'), $patients->pluck('nicu_days'), self::NICU_GROUPS, $this->text('patients')),
            'status' => $this->enumChart($this->text('Follow-up status'), $patients->pluck('status'), PatientStatus::cases(), $this->text('patients')),
        ];
    }

    /**
     * @param  Collection<int, Patient>  $patients
     * @param  Collection<int, EyeSummary>  $eyes
     * @return array<string, array<string, mixed>>
     */
    protected function ropCharts(Collection $patients, Collection $eyes): array
    {
        $affected = $eyes->where('rop', true);

        return [
            'rop' => $this->chart($this->text('Documented ROP'), $this->text('patients'), $patients->count(), $patients->whereNull('any_rop')->count(), [
                ['label' => $this->text('ROP documented'), 'value' => $patients->where('any_rop', true)->count()],
                ['label' => $this->text('No ROP documented'), 'value' => $patients->whereStrict('any_rop', false)->count()],
            ]),
            'highest_stage' => $this->enumChart($this->text('Highest documented stage'), $patients->pluck('highest_stage'), $this->stagedCases(), $this->text('patients')),
            'eye_rop' => $this->chart($this->text('Eyes with documented ROP'), $this->text('eyes'), $eyes->count(), $eyes->whereNull('rop')->count(), [
                ['label' => $this->text('Affected'), 'value' => $affected->count()],
                ['label' => $this->text('Not affected'), 'value' => $eyes->whereStrict('rop', false)->count()],
            ]),
            'eye_stage' => $this->enumChart($this->text('Highest stage per affected eye'), $affected->pluck('stage'), $this->stagedCases(), $this->text('eyes')),
            'eye_zone' => $this->enumChart($this->text('Most posterior zone per affected eye'), $affected->pluck('zone'), [Zone::ZoneOne, Zone::PosteriorZoneTwo, Zone::ZoneTwo, Zone::ZoneThree], $this->text('eyes')),
            'eye_plus' => $this->enumChart($this->text('Worst plus status per eye'), $eyes->pluck('plus'), [PlusDisease::None, PlusDisease::PrePlus, PlusDisease::Plus], $this->text('eyes')),
            'laterality' => $this->chart($this->text('Laterality among patients with ROP'), $this->text('patients'), $patients->where('any_rop', true)->count(), 0, $this->laterality($patients)),
        ];
    }

    /**
     * Patients and eyes with each finding of the eye findings list ticked at one visit or more.
     * A finding that was not ticked is not recorded, which is not the same as absent.
     *
     * @param  Collection<int, Patient>  $patients
     * @return array<string, array<string, mixed>>
     */
    protected function findingCharts(Collection $patients): array
    {
        $patientItems = [];
        $eyeItems = [];

        foreach (Suggestion::eyeFindings() as $finding) {
            $eyes = 0;
            $withFinding = 0;

            foreach ($patients as $patient) {
                $affected = collect(Visit::EYES)
                    ->filter(fn (string $eye): bool => $patient->visits->contains(
                        fn (Visit $visit): bool => in_array($finding['id'], $visit->eyeFindings($eye), true),
                    ))
                    ->count();

                $eyes += $affected;
                $withFinding += $affected > 0 ? 1 : 0;
            }

            // A hidden entry is listed only while visits of the cohort still hold it.
            if ($finding['hidden'] && $withFinding === 0) {
                continue;
            }

            $patientItems[] = ['label' => $finding['label'], 'value' => $withFinding];
            $eyeItems[] = ['label' => $finding['label'], 'value' => $eyes];
        }

        if ($patientItems === []) {
            return [];
        }

        // Most frequent first, so the findings never recorded come last.
        $byValue = fn (array $items): array => collect($items)->sortByDesc('value')->values()->all();
        $patientItems = $byValue($patientItems);
        $eyeItems = $byValue($eyeItems);

        return [
            'finding_patients' => $this->chart($this->text('Patients with each eye finding'), $this->text('patients'), $patients->count(), 0, $patientItems),
            'finding_eyes' => $this->chart($this->text('Eyes with each eye finding'), $this->text('eyes'), $patients->count() * 2, 0, $eyeItems),
        ];
    }

    /**
     * @param  Collection<int, Patient>  $patients
     * @return list<ChartItem>
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
            ['label' => $this->text('Both eyes'), 'value' => $counts['both']],
            ['label' => $this->text('One eye'), 'value' => $counts['one']],
            ['label' => $this->text('Eye not attributed'), 'value' => $counts['unattributed']],
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
            'treated_patients' => $this->chart($this->text('Completed treatment'), $this->text('patients'), $patients->count(), 0, [
                ['label' => $this->text('Injection only'), 'value' => $injected->count() - $both],
                ['label' => $this->text('Laser only'), 'value' => $lasered->count() - $both],
                ['label' => $this->text('Injection and laser'), 'value' => $both],
                ['label' => $this->text('No treatment recorded'), 'value' => $patients->count() - $injected->count() - $lasered->count() + $both],
            ]),
            'treatment_sessions' => $this->enumChart($this->text('Treatment sessions by type'), $treatments->pluck('type'), TreatmentType::cases(), $this->text('sessions')),
            'treated_eyes' => $this->chart($this->text('Eyes treated per session type'), $this->text('eyes'), 0, 0, collect(TreatmentType::cases())
                ->map(fn (TreatmentType $type): array => [
                    'label' => $type->label(),
                    'value' => (int) $treatments->where('type', $type)->sum(fn (Treatment $treatment): int => $treatment->eyesCount()),
                ])
                ->filter(fn (array $item): bool => $item['value'] > 0)
                ->values()
                ->all()),
            'plan_vs_performed' => $this->chart($this->text('Recommended versus performed'), $this->text('patients'), $patients->count(), 0, [
                ['label' => $this->text('Injection recommended'), 'value' => $plannedInjection->count()],
                ['label' => $this->text('Injection performed'), 'value' => $injected->count()],
                ['label' => $this->text('Laser recommended'), 'value' => $plannedLaser->count()],
                ['label' => $this->text('Laser performed'), 'value' => $lasered->count()],
                ['label' => $this->text('Referred to Baghdad'), 'value' => $patients->filter(fn (Patient $patient): bool => $patient->visits->contains('management_plan', ManagementPlan::Referred))->count()],
            ]),
            'management_plans' => $this->enumChart($this->text('Management plans recorded at visits'), $visits->pluck('management_plan'), ManagementPlan::cases(), $this->text('visits')),
            'follow_up' => $this->chart($this->text('Examinations per patient'), $this->text('patients'), $patients->count(), 0, collect([0, 1, 2, 3, 4])
                ->map(fn (int $count): array => ['label' => (string) $count, 'value' => $patients->where('exams_count', $count)->count()])
                ->push(['label' => '5+', 'value' => $patients->where('exams_count', '>=', 5)->count()])
                ->all()),
        ];
    }

    /**
     * Incidence of retinopathy and treatment within each group of patients.
     *
     * @param  array<string, Collection<int, Patient>>  $groups
     * @return array{title: string, rows: list<array<string, mixed>>}
     */
    protected function crosstab(string $title, array $groups): array
    {
        $rows = [];

        foreach ($groups as $label => $group) {
            $known = $group->whereNotNull('any_rop')->count();
            $withRop = $group->where('any_rop', true)->count();

            $rows[] = [
                'label' => (string) $label,
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
     * The patients within each range of a numeric attribute, then those without a value, if any.
     *
     * @param  Collection<int, Patient>  $patients
     * @param  string|Closure(Patient): (int|null)  $attribute  the attribute, or what to read from each patient
     * @param  list<array{0: int, 1: int, 2: string}>  $ranges
     * @return array<string, Collection<int, Patient>>
     */
    protected function rangeGroups(Collection $patients, string|Closure $attribute, array $ranges): array
    {
        $values = $patients->map(fn (Patient $patient): mixed => is_string($attribute) ? $patient->getAttribute($attribute) : $attribute($patient));
        $groups = [];

        foreach ($ranges as [$min, $max, $label]) {
            $groups[$this->text($label)] = $patients->filter(
                fn (Patient $patient, int $key): bool => $values[$key] !== null && $values[$key] >= $min && $values[$key] <= $max,
            );
        }

        return $this->withNotRecorded($groups, $patients->filter(fn (Patient $patient, int $key): bool => $values[$key] === null));
    }

    /**
     * The patients with each value of an enum attribute, then those without a value, if any.
     *
     * @param  Collection<int, Patient>  $patients
     * @param  array<int, BackedEnum>  $cases
     * @return array<string, Collection<int, Patient>>
     */
    protected function enumGroups(Collection $patients, string $attribute, array $cases): array
    {
        $groups = [];

        foreach ($cases as $case) {
            $group = $patients->filter(fn (Patient $patient): bool => $patient->getAttribute($attribute) === $case);

            if ($group->isNotEmpty()) {
                $groups[method_exists($case, 'label') ? $case->label() : (string) $case->value] = $group;
            }
        }

        return $this->withNotRecorded($groups, $patients->filter(fn (Patient $patient): bool => $patient->getAttribute($attribute) === null));
    }

    /**
     * The patients of each referring doctor, most referrals first, then those without one.
     *
     * @param  Collection<int, Patient>  $patients
     * @return array<string, Collection<int, Patient>>
     */
    protected function doctorGroups(Collection $patients): array
    {
        $groups = $patients
            ->whereNotNull('referring_doctor')
            ->groupBy('referring_doctor')
            ->sortBy([fn (Collection $a, Collection $b): int => $b->count() <=> $a->count(), fn (Collection $a, Collection $b): int => strcmp((string) $a->first()?->referring_doctor, (string) $b->first()?->referring_doctor)])
            ->map(fn (Collection $group): Collection => $group->values())
            ->all();

        return $this->withNotRecorded($groups, $patients->whereNull('referring_doctor'));
    }

    /**
     * @param  array<string, Collection<int, Patient>>  $groups
     * @param  Collection<int, Patient>  $unrecorded
     * @return array<string, Collection<int, Patient>>
     */
    protected function withNotRecorded(array $groups, Collection $unrecorded): array
    {
        if ($unrecorded->isNotEmpty()) {
            $groups[$this->text('Not recorded')] = $unrecorded;
        }

        return $groups;
    }

    /**
     * The fees recorded at the visits: the total, how many visits were charged each
     * amount, and the total of each month, newest first. A visit without a fee has no
     * fee recorded, which is not the same as free (a fee of 0).
     *
     * @param  Collection<int, Visit>  $visits
     * @return array{total: int, visits: int, recorded: int, free: int, amounts: list<array{fee: int, visits: int, total: int}>, months: list<array{month: string|null, visits: int, total: int}>}
     */
    protected function fees(Collection $visits): array
    {
        $recorded = $visits->filter(fn (Visit $visit): bool => $visit->fee !== null);

        $summary = fn (Collection $group): array => ['visits' => $group->count(), 'total' => (int) $group->sum('fee')];

        $amounts = $recorded
            ->groupBy('fee')
            ->map(fn (Collection $group, int|string $fee): array => ['fee' => (int) $fee, ...$summary($group)])
            ->sortBy([['visits', 'desc'], ['fee', 'desc']])
            ->values()
            ->all();

        // Visits without a date are listed last.
        $months = $recorded
            ->groupBy(fn (Visit $visit): string => $visit->visit_date?->format('Y-m') ?? '')
            ->sortKeysDesc()
            ->map(fn (Collection $group, string $month): array => ['month' => $month === '' ? null : $month, ...$summary($group)])
            ->values()
            ->all();

        return [
            'total' => (int) $recorded->sum('fee'),
            'visits' => $visits->count(),
            'recorded' => $recorded->count(),
            'free' => $recorded->filter(fn (Visit $visit): bool => $visit->fee === 0)->count(),
            'amounts' => $amounts,
            'months' => $months,
        ];
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
     * @param  array<int, ChartItem>  $items
     * @return array{title: string, unit: string, total: int, unknown: int, items: list<ChartItem>}
     */
    protected function chart(string $title, string $unit, int $total, int $unknown, array $items): array
    {
        return ['title' => $title, 'unit' => $unit, 'total' => $total, 'unknown' => $unknown, 'items' => array_values($items)];
    }

    /**
     * Translate an interface string.
     *
     * @param  array<string, string|int>  $replace
     */
    protected function text(string $key, array $replace = []): string
    {
        $translation = __($key, $replace);

        return is_string($translation) ? $translation : $key;
    }

    protected function percent(int $part, int $whole): string
    {
        return $whole === 0 ? '—' : round($part / $whole * 100, 1).'%';
    }
}
