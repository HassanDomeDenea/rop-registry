<?php

namespace Tests\Feature;

use App\Enums\TreatmentType;
use App\Models\Patient;
use App\Models\Setting;
use App\Models\Treatment;
use App\Models\User;
use App\Models\Visit;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class RegistryToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_can_be_exported_as_an_excel_workbook()
    {
        $patient = Patient::factory()->create(['name' => 'طفل تجريبي', 'file_number' => '501']);
        Visit::factory()->for($patient)->create(['visit_date' => '2026-06-20', 'assessment' => 'Both eyes normal']);
        Treatment::factory()->for($patient)->create(['type' => TreatmentType::Laser, 'performed_date' => '2026-06-25']);

        $this->get(route('patients.workbook'))->assertRedirect(route('login'));

        $response = $this->actingAs(User::factory()->create())->get(route('patients.workbook'));

        $response->assertOk()->assertDownload();

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()));

        $workbook = (string) $zip->getFromName('xl/workbook.xml');
        $registry = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $visits = (string) $zip->getFromName('xl/worksheets/sheet2.xml');
        $zip->close();

        foreach (['ROP Registry', 'Visits', 'Treatments', 'Statistics', 'Review log', 'Lists', 'About'] as $sheet) {
            $this->assertStringContainsString('name="'.$sheet.'"', $workbook);
        }

        $this->assertStringContainsString('طفل تجريبي', $registry);
        $this->assertStringContainsString('Both eyes normal', $registry);
        $this->assertStringContainsString('Both eyes normal', $visits);
    }

    public function test_similar_names_and_shared_birth_dates_are_reported_as_possible_duplicates()
    {
        $user = User::factory()->create();
        $existing = Patient::factory()->create(['name' => 'أحمد علي حسين', 'dob' => '2026-03-01']);
        $twin = Patient::factory()->create(['name' => 'فاطمة كريم', 'dob' => '2026-04-10']);
        Patient::factory()->create(['name' => 'زهراء محمود', 'dob' => '2026-01-05']);

        // A different spelling of the same name, without the hamza.
        $this->actingAs($user)
            ->getJson(route('patients.duplicates', ['name' => 'احمد علي']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $existing->id)
            ->assertJsonPath('0.reason', 'name');

        $this->actingAs($user)
            ->getJson(route('patients.duplicates', ['name' => 'زينب كريم', 'dob' => '2026-04-10']))
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $twin->id)
            ->assertJsonPath('0.reason', 'dob');

        // The record being edited is never its own duplicate.
        $this->actingAs($user)
            ->getJson(route('patients.duplicates', ['name' => 'أحمد علي حسين', 'dob' => '2026-03-01', 'ignore' => $existing->id]))
            ->assertJsonCount(0);
    }

    public function test_unverified_patients_are_left_out_of_statistics_and_reminders_until_confirmed()
    {
        $this->travelTo('2026-10-07 10:00:00');

        $user = User::factory()->create();
        $confirmed = Patient::factory()->create();
        $unverified = Patient::factory()->create(['unverified' => true]);

        foreach ([$confirmed, $unverified] as $patient) {
            Visit::factory()->for($patient)->create(['visit_date' => '2026-09-01', 'next_visit_date' => '2026-09-15']);
        }

        $this->actingAs($user)->get(route('statistics.index'))
            ->assertInertia(fn ($page) => $page->where('statistics.cohort.patients', 1)->where('statistics.cohort.unverified', 1));
        $this->actingAs($user)->get(route('statistics.index', ['unverified' => 1]))
            ->assertInertia(fn ($page) => $page->where('statistics.cohort.patients', 2));
        $this->actingAs($user)->get(route('reminders.index'))
            ->assertInertia(fn ($page) => $page->has('overdue', 1)->where('overdue.0.id', $confirmed->id));
        $this->actingAs($user)->get(route('patients.index', ['unverified' => 1]))
            ->assertInertia(fn ($page) => $page->has('patients.data', 1)->where('patients.data.0.id', $unverified->id));

        $this->actingAs($user)->patch(route('patients.verify', $unverified))->assertSessionHasNoErrors();

        $this->assertFalse($unverified->refresh()->unverified);
        $this->actingAs($user)->get(route('reminders.index'))->assertInertia(fn ($page) => $page->has('overdue', 2));
    }

    public function test_backup_folder_can_be_changed_to_a_writable_folder_only()
    {
        $user = User::factory()->create();
        $folder = storage_path('framework/testing/custom-backups-'.uniqid());

        try {
            $this->actingAs($user)
                ->put(route('backups.settings'), ['path' => $folder])
                ->assertSessionHasNoErrors();

            $this->assertSame($folder, Setting::read('backup_path'));
            $this->assertSame($folder, app(BackupService::class)->directory());

            // A path below an existing file can never become a folder.
            File::put($folder.'/file.txt', 'x');

            $this->actingAs($user)
                ->put(route('backups.settings'), ['path' => $folder.'/file.txt/backups'])
                ->assertSessionHasErrors('path');

            $this->assertSame($folder, Setting::read('backup_path'));

            $this->actingAs($user)->put(route('backups.settings'), ['path' => null])->assertSessionHasNoErrors();
            $this->assertNull(Setting::read('backup_path'));
        } finally {
            File::deleteDirectory($folder);
        }
    }

    public function test_restore_requires_the_typed_confirmation()
    {
        $this->actingAs(User::factory()->create())
            ->post(route('backups.restore'), ['backup' => 'rop-full-20260101-000000.zip', 'confirmation' => 'restore please'])
            ->assertSessionHasErrors('confirmation');

        $this->assertAuthenticated();
    }
}
