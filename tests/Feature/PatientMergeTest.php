<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PatientMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_record_is_merged_into_the_kept_patient()
    {
        $kept = Patient::factory()->create([
            'name' => 'Baby Ali',
            'mother_name' => null,
            'phone' => '07700000001',
            'notes' => 'Seen at the clinic.',
            'illnesses' => ['Sepsis'],
            'unverified' => false,
        ]);
        $duplicate = Patient::factory()->create([
            'name' => 'Baby Aly',
            'mother_name' => 'Zainab',
            'phone' => '07700000002',
            'notes' => 'From the notebook.',
            'illnesses' => ['Sepsis', 'Jaundice'],
            'unverified' => true,
        ]);
        $visit = Visit::factory()->for($duplicate)->create();
        $treatment = Treatment::factory()->for($duplicate)->create();
        $attachment = $duplicate->attachments()->create([
            'disk' => 'local', 'path' => 'attachments/scan.jpg', 'original_name' => 'scan.jpg', 'mime_type' => 'image/jpeg', 'size' => 10,
        ]);
        $reviewItem = $duplicate->reviewItems()->create(['field' => 'dob', 'issue' => 'Check the date']);

        $this->actingAs(User::factory()->create())
            ->post(route('patients.merge.store', $kept), [
                'duplicate_id' => $duplicate->id,
                'choices' => ['phone' => 'duplicate'],
            ])
            ->assertRedirect(route('patients.show', $kept));

        $kept->refresh();

        $this->assertSame('Baby Ali', $kept->name);
        $this->assertSame('Zainab', $kept->mother_name);
        $this->assertSame('07700000002', $kept->phone);
        $this->assertSame("Seen at the clinic.\n\nFrom the notebook.", $kept->notes);
        $this->assertSame(['Sepsis', 'Jaundice'], $kept->illnesses);
        $this->assertFalse($kept->unverified);
        $this->assertSame(1, $kept->exams_count);
        $this->assertTrue($kept->had_injection);

        $this->assertSame($kept->id, $visit->refresh()->patient_id);
        $this->assertSame($kept->id, $treatment->refresh()->patient_id);
        $this->assertSame($kept->id, $attachment->refresh()->patient_id);
        $this->assertSame($kept->id, $reviewItem->refresh()->patient_id);

        $this->assertSoftDeleted($duplicate);

        $merge = Audit::query()->where('event', 'merged')->sole();
        $this->assertSame($kept->id, $merge->patient_id);
        $this->assertSame('Baby Aly', $merge->old_values['name']);
        $this->assertSame(0, Audit::query()->where('patient_id', $duplicate->id)->where('event', '!=', 'deleted')->count());
    }

    public function test_a_patient_cannot_be_merged_with_itself_or_a_deleted_record()
    {
        $patient = Patient::factory()->create();
        $deleted = Patient::factory()->create();
        $deleted->delete();

        $this->actingAs(User::factory()->create());

        $this->post(route('patients.merge.store', $patient), ['duplicate_id' => $patient->id])
            ->assertSessionHasErrors('duplicate_id');
        $this->post(route('patients.merge.store', $patient), ['duplicate_id' => $deleted->id])
            ->assertSessionHasErrors('duplicate_id');

        $this->assertNotSoftDeleted($patient);
    }

    public function test_only_known_details_can_be_chosen()
    {
        $patient = Patient::factory()->create();
        $duplicate = Patient::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('patients.merge.store', $patient), [
                'duplicate_id' => $duplicate->id,
                'choices' => ['unverified' => 'duplicate', 'name' => 'both'],
            ])
            ->assertSessionHasErrors(['choices', 'choices.name']);

        $this->assertNotSoftDeleted($duplicate);
    }

    public function test_merge_page_suggests_likely_duplicates_and_compares_the_chosen_one()
    {
        $patient = Patient::factory()->create(['name' => 'Baby Ali Hassan', 'dob' => '2026-05-16']);
        $twin = Patient::factory()->create(['name' => 'Baby Omar', 'dob' => '2026-05-16']);
        Patient::factory()->create(['name' => 'Unrelated Baby', 'dob' => '2026-01-01']);

        $this->actingAs(User::factory()->create());

        $this->get(route('patients.merge.create', $patient))
            ->assertInertia(fn (Assert $page) => $page
                ->component('patients/Merge')
                ->where('duplicate', null)
                ->has('suggestions', 1)
                ->where('suggestions.0.id', $twin->id));

        Visit::factory()->for($twin)->count(2)->create();

        $this->get(route('patients.merge.create', [$patient, 'with' => $twin->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('duplicate.id', $twin->id)
                ->where('counts.duplicate.visits', 2)
                ->where('counts.patient.visits', 0));
    }
}
