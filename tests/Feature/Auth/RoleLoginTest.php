<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.demo_role_login' => true, 'app.url' => 'http://localhost:8000']);
        User::factory()->create();
    }

    public function test_page_renders_with_roles_and_without_password_fields(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Welcome to the')
            ->assertSee('Asset Manager')
            ->assertSee('name="role"', false)
            ->assertDontSee('type="password"', false);
    }

    #[DataProvider('demo_roles')]
    public function test_selected_role_signs_in_without_a_password(string $role, string $state, string $username): void
    {
        $user = User::factory()->{$state}()->create(['username' => $username, 'last_login' => null]);
        $permissions = $user->permissions;

        $this->post(route('login'), ['role' => $role])
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login);
        $this->assertSame($permissions, $user->fresh()->permissions);
    }

    public static function demo_roles(): array
    {
        return [
            ['superuser', 'firstAdmin', 'admin'],
            ['asset_manager', 'assetManager', 'assetmgr'],
            ['license_manager', 'licenseManager', 'licensemgr'],
            ['accessory_manager', 'accessoryManager', 'accessorymgr'],
            ['consumable_manager', 'consumableManager', 'consumablemgr'],
            ['component_manager', 'componentManager', 'componentmgr'],
            ['user_manager', 'userManager', 'usermgr'],
        ];
    }

    public function test_login_page_shows_session_recovery_errors(): void
    {
        $this->withSession(['error' => 'Your session has expired. Please try again.'])
            ->get(route('login'))
            ->assertOk()
            ->assertSee('Your session has expired. Please try again.');
    }

    #[DataProvider('invalid_roles')]
    public function test_missing_or_unrecognised_roles_do_not_authenticate(array $input): void
    {
        $this->from(route('login'))->post(route('login'), $input)
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('role');

        $this->assertGuest();
    }

    public static function invalid_roles(): array
    {
        return [[[]], [['role' => 'administrator']], [['role' => ['superuser']]]];
    }

    public function test_inactive_role_account_cannot_sign_in(): void
    {
        User::factory()->assetManager()->create(['activated' => 0]);

        $this->post(route('login'), ['role' => 'asset_manager'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('role');

        $this->assertGuest();
    }

    public function test_missing_role_account_cannot_sign_in(): void
    {
        $this->post(route('login'), ['role' => 'asset_manager'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('role');

        $this->assertGuest();
    }

    public function test_submitted_username_cannot_override_selected_role(): void
    {
        User::factory()->firstAdmin()->create();
        $manager = User::factory()->assetManager()->create();

        $this->post(route('login'), ['role' => 'asset_manager', 'username' => 'admin'])
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($manager);
    }

    public function test_user_manager_cannot_access_assets_after_role_sign_in(): void
    {
        User::factory()->userManager()->create();

        $this->post(route('login'), ['role' => 'user_manager']);

        $this->get(route('hardware.index'))->assertForbidden();
    }

    public function test_role_login_is_refused_when_application_url_is_not_local(): void
    {
        config(['app.url' => 'https://example.com']);

        $this->get('/login')->assertForbidden();
        $this->post('/login', ['role' => 'superuser'])->assertForbidden();
        $this->assertGuest();
    }

    public function test_disabling_demo_role_login_restores_password_sign_in(): void
    {
        config(['auth.demo_role_login' => false]);

        $this->get(route('login'))->assertOk()->assertSee('type="password"', false);

        $this->from(route('login'))->post(route('login'), ['role' => 'superuser'])
            ->assertSessionHasErrors(['username', 'password']);

        $this->assertGuest();
    }
}
