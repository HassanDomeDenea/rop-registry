<?php

namespace Tests\Feature;

use App\Enums\SuggestionList;
use App\Models\Patient;
use App\Models\Suggestion;
use App\Models\Treatment;
use App\Models\User;
use App\Models\Visit;
use App\Services\RegistryStatistics;
use App\Support\RegistryPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EyeFindingTest extends TestCase
{
    use RefreshDatabase;

    protected function finding(string $label): Suggestion
    {
        return Suggestion::query()->where('list', SuggestionList::EyeFinding)->where('label', $label)->sole();
    }

    public function test_findings_ticked_for_each_eye_are_saved_by_id_and_shown_by_label()
    {
        $patient = Patient::factory()->create();
        $trd = $this->finding('Tractional RD (TRD)');
        $cataract = $this->finding('Cataract');

        $this->actingAs(User::factory()->create())
            ->post(route('patients.visits.store', $patient), [
                'kind' => 'examination',
                'visit_date' => '2026-06-20',
                'right_findings' => [$cataract->id, $trd->id],
                'left_findings' => [],
            ])
            ->assertSessionHasNoErrors();

        $visit = Visit::query()->sole();

        $this->assertSame([$trd->id, $cataract->id], $visit->right_findings);
        $this->assertNull($visit->left_findings);

        // The history names the findings rather than their ids.
        $audit = RegistryPresenter::audit($visit->audits()->sole());
        $this->assertSame('Tractional RD (TRD), Cataract', $audit['new_values']['right_findings']);

        // Renaming the entry renames it on the visits that recorded it.
        $this->put(route('lists.update', $trd), ['label' => 'TRD'])->assertSessionHasNoErrors();

        $this->get(route('patients.show', $patient))
            ->assertInertia(fn (Assert $page) => $page
                ->where('visits.0.right_finding_labels', ['TRD', 'Cataract'])
                ->where('visits.0.left_finding_labels', []));
    }

    public function test_only_entries_of_the_eye_findings_list_can_be_ticked()
    {
        $patient = Patient::factory()->create();
        $illness = Suggestion::query()->where('list', SuggestionList::Illness)->firstOrFail();

        $this->actingAs(User::factory()->create())
            ->post(route('patients.visits.store', $patient), [
                'kind' => 'examination',
                'right_findings' => [$illness->id],
                'left_findings' => [999999],
            ])
            ->assertSessionHasErrors(['right_findings.0', 'left_findings.0']);

        $this->assertSame(0, Visit::query()->count());
    }

    public function test_a_finding_on_saved_visits_is_hidden_instead_of_deleted_and_can_be_offered_again()
    {
        $user = User::factory()->create();
        $used = $this->finding('Shallow AC');
        $unused = $this->finding('Coloboma');
        Visit::factory()->create(['left_findings' => [$used->id]]);

        $this->actingAs($user)->delete(route('lists.destroy', $used))->assertSessionHasNoErrors();
        $this->actingAs($user)->delete(route('lists.destroy', $unused))->assertSessionHasNoErrors();

        $this->assertNotNull($used->refresh()->hidden_at);
        $this->assertModelMissing($unused);
        $this->assertSame([$used->id], Visit::query()->sole()->left_findings);

        $this->actingAs($user)->get(route('patients.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('findingOptions', fn ($options) => collect($options)->contains(
                    fn (array $option): bool => $option['id'] === $used->id && $option['hidden'] === true,
                )));

        $this->actingAs($user)
            ->put(route('lists.visibility', $used), ['hidden' => false])
            ->assertSessionHasNoErrors();

        $this->assertNull($used->refresh()->hidden_at);
    }

    public function test_patients_and_eyes_with_each_finding_are_counted_and_listed()
    {
        $user = User::factory()->create();
        $trd = $this->finding('Tractional RD (TRD)');

        $bothEyes = Patient::factory()->create(['name' => 'Baby Both']);
        Visit::factory()->for($bothEyes)->create(['right_findings' => [$trd->id]]);
        Visit::factory()->for($bothEyes)->create(['right_findings' => [$trd->id], 'left_findings' => [$trd->id]]);
        $oneEye = Patient::factory()->create(['name' => 'Baby One']);
        Visit::factory()->for($oneEye)->create(['left_findings' => [$trd->id]]);
        Visit::factory()->for(Patient::factory()->create(['name' => 'Baby None']))->create();
        Visit::factory()->for(Patient::factory()->create(['name' => 'Baby Unverified', 'unverified' => true]))
            ->create(['right_findings' => [$trd->id]]);

        $charts = app(RegistryStatistics::class)->calculate(null, null)['charts'];

        $this->assertContains(['label' => 'Tractional RD (TRD)', 'value' => 2], $charts['finding_patients']['items']);
        $this->assertContains(['label' => 'Tractional RD (TRD)', 'value' => 3], $charts['finding_eyes']['items']);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('findings', [['id' => $trd->id, 'label' => 'Tractional RD (TRD)', 'patients' => 2]]));

        $this->actingAs($user)->get(route('patients.index', ['finding' => $trd->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('patients.data', fn ($rows) => collect($rows)->pluck('name')->sort()->values()->all()
                    === ['Baby Both', 'Baby One', 'Baby Unverified']));
    }

    public function test_source_records_can_be_switched_off_for_good()
    {
        $patient = Patient::factory()->create(['source_notes' => 'Full PDF pages: 12']);
        Treatment::factory()->for($patient)->create(['source_reference' => 'Index PDF p.25']);
        $user = User::factory()->create();
        config(['registry.source_records' => true]);

        $this->actingAs($user)->get(route('patients.show', $patient))
            ->assertInertia(fn (Assert $page) => $page
                ->where('patient.source_notes', 'Full PDF pages: 12')
                ->where('treatments.0.source_reference', 'Index PDF p.25'));

        config(['registry.source_records' => false]);

        $this->actingAs($user)->get(route('patients.show', $patient))
            ->assertInertia(fn (Assert $page) => $page
                ->where('patient.source_notes', null)
                ->where('treatments.0.source_reference', null));
    }
}
