<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentImageEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->actingAs(User::factory()->create());
    }

    protected function storedPicture(string $name = 'scan.jpg', string $mimeType = 'image/jpeg'): Attachment
    {
        $patient = Patient::factory()->create();
        $path = "attachments/{$patient->id}/stored.".pathinfo($name, PATHINFO_EXTENSION);

        Storage::disk('local')->put($path, 'first version');

        return $patient->attachments()->create([
            'disk' => 'local', 'path' => $path, 'original_name' => $name, 'mime_type' => $mimeType, 'size' => 13, 'hash' => 'first-version-hash',
        ]);
    }

    public function test_saving_an_edit_shows_the_new_picture_and_keeps_the_first_version()
    {
        $attachment = $this->storedPicture();
        $firstPath = $attachment->path;

        $this->post(route('attachments.image.update', $attachment), ['image' => UploadedFile::fake()->image('edited.jpg')])
            ->assertSessionHasNoErrors();

        $attachment->refresh();

        $this->assertTrue($attachment->isEdited());
        $this->assertNotSame($firstPath, $attachment->path);
        $this->assertSame($firstPath, $attachment->original_path);
        $this->assertSame('image/jpeg', $attachment->original_mime_type);
        $this->assertSame(13, $attachment->original_size);
        $this->assertNotNull($attachment->edited_at);
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertSame('first version', Storage::disk('local')->get($firstPath));
    }

    public function test_the_camera_inbox_still_recognises_an_edited_picture_as_already_received()
    {
        $attachment = $this->storedPicture();

        $this->post(route('attachments.image.update', $attachment), ['image' => UploadedFile::fake()->image('edited.jpg')]);

        $this->assertSame('first-version-hash', $attachment->refresh()->hash);
    }

    public function test_a_second_edit_still_returns_to_the_first_version()
    {
        $attachment = $this->storedPicture();
        $firstPath = $attachment->path;

        $this->post(route('attachments.image.update', $attachment), ['image' => UploadedFile::fake()->image('edited.jpg')]);
        $this->post(route('attachments.image.update', $attachment), ['image' => UploadedFile::fake()->image('again.png')]);

        $this->assertSame($firstPath, $attachment->refresh()->original_path);
        $this->assertSame('image/png', $attachment->mime_type);

        $this->delete(route('attachments.image.restore', $attachment))->assertSessionHasNoErrors();

        $attachment->refresh();

        $this->assertFalse($attachment->isEdited());
        $this->assertSame($firstPath, $attachment->path);
        $this->assertSame('image/jpeg', $attachment->mime_type);
        $this->assertSame(13, $attachment->size);
        $this->assertNull($attachment->edited_at);
    }

    public function test_a_picture_that_was_never_edited_has_nothing_to_restore()
    {
        $attachment = $this->storedPicture();

        $this->delete(route('attachments.image.restore', $attachment))->assertNotFound();
    }

    public function test_only_pictures_can_be_edited()
    {
        $attachment = $this->storedPicture('report.pdf', 'application/pdf');

        $this->post(route('attachments.image.update', $attachment), ['image' => UploadedFile::fake()->image('edited.jpg')])
            ->assertNotFound();

        $this->assertFalse($attachment->refresh()->isEdited());
    }

    public function test_an_edit_must_be_a_jpeg_or_png_picture()
    {
        $attachment = $this->storedPicture();

        $this->post(route('attachments.image.update', $attachment), ['image' => UploadedFile::fake()->create('script.php', 10, 'text/x-php')])
            ->assertSessionHasErrors('image');

        $this->assertFalse($attachment->refresh()->isEdited());
    }

    public function test_guests_cannot_edit_or_restore_a_picture()
    {
        $attachment = $this->storedPicture();
        $this->post(route('attachments.image.update', $attachment), ['image' => UploadedFile::fake()->image('edited.jpg')]);

        auth()->logout();

        $this->post(route('attachments.image.update', $attachment), ['image' => UploadedFile::fake()->image('other.jpg')])
            ->assertRedirect(route('login'));
        $this->delete(route('attachments.image.restore', $attachment))->assertRedirect(route('login'));

        $this->assertTrue($attachment->refresh()->isEdited());
    }

    public function test_the_address_of_a_picture_changes_with_each_edit_so_browsers_do_not_show_a_cached_copy()
    {
        $attachment = $this->storedPicture();

        $this->assertSame(route('attachments.show', $attachment), $this->presentedUrl($attachment));

        $this->post(route('attachments.image.update', $attachment), ['image' => UploadedFile::fake()->image('edited.jpg')]);
        $edited = $this->presentedUrl($attachment);

        $this->assertNotSame(route('attachments.show', $attachment), $edited);

        $this->travel(5)->seconds();
        $this->post(route('attachments.image.update', $attachment), ['image' => UploadedFile::fake()->image('again.jpg')]);

        $this->assertNotSame($edited, $this->presentedUrl($attachment));
    }

    public function test_a_download_is_named_after_the_upload_with_the_extension_of_the_edited_file()
    {
        $attachment = $this->storedPicture('fundus.bmp', 'image/bmp');

        $this->post(route('attachments.image.update', $attachment), ['image' => UploadedFile::fake()->image('edited.png')]);

        $this->get(route('attachments.show', ['attachment' => $attachment, 'download' => 1]))
            ->assertOk()
            ->assertDownload('fundus.png');

        $this->assertSame('fundus.bmp', $attachment->refresh()->original_name);
    }

    protected function presentedUrl(Attachment $attachment): string
    {
        $url = null;

        $this->get(route('patients.show', $attachment->patient_id))
            ->assertInertia(function ($page) use (&$url) {
                $url = $page->toArray()['props']['attachments'][0]['url'];
            });

        return $url;
    }
}
