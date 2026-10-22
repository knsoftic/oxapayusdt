<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_visit_creates_the_platform_admin_and_then_setup_closes(): void
    {
        $this->get('/login')->assertRedirect('/setup');

        $this->post('/setup', [
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect(route('admin.settings.edit'));

        $this->assertTrue(User::sole()->isAdmin());

        $this->post('/logout');
        $this->get('/setup')->assertRedirect('/login');
        $this->post('/setup', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret-password', 'password_confirmation' => 'secret-password'])
            ->assertRedirect('/login');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_merchants_can_register_and_land_on_their_dashboard(): void
    {
        Notification::fake();
        $this->admin();

        $this->post('/register', [
            'name' => 'Ali',
            'business_name' => 'Ali Electronics',
            'email' => 'ali@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'terms' => '1',
        ])->assertRedirect('/dashboard');

        $user = User::where('email', 'ali@example.com')->sole();
        $this->assertTrue($user->isMerchant());
        $this->assertSame('Ali Electronics', $user->merchant->business_name);
        $this->assertFalse($user->merchant->live_enabled);
        $this->assertStringStartsWith('whsec_', $user->merchant->webhook_secret);
        $this->assertAuthenticatedAs($user);
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->get('/dashboard')->assertOk()->assertSee('Ali Electronics')->assertSee('Test mode');
    }

    public function test_registration_cannot_make_someone_an_admin(): void
    {
        $this->admin();

        $this->post('/register', [
            'name' => 'Sneaky',
            'business_name' => 'Sneaky Co',
            'email' => 'sneaky@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'terms' => '1',
            'role' => 'admin',
        ]);

        $this->assertTrue(User::where('email', 'sneaky@example.com')->sole()->isMerchant());
    }

    public function test_login_sends_each_role_to_its_own_area(): void
    {
        $admin = $this->admin();
        $merchant = Merchant::factory()->create();

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
        $this->get('/dashboard')->assertRedirect(route('admin.dashboard'));
        $this->post('/logout');

        $this->post('/login', ['email' => $merchant->user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->get('/admin')->assertRedirect(route('dashboard'));
        $this->get('/admin/settings')->assertRedirect(route('dashboard'));
    }

    public function test_suspended_merchants_cannot_log_in_and_are_logged_out(): void
    {
        $this->admin();
        $merchant = Merchant::factory()->suspended()->create();

        $this->post('/login', ['email' => $merchant->user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $active = Merchant::factory()->create();
        $this->actingAs($active->user)->get('/dashboard')->assertOk();
        $active->update(['status' => Merchant::STATUS_SUSPENDED]);
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_password_reset_link_is_sent_without_revealing_accounts(): void
    {
        Notification::fake();
        $this->admin();
        $merchant = Merchant::factory()->create();

        $this->post('/forgot-password', ['email' => $merchant->user->email])->assertSessionHas('status');
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertSessionHas('status');

        Notification::assertSentTo($merchant->user, ResetPassword::class, function (ResetPassword $notification) use ($merchant) {
            $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $merchant->user->email,
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ])->assertRedirect('/login');

            return true;
        });

        $this->post('/login', ['email' => $merchant->user->email, 'password' => 'brand-new-password'])->assertRedirect(route('dashboard'));
    }
}
