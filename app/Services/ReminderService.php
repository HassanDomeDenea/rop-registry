<?php

namespace App\Services;

use App\Enums\PatientStatus;
use App\Models\Patient;
use App\Models\ReviewItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Derives reminders from the registry: appointments, pending treatments and
 * patients who are under surveillance after an intravitreal injection.
 */
class ReminderService
{
    /**
     * @return Builder<Patient>
     */
    protected function active(): Builder
    {
        return Patient::query()->verified()->where('status', PatientStatus::Active);
    }

    /**
     * Appointments planned for today.
     *
     * @return Builder<Patient>
     */
    public function today(): Builder
    {
        return $this->active()->whereDate('next_appointment_date', Carbon::today())->orderBy('name');
    }

    /**
     * Appointments planned within the coming days.
     *
     * @return Builder<Patient>
     */
    public function upcoming(): Builder
    {
        $today = Carbon::today();

        return $this->active()
            ->whereDate('next_appointment_date', '>', $today)
            ->whereDate('next_appointment_date', '<=', $today->addDays((int) config('registry.reminders.upcoming_days')))
            ->orderBy('next_appointment_date');
    }

    /**
     * Planned appointments whose date has passed without a recorded visit. This does
     * not establish a missed visit: the examination may simply not be entered yet.
     *
     * @return Builder<Patient>
     */
    public function overdue(): Builder
    {
        return $this->active()
            ->whereDate('next_appointment_date', '<', Carbon::today())
            ->orderByDesc('next_appointment_date');
    }

    /**
     * Patients whose latest plan recommends an injection or laser that is not recorded as performed.
     *
     * @return Builder<Patient>
     */
    public function treatmentPending(): Builder
    {
        return $this->active()->where('treatment_pending', true)->orderByDesc('last_visit_date');
    }

    /**
     * Patients who received an intravitreal injection and remain under surveillance,
     * because retinopathy can reactivate late after anti-VEGF treatment.
     *
     * @return Builder<Patient>
     */
    public function injectionSurveillance(): Builder
    {
        $since = Carbon::today()->subWeeks((int) config('registry.reminders.injection_surveillance_weeks'));

        return $this->active()
            ->where('had_injection', true)
            ->where(fn (Builder $query) => $query
                ->whereNull('last_injection_date')
                ->orWhereDate('last_injection_date', '>=', $since))
            ->orderByDesc('last_injection_date');
    }

    /**
     * Examined patients in active follow-up who have no planned return date.
     *
     * @return Builder<Patient>
     */
    public function withoutAppointment(): Builder
    {
        return $this->active()
            ->whereNull('next_appointment_date')
            ->whereNotNull('last_visit_date')
            ->orderByDesc('last_visit_date');
    }

    /**
     * @return array{today: int, upcoming: int, overdue: int, treatment_pending: int, injection_surveillance: int, without_appointment: int, review: int, attention: int}
     */
    public function counts(): array
    {
        $counts = [
            'today' => $this->today()->count(),
            'upcoming' => $this->upcoming()->count(),
            'overdue' => $this->overdue()->count(),
            'treatment_pending' => $this->treatmentPending()->count(),
            'injection_surveillance' => $this->injectionSurveillance()->count(),
            'without_appointment' => $this->withoutAppointment()->count(),
            'review' => ReviewItem::query()->open()->whereHas('patient', fn (Builder $query) => $query->whereNull('deleted_at'))->count(),
        ];

        $counts['attention'] = $counts['today'] + $counts['overdue'] + $counts['treatment_pending'];

        return $counts;
    }
}
