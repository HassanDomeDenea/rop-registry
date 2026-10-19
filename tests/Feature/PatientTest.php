<?php

namespace Tests\Feature;

use App\Enums\PatientStatus;
use App\Enums\Sex;
use App\Enums\Stage;
use App\Enums\Zone;
use App\Models\Audit;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
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
            'mother_name' => 'أم تجريبية',
            'sex' => 'male',
            'dob' => '2026-05-16',
            'ga_weeks' => 30,
            'birth_weight_g' => 1400,
            'status' => 'active',
        ]);

        $patient = Patient::query()->sole();

        $response->assertRedirect(route('patients.show', $patient));
        $this->assertSame('طفل تجريبي', $patient->name);
        $this->assertSame('أم تجريبية', $patient->mother_name);
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

    /**
     * @return array<string, array{array<string, int|string|null>, array<string, int|null>}>
     */
    public static function supportDays(): array
    {
        return [
            'one type keeps its days as the total' => [
                ['respiratory_support' => 'o2', 'o2_days' => 8],
                ['support_days' => 8, 'o2_days' => null, 'cpap_days' => null],
            ],
            'one type drops a split' => [
                ['respiratory_support' => 'cpap', 'support_days' => 6, 'o2_days' => 2, 'cpap_days' => 4],
                ['support_days' => 6, 'o2_days' => null, 'cpap_days' => null],
            ],
            'O2 and CPAP days stay separate' => [
                ['respiratory_support' => 'o2_cpap', 'o2_days' => 9, 'cpap_days' => 5],
                ['support_days' => null, 'o2_days' => 9, 'cpap_days' => 5],
            ],
            'O2 and CPAP keep an earlier combined total' => [
                ['respiratory_support' => 'o2_cpap', 'support_days' => 12, 'o2_days' => 4],
                ['support_days' => 12, 'o2_days' => 4, 'cpap_days' => null],
            ],
            'no support has no days' => [
                ['respiratory_support' => 'none', 'support_days' => 5, 'o2_days' => 5],
                ['support_days' => null, 'o2_days' => null, 'cpap_days' => null],
            ],
            'unrecorded support keeps what was entered' => [
                ['respiratory_support' => null, 'support_days' => 5],
                ['support_days' => 5, 'o2_days' => null, 'cpap_days' => null],
            ],
        ];
    }

    /**
     * @param  array<string, int|string|null>  $input
     * @param  array<string, int|null>  $expected
     */
    #[DataProvider('supportDays')]
    public function test_support_days_follow_the_type_of_respiratory_support(array $input, array $expected)
    {
        $this->actingAs(User::factory()->create())
            ->post(route('patients.store'), [...$this->newPatient(), ...$input])
            ->assertSessionHasNoErrors();

        $this->assertSame($expected, Patient::query()->sole()->only(['support_days', 'o2_days', 'cpap_days']));
    }

    public function test_existing_days_of_a_single_type_of_support_move_without_losing_values()
    {
        $rows = [
            'o2' => ['respiratory_support' => 'o2', 'support_days' => null, 'o2_days' => 8, 'cpap_days' => null],
            'cpap' => ['respiratory_support' => 'cpap', 'support_days' => null, 'o2_days' => null, 'cpap_days' => 7],
            'total' => ['respiratory_support' => 'o2', 'support_days' => 10, 'o2_days' => null, 'cpap_days' => null],
            'conflict' => ['respiratory_support' => 'o2', 'support_days' => 10, 'o2_days' => 4, 'cpap_days' => null],
            'split' => ['respiratory_support' => 'o2_cpap', 'support_days' => null, 'o2_days' => 9, 'cpap_days' => 5],
            'partial' => ['respiratory_support' => 'o2_cpap', 'support_days' => null, 'o2_days' => 2, 'cpap_days' => null],
        ];

        $ids = collect($rows)->map(fn (array $row): int => Patient::factory()->create(['respiratory_support' => null])->id);

        foreach ($rows as $key => $row) {
            DB::table('patients')->where('id', $ids[$key])->update($row);
        }

        (require database_path('migrations/2026_10_19_213201_consolidate_respiratory_support_days.php'))->up();

        $stored = fn (string $key): array => (array) DB::table('patients')->where('id', $ids[$key])
            ->first(['support_days', 'o2_days', 'cpap_days']);

        $this->assertEquals(['support_days' => 8, 'o2_days' => null, 'cpap_days' => null], $stored('o2'));
        $this->assertEquals(['support_days' => 7, 'o2_days' => null, 'cpap_days' => null], $stored('cpap'));
        $this->assertEquals(['support_days' => 10, 'o2_days' => null, 'cpap_days' => null], $stored('total'));
        $this->assertEquals(['support_days' => 10, 'o2_days' => 4, 'cpap_days' => null], $stored('conflict'));
        $this->assertEquals(['support_days' => null, 'o2_days' => 9, 'cpap_days' => 5], $stored('split'));
        $this->assertEquals(['support_days' => null, 'o2_days' => 2, 'cpap_days' => null], $stored('partial'));
    }

    public function test_patient_can_be_registered_together_with_the_first_examination()
    {
        $response = $this->actingAs(User::factory()->create())
            ->post(route('patients.store'), [...$this->newPatient(), 'visit' => $this->examination()]);

        $patient = Patient::query()->sole();
        $visit = Visit::query()->sole();

        $response->assertRedirect(route('patients.show', $patient));
        $response->assertInertiaFlash('toast.message', 'Patient registered with the first examination.');
        $this->assertSame($patient->id, $visit->patient_id);
        $this->assertSame('2026-06-20', $visit->visit_date->format('Y-m-d'));
        $this->assertSame(Zone::ZoneTwo, $visit->right_zone);
        $this->assertSame(Stage::StageTwo, $visit->right_stage);
        $this->assertSame(1, $patient->refresh()->exams_count);
    }

    public function test_patient_is_registered_without_an_examination_when_it_is_switched_off()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('patients.store'), [...$this->newPatient(), 'visit' => null])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Patient::query()->count());
        $this->assertSame(0, Visit::query()->count());
    }

    public function test_invalid_first_examination_keeps_the_patient_from_being_registered()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('patients.store'), [
                ...$this->newPatient(),
                'visit' => $this->examination(['next_visit_date' => '2026-06-01', 'right_stage' => 'stage_9']),
            ])
            ->assertSessionHasErrors([
                'visit.next_visit_date' => 'The next visit date field must be a date after or equal to visit date.',
                'visit.right_stage',
            ]);

        $this->assertSame(0, Patient::query()->count());
        $this->assertSame(0, Visit::query()->count());
    }

    public function test_patient_form_offers_the_earliest_dated_visit_as_the_first_examination()
    {
        $patient = Patient::factory()->create();
        Visit::factory()->for($patient)->create(['visit_date' => null]);
        Visit::factory()->for($patient)->create(['visit_date' => '2026-07-10']);
        $earliest = Visit::factory()->for($patient)->create(['visit_date' => '2026-06-20']);

        $this->actingAs(User::factory()->create());

        $this->get(route('patients.edit', $patient))
            ->assertInertia(fn (Assert $page) => $page->where('firstVisit.id', $earliest->id));
        $this->get(route('patients.edit', Patient::factory()->create()))
            ->assertInertia(fn (Assert $page) => $page->where('firstVisit', null));
    }

    public function test_first_examination_can_be_corrected_from_the_patient_form()
    {
        $patient = Patient::factory()->create();
        $first = Visit::factory()->for($patient)->create(['visit_date' => '2026-06-20', 'right_stage' => Stage::StageOne]);
        $later = Visit::factory()->for($patient)->create(['visit_date' => '2026-07-10', 'right_stage' => Stage::StageOne]);

        $this->actingAs(User::factory()->create())
            ->put(route('patients.update', $patient), [
                ...$this->existingPatient($patient),
                'visit' => $this->examination(['right_stage' => 'stage_3']),
                'visit_id' => $first->id,
            ])
            ->assertRedirect(route('patients.show', $patient));

        $this->assertSame(Stage::StageThree, $first->refresh()->right_stage);
        $this->assertSame(Stage::StageOne, $later->refresh()->right_stage);
        $this->assertSame(2, Visit::query()->count());
    }

    public function test_first_examination_can_be_added_while_editing_a_patient_without_visits()
    {
        $patient = Patient::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('patients.update', $patient), [
                ...$this->existingPatient($patient),
                'visit' => $this->examination(),
                'visit_id' => null,
            ])
            ->assertRedirect(route('patients.show', $patient));

        $visit = Visit::query()->sole();

        $this->assertSame($patient->id, $visit->patient_id);
        $this->assertSame(Stage::StageTwo, $visit->right_stage);
    }

    public function test_switched_off_examination_leaves_the_visit_unchanged()
    {
        $patient = Patient::factory()->create(['birth_weight_g' => 1000]);
        $visit = Visit::factory()->for($patient)->create(['right_stage' => Stage::StageOne]);

        $this->actingAs(User::factory()->create())
            ->put(route('patients.update', $patient), [
                ...$this->existingPatient($patient),
                'birth_weight_g' => 1400,
                'visit' => null,
                'visit_id' => $visit->id,
            ])
            ->assertRedirect(route('patients.show', $patient));

        $this->assertSame(1400, $patient->refresh()->birth_weight_g);
        $this->assertSame(Stage::StageOne, $visit->refresh()->right_stage);
    }

    public function test_visit_of_another_patient_cannot_be_corrected_through_the_patient_form()
    {
        $patient = Patient::factory()->create(['birth_weight_g' => 1000]);
        $other = Visit::factory()->create(['right_stage' => Stage::StageOne]);

        $this->actingAs(User::factory()->create())
            ->put(route('patients.update', $patient), [
                ...$this->existingPatient($patient),
                'birth_weight_g' => 1400,
                'visit' => $this->examination(['right_stage' => 'stage_3']),
                'visit_id' => $other->id,
            ])
            ->assertSessionHasErrors('visit_id');

        $this->assertSame(1000, $patient->refresh()->birth_weight_g);
        $this->assertSame(Stage::StageOne, $other->refresh()->right_stage);
        $this->assertSame(1, Visit::query()->count());
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

    public function test_source_records_can_be_marked_as_reviewed_and_shown_again()
    {
        $this->freezeSecond();
        $patient = Patient::factory()->create(['source_notes' => 'Full PDF pages: 95, 96']);
        $this->actingAs(User::factory()->create());

        $this->patch(route('patients.source-notes', $patient), ['reviewed' => true])->assertSessionHasNoErrors();

        $this->assertEquals(now(), $patient->refresh()->source_notes_reviewed_at);
        $this->assertSame('Full PDF pages: 95, 96', $patient->source_notes);
        $this->assertSame(['source_notes_reviewed_at' => null], Audit::query()->latest('id')->first()->old_values);
        $this->get(route('patients.show', $patient))
            ->assertInertia(fn ($page) => $page->where('patient.source_notes_reviewed_at', now()->toIso8601String()));

        $this->patch(route('patients.source-notes', $patient), ['reviewed' => false])->assertSessionHasNoErrors();

        $this->assertNull($patient->refresh()->source_notes_reviewed_at);
    }

    public function test_source_records_review_requires_a_yes_or_no()
    {
        $patient = Patient::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('patients.source-notes', $patient), ['reviewed' => 'later'])
            ->assertSessionHasErrors('reviewed');

        $this->assertNull($patient->refresh()->source_notes_reviewed_at);
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

        // The referring doctor is matched the same way.
        $referred = Patient::factory()->create(['name' => 'Baby of Example', 'referring_doctor' => 'د. آمنة أحمد']);

        $this->actingAs($user)
            ->getJson(route('patients.lookup', ['search' => 'امنه احمد']))
            ->assertJsonPath('total', 1)
            ->assertJsonPath('patients.0.id', $referred->id);

        // So is the mother's name.
        $mothered = Patient::factory()->create(['name' => 'Baby of Another', 'mother_name' => 'هدى إبراهيم']);

        $this->actingAs($user)
            ->getJson(route('patients.lookup', ['search' => 'هدي ابراهيم']))
            ->assertJsonPath('total', 1)
            ->assertJsonPath('patients.0.id', $mothered->id);

        // The change log finds records by the same rule.
        $this->actingAs($user)->get(route('audits.index', ['search' => 'مصطفي']))
            ->assertInertia(fn ($page) => $page->has('audits.data', 1));

        // The key follows the name when it is corrected.
        $patient->update(['name' => 'إيمان علي']);

        $this->actingAs($user)
            ->getJson(route('patients.lookup', ['search' => 'ايمان']))
            ->assertJsonPath('total', 1);
    }

    /**
     * @return array<string, string>
     */
    protected function newPatient(): array
    {
        return ['name' => 'طفل تجريبي', 'sex' => 'female', 'status' => 'active'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function existingPatient(Patient $patient): array
    {
        return [
            ...$patient->only(['name', 'birth_weight_g']),
            'sex' => $patient->sex->value,
            'status' => $patient->status->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function examination(array $overrides = []): array
    {
        return [
            'kind' => 'examination',
            'visit_date' => '2026-06-20',
            'right_zone' => 'zone_2',
            'right_stage' => 'stage_2',
            'right_plus' => 'none',
            'left_rop_status' => 'no_rop',
            'next_visit_date' => '2026-07-04',
            ...$overrides,
        ];
    }
}
