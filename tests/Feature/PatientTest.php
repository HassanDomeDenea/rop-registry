<?php

namespace Tests\Feature;

use App\Enums\PatientStatus;
use App\Enums\Sex;
use App\Models\Audit;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_reach_the_registry()
    {
        $patient = Patient::factory()->create();

        $this->get(route('patients.index'))->assertRedirect(route('login'));
        $this->get(route('patients.show', $patient))->assertRedirect(route('login'));
        $this->post(route('patients.store'), ['name' => 'Baby', 'sex' => 'male', 'status' => 'active'])
            ->assertRedirect(route('login'));

        $this->assertSame(1, Patient::query()->count());
    }

    public function test_patient_can_be_registered_and_the_creation_is_audited()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('patients.store'), [
            'name' => 'طفل تجريبي',
            'sex' => 'male',
            'dob' => '2026-05-16',
            'ga_weeks' => 30,
            'birth_weight_g' => 1400,
            'status' => 'active',
        ]);

        $patient = Patient::query()->sole();

        $response->assertRedirect(route('patients.show', $patient));
        $this->assertSame('طفل تجريبي', $patient->name);
        $this->assertSame(Sex::Male, $patient->sex);
        $this->assertSame(1400, $patient->birth_weight_g);

        $audit = Audit::query()->sole();

        $this->assertSame('created', $audit->event);
        $this->assertSame($user->id, $audit->user_id);
        $this->assertSame($patient->id, $audit->patient_id);
    }

    public function test_patient_requires_a_name_and_plausible_values()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('patients.store'), [
                'name' => '',
                'sex' => 'other',
                'ga_weeks' => 60,
                'dob' => now()->addDay()->format('Y-m-d'),
                'status' => 'active',
            ])
            ->assertSessionHasErrors(['name', 'sex', 'ga_weeks', 'dob']);

        $this->assertSame(0, Patient::query()->count());
    }

    public function test_updating_a_patient_records_only_the_changed_fields()
    {
        $patient = Patient::factory()->create(['birth_weight_g' => 1000, 'nicu_days' => 10]);

        $this->actingAs(User::factory()->create())
            ->put(route('patients.update', $patient), [
                ...$patient->only(['name', 'file_number', 'ga_weeks', 'ga_days', 'nicu_days']),
                'sex' => $patient->sex->value,
                'status' => $patient->status->value,
                'birth_weight_g' => 1400,
            ])
            ->assertRedirect(route('patients.show', $patient));

        $audit = Audit::query()->where('event', 'updated')->sole();

        $this->assertSame(1400, $patient->refresh()->birth_weight_g);
        $this->assertSame(1000, $audit->old_values['birth_weight_g']);
        $this->assertSame(1400, $audit->new_values['birth_weight_g']);
        $this->assertArrayNotHasKey('nicu_days', $audit->new_values);
    }

    public function test_deleted_patient_moves_to_the_recycle_bin_and_can_be_restored()
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create();

        $this->actingAs($user)->delete(route('patients.destroy', $patient))->assertRedirect(route('patients.index'));

        $this->assertSoftDeleted($patient);
        $this->actingAs($user)->get(route('patients.show', $patient))->assertNotFound();

        $this->actingAs($user)
            ->get(route('patients.index', ['trashed' => 1]))
            ->assertInertia(fn ($page) => $page->where('patients.data.0.id', $patient->id));

        $this->actingAs($user)->post(route('patients.restore', $patient))->assertRedirect(route('patients.show', $patient));

        $this->assertNotSoftDeleted($patient);
        $this->assertSame(['created', 'deleted', 'restored'], Audit::query()->orderBy('id')->pluck('event')->all());
    }

    public function test_patient_list_can_be_searched_and_filtered()
    {
        $user = User::factory()->create();
        $match = Patient::factory()->create(['name' => 'طفل تجريبي أول', 'file_number' => '26', 'status' => PatientStatus::Active]);
        Patient::factory()->create(['name' => 'طفلة تجريبية', 'file_number' => '90', 'status' => PatientStatus::Discharged]);

        $this->actingAs($user)
            ->get(route('patients.index', ['search' => 'تجريبي أول']))
            ->assertInertia(fn ($page) => $page->has('patients.data', 1)->where('patients.data.0.id', $match->id));

        $this->actingAs($user)
            ->get(route('patients.index', ['status' => 'discharged']))
            ->assertInertia(fn ($page) => $page->has('patients.data', 1)->where('patients.data.0.file_number', '90'));
    }

    public function test_patient_list_can_be_exported_as_csv()
    {
        Patient::factory()->create(['name' => 'طفل تجريبي أول']);

        $response = $this->actingAs(User::factory()->create())->get(route('patients.export'));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('طفل تجريبي أول', $response->streamedContent());
    }

    public function test_status_can_be_changed_from_the_reminder_lists()
    {
        $patient = Patient::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('patients.status', $patient), ['status' => 'discharged'])
            ->assertSessionHasNoErrors();

        $this->assertSame(PatientStatus::Discharged, $patient->refresh()->status);
    }

    public function test_top_bar_search_returns_the_first_matching_patients()
    {
        $user = User::factory()->create();
        $match = Patient::factory()->create(['name' => 'Baby of Example', 'file_number' => '777']);
        Patient::factory()->create(['name' => 'Someone Else', 'file_number' => '12']);

        $this->getJson(route('patients.lookup', ['search' => 'Example']))->assertUnauthorized();

        $this->actingAs($user)
            ->getJson(route('patients.lookup', ['search' => 'Example']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('patients.0.id', $match->id);

        $this->actingAs($user)
            ->getJson(route('patients.lookup', ['search' => '777']))
            ->assertJsonPath('patients.0.id', $match->id);

        $this->actingAs($user)
            ->getJson(route('patients.lookup', ['search' => ' ']))
            ->assertJsonPath('total', 0);
    }

    public function test_search_ignores_arabic_spelling_variants_of_the_name()
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create(['name' => 'أحمد مصطفى فاطمة']);
        Patient::factory()->create(['name' => 'زينب كريم']);

        // Without the hamza, with alef maqsura replaced, and with ta marbuta typed as ha.
        foreach (['احمد', 'مصطفي', 'فاطمه'] as $term) {
            $this->actingAs($user)
                ->getJson(route('patients.lookup', ['search' => $term]))
                ->assertJsonPath('total', 1)
                ->assertJsonPath('patients.0.id', $patient->id);
        }

        $this->actingAs($user)->get(route('patients.index', ['search' => 'احمد']))
            ->assertInertia(fn ($page) => $page->has('patients.data', 1));

        // The key follows the name when it is corrected.
        $patient->update(['name' => 'إيمان علي']);

        $this->actingAs($user)
            ->getJson(route('patients.lookup', ['search' => 'ايمان']))
            ->assertJsonPath('total', 1);
    }
}
