<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\PatientMerger;
use App\Support\DuplicateFinder;
use App\Support\RegistryPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PatientMergeController extends Controller
{
    /**
     * Show the comparison of the patient with the record chosen as its duplicate,
     * or the likely duplicates to choose from.
     */
    public function create(Request $request, Patient $patient, DuplicateFinder $finder): Response
    {
        $duplicate = $request->filled('with')
            ? Patient::query()->whereKeyNot($patient->id)->find($request->integer('with'))
            : null;

        return Inertia::render('patients/Merge', [
            'patient' => RegistryPresenter::patient($patient),
            'duplicate' => $duplicate === null ? null : RegistryPresenter::patient($duplicate),
            'counts' => [
                'patient' => $this->counts($patient),
                'duplicate' => $duplicate === null ? null : $this->counts($duplicate),
            ],
            'suggestions' => $duplicate === null
                ? $finder->find($patient->name, $patient->dob?->format('Y-m-d'), $patient->id)
                : [],
        ]);
    }

    /**
     * Merge the duplicate into the patient and move the duplicate to the recycle bin.
     */
    public function store(Request $request, Patient $patient, PatientMerger $merger): RedirectResponse
    {
        $validated = $request->validate([
            'duplicate_id' => [
                'required',
                'integer',
                Rule::notIn([$patient->id]),
                Rule::exists('patients', 'id')->whereNull('deleted_at'),
            ],
            'choices' => ['nullable', 'array:'.implode(',', array_keys(PatientMerger::FIELDS))],
            'choices.*' => ['required', Rule::in(['kept', 'duplicate'])],
        ]);

        $merger->merge($patient, Patient::query()->findOrFail($validated['duplicate_id']), $validated['choices'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Records merged.')]);

        return to_route('patients.show', $patient);
    }

    /**
     * Count the records that belong to the patient.
     *
     * @return array{visits: int, treatments: int, attachments: int, review_items: int}
     */
    protected function counts(Patient $patient): array
    {
        return [
            'visits' => $patient->visits()->count(),
            'treatments' => $patient->treatments()->count(),
            'attachments' => $patient->attachments()->count(),
            'review_items' => $patient->reviewItems()->count(),
        ];
    }
}
