<?php

namespace Tests\Feature;

use App\Enums\PatientStatus;
use App\Enums\Stage;
use App\Enums\SuggestionList;
use App\Models\Patient;
use App\Models\Suggestion;
use App\Models\User;
use App\Services\SuggestionLists;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuggestionListTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_form_offers_the_lists_and_saves_ticked_illnesses()
    {
        $user = User::factory()->create();
        Suggestion::remember(SuggestionList::ReferringDoctor, 'Dr. Example');

        $this->actingAs($user)->get(route('patients.create'))
            ->assertInertia(fn ($page) => $page
                ->where('illnessOptions', SuggestionLists::DEFAULT_ILLNESSES)
                ->where('doctorOptions', ['Dr. Example']));

        $this->actingAs($user)->post(route('patients.store'), [
            'name' => 'Baby of Example',
            'sex' => 'female',
            'status' => 'active',
            'illnesses' => ['RDS', 'Jaundice'],
            'systemic_illness' => 'colic',
            'referring_doctor' => 'Dr. New Name',
        ])->assertSessionHasNoErrors();

        $patient = Patient::query()->sole();

        $this->assertSame(['RDS', 'Jaundice'], $patient->illnesses);
        $this->assertSame('RDS; Jaundice; colic', $patient->illnessSummary());

        // A doctor typed for the first time is offered from then on.
        $this->assertContains('Dr. New Name', Suggestion::labels(SuggestionList::ReferringDoctor));

        // Unticking everything means "not recorded", not an empty list.
        $this->actingAs($user)->put(route('patients.update', $patient), [
            'name' => 'Baby of Example',
            'sex' => 'female',
            'status' => 'active',
            'illnesses' => [],
        ])->assertSessionHasNoErrors();

        $this->assertNull($patient->refresh()->illnesses);
    }

    public function test_lists_can_be_extended_renamed_and_shortened_from_settings()
    {
        $user = User::factory()->create();

        $this->get(route('lists.index'))->assertRedirect(route('login'));

        $this->actingAs($user)
            ->post(route('lists.store'), ['list' => 'illness', 'label' => 'Hypoglycemia'])
            ->assertSessionHasNoErrors();
        $this->actingAs($user)
            ->post(route('lists.store'), ['list' => 'illness', 'label' => 'Hypoglycemia'])
            ->assertSessionHasErrors('label');

        // The same text may exist in another list.
        $this->actingAs($user)
            ->post(route('lists.store'), ['list' => 'referring_doctor', 'label' => 'Hypoglycemia'])
            ->assertSessionHasNoErrors();

        $entry = Suggestion::query()->where('list', 'illness')->where('label', 'Hypoglycemia')->sole();

        $this->actingAs($user)
            ->put(route('lists.update', $entry), ['label' => 'RDS'])
            ->assertSessionHasErrors('label');
        $this->actingAs($user)
            ->put(route('lists.update', $entry), ['label' => 'Hypoglycaemia'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Hypoglycaemia', array_last(Suggestion::labels(SuggestionList::Illness)));

        $this->actingAs($user)->delete(route('lists.destroy', $entry))->assertSessionHasNoErrors();

        $this->assertSame(SuggestionLists::DEFAULT_ILLNESSES, Suggestion::labels(SuggestionList::Illness));
    }

    public function test_free_text_illnesses_are_moved_to_the_checklist_without_losing_the_rest()
    {
        $mixed = Patient::factory()->create(['systemic_illness' => 'RDS; jaundice; Bloodtransfusion; colic']);
        $uncertain = Patient::factory()->create(['systemic_illness' => 'RSD (as written; possibly RDS)']);
        Patient::factory()->create(['referring_doctor' => 'Dr. Example']);
        Patient::factory()->create(['referring_doctor' => 'Dr. Ali [rest unclear]']);

        app(SuggestionLists::class)->adoptExistingRecords();

        $this->assertSame(['RDS', 'Jaundice', 'Blood transfusion'], $mixed->refresh()->illnesses);
        $this->assertSame('colic', $mixed->systemic_illness);

        // Text that does not match the list exactly is left exactly as written.
        $this->assertNull($uncertain->refresh()->illnesses);
        $this->assertSame('RSD (as written; possibly RDS)', $uncertain->systemic_illness);

        $this->assertSame(['Dr. Example'], Suggestion::labels(SuggestionList::ReferringDoctor));
    }

    public function test_clinical_terms_keep_an_english_label_in_the_arabic_interface()
    {
        app()->setLocale('ar');

        $this->assertSame('Stage 2', Stage::StageTwo->label());
        $this->assertSame('Stage 2', Stage::StageTwo->englishLabel());
        $this->assertNotSame(PatientStatus::Active->englishLabel(), PatientStatus::Active->label());
    }
}
