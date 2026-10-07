<?php

namespace App\Http\Controllers;

use App\Http\Requests\VisitRequest;
use App\Models\Patient;
use App\Models\Visit;
use App\Support\RegistryPresenter;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class VisitController extends Controller
{
    /**
     * Show the examination form for a new visit.
     */
    public function create(Patient $patient): Response
    {
        return Inertia::render('visits/Form', [
            'patient' => RegistryPresenter::patient($patient),
            'visit' => null,
            'previous' => $this->previousVisit($patient),
            'visitNumber' => $patient->visits()->count() + 1,
        ]);
    }

    /**
     * Record a new visit.
     */
    public function store(VisitRequest $request, Patient $patient): RedirectResponse
    {
        $patient->visits()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Visit recorded.')]);

        return to_route('patients.show', $patient);
    }

    /**
     * Show the examination form for an existing visit.
     */
    public function edit(Patient $patient, Visit $visit): Response
    {
        return Inertia::render('visits/Form', [
            'patient' => RegistryPresenter::patient($patient),
            'visit' => RegistryPresenter::visit($visit, $patient),
            'previous' => null,
            'visitNumber' => $this->visitNumber($patient, $visit),
        ]);
    }

    /**
     * Update the visit.
     */
    public function update(VisitRequest $request, Patient $patient, Visit $visit): RedirectResponse
    {
        $visit->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Visit updated.')]);

        return to_route('patients.show', $patient);
    }

    /**
     * Delete the visit.
     */
    public function destroy(Patient $patient, Visit $visit): RedirectResponse
    {
        $visit->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Visit deleted.')]);

        return to_route('patients.show', $patient);
    }

    /**
     * Show the printable examination report, laid out like the paper form.
     */
    public function print(Patient $patient, Visit $visit): Response
    {
        return Inertia::render('print/Visit', [
            'patient' => RegistryPresenter::patient($patient),
            'visit' => RegistryPresenter::visit($visit, $patient),
            'visitNumber' => $this->visitNumber($patient, $visit),
            'clinic' => config('registry.clinic'),
        ]);
    }

    /**
     * Get the position of the visit in the chronological order of the patient's visits.
     */
    protected function visitNumber(Patient $patient, Visit $visit): int
    {
        $position = $patient->visits()->pluck('id')->search($visit->id);

        return is_int($position) ? $position + 1 : 1;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function previousVisit(Patient $patient): ?array
    {
        $previous = Visit::query()
            ->whereBelongsTo($patient)
            ->whereNotNull('visit_date')
            ->orderByDesc('visit_date')
            ->orderByDesc('id')
            ->first();

        return $previous === null ? null : RegistryPresenter::visit($previous, $patient);
    }
}
