<?php

namespace Tests\Feature;

use App\Models\ServerReading;
use App\Models\SwagePanel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private SwagePanel $alpha;

    private SwagePanel $beta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->alpha = SwagePanel::factory()->create(['name' => 'Alpha', 'url' => 'https://alpha.test', 'token' => 'alpha-token']);
        $this->beta = SwagePanel::factory()->create(['name' => 'Beta', 'url' => 'https://beta.test', 'token' => 'beta-token']);
        $this->freezeTime();
        Http::preventStrayRequests();
    }

    public function test_servers_from_every_swage_panel_are_listed_in_the_inventory(): void
    {
        $this->fakePanel('alpha.test', 'web-1', 'example.com');
        $this->fakePanel('beta.test', 'db-1', 'beta.example', readMinutesAgo: 60);

        $response = $this->getJson(route('data'))->assertOk();

        $response->assertSee('example.com')
            ->assertSee('beta.example')
            ->assertSee('php artisan schedule:run')
            ->assertSee('Installing daemon daemon-2');

        $hosts = collect($response->json()['hosts'])->keyBy('key');
        [$alpha, $beta] = [$this->alpha->id.'/1', $this->beta->id.'/1'];
        $this->assertSame([$alpha, $beta], $hosts->keys()->all());
        $this->assertSame(['web-1', 'Alpha', 'ok'], [$hosts[$alpha]['hostname'], $hosts[$alpha]['source_name'], $hosts[$alpha]['status']]);
        $this->assertSame(['db-1', 'Beta', 'down'], [$hosts[$beta]['hostname'], $hosts[$beta]['source_name'], $hosts[$beta]['status']]);
        $this->assertSame(['example.com'], collect($hosts[$alpha]['sites'])->pluck('domain')->all());

        Http::assertSent(fn ($request): bool => $request->url() === 'https://alpha.test/api/v1/servers'
            && $request->hasHeader('Authorization', 'Bearer alpha-token'));
        Http::assertSent(fn ($request): bool => $request->url() === 'https://beta.test/api/v1/servers'
            && $request->hasHeader('Authorization', 'Bearer beta-token'));
    }

    public function test_the_page_is_a_shell_that_loads_its_data_in_the_background(): void
    {
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('<title>Servers - Swage Console</title>', false)
            ->assertSee('System monitor')
            ->assertDontSee('IT Dashboards')
            ->assertSee(json_encode(route('data')), false)
            ->assertSee(route('panels.index'))
            ->assertSee('setTimeout(load', false)
            ->assertDontSee('journalctl')
            ->assertDontSee('Fleet');

        Http::assertNothingSent();
    }

    public function test_the_data_endpoint_is_not_cached_by_the_browser(): void
    {
        $this->fakePanel('alpha.test', 'web-1', 'example.com');
        $this->fakePanel('beta.test', 'db-1', 'beta.example');

        $this->getJson(route('data'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure(['errors', 'sources', 'fetched_at', 'hosts', 'summary', 'deployments', 'certificates', 'jobs', 'activity']);

        $this->assertStringContainsString('no-store', $this->getJson(route('data'))->headers->get('Cache-Control'));
    }

    public function test_stale_data_is_served_straight_away_and_refreshed_after_the_response(): void
    {
        $this->beta->delete();
        $this->fakePanel('alpha.test', 'web-1', 'example.com', cpu: 12.5);
        $this->getJson(route('data'))->assertOk();

        $this->travel(2)->minutes();
        Http::swap(new HttpFactory);
        Http::preventStrayRequests();
        $this->fakePanel('alpha.test', 'web-1', 'example.com', cpu: 55.0, readMinutesAgo: 0);
        $this->assertEquals(12.5, $this->getJson(route('data'))->json('hosts.0.cpu'));

        $this->assertEquals(55.0, $this->getJson(route('data'))->json('hosts.0.cpu'));
    }

    public function test_readings_are_kept_apart_for_servers_with_the_same_id_on_different_panels(): void
    {
        $this->fakePanel('alpha.test', 'web-1', 'example.com', cpu: 12.5);
        $this->fakePanel('beta.test', 'db-1', 'beta.example', cpu: 40.0);

        $this->getJson(route('data'))->assertOk();
        cache()->flush();
        $response = $this->getJson(route('data'))->assertOk();

        $this->assertSame(2, ServerReading::count());
        $hosts = collect($response->json()['hosts'])->keyBy('key');
        $this->assertEquals([30.0, 12.5], $hosts[$this->alpha->id.'/1']['history']['cpu']);
        $this->assertEquals([30.0, 40.0], $hosts[$this->beta->id.'/1']['history']['cpu']);
    }

    public function test_one_unreachable_panel_does_not_hide_the_others(): void
    {
        $this->fakePanel('alpha.test', 'web-1', 'example.com');
        Http::fake(['beta.test/*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

        $response = $this->getJson(route('data'))
            ->assertOk()
            ->assertSee('Could not reach Beta');

        $this->assertSame([$this->alpha->id.'/1'], collect($response->json()['hosts'])->pluck('key')->all());
    }

    public function test_a_panel_returning_a_sign_in_page_shows_an_error_instead_of_no_servers(): void
    {
        $this->fakePanel('alpha.test', 'web-1', 'example.com');
        Http::fake(['beta.test/*' => Http::response('<html><title>Sign in ・ Cloudflare Access</title></html>', 200, ['Content-Type' => 'text/html'])]);

        $response = $this->getJson(route('data'))
            ->assertOk()
            ->assertSee('Could not reach Beta: the panel is behind Cloudflare Access');

        $this->assertSame(['Alpha' => true, 'Beta' => false], collect($response->json()['sources'])->pluck('ok', 'name')->all());
    }

    public function test_it_points_to_settings_when_no_panels_are_configured(): void
    {
        SwagePanel::query()->delete();

        $this->getJson(route('data'))
            ->assertOk()
            ->assertJsonPath('errors.0', 'No Swage panels yet. Add one in Settings.');

        Http::assertNothingSent();
    }

    public function test_disabled_panels_are_left_off_the_console(): void
    {
        $this->beta->update(['enabled' => false]);
        $this->fakePanel('alpha.test', 'web-1', 'example.com');

        $response = $this->getJson(route('data'))->assertOk();

        $this->assertSame(['Alpha'], collect($response->json()['hosts'])->pluck('source_name')->all());
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'beta.test'));
    }

    public function test_the_record_readings_command_stores_readings_for_every_panel(): void
    {
        $this->fakePanel('alpha.test', 'web-1', 'example.com');
        $this->fakePanel('beta.test', 'db-1', 'beta.example');

        $this->assertSame(0, Artisan::call('swage:record-readings'));

        $this->assertEqualsCanonicalizing([(string) $this->alpha->id, (string) $this->beta->id], ServerReading::pluck('source')->all());
    }

    public function test_servers_still_list_when_a_panel_reports_no_usage(): void
    {
        $this->beta->delete();
        $this->fakePanel('alpha.test', 'web-1', 'example.com', withUsage: false);

        $response = $this->getJson(route('data'))->assertOk();

        $host = $response->json()['hosts'][0];
        $this->assertSame(['web-1', false, 'ok'], [$host['hostname'], $host['has_usage'], $host['status']]);
        $this->assertSame(0, ServerReading::count());
    }

    public function test_site_certificates_deployments_and_secrets_are_read_from_the_live_api_shape(): void
    {
        $this->beta->delete();
        $this->fakePanel('alpha.test', 'web-1', 'example.com');

        $response = $this->getJson(route('data'))
            ->assertOk()
            ->assertSee('abc1234 add https redirect')
            ->assertDontSee('secret-trigger-token')
            ->assertDontSee('composer install output');

        $d = $response->json();
        $this->assertSame([['name' => 'example.com', 'url' => 'https://alpha.test/servers/1/sites/1/ssl', 'expires_in' => 60, 'server' => 'web-1', 'source_name' => 'Alpha']], $d['certificates']);
        $site = $d['hosts'][0]['sites'][0];
        $this->assertSame([60, 'finished'], [$site['cert_days'], $site['deploy_status']]);
        $this->assertNotNull($site['deployed_at']);
    }

    public function test_servers_sites_and_sections_link_to_their_pages_in_swage(): void
    {
        $this->beta->delete();
        $this->alpha->update(['url' => 'https://alpha.test/']);
        $this->fakePanel('alpha.test', 'web-1', 'example.com');

        $d = $this->getJson(route('data'))->assertOk()->json();
        $host = $d['hosts'][0];

        $this->assertSame('https://alpha.test/dashboard', $d['sources'][0]['url']);
        $this->assertSame('https://alpha.test/servers/1', $host['url']);
        $this->assertSame([
            'sites' => 'https://alpha.test/servers/1/sites',
            'daemons' => 'https://alpha.test/servers/1/daemons',
            'scheduler' => 'https://alpha.test/servers/1/scheduler',
            'activity' => 'https://alpha.test/servers/1/activity',
        ], $host['links']);
        $this->assertSame(['https://alpha.test/servers/1/sites/1', 'https://alpha.test/servers/1/sites/1/ssl'], [$host['sites'][0]['url'], $host['sites'][0]['certificate_url']]);
        $this->assertSame('https://alpha.test/servers/1/sites/1', $d['deployments'][0]['url']);
        $this->assertSame('https://alpha.test/servers/1/activity', $d['activity'][0]['url']);
    }

    public function test_only_the_rest_api_is_used(): void
    {
        $this->fakePanel('alpha.test', 'web-1', 'example.com');
        $this->fakePanel('beta.test', 'db-1', 'beta.example');

        $this->getJson(route('data'))->assertOk();

        Http::assertNotSent(fn ($request): bool => ! str_contains($request->url(), '/api/v1/'));
        Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://alpha.test/api/v1/servers/1/metrics') && (int) $request['hours'] === 24);
    }

    public function test_a_containers_host_load_does_not_flag_it_as_overloaded(): void
    {
        $this->beta->delete();
        $this->fakePanel('alpha.test', 'web-1', 'example.com', loadIsHost: true, load: 9.5);

        $host = $this->getJson(route('data'))->assertOk()->json()['hosts'][0];

        $this->assertSame([true, 'ok'], [$host['load_is_host'], $host['status']]);
    }

    public function test_an_overloaded_server_is_critical(): void
    {
        $this->beta->delete();
        $this->fakePanel('alpha.test', 'web-1', 'example.com', load: 9.5);

        $this->assertSame('crit', $this->getJson(route('data'))->assertOk()->json()['hosts'][0]['status']);
    }

    public function test_charts_start_with_swage_hourly_averages_before_local_readings(): void
    {
        $this->beta->delete();
        $this->fakePanel('alpha.test', 'web-1', 'example.com', cpu: 12.5);

        $history = $this->getJson(route('data'))->assertOk()->json()['hosts'][0]['history'];

        $this->assertEquals([30.0, 12.5], $history['cpu']);
        $this->assertEquals([1.2, 0.2], $history['load']);
        $this->assertSame(now()->subHours(3)->startOfHour()->addMinutes(30)->getTimestampMs(), $history['time'][0]);
    }

    public function test_removed_dashboard_routes_return_not_found(): void
    {
        foreach (['/d/noc', '/d/security', '/d/sysadmin'] as $uri) {
            $this->get($uri)->assertNotFound();
        }
    }

    /**
     * Fake one Swage panel with a single server (ID 1) running a single site, shaped like the live API.
     */
    private function fakePanel(string $host, string $serverName, string $domain, float $cpu = 12.5, int $readMinutesAgo = 2, bool $withUsage = true, bool $loadIsHost = false, float $load = 0.2): void
    {
        $usage = [
            'cpu_percent' => $cpu, 'load' => [$load, 0.1, 0.1], 'load_is_host' => $loadIsHost, 'memory_percent' => 11.1,
            'memory_used_bytes' => 940437504, 'memory_total_bytes' => 8489271296,
            'swap_percent' => 0, 'swap_used_bytes' => 0, 'swap_total_bytes' => 8489271296, 'disk_percent' => 9.4,
            'disk_used_bytes' => 4943179776, 'disk_total_bytes' => 52521566208,
            'uptime_seconds' => 1613113, 'read_at' => now()->subMinutes($readMinutesAgo)->toIso8601String(),
        ];
        $server = [
            'id' => 1, 'name' => $serverName, 'type' => 'app', 'status' => 'active', 'ip_address' => '203.0.113.10',
            'os_version' => '24.04', 'cpu_cores' => 6, 'memory_mb' => 8096, 'disk_gb' => 49,
            'php_versions' => ['8.5'], 'default_php_version' => '8.5', 'database_engines' => ['mysql'], 'has_redis' => true,
            'reboot_required' => false, 'reboot_required_since' => null, 'reboot_required_packages' => [],
            'usage' => $withUsage ? $usage : null,
        ];
        $deployment = [
            'id' => 9, 'site_id' => 1, 'status' => 'finished', 'triggered_by' => 'Administrator',
            'commit' => ['hash' => 'abc1234def5678', 'message' => 'add https redirect', 'author' => 'Dev'],
            'output' => str_repeat('composer install output ', 50),
            'started_at' => now()->subHours(3)->toIso8601String(), 'finished_at' => now()->subHours(3)->addSeconds(13)->toIso8601String(),
        ];
        $hourly = [
            ['hour' => now()->subHours(3)->startOfHour()->toIso8601String(), 'cpu_percent_average' => 30.0, 'cpu_percent_peak' => 55.0, 'load_peak' => 1.2,
                'memory_percent_average' => 20.0, 'memory_percent_peak' => 21.0, 'swap_percent_peak' => 0, 'disk_percent' => 9.3, 'readings' => 12],
            ['hour' => now()->subHours(2)->startOfHour()->toIso8601String(), 'cpu_percent_average' => 0, 'load_peak' => 0,
                'memory_percent_average' => 0, 'disk_percent' => 0, 'readings' => 0],
        ];

        Http::fake([
            "{$host}/api/v1/servers" => Http::response(['data' => [$server]]),
            "{$host}/api/v1/servers/1" => Http::response(['data' => [...$server, 'certificates_not_renewing' => [], 'recent_failed_tasks' => []]]),
            "{$host}/api/v1/servers/1/metrics*" => Http::response(['data' => ['latest' => $withUsage ? $usage : null, 'hourly' => $withUsage ? $hourly : []]]),
            "{$host}/api/v1/servers/1/daemons" => Http::response(['data' => [['id' => 2, 'site_id' => 1, 'command' => 'php artisan queue:work', 'user' => 'example', 'processes' => 1, 'status' => 'installed']]]),
            "{$host}/api/v1/servers/1/scheduled-jobs" => Http::response(['data' => [['id' => 1, 'site_id' => 1, 'command' => 'php artisan schedule:run', 'user' => 'example', 'frequency' => '* * * * *', 'status' => 'installed']]]),
            "{$host}/api/v1/servers/1/activity" => Http::response(['data' => [['id' => 50, 'name' => 'Installing daemon daemon-2', 'status' => 'finished', 'exit_code' => 0, 'created_at' => now()->subDay()->toIso8601String(), 'updated_at' => now()->subDay()->toIso8601String()]]]),
            "{$host}/api/v1/sites" => Http::response(['data' => [[
                'id' => 1, 'server_id' => 1, 'domain' => $domain, 'status' => 'installed', 'php_version' => '8.5',
                'repository' => ['source' => 'github', 'repository' => 'acme/example', 'branch' => 'main'],
                'deploy_trigger_url' => "https://{$host}/deploy/1/secret-trigger-token",
                'latest_deployment' => $deployment,
                'certificate' => ['id' => 4, 'domains' => [$domain], 'status' => 'installed', 'expires_at' => now()->addDays(60)->addHour()->toIso8601String(), 'renewal_overdue' => false],
            ]]]),
            "{$host}/api/v1/servers/1/sites/1/deployments" => Http::response(['data' => [$deployment]]),
        ]);
    }
}
