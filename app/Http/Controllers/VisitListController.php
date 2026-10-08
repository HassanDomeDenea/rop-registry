<?php

namespace App\Http\Controllers;

use App\Enums\ManagementPlan;
use App\Enums\PlusDisease;
use App\Enums\RopStatus;
use App\Enums\RopType;
use App\Enums\VisitKind;
use App\Models\Patient;
use App\Models\Visit;
use App\Support\ArabicText;
use App\Support\RegistryPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VisitListController extends Controller
{
    /**
     * The sortable columns with the expression that keeps empty values at the end.
     *
     * @var array<string, literal-string>
     */
    protected const NULLS_LAST = [
        'visit_date' => 'visits.visit_date is null',
        'patient' => 'visits.patient_id is null',
        'management_plan' => 'visits.management_plan is null',
        'next_visit_date' => 'visits.next_visit_date is null',
        'fee' => 'visits.fee is null',
    ];

    /**
     * List the visits of every patient, newest first.
     */
    public function __invoke(Request $request): Response
    {
        $filters = $this->filters($request);

        $visits = $this->query($filters)
            ->with('patient')
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (Visit $visit): array => [
                ...RegistryPresenter::visit($visit, $visit->patient),
                'patient' => $visit->patient->only(['id', 'name', 'file_number', 'ga_weeks', 'ga_days', 'birth_weight_g', 'unverified']),
            ]);

        return Inertia::render('visits/Index', [
            'visits' => $visits,
            'filters' => $filters,
        ]);
    }

    /**
     * @return array{search: string, kind: string, plan: string, finding: string, from: string, to: string, sort: key-of<self::NULLS_LAST>, direction: 'asc'|'desc', per_page: int}
     */
    protected function filters(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $sort = $request->string('sort')->toString();
        $perPage = $request->integer('per_page', 25);
        $kind = $request->string('kind')->toString();
        $plan = $request->string('plan')->toString();
        $finding = $request->string('finding')->toString();

        return [
            'search' => $request->string('search')->trim()->toString(),
            'kind' => VisitKind::tryFrom($kind) ? $kind : '',
            'plan' => ManagementPlan::tryFrom($plan) ? $plan : '',
            'finding' => in_array($finding, ['rop', 'plus', 'type_1', 'no_rop'], true) ? $finding : '',
            'from' => (string) ($validated['from'] ?? ''),
            'to' => (string) ($validated['to'] ?? ''),
            'sort' => array_key_exists($sort, self::NULLS_LAST) ? $sort : 'visit_date',
            'direction' => $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc',
            'per_page' => in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25,
        ];
    }

    /**
     * @param  array{search: string, kind: string, plan: string, finding: string, from: string, to: string, sort: key-of<self::NULLS_LAST>, direction: 'asc'|'desc', per_page: int}  $filters
     * @return Builder<Visit>
     */
    protected function query(array $filters): Builder
    {
        $search = $filters['search'];

        return Visit::query()
            ->whereHas('patient', fn (Builder $query) => $query->whereNull('deleted_at'))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereHas('patient', fn (Builder $query) => $query->search($search))
                ->orWhere(ArabicText::foldedColumn('examiner'), 'like', ArabicText::pattern($search))
                ->orWhere('assessment', 'like', '%'.$search.'%')))
            ->when($filters['kind'] !== '', fn (Builder $query) => $query->where('kind', $filters['kind']))
            ->when($filters['plan'] !== '', fn (Builder $query) => $query->where('management_plan', $filters['plan']))
            ->when($filters['finding'] === 'rop', fn (Builder $query) => $this->eitherEye($query, 'rop_status', RopStatus::Present->value))
            ->when($filters['finding'] === 'plus', fn (Builder $query) => $this->eitherEye($query, 'plus', PlusDisease::Plus->value))
            ->when($filters['finding'] === 'type_1', fn (Builder $query) => $this->eitherEye($query, 'rop_type', RopType::TypeOne->value))
            ->when($filters['finding'] === 'no_rop', fn (Builder $query) => $query
                ->where('right_rop_status', RopStatus::NoRop->value)
                ->where('left_rop_status', RopStatus::NoRop->value))
            ->when($filters['from'] !== '', fn (Builder $query) => $query->whereDate('visit_date', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn (Builder $query) => $query->whereDate('visit_date', '<=', $filters['to']))
            ->orderByRaw(self::NULLS_LAST[$filters['sort']])
            ->when(
                $filters['sort'] === 'patient',
                fn (Builder $query) => $query->orderBy(
                    Patient::query()->select('name')->whereColumn('patients.id', 'visits.patient_id'),
                    $filters['direction'],
                ),
                fn (Builder $query) => $query->orderBy($filters['sort'], $filters['direction']),
            )
            ->orderBy('id', $filters['direction']);
    }

    /**
     * Limit the visits to those where the right or the left eye has the finding.
     *
     * @param  Builder<Visit>  $query
     */
    protected function eitherEye(Builder $query, string $field, string $value): void
    {
        $query->where(fn (Builder $query) => $query->where("right_{$field}", $value)->orWhere("left_{$field}", $value));
    }
}
