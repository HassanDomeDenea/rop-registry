<?php

namespace Tests\Feature;

use App\Models\Capture;
use App\Models\Patient;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visit;
use App\Services\CaptureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CaptureTest extends TestCase
{
    use RefreshDatabase;

    protected string $spool;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->spool = storage_path('framework/testing/spool-'.uniqid());
        File::ensureDirectoryExists($this->spool);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->spool);

        parent::tearDown();
    }

    /**
     * Write a small picture where the virtual printer or the camera would save it.
     */
    protected function printed(string $name, int $width = 20): string
    {
        $path = $this->spool.DIRECTORY_SEPARATOR.$name;

        File::put($path, UploadedFile::fake()->image($name, $width, 20)->getContent());

        return $path;
    }

    public function test_printed_pages_land_in_the_inbox_once_and_the_originals_are_removed()
    {
        $first = $this->printed('page-1.png', 20);
        $second = $this->printed('page-2.png', 30);
        $note = $this->spool.DIRECTORY_SEPARATOR.'notes.txt';
        File::put($note, 'not an image');

        $this->artisan('registry:capture', ['files' => [$first, $second, $note]])->assertSuccessful();

        $this->assertSame(2, Capture::query()->count());
        $this->assertCount(1, Capture::query()->distinct()->pluck('batch'));
        $this->assertFileDoesNotExist($first);
        $this->assertFileDoesNotExist($second);
        $this->assertFileExists($note);

        foreach (Capture::all() as $capture) {
            Storage::disk('local')->assertExists($capture->path);
        }

        // The same page printed again is recognised and not stored twice.
        $again = $this->printed('page-1-again.png', 20);
        $this->artisan('registry:capture', ['files' => [$again]])->assertSuccessful();

        $this->assertSame(2, Capture::query()->count());
        $this->assertFileDoesNotExist($again);
    }

    public function test_images_are_assigned_to_the_visit_of_today_and_leave_the_inbox()
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create();
        $today = Visit::factory()->for($patient)->create(['visit_date' => today()]);

        app(CaptureService::class)->ingest([$this->printed('a.png', 20), $this->printed('b.png', 30)], 'printer');
        [$kept, $assigned] = Capture::query()->oldest('id')->get()->all();

        $this->actingAs($user)->get(route('captures.index'))
            ->assertInertia(fn ($page) => $page->component('captures/Index')->has('batches', 1)->has('batches.0.captures', 2));
        $this->actingAs($user)->getJson(route('captures.status'))->assertJsonPath('pending', 2);

        $this->actingAs($user)
            ->post(route('captures.assign'), ['captures' => [$assigned->id], 'patient_id' => $patient->id, 'today_visit' => true])
            ->assertSessionHasNoErrors();

        $attachment = $patient->attachments()->sole();

        $this->assertSame($today->id, $attachment->visit_id);
        Storage::disk('local')->assertExists($attachment->path);
        Storage::disk('local')->assertMissing($assigned->path);
        $this->assertSame([$kept->id], Capture::query()->pluck('id')->all());

        // An image that is already attached is not taken in again.
        File::put($this->spool.DIRECTORY_SEPARATOR.'b-copy.png', Storage::disk('local')->get($attachment->path));
        app(CaptureService::class)->ingest([$this->spool.DIRECTORY_SEPARATOR.'b-copy.png'], 'printer');

        $this->assertSame(1, Capture::query()->count());

        $this->actingAs($user)->post(route('captures.discard'), ['captures' => [$kept->id]])->assertSessionHasNoErrors();

        $this->assertSame(0, Capture::query()->count());
        Storage::disk('local')->assertMissing($kept->path);
    }

    public function test_incoming_images_go_straight_to_the_patient_who_is_receiving()
    {
        $user = User::factory()->create();
        $patient = Patient::factory()->create();

        $this->actingAs($user)->post(route('captures.receive', $patient))->assertSessionHasNoErrors();
        $this->actingAs($user)->getJson(route('captures.status'))
            ->assertJsonPath('target.patient_id', $patient->id)
            ->assertJsonPath('target.received', 0);

        $this->artisan('registry:capture', ['files' => [$this->printed('a.png')]])->assertSuccessful();

        $this->assertSame(0, Capture::query()->count());
        $this->assertSame(1, $patient->attachments()->count());
        $this->actingAs($user)->getJson(route('captures.status'))->assertJsonPath('target.received', 1);

        // After the receiving time, or after pressing Stop, images wait in the inbox again.
        $this->travel(10)->minutes();
        $this->actingAs($user)->getJson(route('captures.status'))->assertJsonPath('target', null);

        $this->artisan('registry:capture', ['files' => [$this->printed('b.png', 30)]])->assertSuccessful();

        $this->assertSame(1, Capture::query()->count());
        $this->assertSame(1, $patient->attachments()->count());
    }

    public function test_files_exported_into_the_camera_folder_are_taken_over_when_they_are_complete()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put(route('captures.settings'), ['folder' => $this->spool])->assertSessionHasNoErrors();
        $this->assertSame($this->spool, Setting::read('capture_folder'));

        $finished = $this->printed('export-1.jpg', 20);
        $writing = $this->printed('export-2.jpg', 30);
        touch($finished, time() - 30);

        $this->actingAs($user)->getJson(route('captures.status'))->assertJsonPath('pending', 1);

        $this->assertFileDoesNotExist($finished);
        $this->assertFileExists($writing);
        $this->assertSame('export', Capture::query()->sole()->source);
    }
}
