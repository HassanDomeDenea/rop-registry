<?php

namespace Tests\Feature;

use App\Jobs\CreateBackup;
use App\Jobs\RestoreBackup;
use App\Models\Attachment;
use App\Models\Patient;
use App\Models\User;
use App\Services\BackupService;
use App\Services\HostedBackupService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class HostedBackupTest extends TestCase
{
    // A restore switches foreign keys off, which SQLite refuses inside the transaction RefreshDatabase opens.
    use DatabaseMigrations;

    protected string $workspace;

    /**
     * The hosted registry keeps its files on the "registry" disk; "local" stands for the single computer.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = storage_path('framework/testing/hosted-'.uniqid());
        File::ensureDirectoryExists($this->workspace);

        Storage::fake('local');
        Storage::fake('registry');

        config([
            'registry.attachments.disk' => 'registry',
            'registry.backup.path' => $this->workspace.'/backups',
        ]);

        $this->app->bind(BackupService::class, HostedBackupService::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->workspace);
        File::deleteDirectory(storage_path('app/backup-work'));

        parent::tearDown();
    }

    public function test_hosted_backup_is_an_archive_a_single_computer_can_restore()
    {
        $patient = Patient::factory()->create(['name' => 'Hosted baby']);
        $this->attach($patient, 'registry', 'scan');

        $name = app(HostedBackupService::class)->create('full');

        $zip = new ZipArchive;
        $archive = $this->workspace.'/'.$name;
        File::put($archive, (string) Storage::disk('registry')->get("backups/{$name}"));
        $this->assertTrue($zip->open($archive));
        $zip->extractTo($this->workspace.'/unpacked');
        $zip->close();

        config(['database.connections.unpacked' => ['driver' => 'sqlite', 'database' => $this->workspace.'/unpacked/database.sqlite', 'prefix' => '']]);

        try {
            $this->assertSame(['Hosted baby'], DB::connection('unpacked')->table('patients')->pluck('name')->all());
            $this->assertSame(['local'], DB::connection('unpacked')->table('attachments')->pluck('disk')->all());
            $this->assertTrue(DB::connection('unpacked')->table('migrations')->exists());
        } finally {
            DB::purge('unpacked');
        }

        $this->assertSame('scan', File::get($this->workspace."/unpacked/attachments/{$patient->id}/scan.jpg"));
    }

    public function test_database_backup_leaves_the_attachments_out()
    {
        $this->attach(Patient::factory()->create(), 'registry', 'scan');

        $name = app(HostedBackupService::class)->create('database');

        $zip = new ZipArchive;
        File::put($this->workspace.'/'.$name, (string) Storage::disk('registry')->get("backups/{$name}"));
        $zip->open($this->workspace.'/'.$name);

        $this->assertNotFalse($zip->locateName('database.sqlite'));
        $this->assertSame(2, $zip->numFiles);

        $zip->close();
    }

    public function test_archive_from_a_single_computer_replaces_the_hosted_registry()
    {
        $user = User::factory()->create(['email' => 'clinic@example.com']);
        $kept = Patient::factory()->create(['name' => 'Before backup']);
        $this->attach($kept, 'local', 'original');

        config(['registry.attachments.disk' => 'local']);
        $archive = (new BackupService)->create('full');
        config(['registry.attachments.disk' => 'registry']);

        $user->update(['email' => 'changed@example.com']);
        $added = Patient::factory()->create(['name' => 'After backup']);
        $this->attach($added, 'registry', 'left over');

        $safety = app(HostedBackupService::class)->restore($this->workspace.'/backups/'.$archive);

        $this->assertSame(['Before backup'], Patient::query()->pluck('name')->all());
        $this->assertSame(['clinic@example.com'], User::query()->pluck('email')->all());
        $this->assertSame(['registry'], Attachment::query()->pluck('disk')->all());
        $this->assertSame('original', Storage::disk('registry')->get("attachments/{$kept->id}/scan.jpg"));
        Storage::disk('registry')->assertMissing("attachments/{$added->id}/scan.jpg");
        Storage::disk('registry')->assertExists("backups/{$safety}");
    }

    public function test_a_file_that_is_not_a_registry_backup_changes_nothing()
    {
        Patient::factory()->create();
        File::put($this->workspace.'/broken.zip', 'not a zip archive');

        try {
            app(HostedBackupService::class)->restore($this->workspace.'/broken.zip');
            $this->fail('The broken archive was accepted.');
        } catch (RuntimeException) {
            $this->assertSame(1, Patient::query()->count());
            $this->assertSame([], app(HostedBackupService::class)->all());
        }
    }

    public function test_only_the_newest_hosted_backups_are_kept()
    {
        config(['registry.backup.keep' => 2]);

        foreach (['20260101-000000', '20260102-000000', '20260103-000000'] as $stamp) {
            Storage::disk('registry')->put("backups/rop-auto-{$stamp}.zip", 'x');
        }

        Storage::disk('registry')->put('backups/rop-full-20251201-000000.zip', 'x');
        Storage::disk('registry')->put('backups/notes.txt', 'x');

        $backups = app(HostedBackupService::class);
        $backups->prune();

        $this->assertSame(
            ['rop-auto-20260103-000000.zip', 'rop-auto-20260102-000000.zip', 'rop-full-20251201-000000.zip'],
            array_column($backups->all(), 'name'),
        );
    }

    public function test_backup_is_handed_to_the_queue_and_only_one_runs_at_a_time()
    {
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('backups.store'), ['type' => 'full'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('backups.store'), ['type' => 'database']);

        Queue::assertPushed(CreateBackup::class, 1);
        Queue::assertPushed(fn (CreateBackup $job): bool => $job->type === 'full');

        $this->actingAs($user)->get(route('backups.index'))
            ->assertInertia(fn ($page) => $page->where('hosted', true)->where('task.kind', 'backup')->where('task.status', 'running'));
    }

    public function test_finished_backup_job_frees_the_registry_for_the_next_one()
    {
        $backups = app(HostedBackupService::class);
        $backups->startTask('backup');

        (new CreateBackup('database'))->handle($backups);

        $this->assertNull($backups->task());
        $this->assertSame('database', $backups->all()[0]['type']);
    }

    public function test_failed_job_is_reported_on_the_backups_page()
    {
        $backups = app(HostedBackupService::class);
        $backups->startTask('restore');

        (new RestoreBackup('rop-full-20260101-000000.zip'))->failed(new RuntimeException('Storage is unreachable.'));

        $this->assertSame('failed', $backups->task()['status'] ?? null);
        $this->assertSame('Storage is unreachable.', $backups->task()['message'] ?? null);
        $this->assertTrue($backups->startTask('backup'));
    }

    public function test_restore_is_queued_for_a_listed_backup_only()
    {
        Queue::fake();
        $user = User::factory()->create();
        Storage::disk('registry')->put('backups/rop-full-20260101-000000.zip', 'x');

        $this->actingAs($user)
            ->post(route('backups.restore'), ['backup' => 'rop-full-20260909-000000.zip', 'confirmation' => 'RESTORE'])
            ->assertNotFound();

        $this->actingAs($user)
            ->post(route('backups.restore'), ['backup' => 'rop-full-20260101-000000.zip', 'confirmation' => 'restore'])
            ->assertSessionHasErrors('confirmation');

        Queue::assertNothingPushed();

        $this->actingAs($user)
            ->post(route('backups.restore'), ['backup' => 'rop-full-20260101-000000.zip', 'confirmation' => 'RESTORE'])
            ->assertSessionHasNoErrors();

        Queue::assertPushed(fn (RestoreBackup $job): bool => $job->backup === 'rop-full-20260101-000000.zip');
    }

    public function test_restore_job_replaces_the_registry_from_a_stored_archive()
    {
        Patient::factory()->create(['name' => 'Before backup']);
        $backups = app(HostedBackupService::class);
        $name = $backups->create('full');
        Patient::factory()->create(['name' => 'After backup']);

        (new RestoreBackup($name))->handle($backups);

        $this->assertSame(['Before backup'], Patient::query()->pluck('name')->all());
    }

    public function test_download_goes_straight_to_storage()
    {
        $user = User::factory()->create();
        Storage::disk('registry')->put('backups/rop-full-20260101-000000.zip', 'x');
        Storage::disk('registry')->buildTemporaryUrlsUsing(fn (string $path): string => 'https://storage.example/'.$path);

        $this->get(route('backups.show', 'rop-full-20260101-000000.zip'))->assertRedirect(route('login'));

        $this->actingAs($user)
            ->get(route('backups.show', 'rop-full-20260101-000000.zip'))
            ->assertRedirect('https://storage.example/backups/rop-full-20260101-000000.zip');

        $this->actingAs($user)->get(route('backups.show', '..%2F.env'))->assertNotFound();
    }

    public function test_browser_is_given_an_address_to_upload_an_archive_to()
    {
        $this->travelTo('2026-03-04 05:06:07');

        config([
            'filesystems.disks.bucket' => ['driver' => 's3', 'key' => 'key', 'secret' => 'secret', 'region' => 'auto', 'bucket' => 'registry', 'endpoint' => 'https://storage.example', 'use_path_style_endpoint' => true],
            'registry.attachments.disk' => 'bucket',
        ]);

        $user = User::factory()->create();

        $this->get(route('backups.upload', ['name' => 'rop-database-20260101-000000.zip']))->assertRedirect(route('login'));

        $response = $this->actingAs($user)
            ->getJson(route('backups.upload', ['name' => 'rop-database-20260101-000000.zip']))
            ->assertOk()
            ->assertJsonPath('name', 'rop-database-20260304-050607.zip');

        $this->assertStringStartsWith('https://storage.example/registry/backups/rop-database-20260304-050607.zip?', $response->json('url'));

        $this->actingAs($user)
            ->getJson(route('backups.upload', ['name' => 'My clinic backup (1).zip']))
            ->assertJsonPath('name', 'rop-full-20260304-050607.zip');

        $this->actingAs($user)
            ->getJson(route('backups.upload', ['name' => 'photo.jpg']))
            ->assertJsonValidationErrors('name');
    }

    public function test_backup_folder_cannot_be_set_on_a_hosted_registry()
    {
        $this->actingAs(User::factory()->create())
            ->put(route('backups.settings'), ['path' => $this->workspace])
            ->assertNotFound();
    }

    public function test_single_computer_offers_no_upload_address()
    {
        $this->app->bind(BackupService::class, fn (): BackupService => new BackupService);

        $this->actingAs(User::factory()->create())
            ->getJson(route('backups.upload', ['name' => 'rop-full-20260101-000000.zip']))
            ->assertNotFound();
    }

    protected function attach(Patient $patient, string $disk, string $contents): void
    {
        $path = "attachments/{$patient->id}/scan.jpg";

        Storage::disk($disk)->put($path, $contents);

        $patient->attachments()->create([
            'disk' => $disk,
            'path' => $path,
            'original_name' => 'scan.jpg',
            'mime_type' => 'image/jpeg',
            'size' => strlen($contents),
        ]);
    }
}
