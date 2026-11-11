<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SecuritySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_security_page_asks_for_the_password_first(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('security'))->assertRedirect(route('password.confirm'));
        $this->get(route('password.confirm'))->assertOk()->assertSee('Confirm it');

        $this->post(route('password.confirm.store'), ['password' => 'password'])->assertRedirect(route('security'));
        $this->get(route('security'))->assertOk()->assertSee('Passkeys')->assertSee('Two-factor authentication')->assertSee('Add passkey');
    }

    public function test_turning_on_two_factor_authentication(): void
    {
        $user = $this->confirmedUser();

        $this->post(route('two-factor.enable'))->assertRedirect();
        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertFalse($user->hasEnabledTwoFactorAuthentication());

        $this->get(route('security'))->assertOk()->assertSee('Scan this code')->assertSee('<svg', false);

        $this->post(route('two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrorsIn('confirmTwoFactorAuthentication', 'code');
        $this->assertFalse($user->fresh()->hasEnabledTwoFactorAuthentication());

        $code = (new Google2FA)->getCurrentOtp(decrypt($user->two_factor_secret));
        $this->from(route('security'))->post(route('two-factor.confirm'), ['code' => $code])->assertRedirect(route('security'));

        $user->refresh();
        $this->assertTrue($user->hasEnabledTwoFactorAuthentication());
        $this->followingRedirects()->from(route('security'))->post(route('two-factor.regenerate-recovery-codes'))
            ->assertSee('New recovery codes were made')
            ->assertSee($user->fresh()->recoveryCodes()[0]);
    }

    public function test_turning_off_two_factor_authentication(): void
    {
        $user = $this->confirmedUser(User::factory()->withTwoFactor()->create());

        $this->delete(route('two-factor.disable'))->assertRedirect();

        $this->assertFalse($user->fresh()->hasEnabledTwoFactorAuthentication());
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_changing_the_password_needs_the_current_one(): void
    {
        $user = $this->confirmedUser();

        $this->put(route('user-password.update'), ['current_password' => 'wrong', 'password' => 'a-new-long-password', 'password_confirmation' => 'a-new-long-password'])
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');

        $this->put(route('user-password.update'), ['current_password' => 'password', 'password' => 'a-new-long-password', 'password_confirmation' => 'a-new-long-password'])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('a-new-long-password', $user->fresh()->password));
    }

    public function test_updating_the_profile(): void
    {
        $user = $this->confirmedUser();

        $this->put(route('user-profile-information.update'), ['name' => 'New Name', 'email' => 'new@example.com'])->assertSessionHasNoErrors();

        $this->assertSame(['New Name', 'new@example.com'], [$user->fresh()->name, $user->fresh()->email]);
    }

    public function test_passkey_registration_options_are_offered_after_confirming_the_password(): void
    {
        $this->confirmedUser();

        $this->getJson(route('passkey.registration-options'))->assertOk()->assertJsonStructure(['options' => ['challenge', 'user', 'rp']]);
    }

    private function confirmedUser(?User $user = null): User
    {
        $user ??= User::factory()->create();
        $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

        return $user;
    }
}
