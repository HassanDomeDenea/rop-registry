<?php

namespace App\Services;

use App\Enums\PatientStatus;
use App\Enums\PlusDisease;
use App\Enums\RopType;
use App\Enums\Stage;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\Visit;
use Illuminate\Support\Collection;

/**
 * Maintains the denormalised summary columns on the patients table, so that the
 * patient list, reminders and statistics can filter and sort with plain queries.
 */
class PatientSummarizer
{
    public function refreshById(int $patientId): void
    {
        $patient = Patient::withTrashed()->find($patientId);

        if ($patient !== null) {
            $this->refresh($patient);
        }
    }

    public function refresh(Patient $patient): void
    {
        $visits = $patient->visits()->get();
        $treatments = $patient->treatments()->get();

        $datedVisits = $visits->whereNotNull('visit_date');
        $lastVisitDate = $datedVisits->max('visit_date');
        $injections = $treatments->filter(fn (Treatment $treatment): bool => $treatment->type->isInjection());

        $patient->forceFill([
            'exams_count' => $visits->filter(fn (Visit $visit): bool => $visit->kind->isExamination())->count(),
            'first_visit_date' => $datedVisits->min('visit_date'),
            'last_visit_date' => $lastVisitDate,
            'next_appointment_date' => $this->nextAppointment($patient, $visits, $lastVisitDate),
            'any_rop' => $this->anyRop($visits, $treatments->isNotEmpty()),
            'highest_stage' => $this->highestStage($visits),
            'any_plus' => $visits->contains(fn (Visit $visit): bool => $visit->right_plus === PlusDisease::Plus || $visit->left_plus === PlusDisease::Plus),
            'type_one' => $visits->contains(fn (Visit $visit): bool => $visit->eyeRopType('right') === RopType::TypeOne || $visit->eyeRopType('left') === RopType::TypeOne),
            'had_injection' => $injections->isNotEmpty(),
            'had_laser' => $treatments->contains(fn (Treatment $treatment): bool => $treatment->type->value === 'laser'),
            'last_injection_date' => $injections->max('performed_date'),
            'treatment_pending' => $this->treatmentPending($visits, $treatments),
        ])->saveQuietly();
    }

    /**
     * The next appointment is the latest planned return date that no recorded visit has reached yet.
     *
     * @param  Collection<int, Visit>  $visits
     */
    protected function nextAppointment(Patient $patient, $visits, mixed $lastVisitDate): mixed
    {
        if ($patient->status !== PatientStatus::Active) {
            return null;
        }

        $planned = $visits->max('next_visit_date');

        if ($planned === null || ($lastVisitDate !== null && $planned->lte($lastVisitDate))) {
            return null;
        }

        return $planned;
    }

    /**
     * True when retinopathy was documented, false when it was explicitly excluded, null when unknown.
     *
     * @param  Collection<int, Visit>  $visits
     */
    protected function anyRop($visits, bool $wasTreated): ?bool
    {
        if ($wasTreated || $visits->contains(fn (Visit $visit): bool => $visit->eyeHasRop('right') || $visit->eyeHasRop('left'))) {
            return true;
        }

        if ($visits->contains(fn (Visit $visit): bool => $visit->eyeExcludesRop('right') || $visit->eyeExcludesRop('left'))) {
            return false;
        }

        return null;
    }

    /**
     * @param  Collection<int, Visit>  $visits
     */
    protected function highestStage($visits): ?Stage
    {
        return $visits
            ->flatMap(fn (Visit $visit): array => [$visit->right_stage, $visit->left_stage])
            ->filter(fn (?Stage $stage): bool => $stage?->severity() !== null)
            ->sortByDesc(fn (Stage $stage): int => (int) $stage->severity())
            ->first();
    }

    /**
     * A treatment is pending when the latest plan recommends an injection or laser
     * and no matching treatment has been recorded on or after that visit.
     *
     * @param  Collection<int, Visit>  $visits
     * @param  Collection<int, Treatment>  $treatments
     */
    protected function treatmentPending($visits, $treatments): bool
    {
        /** @var Visit|null $latest */
        $latest = $visits->whereNotNull('management_plan')->sortBy(fn (Visit $visit): string => ($visit->visit_date?->format('Y-m-d') ?? '0000-00-00').str_pad((string) $visit->id, 10, '0', STR_PAD_LEFT))->last();

        if ($latest === null || $latest->management_plan === null) {
            return false;
        }

        $plan = $latest->management_plan;

        if (! $plan->includesInjection() && ! $plan->includesLaser()) {
            return false;
        }

        $performedSince = $treatments->filter(
            fn (Treatment $treatment): bool => $latest->visit_date === null
                || $treatment->performed_date === null
                || $treatment->performed_date->gte($latest->visit_date),
        );

        $injectionDone = ! $plan->includesInjection() || $performedSince->contains(fn (Treatment $treatment): bool => $treatment->type->isInjection());
        $laserDone = ! $plan->includesLaser() || $performedSince->contains(fn (Treatment $treatment): bool => $treatment->type->value === 'laser');

        return ! ($injectionDone && $laserDone);
    }
}
