<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupTest extends TestCase
{
    // SQLite cannot snapshot the database inside the transaction RefreshDatabase opens.
    use DatabaseMigrations;

    public function test_backup_can_be_created_downloaded_and_deleted()
    {
        $directory = storage_path('framework/testing/backups-'.uniqid());
        config(['registry.backup.path' => $directory]);

        try {
            $backups = app(BackupService::class);
            $name = $backups->create('database');

            $this->assertFileExists($directory.DIRECTORY_SEPARATOR.$name);
            $this->assertSame('database', $backups->all()[0]['type']);

            $user = User::factory()->create();

            $this->get(route('backups.show', $name))->assertRedirect(route('login'));
            $this->actingAs($user)->get(route('backups.show', $name))->assertOk();
            $this->actingAs($user)->get(route('backups.show', '..%2F.env'))->assertNotFound();

            $this->actingAs($user)->delete(route('backups.destroy', $name))->assertSessionHasNoErrors();
            $this->assertFileDoesNotExist($directory.DIRECTORY_SEPARATOR.$name);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_only_the_newest_backups_are_kept()
    {
        $directory = storage_path('framework/testing/backups-'.uniqid());
        config(['registry.backup.path' => $directory, 'registry.backup.keep' => 2]);

        try {
            File::ensureDirectoryExists($directory);

            foreach (['20260101-000000', '20260102-000000', '20260103-000000'] as $stamp) {
                File::put("{$directory}/rop-auto-{$stamp}.zip", 'x');
            }

            File::put("{$directory}/rop-full-20251201-000000.zip", 'x');

            $backups = app(BackupService::class);
            $backups->prune();

            $this->assertSame(
                ['rop-full-20251201-000000.zip', 'rop-auto-20260103-000000.zip', 'rop-auto-20260102-000000.zip'],
                collect($backups->all())->pluck('name')->sortDesc()->values()->all(),
            );
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
