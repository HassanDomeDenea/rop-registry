<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
    }

    public function test_interface_language_can_be_changed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('appearance.edit'))
            ->put(route('preferences.update'), ['locale' => 'ar']);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('appearance.edit'));

        $this->assertSame('ar', $user->refresh()->locale);

        $this->actingAs($user)
            ->get(route('appearance.edit'))
            ->assertInertia(fn ($page) => $page->where('locale', 'ar')->where('direction', 'rtl'));
    }

    public function test_unsupported_interface_language_is_rejected()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('preferences.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');

        $this->assertNull($user->refresh()->locale);
    }
}
