<?php

namespace Tests\Feature;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
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

    public function test_nobody_can_sign_up_themselves(): void
    {
        $this->admin();

        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'Ali', 'business_name' => 'Ali Electronics', 'email' => 'ali@example.com',
            'password' => 'secret-password', 'password_confirmation' => 'secret-password', 'terms' => '1',
        ])->assertNotFound();

        $this->get('/login')->assertDontSee('/register');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_admin_creates_a_merchant_who_can_then_log_in(): void
    {
        Notification::fake();
        $this->actingAs($this->admin());

        $this->get('/admin/merchants/create')->assertOk()->assertSee('Add merchant');

        $this->post('/admin/merchants', [
            'name' => 'Ali',
            'email' => 'ali@example.com',
            'password' => 'given-by-admin-1',
            'business_name' => 'Ali Electronics',
            'live_enabled' => '1',
            'fee_payin_percent' => '1.5',
            'fee_payin_fixed' => '',
            'fee_payout_percent' => '0.5',
            'fee_payout_fixed' => '2',
            'role' => 'admin', // ignored: only merchants are created here
        ])->assertRedirect();

        $user = User::where('email', 'ali@example.com')->sole();
        $merchant = $user->merchant;
        $this->assertTrue($user->isMerchant());
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue($merchant->live_enabled);
        $this->assertSame(['1.50', null, '0.50', '2.00'], [$merchant->fee_payin_percent, $merchant->fee_payin_fixed, $merchant->fee_payout_percent, $merchant->fee_payout_fixed]);
        $this->assertStringStartsWith('whsec_', $merchant->webhook_secret);
        Notification::assertNothingSent();

        $this->post('/admin/merchants', ['name' => 'X', 'email' => 'ali@example.com', 'password' => 'given-by-admin-1', 'business_name' => 'Dup'])
            ->assertSessionHasErrors('email');

        $this->post('/logout');
        $this->post('/login', ['email' => 'ali@example.com', 'password' => 'given-by-admin-1'])->assertRedirect(route('dashboard'));
        $this->get('/dashboard')->assertOk()->assertSee('Ali Electronics')->assertDontSee('Please verify your email');
        $this->get('/admin/merchants/create')->assertRedirect(route('dashboard'));
    }

    public function test_admin_can_change_a_merchants_login_details(): void
    {
        $merchant = Merchant::factory()->create();
        $this->actingAs($this->admin());

        $this->put(route('admin.merchants.account', $merchant), [
            'name' => 'New Contact', 'email' => 'new@example.com', 'business_name' => 'Renamed Co', 'password' => '',
        ])->assertSessionHas('status', 'Login details saved.');

        $this->put(route('admin.merchants.account', $merchant), [
            'name' => 'New Contact', 'email' => 'new@example.com', 'business_name' => 'Renamed Co', 'password' => 'reset-by-admin-9',
        ])->assertSessionHas('status', 'Login details saved, including the new password.');

        $merchant->refresh();
        $this->assertSame(['New Contact', 'new@example.com', 'Renamed Co'], [$merchant->user->name, $merchant->user->email, $merchant->business_name]);

        $this->post('/logout');
        $this->post('/login', ['email' => 'new@example.com', 'password' => 'reset-by-admin-9'])->assertRedirect(route('dashboard'));
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
