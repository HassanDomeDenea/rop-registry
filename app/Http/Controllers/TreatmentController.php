<?php

namespace App\Http\Controllers;

use App\Http\Requests\TreatmentRequest;
use App\Models\Patient;
use App\Models\Treatment;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TreatmentController extends Controller
{
    /**
     * Record a performed treatment.
     */
    public function store(TreatmentRequest $request, Patient $patient): RedirectResponse
    {
        $patient->treatments()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Treatment recorded.')]);

        return back();
    }

    /**
     * Update the treatment.
     */
    public function update(TreatmentRequest $request, Patient $patient, Treatment $treatment): RedirectResponse
    {
        $treatment->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Treatment updated.')]);

        return back();
    }

    /**
     * Delete the treatment.
     */
    public function destroy(Patient $patient, Treatment $treatment): RedirectResponse
    {
        $treatment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Treatment deleted.')]);

        return back();
    }
}
