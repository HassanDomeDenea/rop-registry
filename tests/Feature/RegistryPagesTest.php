<?php

namespace Tests\Feature;

use App\Enums\TreatmentType;
use App\Models\Attachment;
use App\Models\Patient;
use App\Models\ReviewItem;
use App\Models\Treatment;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistryPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function pages(): array
    {
        return [
            'dashboard' => ['dashboard'],
            'patients' => ['patients.index'],
            'new patient' => ['patients.create'],
            'reminders' => ['reminders.index'],
            'statistics' => ['statistics.index'],
            'review queue' => ['review.index'],
            'audit log' => ['audits.index'],
            'backups' => ['backups.index'],
        ];
    }

    #[DataProvider('pages')]
    public function test_registry_pages_render_for_the_administrator(string $route)
    {
        config(['registry.backup.path' => storage_path('framework/testing/backups')]);

        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        Treatment::factory()->for($patient)->create(['visit_id' => $visit->id]);
        $patient->reviewItems()->create(['issue' => 'Birth weight differs between referral and report']);

        $this->get(route($route))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route($route))->assertOk();
    }

    public function test_patient_record_and_print_pages_render()
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();

        $this->actingAs($user)->get(route('patients.show', $patient))
            ->assertInertia(fn ($page) => $page->component('patients/Show')->has('visits', 1));
        $this->actingAs($user)->get(route('patients.print', $patient))->assertOk();
        $this->actingAs($user)->get(route('patients.visits.print', [$patient, $visit]))->assertOk();
        $this->actingAs($user)->get(route('patients.visits.edit', [$patient, $visit]))->assertOk();
    }

    public function test_reminders_group_patients_by_what_needs_attention()
    {
        $this->travelTo('2026-10-07 10:00:00');

        $today = Patient::factory()->create();
        Visit::factory()->for($today)->create(['visit_date' => '2026-09-23', 'next_visit_date' => '2026-10-07']);

        $overdue = Patient::factory()->create();
        Visit::factory()->for($overdue)->create(['visit_date' => '2026-09-01', 'next_visit_date' => '2026-09-15']);

        $injected = Patient::factory()->create();
        Visit::factory()->for($injected)->create(['visit_date' => '2026-09-20', 'next_visit_date' => '2026-10-12']);
        Treatment::factory()->for($injected)->create(['type' => TreatmentType::Eylea, 'performed_date' => '2026-09-21']);

        $this->actingAs(User::factory()->create())
            ->get(route('reminders.index'))
            ->assertInertia(fn ($page) => $page
                ->where('today.0.id', $today->id)
                ->has('today', 1)
                ->where('overdue.0.id', $overdue->id)
                ->has('overdue', 1)
                ->where('upcoming.0.id', $injected->id)
                ->where('injectionSurveillance.0.id', $injected->id)
                ->where('reminderCounts.attention', 2));
    }

    public function test_statistics_separate_known_and_unknown_rop_status()
    {
        $withRop = Patient::factory()->create(['ga_weeks' => 27]);
        Visit::factory()->for($withRop)->create(['visit_date' => '2026-06-01', 'right_rop_status' => 'present', 'right_stage' => 'stage_2']);

        $withoutRop = Patient::factory()->create(['ga_weeks' => 33]);
        Visit::factory()->for($withoutRop)->create(['visit_date' => '2026-06-10']);

        Patient::factory()->create(['ga_weeks' => 33]);

        $this->actingAs(User::factory()->create())
            ->get(route('statistics.index'))
            ->assertInertia(fn ($page) => $page
                ->where('statistics.cohort.patients', 3)
                ->where('statistics.charts.rop.items.0.value', 1)
                ->where('statistics.charts.rop.items.1.value', 1)
                ->where('statistics.charts.rop.unknown', 1)
                ->where('statistics.charts.eye_rop.items.0.value', 1)
                ->where('statistics.crosstabs.0.rows.0.rop', 1));
    }

    public function test_statistics_can_be_limited_to_a_date_range()
    {
        $early = Patient::factory()->create();
        Visit::factory()->for($early)->create(['visit_date' => '2026-02-10']);

        $late = Patient::factory()->create();
        Visit::factory()->for($late)->create(['visit_date' => '2026-08-10']);

        $this->actingAs(User::factory()->create())
            ->get(route('statistics.index', ['from' => '2026-08-01', 'to' => '2026-08-31']))
            ->assertInertia(fn ($page) => $page
                ->where('statistics.cohort.patients', 1)
                ->where('statistics.cohort.registry_total', 2));
    }

    public function test_pictures_and_pdfs_can_be_attached_viewed_and_deleted()
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $patient = Patient::factory()->create();

        $this->actingAs($user)
            ->post(route('patients.attachments.store', $patient), [
                'files' => [
                    UploadedFile::fake()->image('fundus.jpg'),
                    UploadedFile::fake()->create('report.pdf', 120, 'application/pdf'),
                ],
            ])
            ->assertSessionHasNoErrors();

        $attachments = Attachment::query()->orderBy('id')->get();

        $this->assertCount(2, $attachments);
        $this->assertTrue($attachments[0]->isImage());
        Storage::disk('local')->assertExists($attachments[0]->path);

        $this->get(route('attachments.show', $attachments[0]))->assertOk();

        $this->delete(route('attachments.destroy', $attachments[1]))->assertSessionHasNoErrors();
        $this->assertSoftDeleted($attachments[1]);
    }

    public function test_unsupported_files_cannot_be_attached()
    {
        Storage::fake('local');

        $patient = Patient::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('patients.attachments.store', $patient), [
                'files' => [UploadedFile::fake()->create('script.php', 10, 'text/x-php')],
            ])
            ->assertSessionHasErrors('files.0');

        $this->assertSame(0, Attachment::query()->count());
    }

    public function test_guests_cannot_view_attachments()
    {
        Storage::fake('local');

        $patient = Patient::factory()->create();
        $attachment = $patient->attachments()->create([
            'disk' => 'local', 'path' => 'attachments/1/a.jpg', 'original_name' => 'a.jpg', 'mime_type' => 'image/jpeg', 'size' => 10,
        ]);

        $this->get(route('attachments.show', $attachment))->assertRedirect(route('login'));
    }

    public function test_review_item_can_be_resolved_and_reopened()
    {
        $user = User::factory()->create();
        $item = Patient::factory()->create()->reviewItems()->create(['issue' => 'Date unclear']);

        $this->actingAs($user)->patch(route('review-items.update', $item), ['resolved' => true, 'resolution' => 'Confirmed from the photo']);

        $this->assertNotNull($item->refresh()->resolved_at);
        $this->assertSame('Confirmed from the photo', $item->resolution);
        $this->assertSame(0, ReviewItem::query()->open()->count());

        $this->actingAs($user)->patch(route('review-items.update', $item), ['resolved' => false]);

        $this->assertNull($item->refresh()->resolved_at);
    }
}
