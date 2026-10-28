<?php

namespace Tests\Feature;

use App\Enums\SuggestionList;
use App\Models\Patient;
use App\Models\Suggestion;
use App\Models\User;
use App\Services\RegistryStatistics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReferringDoctorTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_referring_doctor_of_selected_patients_can_be_set_or_cleared_at_once()
    {
        $user = User::factory()->create();
        $first = Patient::factory()->create(['referring_doctor' => 'Dr Typo']);
        $second = Patient::factory()->create(['referring_doctor' => null]);
        $untouched = Patient::factory()->create(['referring_doctor' => 'Dr Typo']);

        $this->actingAs($user)
            ->put(route('patients.referring-doctor'), ['ids' => [$first->id, $second->id], 'referring_doctor' => ' Dr Right '])
            ->assertSessionHasNoErrors();

        $this->assertSame('Dr Right', $first->refresh()->referring_doctor);
        $this->assertSame('Dr Right', $second->refresh()->referring_doctor);
        $this->assertSame('Dr Typo', $untouched->refresh()->referring_doctor);
        $this->assertSame(['old' => 'Dr Typo', 'new' => 'Dr Right'], [
            'old' => $first->audits()->latest('id')->first()->old_values['referring_doctor'],
            'new' => $first->audits()->latest('id')->first()->new_values['referring_doctor'],
        ]);
        $this->assertContains('Dr Right', Suggestion::labels(SuggestionList::ReferringDoctor));

        $this->actingAs($user)
            ->put(route('patients.referring-doctor'), ['ids' => [$first->id], 'referring_doctor' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull($first->refresh()->referring_doctor);
    }

    public function test_bulk_change_needs_existing_patients()
    {
        $this->actingAs(User::factory()->create())
            ->put(route('patients.referring-doctor'), ['ids' => [999999], 'referring_doctor' => 'Dr Right'])
            ->assertSessionHasErrors('ids.0');

        $this->actingAs(User::factory()->create())
            ->put(route('patients.referring-doctor'), ['ids' => [], 'referring_doctor' => 'Dr Right'])
            ->assertSessionHasErrors('ids');
    }

    public function test_renaming_a_doctor_in_the_list_renames_them_on_their_patients()
    {
        $user = User::factory()->create();
        Suggestion::remember(SuggestionList::ReferringDoctor, 'Dr Typo');
        $typo = Suggestion::query()->where('label', 'Dr Typo')->sole();
        $patient = Patient::factory()->create(['referring_doctor' => 'Dr Typo']);
        $trashed = Patient::factory()->create(['referring_doctor' => 'Dr Typo']);
        $trashed->delete();
        $other = Patient::factory()->create(['referring_doctor' => 'Dr Other']);

        $this->actingAs($user)
            ->put(route('lists.update', $typo), ['label' => 'Dr Right'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Dr Right', $typo->refresh()->label);
        $this->assertSame('Dr Right', $patient->refresh()->referring_doctor);
        $this->assertSame('Dr Right', $trashed->refresh()->referring_doctor);
        $this->assertSame('Dr Other', $other->refresh()->referring_doctor);
    }

    public function test_renaming_a_doctor_to_another_entry_merges_the_two()
    {
        $user = User::factory()->create();
        Suggestion::remember(SuggestionList::ReferringDoctor, 'Dr Right');
        Suggestion::remember(SuggestionList::ReferringDoctor, 'Dr Typo');
        $typo = Suggestion::query()->where('label', 'Dr Typo')->sole();
        $patient = Patient::factory()->create(['referring_doctor' => 'Dr Typo']);

        $this->actingAs($user)
            ->put(route('lists.update', $typo), ['label' => 'Dr Right'])
            ->assertSessionHasNoErrors();

        $this->assertModelMissing($typo);
        $this->assertSame('Dr Right', $patient->refresh()->referring_doctor);
        $this->assertSame(1, Suggestion::query()->where('list', SuggestionList::ReferringDoctor)->where('label', 'Dr Right')->count());

        $this->actingAs($user)->get(route('lists.index'))
            ->assertInertia(fn (Assert $page) => $page->where('suggestions', fn ($rows) => collect($rows)
                ->firstWhere('label', 'Dr Right')['patients_count'] === 1));
    }

    public function test_renaming_an_illness_keeps_the_text_saved_on_patients()
    {
        $illness = Suggestion::query()->where('list', SuggestionList::Illness)->where('label', 'Sepsis')->sole();
        $patient = Patient::factory()->create(['illnesses' => ['Sepsis']]);

        $this->actingAs(User::factory()->create())
            ->put(route('lists.update', $illness), ['label' => 'Neonatal sepsis'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Neonatal sepsis', $illness->refresh()->label);
        $this->assertSame(['Sepsis'], $patient->refresh()->illnesses);
    }

    public function test_patients_can_be_listed_and_sorted_by_referring_doctor()
    {
        $user = User::factory()->create();
        Patient::factory()->create(['name' => 'Baby B', 'referring_doctor' => 'Dr Beta']);
        Patient::factory()->create(['name' => 'Baby A', 'referring_doctor' => 'Dr Alpha']);
        Patient::factory()->create(['name' => 'Baby None', 'referring_doctor' => null]);

        $this->actingAs($user)->get(route('patients.index', ['sort' => 'referring_doctor', 'direction' => 'asc']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('patients.data.0.referring_doctor', 'Dr Alpha')
                ->where('patients.data.2.referring_doctor', null)
                ->where('doctorFilterOptions', [
                    ['label' => 'Dr Alpha', 'patients' => 1],
                    ['label' => 'Dr Beta', 'patients' => 1],
                ]));

        $this->actingAs($user)->get(route('patients.index', ['doctor' => 'Dr Beta']))
            ->assertInertia(fn (Assert $page) => $page->where('patients.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['Baby B']));

        $this->actingAs($user)->get(route('patients.index', ['doctor' => 'none']))
            ->assertInertia(fn (Assert $page) => $page->where('patients.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['Baby None']));
    }

    public function test_statistics_count_rop_and_treatment_per_referring_doctor()
    {
        Patient::factory()->create(['referring_doctor' => 'Dr Alpha', 'any_rop' => true, 'had_injection' => true]);
        Patient::factory()->create(['referring_doctor' => 'Dr Alpha', 'any_rop' => false]);
        Patient::factory()->create(['referring_doctor' => 'Dr Beta', 'any_rop' => null]);
        Patient::factory()->create(['referring_doctor' => null, 'any_rop' => true]);
        Patient::factory()->create(['referring_doctor' => 'Dr Beta', 'unverified' => true]);

        $rows = app(RegistryStatistics::class)->calculate(null, null)['doctors']['rows'];

        $this->assertSame(['Dr Alpha', 'Dr Beta', 'Not recorded'], array_column($rows, 'label'));
        $this->assertSame([2, 2, 1, 50.0, 1], [$rows[0]['patients'], $rows[0]['known'], $rows[0]['rop'], $rows[0]['rop_percent'], $rows[0]['treated']]);
        $this->assertSame([1, 0, null], [$rows[1]['patients'], $rows[1]['known'], $rows[1]['rop_percent']]);
        $this->assertSame(1, $rows[2]['rop']);
    }
}
