<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use App\Services\ReminderService;
use App\Support\RegistryPresenter;
use Inertia\Inertia;
use Inertia\Response;

class ReminderController extends Controller
{
    /**
     * List every reminder derived from the registry.
     */
    public function __invoke(ReminderService $reminders): Response
    {
        $present = fn (Patient $patient): array => RegistryPresenter::patientRow($patient);

        return Inertia::render('reminders/Index', [
            'today' => $reminders->today()->get()->map($present),
            'upcoming' => $reminders->upcoming()->get()->map($present),
            'overdue' => $reminders->overdue()->get()->map($present),
            'treatmentPending' => $reminders->treatmentPending()->get()->map($present),
            'injectionSurveillance' => $reminders->injectionSurveillance()->get()->map($present),
            'withoutAppointment' => $reminders->withoutAppointment()->get()->map($present),
            'settings' => config('registry.reminders'),
        ]);
    }
}
