<?php

namespace App\Http\Controllers;

use App\Enums\PatientStatus;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\Visit;
use App\Services\ReminderService;
use App\Support\RegistryPresenter;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the overview: key figures, the appointments of the day and what needs attention.
     */
    public function __invoke(ReminderService $reminders): Response
    {
        $present = fn (Patient $patient): array => RegistryPresenter::patientRow($patient);
        $monthStart = Carbon::today()->startOfMonth();

        return Inertia::render('Dashboard', [
            'figures' => [
                'patients' => Patient::query()->count(),
                'active' => Patient::query()->where('status', PatientStatus::Active)->count(),
                'with_rop' => Patient::query()->where('any_rop', true)->count(),
                'treated' => Patient::query()->where(fn ($query) => $query->where('had_injection', true)->orWhere('had_laser', true))->count(),
                'visits_this_month' => Visit::query()->whereDate('visit_date', '>=', $monthStart)->count(),
                'new_this_month' => Patient::query()->whereRaw('date(coalesce(first_visit_date, referral_date, created_at)) >= ?', [$monthStart->format('Y-m-d')])->count(),
                'treatments_this_month' => Treatment::query()->whereDate('performed_date', '>=', $monthStart)->count(),
            ],
            'today' => $reminders->today()->get()->map($present),
            'upcoming' => $reminders->upcoming()->limit(8)->get()->map($present),
            'overdue' => $reminders->overdue()->limit(8)->get()->map($present),
            'treatmentPending' => $reminders->treatmentPending()->limit(8)->get()->map($present),
            'injectionSurveillance' => $reminders->injectionSurveillance()->limit(8)->get()->map($present),
            'recent' => Patient::query()->latest('id')->limit(6)->get()->map($present),
        ]);
    }
}
