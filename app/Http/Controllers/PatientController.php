<?php

namespace App\Http\Controllers;

use App\Enums\PatientStatus;
use App\Http\Requests\PatientRequest;
use App\Models\Audit;
use App\Models\Patient;
use App\Support\RegistryPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatientController extends Controller
{
    protected const SORTABLE = [
        'file_number', 'name', 'dob', 'ga_weeks', 'birth_weight_g', 'exams_count',
        'last_visit_date', 'next_appointment_date', 'status', 'created_at',
    ];

    /**
     * List the patients with search, filters, sorting and pagination.
     */
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $patients = $this->query($filters)
            ->withCount(['reviewItems as open_review_items_count' => fn (Builder $query) => $query->whereNull('resolved_at')])
            ->paginate($filters['per_page'])
            ->withQueryString()
            ->through(fn (Patient $patient): array => RegistryPresenter::patientRow($patient));

        return Inertia::render('patients/Index', [
            'patients' => $patients,
            'filters' => $filters,
            'trashedCount' => Patient::onlyTrashed()->count(),
        ]);
    }

    /**
     * Show the form for registering a new patient.
     */
    public function create(): Response
    {
        return Inertia::render('patients/Form', ['patient' => null]);
    }

    /**
     * Register a new patient.
     */
    public function store(PatientRequest $request): RedirectResponse
    {
        $patient = Patient::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Patient registered.')]);

        return to_route('patients.show', $patient);
    }

    /**
     * Show the patient record with visits, treatments, attachments and history.
     */
    public function show(Patient $patient): Response
    {
        $patient->load(['visits', 'treatments', 'attachments', 'reviewItems']);
        $patient->loadCount(['reviewItems as open_review_items_count' => fn (Builder $query) => $query->whereNull('resolved_at')]);

        return Inertia::render('patients/Show', [
            'patient' => RegistryPresenter::patient($patient),
            'visits' => $patient->visits->map(fn ($visit) => RegistryPresenter::visit($visit, $patient))->values(),
            'treatments' => $patient->treatments->map(fn ($treatment) => RegistryPresenter::treatment($treatment, $patient))->values(),
            'attachments' => $patient->attachments->map(fn ($attachment) => RegistryPresenter::attachment($attachment))->values(),
            'reviewItems' => $patient->reviewItems->map(fn ($item) => RegistryPresenter::reviewItem($item))->values(),
            'audits' => Inertia::defer(fn () => Audit::query()
                ->with('user')
                ->where('patient_id', $patient->id)
                ->latest('id')
                ->limit(200)
                ->get()
                ->map(fn (Audit $audit) => RegistryPresenter::audit($audit))),
        ]);
    }

    /**
     * Show the form for editing the patient.
     */
    public function edit(Patient $patient): Response
    {
        return Inertia::render('patients/Form', ['patient' => RegistryPresenter::patient($patient)]);
    }

    /**
     * Update the patient.
     */
    public function update(PatientRequest $request, Patient $patient): RedirectResponse
    {
        $patient->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Patient updated.')]);

        return to_route('patients.show', $patient);
    }

    /**
     * Change only the follow-up status of the patient.
     */
    public function status(Request $request, Patient $patient): RedirectResponse
    {
        $patient->update($request->validate([
            'status' => ['required', Rule::enum(PatientStatus::class)],
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Status updated.')]);

        return back();
    }

    /**
     * Move the patient to the recycle bin.
     */
    public function destroy(Patient $patient): RedirectResponse
    {
        $patient->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Patient moved to the recycle bin.')]);

        return to_route('patients.index');
    }

    /**
     * Restore a patient from the recycle bin.
     */
    public function restore(Patient $patient): RedirectResponse
    {
        $patient->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Patient restored.')]);

        return to_route('patients.show', $patient);
    }

    /**
     * Download the filtered patient list as a spreadsheet (CSV).
     */
    public function export(Request $request): StreamedResponse
    {
        $patients = $this->query($this->filters($request))->get();

        $columns = [
            __('File no.') => fn (Patient $patient) => $patient->file_number,
            __('Name') => fn (Patient $patient) => $patient->name,
            __('Date of birth') => fn (Patient $patient) => $patient->dob?->format('Y-m-d'),
            __('Sex') => fn (Patient $patient) => $patient->sex->label(),
            __('Birth weight (g)') => fn (Patient $patient) => $patient->birth_weight_g,
            __('GA weeks') => fn (Patient $patient) => $patient->ga_weeks,
            __('GA days') => fn (Patient $patient) => $patient->ga_days,
            __('Multiplicity') => fn (Patient $patient) => $patient->multiplicity?->label(),
            __('Delivery mode') => fn (Patient $patient) => $patient->delivery_mode?->label(),
            __('Referral date') => fn (Patient $patient) => $patient->referral_date?->format('Y-m-d'),
            __('Referring doctor') => fn (Patient $patient) => $patient->referring_doctor,
            __('NICU stay (days)') => fn (Patient $patient) => $patient->nicu_days,
            __('Respiratory support') => fn (Patient $patient) => $patient->respiratory_support?->label(),
            __('Support duration (days)') => fn (Patient $patient) => $patient->support_days,
            __('Systemic illness') => fn (Patient $patient) => $patient->systemic_illness,
            __('Phone') => fn (Patient $patient) => $patient->phone,
            __('Address') => fn (Patient $patient) => $patient->address,
            __('Status') => fn (Patient $patient) => $patient->status->label(),
            __('Examinations') => fn (Patient $patient) => $patient->exams_count,
            __('First visit') => fn (Patient $patient) => $patient->first_visit_date?->format('Y-m-d'),
            __('Last visit') => fn (Patient $patient) => $patient->last_visit_date?->format('Y-m-d'),
            __('Next appointment') => fn (Patient $patient) => $patient->next_appointment_date?->format('Y-m-d'),
            __('Documented ROP') => fn (Patient $patient) => match ($patient->any_rop) {
                true => __('Yes'),
                false => __('No'),
                null => __('Unknown'),
            },
            __('Highest stage') => fn (Patient $patient) => $patient->highest_stage?->label(),
            __('Injection performed') => fn (Patient $patient) => $patient->had_injection ? __('Yes') : __('No'),
            __('Laser performed') => fn (Patient $patient) => $patient->had_laser ? __('Yes') : __('No'),
            __('Notes') => fn (Patient $patient) => $patient->notes,
        ];

        return response()->streamDownload(function () use ($patients, $columns): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            // The byte order mark lets Excel open the Arabic text correctly.
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_keys($columns));

            foreach ($patients as $patient) {
                fputcsv($output, array_map(fn (callable $column) => $column($patient), array_values($columns)));
            }

            fclose($output);
        }, 'rop-patients-'.Carbon::now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{search: string, status: string, sex: string, rop: string, treatment: string, review: bool, trashed: bool, sort: string, direction: string, per_page: int}
     */
    protected function filters(Request $request): array
    {
        $sort = $request->string('sort')->toString();
        $perPage = $request->integer('per_page', 25);

        return [
            'search' => $request->string('search')->trim()->toString(),
            'status' => $request->string('status')->toString(),
            'sex' => $request->string('sex')->toString(),
            'rop' => $request->string('rop')->toString(),
            'treatment' => $request->string('treatment')->toString(),
            'review' => $request->boolean('review'),
            'trashed' => $request->boolean('trashed'),
            'sort' => in_array($sort, self::SORTABLE, true) ? $sort : 'created_at',
            'direction' => $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc',
            'per_page' => in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25,
        ];
    }

    /**
     * @param  array{search: string, status: string, sex: string, rop: string, treatment: string, review: bool, trashed: bool, sort: string, direction: string, per_page: int}  $filters
     * @return Builder<Patient>
     */
    protected function query(array $filters): Builder
    {
        return Patient::query()
            ->when($filters['trashed'], fn (Builder $query) => $query->onlyTrashed())
            ->search($filters['search'])
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['sex'] !== '', fn (Builder $query) => $query->where('sex', $filters['sex']))
            ->when($filters['rop'] === 'yes', fn (Builder $query) => $query->where('any_rop', true))
            ->when($filters['rop'] === 'no', fn (Builder $query) => $query->where('any_rop', false))
            ->when($filters['rop'] === 'unknown', fn (Builder $query) => $query->whereNull('any_rop'))
            ->when($filters['rop'] === 'type_one', fn (Builder $query) => $query->where('type_one', true))
            ->when($filters['treatment'] === 'injection', fn (Builder $query) => $query->where('had_injection', true))
            ->when($filters['treatment'] === 'laser', fn (Builder $query) => $query->where('had_laser', true))
            ->when($filters['treatment'] === 'pending', fn (Builder $query) => $query->where('treatment_pending', true))
            ->when($filters['treatment'] === 'none', fn (Builder $query) => $query->where('had_injection', false)->where('had_laser', false))
            ->when($filters['review'], fn (Builder $query) => $query->whereHas('reviewItems', fn (Builder $query) => $query->whereNull('resolved_at')))
            ->when(
                $filters['sort'] === 'file_number',
                fn (Builder $query) => $query->orderByRaw('file_number is null')->orderByRaw('cast(file_number as integer) '.$filters['direction'])->orderBy('file_number', $filters['direction']),
                fn (Builder $query) => $query->orderByRaw($filters['sort'].' is null')->orderBy($filters['sort'], $filters['direction']),
            )
            ->orderBy('id', $filters['direction']);
    }
}
