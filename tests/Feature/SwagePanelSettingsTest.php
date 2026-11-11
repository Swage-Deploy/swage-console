<?php

namespace Tests\Feature;

use App\Models\ServerReading;
use App\Models\SwagePanel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SwagePanelSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        Http::preventStrayRequests();
    }

    public function test_the_settings_page_lists_panels_without_revealing_their_tokens(): void
    {
        SwagePanel::factory()->create(['name' => 'Production', 'url' => 'https://swage.prod.test', 'token' => 'super-secret-token-9f3a']);

        $this->get(route('panels.index'))
            ->assertOk()
            ->assertSee('Production')
            ->assertSee('https://swage.prod.test')
            ->assertSee('…9f3a')
            ->assertDontSee('super-secret-token');
    }

    public function test_the_add_form_starts_empty_below_the_list_of_panels(): void
    {
        SwagePanel::factory()->create(['name' => 'Production', 'url' => 'https://swage.prod.test']);

        $this->get(route('panels.index'))
            ->assertOk()
            ->assertDontSee('value="Production"', false)
            ->assertDontSee('value="https://swage.prod.test"', false);
    }

    public function test_a_panel_is_added_with_an_encrypted_token_and_tested(): void
    {
        Http::fake(['swage.prod.test/api/v1/user' => Http::response(['data' => ['name' => 'Ops Bot', 'abilities' => ['servers:read', 'sites:read']]])]);

        $this->post(route('panels.store'), [
            'name' => 'Production',
            'url' => 'https://swage.prod.test/',
            'token' => 'secret-token',
            'enabled' => '1',
        ])->assertRedirect(route('panels.index'));

        $panel = SwagePanel::sole();
        $this->assertSame('https://swage.prod.test', $panel->url);
        $this->assertSame('secret-token', $panel->token);
        $this->assertNotSame('secret-token', DB::table('swage_panels')->value('token'));
        $this->assertTrue($panel->check_ok);
        $this->assertSame('Connected as Ops Bot · servers:read, sites:read', $panel->check_message);
        Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer secret-token'));
    }

    public function test_a_failed_connection_test_is_recorded(): void
    {
        Http::fake(['swage.prod.test/*' => Http::response(['message' => 'Unauthenticated.'], 401)]);
        $panel = SwagePanel::factory()->create(['url' => 'https://swage.prod.test']);

        $this->post(route('panels.check', $panel))->assertRedirect(route('panels.index'));

        $panel->refresh();
        $this->assertFalse($panel->check_ok);
        $this->assertNotNull($panel->checked_at);
        $this->assertStringContainsString('401', $panel->check_message);
    }

    public function test_a_new_panel_needs_a_name_url_and_token(): void
    {
        $this->post(route('panels.store'), ['url' => 'ftp://swage.test'])
            ->assertSessionHasErrors(['name', 'url', 'token']);

        $this->assertDatabaseCount('swage_panels', 0);
    }

    public function test_leaving_the_token_blank_keeps_the_saved_one(): void
    {
        $panel = SwagePanel::factory()->create(['name' => 'Old', 'url' => 'https://swage.prod.test', 'token' => 'kept-token']);

        $this->put(route('panels.update', $panel), [
            'name' => 'Renamed',
            'url' => 'https://swage.prod.test',
            'token' => '',
        ])->assertRedirect(route('panels.index'));

        $panel->refresh();
        $this->assertSame(['Renamed', 'kept-token', false], [$panel->name, $panel->token, $panel->enabled]);
        Http::assertNothingSent();
    }

    public function test_changing_the_url_requires_the_token_again(): void
    {
        $panel = SwagePanel::factory()->create(['url' => 'https://swage.prod.test', 'token' => 'kept-token']);

        $this->put(route('panels.update', $panel), [
            'name' => $panel->name,
            'url' => 'https://elsewhere.test',
            'token' => '',
        ])->assertSessionHasErrors('token');

        $this->assertSame('https://swage.prod.test', $panel->refresh()->url);
        Http::assertNothingSent();
    }

    public function test_removing_a_panel_also_removes_its_usage_history(): void
    {
        $panel = SwagePanel::factory()->create();
        $other = SwagePanel::factory()->create();
        ServerReading::factory()->create(['source' => $panel->sourceKey()]);
        ServerReading::factory()->create(['source' => $other->sourceKey()]);

        $this->delete(route('panels.destroy', $panel))->assertRedirect(route('panels.index'));

        $this->assertModelMissing($panel);
        $this->assertSame([$other->sourceKey()], ServerReading::pluck('source')->all());
    }

    public function test_a_cloudflare_access_sign_in_page_fails_the_connection_test(): void
    {
        Http::fake(['swage.prod.test/*' => Http::response('<!DOCTYPE html><html><head><title>Sign in ・ Cloudflare Access</title></head></html>', 200, ['Content-Type' => 'text/html'])]);
        $panel = SwagePanel::factory()->create(['url' => 'https://swage.prod.test']);

        $this->post(route('panels.check', $panel));

        $panel->refresh();
        $this->assertFalse($panel->check_ok);
        $this->assertStringContainsString('behind Cloudflare Access', $panel->check_message);
    }

    public function test_the_edit_page_never_shows_the_token(): void
    {
        $panel = SwagePanel::factory()->create(['token' => 'super-secret-token-9f3a']);

        $this->get(route('panels.edit', $panel))
            ->assertOk()
            ->assertSee($panel->name)
            ->assertDontSee('super-secret-token');
    }
}
