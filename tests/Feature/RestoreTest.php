<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RestoreTest extends TestCase
{
    protected string $workspace;

    /**
     * A restore replaces the database file itself, so these tests run on a real SQLite file.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = storage_path('framework/testing/restore-'.uniqid());
        File::ensureDirectoryExists($this->workspace);
        File::put($this->workspace.'/database.sqlite', '');

        config([
            'database.connections.sqlite.database' => $this->workspace.'/database.sqlite',
            'registry.backup.path' => $this->workspace.'/backups',
        ]);

        DB::purge();
        Artisan::call('migrate', ['--force' => true]);
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        DB::disconnect();
        File::deleteDirectory($this->workspace);

        parent::tearDown();
    }

    public function test_full_backup_restores_patients_and_attachments_and_signs_the_user_out()
    {
        $user = User::factory()->create();
        $kept = Patient::factory()->create(['name' => 'Before backup']);
        Storage::disk('local')->put("attachments/{$kept->id}/scan.jpg", 'original');

        $backup = app(BackupService::class)->create('full');

        Patient::factory()->create(['name' => 'After backup']);
        Storage::disk('local')->put("attachments/{$kept->id}/scan.jpg", 'changed');

        $this->actingAs($user)
            ->post(route('backups.restore'), ['backup' => $backup, 'confirmation' => 'RESTORE'])
            ->assertRedirect(route('login'));

        $this->assertSame(['Before backup'], Patient::query()->pluck('name')->all());
        $this->assertSame('original', Storage::disk('local')->get("attachments/{$kept->id}/scan.jpg"));

        // The state before the restore is kept as a safety backup.
        $this->assertContains('safety', array_column(app(BackupService::class)->all(), 'type'));
    }

    public function test_a_file_that_is_not_a_registry_backup_is_rejected()
    {
        $user = User::factory()->create();
        Patient::factory()->create();

        File::ensureDirectoryExists($this->workspace.'/backups');
        File::put($this->workspace.'/backups/rop-full-20260101-000000.zip', 'not a zip archive');

        $this->actingAs($user)
            ->from(route('backups.index'))
            ->post(route('backups.restore'), ['backup' => 'rop-full-20260101-000000.zip', 'confirmation' => 'RESTORE'])
            ->assertRedirect(route('backups.index'))
            ->assertSessionHasErrors('archive');

        $this->assertSame(1, Patient::query()->count());
    }
}
