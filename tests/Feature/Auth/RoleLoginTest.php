<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleLoginTest extends TestCase
{
    public function test_page_requires_credentials_instead_of_role_selection(): void
    {
        config(['auth.demo_role_login' => true]);
        User::factory()->create();

        $this->get(route('login'))->assertOk()
            ->assertSee('name="username"', false)
            ->assertSee('type="password"', false)
            ->assertDontSee('name="role"', false);
    }

    #[DataProvider('account_roles')]
    public function test_credentials_sign_in_to_the_existing_account_without_changing_permissions(string $state): void
    {
        $user = User::factory()->{$state}()->create();
        $permissions = $user->permissions;

        $this->post(route('login'), ['username' => $user->username, 'password' => 'password', 'role' => 'superuser'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertSame($permissions, $user->fresh()->permissions);
    }

    public static function account_roles(): array
    {
        return array_map(fn ($state) => [$state], [
            'firstAdmin', 'assetManager', 'licenseManager', 'accessoryManager',
            'consumableManager', 'componentManager', 'userManager',
        ]);
    }

    public function test_role_only_submission_cannot_bypass_password_authentication(): void
    {
        config(['auth.demo_role_login' => true]);
        User::factory()->firstAdmin()->create();

        $this->from(route('login'))->post(route('login'), ['role' => 'superuser'])
            ->assertSessionHasErrors(['username', 'password']);
        $this->assertGuest();
    }

    public function test_wrong_password_cannot_sign_in_and_is_not_rendered_back(): void
    {
        $user = User::factory()->create();
        $this->from(route('login'))->post(route('login'), ['username' => $user->username, 'password' => 'incorrect-password'])
            ->assertSessionHas('error');
        $this->assertGuest();
        $this->get(route('login'))->assertDontSee('incorrect-password');
    }

    public function test_inactive_account_cannot_sign_in(): void
    {
        $user = User::factory()->create(['activated' => 0]);
        $this->from(route('login'))->post(route('login'), ['username' => $user->username, 'password' => 'password'])
            ->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_user_manager_cannot_access_assets_after_sign_in(): void
    {
        $user = User::factory()->userManager()->create();
        $this->post(route('login'), ['username' => $user->username, 'password' => 'password']);
        $this->get(route('hardware.index'))->assertForbidden();
    }
}
