<?php

namespace App\Http\Controllers;

use App\Enums\PatientStatus;
use App\Enums\SuggestionList;
use App\Http\Requests\PatientRequest;
use App\Models\Audit;
use App\Models\Patient;
use App\Models\Suggestion;
use App\Services\RegistryWorkbook;
use App\Support\DuplicateFinder;
use App\Support\RegistryPresenter;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatientController extends Controller
{
    /**
     * The sortable columns, each with the expression that keeps its empty values last.
     */
    protected const NULLS_LAST = [
        'file_number' => 'file_number is null',
        'name' => 'name is null',
        'dob' => 'dob is null',
        'ga_weeks' => 'ga_weeks is null',
        'birth_weight_g' => 'birth_weight_g is null',
        'exams_count' => 'exams_count is null',
        'last_visit_date' => 'last_visit_date is null',
        'next_appointment_date' => 'next_appointment_date is null',
        'status' => 'status is null',
        'created_at' => 'created_at is null',
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
            'unverifiedCount' => Patient::query()->where('unverified', true)->count(),
        ]);
    }

    /**
     * Show the form for registering a new patient.
     */
    public function create(): Response
    {
        return Inertia::render('patients/Form', ['patient' => null, ...$this->suggestions()]);
    }

    /**
     * Register a new patient.
     */
    public function store(PatientRequest $request): RedirectResponse
    {
        $patient = Patient::query()->create($request->validated());
        Suggestion::remember(SuggestionList::ReferringDoctor, $patient->referring_doctor);

        Inertia::flash('toast', ['type' => 'success', 'message' => $this->text('Patient registered.')]);

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
        return Inertia::render('patients/Form', ['patient' => RegistryPresenter::patient($patient), ...$this->suggestions()]);
    }

    /**
     * Update the patient.
     */
    public function update(PatientRequest $request, Patient $patient): RedirectResponse
    {
        $patient->update($request->validated());
        Suggestion::remember(SuggestionList::ReferringDoctor, $patient->referring_doctor);

        Inertia::flash('toast', ['type' => 'success', 'message' => $this->text('Patient updated.')]);

        return to_route('patients.show', $patient);
    }

    /**
     * Get the pick lists of the patient form.
     *
     * @return array{illnessOptions: list<string>, doctorOptions: list<string>}
     */
    protected function suggestions(): array
    {
        return [
            'illnessOptions' => Suggestion::labels(SuggestionList::Illness),
            'doctorOptions' => Suggestion::labels(SuggestionList::ReferringDoctor),
        ];
    }

    /**
     * Change only the follow-up status of the patient.
     */
    public function status(Request $request, Patient $patient): RedirectResponse
    {
        $patient->update($request->validate([
            'status' => ['required', Rule::enum(PatientStatus::class)],
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => $this->text('Status updated.')]);

        return back();
    }

    /**
     * Confirm the identity of a patient that was imported without a matching report.
     */
    public function verify(Patient $patient): RedirectResponse
    {
        $patient->update(['unverified' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Patient confirmed.')]);

        return back();
    }

    /**
     * Find the first few patients matching what is typed in the search box of the top bar.
     */
    public function lookup(Request $request): JsonResponse
    {
        $term = trim($request->string('search')->toString());

        if ($term === '') {
            return response()->json(['total' => 0, 'patients' => []]);
        }

        $query = Patient::query()->search($term);

        return response()->json([
            'total' => $query->count(),
            'patients' => $query->orderBy('name')->limit(8)->get()->map(fn (Patient $patient): array => [
                'id' => $patient->id,
                'name' => $patient->name,
                'file_number' => $patient->file_number,
                'dob' => $patient->dob?->format('Y-m-d'),
                'phone' => $patient->phone,
                'unverified' => $patient->unverified,
            ]),
        ]);
    }

    /**
     * List registered patients that may be the same baby as the one being entered.
     */
    public function duplicates(Request $request, DuplicateFinder $finder): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'dob' => ['nullable', 'date'],
            'ignore' => ['nullable', 'integer'],
        ]);

        return response()->json($finder->find(
            (string) ($validated['name'] ?? ''),
            $validated['dob'] ?? null,
            isset($validated['ignore']) ? (int) $validated['ignore'] : null,
        ));
    }

    /**
     * Download the filtered patients as an Excel workbook: one row per baby, plus
     * long-form visits and treatments, statistics and the review log.
     */
    public function workbook(Request $request, RegistryWorkbook $workbook): BinaryFileResponse
    {
        $path = storage_path('app/registry-export-'.Str::uuid().'.xlsx');

        $workbook->write($this->query($this->filters($request))->get(), $path);

        return response()
            ->download($path, 'rop-registry-'.Carbon::now()->format('Ymd-His').'.xlsx')
            ->deleteFileAfterSend();
    }

    /**
     * Move the patient to the recycle bin.
     */
    public function destroy(Patient $patient): RedirectResponse
    {
        $patient->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => $this->text('Patient moved to the recycle bin.')]);

        return to_route('patients.index');
    }

    /**
     * Restore a patient from the recycle bin.
     */
    public function restore(Patient $patient): RedirectResponse
    {
        $patient->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => $this->text('Patient restored.')]);

        return to_route('patients.show', $patient);
    }

    /**
     * Download the filtered patient list as a spreadsheet (CSV).
     */
    public function export(Request $request): StreamedResponse
    {
        $patients = $this->query($this->filters($request))->get();

        $columns = $this->exportColumns();

        return response()->streamDownload(function () use ($patients, $columns): void {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            // The byte order mark lets Excel open the Arabic text correctly.
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_keys($columns));

            foreach ($patients as $patient) {
                fputcsv($output, array_map(fn (Closure $column): string|int|null => $column($patient), array_values($columns)));
            }

            fclose($output);
        }, 'rop-patients-'.Carbon::now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Translate an interface string.
     */
    protected function text(string $key): string
    {
        $translation = __($key);

        return is_string($translation) ? $translation : $key;
    }

    /**
     * Get the columns of the spreadsheet export: heading => value resolver.
     *
     * @return array<string, Closure(Patient): (string|int|null)>
     */
    protected function exportColumns(): array
    {
        return [
            $this->text('File no.') => fn (Patient $patient) => $patient->file_number,
            $this->text('Name') => fn (Patient $patient) => $patient->name,
            $this->text('Mother name') => fn (Patient $patient) => $patient->mother_name,
            $this->text('Date of birth') => fn (Patient $patient) => $patient->dob?->format('Y-m-d'),
            $this->text('Sex') => fn (Patient $patient) => $patient->sex->label(),
            $this->text('Birth weight (g)') => fn (Patient $patient) => $patient->birth_weight_g,
            $this->text('GA weeks') => fn (Patient $patient) => $patient->ga_weeks,
            $this->text('GA days') => fn (Patient $patient) => $patient->ga_days,
            $this->text('Multiplicity') => fn (Patient $patient) => $patient->multiplicity?->label(),
            $this->text('Delivery mode') => fn (Patient $patient) => $patient->delivery_mode?->label(),
            $this->text('Referral date') => fn (Patient $patient) => $patient->referral_date?->format('Y-m-d'),
            $this->text('Referring doctor') => fn (Patient $patient) => $patient->referring_doctor,
            $this->text('NICU stay (days)') => fn (Patient $patient) => $patient->nicu_days,
            $this->text('Respiratory support') => fn (Patient $patient) => $patient->respiratory_support?->label(),
            $this->text('Support duration (days)') => fn (Patient $patient) => $patient->support_days,
            $this->text('Systemic illness') => fn (Patient $patient) => $patient->illnessSummary(),
            $this->text('Phone') => fn (Patient $patient) => $patient->phone,
            $this->text('Address') => fn (Patient $patient) => $patient->address,
            $this->text('Status') => fn (Patient $patient) => $patient->status->label(),
            $this->text('Examinations') => fn (Patient $patient) => $patient->exams_count,
            $this->text('First visit') => fn (Patient $patient) => $patient->first_visit_date?->format('Y-m-d'),
            $this->text('Last visit') => fn (Patient $patient) => $patient->last_visit_date?->format('Y-m-d'),
            $this->text('Next appointment') => fn (Patient $patient) => $patient->next_appointment_date?->format('Y-m-d'),
            $this->text('Documented ROP') => fn (Patient $patient) => match ($patient->any_rop) {
                true => $this->text('Yes'),
                false => $this->text('No'),
                null => $this->text('Unknown'),
            },
            $this->text('Highest stage') => fn (Patient $patient) => $patient->highest_stage?->label(),
            $this->text('Injection performed') => fn (Patient $patient) => $patient->had_injection ? $this->text('Yes') : $this->text('No'),
            $this->text('Laser performed') => fn (Patient $patient) => $patient->had_laser ? $this->text('Yes') : $this->text('No'),
            $this->text('Notes') => fn (Patient $patient) => $patient->notes,
        ];
    }

    /**
     * @return array{search: string, status: string, sex: string, rop: string, treatment: string, review: bool, unverified: bool, trashed: bool, sort: key-of<self::NULLS_LAST>, direction: 'asc'|'desc', per_page: int}
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
            'unverified' => $request->boolean('unverified'),
            'trashed' => $request->boolean('trashed'),
            'sort' => array_key_exists($sort, self::NULLS_LAST) ? $sort : 'created_at',
            'direction' => $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc',
            'per_page' => in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 25,
        ];
    }

    /**
     * @param  array{search: string, status: string, sex: string, rop: string, treatment: string, review: bool, unverified: bool, trashed: bool, sort: key-of<self::NULLS_LAST>, direction: 'asc'|'desc', per_page: int}  $filters
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
            ->when($filters['unverified'], fn (Builder $query) => $query->where('unverified', true))
            ->when($filters['review'], fn (Builder $query) => $query->whereHas('reviewItems', fn (Builder $query) => $query->whereNull('resolved_at')))
            ->orderByRaw(self::NULLS_LAST[$filters['sort']])
            ->when($filters['sort'] === 'file_number', fn (Builder $query) => $query->orderByRaw(
                $filters['direction'] === 'asc' ? 'cast(file_number as integer) asc' : 'cast(file_number as integer) desc',
            ))
            ->orderBy($filters['sort'], $filters['direction'])
            ->orderBy('id', $filters['direction']);
    }
}
