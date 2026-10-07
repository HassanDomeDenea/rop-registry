<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Support\RegistryPresenter;
use Inertia\Inertia;
use Inertia\Response;

class PatientPrintController extends Controller
{
    /**
     * Show the printable patient summary with the full examination history.
     */
    public function __invoke(Patient $patient): Response
    {
        $patient->load(['visits', 'treatments']);

        return Inertia::render('print/Patient', [
            'patient' => RegistryPresenter::patient($patient),
            'visits' => $patient->visits->map(fn ($visit) => RegistryPresenter::visit($visit, $patient))->values(),
            'treatments' => $patient->treatments->map(fn ($treatment) => RegistryPresenter::treatment($treatment, $patient))->values(),
            'clinic' => config('registry.clinic'),
        ]);
    }
}
