<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_install_sends_everyone_to_setup(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('setup'));
        $this->get(route('login'))->assertRedirect(route('setup'));
        $this->get(route('setup'))->assertOk()->assertSee('Welcome to Swage Console');
    }

    public function test_setup_creates_the_account_and_signs_it_in(): void
    {
        $this->post(route('setup.store'), [
            'name' => 'Sam Admin',
            'email' => 'sam@example.com',
            'password' => 'a-long-enough-password',
            'password_confirmation' => 'a-long-enough-password',
        ])->assertRedirect(route('security'));

        $user = User::sole();
        $this->assertSame(['Sam Admin', 'sam@example.com'], [$user->name, $user->email]);
        $this->assertTrue(Hash::check('a-long-enough-password', $user->password));
        $this->assertAuthenticatedAs($user);

        /* Straight after setup the security page opens without asking for the password again. */
        $this->get(route('security'))->assertOk()->assertSee('Your account is ready');
    }

    public function test_setup_validates_the_account(): void
    {
        $this->post(route('setup.store'), ['name' => '', 'email' => 'not-an-email', 'password' => 'short', 'password_confirmation' => 'other'])
            ->assertSessionHasErrors(['name', 'email', 'password']);

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_setup_is_gone_once_an_account_exists(): void
    {
        User::factory()->create();

        $this->get(route('setup'))->assertRedirect(route('login'));
        $this->post(route('setup.store'), [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'password' => 'a-long-enough-password',
            'password_confirmation' => 'a-long-enough-password',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public function test_guests_are_sent_to_sign_in_and_the_data_endpoint_refuses_them(): void
    {
        User::factory()->create();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('panels.index'))->assertRedirect(route('login'));
        $this->getJson(route('data'))->assertUnauthorized();
        $this->get(route('login'))->assertOk()->assertSee('Sign in with a passkey')->assertSee(route('passkey.login-options'));
    }

    public function test_signing_in_with_a_password(): void
    {
        $user = User::factory()->create(['email' => 'sam@example.com']);

        $this->post(route('login.store'), ['email' => 'sam@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('login.store'), ['email' => 'sam@example.com', 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_two_factor_users_need_a_code_after_their_password(): void
    {
        $user = User::factory()->withTwoFactor()->create(['email' => 'sam@example.com']);

        $this->post(route('login.store'), ['email' => 'sam@example.com', 'password' => 'password'])->assertRedirect(route('two-factor.login'));
        $this->assertGuest();
        $this->get(route('two-factor.login'))->assertOk()->assertSee('Authentication code');

        $this->post(route('two-factor.login.store'), ['code' => '000000'])->assertRedirect(route('two-factor.login'));
        $this->assertGuest();

        $code = (new Google2FA)->getCurrentOtp(decrypt($user->two_factor_secret));
        $this->post(route('two-factor.login.store'), ['code' => $code])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_recovery_code_works_once(): void
    {
        $user = User::factory()->withTwoFactor()->create(['email' => 'sam@example.com']);
        $recoveryCode = $user->recoveryCodes()[0];

        $this->post(route('login.store'), ['email' => 'sam@example.com', 'password' => 'password']);
        $this->post(route('two-factor.login.store'), ['recovery_code' => $recoveryCode])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotContains($recoveryCode, $user->fresh()->recoveryCodes());
    }

    public function test_guests_can_ask_for_passkey_sign_in_options(): void
    {
        User::factory()->create();

        $this->getJson(route('passkey.login-options'))->assertOk()->assertJsonStructure(['options' => ['challenge']]);
    }

    public function test_signing_out(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('logout'))->assertRedirect('/');
        $this->assertGuest();
    }
}
