<?php

namespace Tests\Feature;

use App\Enums\ManagementPlan;
use App\Enums\PatientStatus;
use App\Enums\PlusDisease;
use App\Enums\RopStatus;
use App\Enums\Stage;
use App\Enums\TreatmentType;
use App\Enums\Zone;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisitTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_a_visit_updates_the_patient_summary()
    {
        $patient = Patient::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('patients.visits.store', $patient), [
                'kind' => 'examination',
                'visit_date' => '2026-06-20',
                'right_zone' => 'zone_2',
                'right_stage' => 'stage_2',
                'right_plus' => 'none',
                'right_rop_status' => 'present',
                'left_rop_status' => 'no_rop',
                'management_plan' => 'observe',
                'next_visit_date' => '2026-07-04',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('patients.show', $patient));

        $patient->refresh();

        $this->assertSame(1, $patient->exams_count);
        $this->assertTrue($patient->any_rop);
        $this->assertSame(Stage::StageTwo, $patient->highest_stage);
        $this->assertSame('2026-06-20', $patient->last_visit_date->format('Y-m-d'));
        $this->assertFalse($patient->type_one);
    }

    public function test_next_visit_cannot_precede_the_visit()
    {
        $patient = Patient::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('patients.visits.store', $patient), [
                'kind' => 'examination',
                'visit_date' => '2026-06-20',
                'next_visit_date' => '2026-06-01',
                'right_stage' => 'stage_9',
            ])
            ->assertSessionHasErrors(['next_visit_date', 'right_stage']);

        $this->assertSame(0, Visit::query()->count());
    }

    public function test_visit_of_another_patient_cannot_be_updated_through_a_patient()
    {
        $visit = Visit::factory()->create();
        $other = Patient::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('patients.visits.update', [$other, $visit]), ['kind' => 'examination', 'assessment' => 'changed'])
            ->assertNotFound();

        $this->assertNull($visit->refresh()->assessment);
    }

    public function test_next_appointment_is_the_latest_plan_not_yet_reached_by_a_visit()
    {
        $patient = Patient::factory()->create();

        Visit::factory()->for($patient)->create(['visit_date' => '2026-06-01', 'next_visit_date' => '2026-06-15']);
        $this->assertSame('2026-06-15', $patient->refresh()->next_appointment_date->format('Y-m-d'));

        Visit::factory()->for($patient)->create(['visit_date' => '2026-06-16', 'next_visit_date' => null]);
        $this->assertNull($patient->refresh()->next_appointment_date);

        Visit::factory()->for($patient)->create(['visit_date' => '2026-06-30', 'next_visit_date' => '2026-07-14']);
        $this->assertSame('2026-07-14', $patient->refresh()->next_appointment_date->format('Y-m-d'));

        $patient->update(['status' => PatientStatus::Discharged]);
        $this->assertNull($patient->refresh()->next_appointment_date);
    }

    public function test_type_one_rop_is_detected_from_zone_stage_and_plus()
    {
        $patient = Patient::factory()->create();

        Visit::factory()->for($patient)->create([
            'left_zone' => Zone::ZoneOne,
            'left_stage' => Stage::StageThree,
            'left_plus' => PlusDisease::None,
            'left_rop_status' => RopStatus::Present,
        ]);

        $patient->refresh();

        $this->assertTrue($patient->type_one);
        $this->assertFalse($patient->any_plus);
    }

    public function test_recommended_injection_is_pending_until_a_treatment_is_recorded()
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create(['visit_date' => '2026-04-09', 'management_plan' => ManagementPlan::Eylea]);

        $patient->refresh();
        $this->assertTrue($patient->treatment_pending);
        $this->assertFalse($patient->had_injection);

        $this->actingAs($user)
            ->post(route('patients.treatments.store', $patient), [
                'type' => 'eylea',
                'eye' => 'both',
                'performed_date' => '2026-04-12',
                'visit_id' => $visit->id,
            ])
            ->assertSessionHasNoErrors();

        $patient->refresh();
        $this->assertFalse($patient->treatment_pending);
        $this->assertTrue($patient->had_injection);
        $this->assertTrue($patient->any_rop);
        $this->assertSame('2026-04-12', $patient->last_injection_date->format('Y-m-d'));

        $this->actingAs($user)->delete(route('patients.treatments.destroy', [$patient, Treatment::query()->sole()]));

        $this->assertTrue($patient->refresh()->treatment_pending);
    }

    public function test_treatment_cannot_be_linked_to_a_visit_of_another_patient()
    {
        $patient = Patient::factory()->create();
        $foreignVisit = Visit::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('patients.treatments.store', $patient), [
                'type' => TreatmentType::Laser->value,
                'eye' => 'right',
                'visit_id' => $foreignVisit->id,
            ])
            ->assertSessionHasErrors('visit_id');

        $this->assertSame(0, Treatment::query()->count());
    }

    public function test_deleting_a_visit_recalculates_the_summary()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create(['right_rop_status' => RopStatus::Present]);

        $this->assertTrue($patient->refresh()->any_rop);

        $this->actingAs(User::factory()->create())
            ->delete(route('patients.visits.destroy', [$patient, $visit]))
            ->assertRedirect(route('patients.show', $patient));

        $this->assertSoftDeleted($visit);
        $this->assertNull($patient->refresh()->any_rop);
        $this->assertSame(0, $patient->exams_count);
    }

    public function test_visits_of_all_patients_are_listed_newest_first_with_search_filters_and_sorting()
    {
        $user = User::factory()->create();
        $first = Patient::factory()->create(['name' => 'أحمد الأول']);
        $second = Patient::factory()->create(['name' => 'Baby Second']);

        $old = Visit::factory()->for($first)->create(['visit_date' => '2026-05-01', 'right_plus' => 'plus', 'management_plan' => 'eylea']);
        $new = Visit::factory()->for($second)->create(['visit_date' => '2026-06-01', 'right_plus' => 'none', 'left_plus' => 'none', 'management_plan' => 'observe']);

        // The visits of a patient in the recycle bin are not listed.
        $removed = Patient::factory()->create();
        Visit::factory()->for($removed)->create(['visit_date' => '2026-07-01']);
        $removed->delete();

        $this->get(route('visits.index'))->assertRedirect(route('login'));

        $this->actingAs($user)->get(route('visits.index'))
            ->assertInertia(fn ($page) => $page
                ->component('visits/Index')
                ->has('visits.data', 2)
                ->where('visits.data.0.id', $new->id)
                ->where('visits.data.0.patient.name', 'Baby Second')
                ->where('visits.data.1.id', $old->id));

        $this->actingAs($user)->get(route('visits.index', ['sort' => 'patient', 'direction' => 'asc']))
            ->assertInertia(fn ($page) => $page->where('visits.data.0.id', $new->id));

        foreach ([['search' => 'احمد'], ['finding' => 'plus'], ['plan' => 'eylea'], ['to' => '2026-05-15']] as $filter) {
            $this->actingAs($user)->get(route('visits.index', $filter))
                ->assertInertia(fn ($page) => $page->has('visits.data', 1)->where('visits.data.0.id', $old->id));
        }
    }
}
