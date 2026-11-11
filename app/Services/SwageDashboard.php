<?php

namespace App\Services;

use App\Models\ServerReading;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Merges what every configured Swage panel reports into the shape the sysadmin console renders.
 */
class SwageDashboard
{
    /**
     * Each panel's web address, keyed by source, for linking into Swage.
     *
     * @var array<string, string>
     */
    private array $panelUrls = [];

    /**
     * @return array{errors: list<string>, sources: list<array{key: string, name: string, ok: bool, url: ?string}>, fetched_at: string, hosts: list<array<string, mixed>>, summary: array<string, int>, deployments: list<array<string, mixed>>, certificates: list<array<string, mixed>>, jobs: list<array<string, mixed>>, activity: list<array<string, mixed>>}
     */
    public function build(): array
    {
        $clients = SwageClient::all();
        $errors = [];
        $sources = [];
        $payload = ['servers' => [], 'sites' => [], 'deployments' => []];

        if ($clients->isEmpty()) {
            $errors[] = 'No Swage panels yet. Add one in Settings.';
        }

        foreach ($clients as $client) {
            if (filled($client->webUrl())) {
                $this->panelUrls[$client->key] = $client->webUrl();
            }

            if (! $client->isConfigured()) {
                $errors[] = "{$client->name}: add its URL and API token in Settings.";
                $sources[] = ['key' => $client->key, 'name' => $client->name, 'ok' => false, 'url' => $client->webUrl()];

                continue;
            }

            try {
                /* Fresh for cache_seconds, then served stale while it refreshes after the response is sent. */
                $fresh = (int) config('swage.cache_seconds', 60);
                $part = Cache::flexible("swage.dashboard.{$client->key}", [$fresh, $fresh * 10], function () use ($client): array {
                    $part = $this->fetch($client);
                    $this->recordReadings($client->key, $part['servers']);

                    return $part;
                });

                foreach ($payload as $section => $items) {
                    $payload[$section] = [...$items, ...$part[$section]];
                }
                $sources[] = ['key' => $client->key, 'name' => $client->name, 'ok' => true, 'url' => $client->webUrl('dashboard')];
            } catch (Throwable $exception) {
                report($exception);
                $errors[] = "Could not reach {$client->name}: ".Str::limit($exception->getMessage(), 160);
                $sources[] = ['key' => $client->key, 'name' => $client->name, 'ok' => false, 'url' => $client->webUrl()];
            }
        }

        return [...$this->present($payload), 'errors' => $errors, 'sources' => $sources];
    }

    /**
     * Store each server's latest usage reading for a panel, skipping readings already stored.
     *
     * @param  list<array<string, mixed>>  $servers
     */
    public function recordReadings(string $source, array $servers): int
    {
        $rows = collect($servers)
            ->filter(fn (array $server): bool => filled(data_get($server, 'usage.read_at')))
            ->map(fn (array $server): array => [
                'source' => $source,
                'server_id' => $server['id'],
                'cpu_percent' => (float) data_get($server, 'usage.cpu_percent', 0),
                'memory_percent' => (float) data_get($server, 'usage.memory_percent', 0),
                'disk_percent' => (float) data_get($server, 'usage.disk_percent', 0),
                'swap_percent' => $this->swapPercent($server['usage']),
                'load_1' => (float) data_get($server, 'usage.load.0', 0),
                'read_at' => CarbonImmutable::parse($server['usage']['read_at'])->utc()->toDateTimeString(),
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->values()
            ->all();

        return ServerReading::insertOrIgnore($rows);
    }

    /**
     * Fetch everything the console shows from one panel, tagging each item with its source.
     *
     * @return array{servers: list<array<string, mixed>>, sites: list<array<string, mixed>>, deployments: list<array<string, mixed>>}
     */
    private function fetch(SwageClient $client): array
    {
        $source = ['source' => $client->key, 'source_name' => $client->name];

        $servers = collect($client->servers())->map(function (array $server) use ($client, $source): array {
            $id = (int) $server['id'];
            $details = $this->optional(fn (): array => $client->server($id));

            return [
                ...$server,
                'recent_failed_tasks' => $details['recent_failed_tasks'] ?? [],
                'certificates_not_renewing' => $details['certificates_not_renewing'] ?? [],
                'hourly' => $this->optional(fn (): array => $client->metrics($id, (int) config('swage.history_hours', 24)))['hourly'] ?? [],
                ...$source,
                'daemons' => $this->optional(fn (): array => $client->daemons($id)),
                'scheduled_jobs' => $this->optional(fn (): array => $client->scheduledJobs($id)),
                'activity' => array_slice($this->optional(fn (): array => $client->activity($id)), 0, 15),
            ];
        })->all();

        /* Deploy output can run to hundreds of KB, and the trigger URL deploys without a key: keep neither. */
        $sites = collect($client->sites())
            ->map(fn (array $site): array => [...Arr::except($site, ['latest_deployment.output', 'deploy_trigger_url']), ...$source])
            ->all();

        $deployments = collect($sites)->flatMap(fn (array $site): array => collect($this->optional(
            fn (): array => $client->deployments($this->siteServerId($site), (int) $site['id'])
        ))->take(5)->map(fn (array $deployment): array => [...Arr::except($deployment, ['output']), ...$source, 'site' => $site['domain'] ?? '?'])->all())->all();

        return ['servers' => $servers, 'sites' => $sites, 'deployments' => $deployments];
    }

    /**
     * A page in the Swage panel a source came from, or null when its address is unknown.
     */
    private function panelUrl(string $source, string $page): ?string
    {
        return isset($this->panelUrls[$source]) ? $this->panelUrls[$source].'/'.$page : null;
    }

    /**
     * A site's page in Swage, such as its SSL tab.
     *
     * @param  array<string, mixed>  $site
     */
    private function siteUrl(array $site, string $tab = ''): ?string
    {
        if (! isset($site['source'], $site['id'])) {
            return null;
        }

        return $this->panelUrl($site['source'], rtrim("servers/{$this->siteServerId($site)}/sites/{$site['id']}/{$tab}", '/'));
    }

    /**
     * Endpoints the key may not have the ability for (daemons, deployments…) degrade to an empty list.
     *
     * @param  callable(): array<mixed>  $call
     * @return array<mixed>
     */
    private function optional(callable $call): array
    {
        try {
            return $call();
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    /**
     * @param  array{servers: list<array<string, mixed>>, sites: list<array<string, mixed>>, deployments: list<array<string, mixed>>}  $payload
     * @return array<string, mixed>
     */
    private function present(array $payload): array
    {
        $sites = collect($payload['sites']);
        $history = $payload['servers'] === [] ? collect() : ServerReading::query()
            ->where('read_at', '>=', now()->subHours((int) config('swage.history_hours', 24)))
            ->orderBy('read_at')
            ->get()
            ->groupBy(fn (ServerReading $reading): string => $reading->source.'/'.$reading->server_id);

        $hosts = collect($payload['servers'])
            ->map(fn (array $server): array => $this->host($server, $sites, $history->get($server['source'].'/'.$server['id'], collect())))
            ->sortBy([['source_name', 'asc'], ['hostname', 'asc']])
            ->values();

        $serverNames = collect($payload['servers'])->mapWithKeys(fn (array $server): array => [$server['source'].'/'.$server['id'] => $server['name']]);

        $certificates = $sites
            ->filter(fn (array $site): bool => $this->certificateExpiry($site) !== null)
            ->map(fn (array $site): array => [
                'name' => $site['domain'],
                'url' => $this->siteUrl($site, 'ssl'),
                'expires_in' => $this->daysUntil($this->certificateExpiry($site)),
                'server' => $serverNames->get($site['source'].'/'.$this->siteServerId($site), data_get($site, 'server.name', '')),
                'source_name' => $site['source_name'],
            ])
            ->sortBy('expires_in')
            ->values();

        $sitesByKey = $sites->keyBy(fn (array $site): string => $site['source'].'/'.$site['id']);

        $deployments = collect($payload['deployments'])
            ->map(fn (array $deployment): array => [
                'site' => $deployment['site'],
                'url' => $this->siteUrl($sitesByKey->get($deployment['source'].'/'.($deployment['site_id'] ?? '')) ?? []),
                'source_name' => $deployment['source_name'],
                'status' => $deployment['status'] ?? 'unknown',
                'state' => $this->deploymentState($deployment['status'] ?? null),
                'commit' => $this->commit($deployment),
                'at' => $this->firstTime($deployment, ['finished_at', 'started_at', 'created_at', 'at']),
            ])
            ->sortByDesc('at')
            ->take(8)
            ->values();

        $jobs = collect($payload['servers'])
            ->flatMap(fn (array $server): array => collect($server['scheduled_jobs'] ?? [])->map(fn (array $job): array => [
                'command' => $job['command'] ?? $job['name'] ?? 'job #'.($job['id'] ?? '?'),
                'frequency' => $job['frequency'] ?? $job['cron'] ?? '',
                'user' => $job['user'] ?? '',
                'host' => $server['name'],
                'source_name' => $server['source_name'],
                'state' => in_array($job['status'] ?? 'installed', ['failed', 'error'], true) ? 'fail' : 'ok',
            ])->all())
            ->values();

        $activity = collect($payload['servers'])
            ->flatMap(fn (array $server): array => collect($server['activity'] ?? [])->map(fn (array $task): array => [
                'name' => $task['name'] ?? 'task',
                'url' => $this->panelUrl($server['source'], "servers/{$server['id']}/activity"),
                'host' => $server['name'],
                'source_name' => $server['source_name'],
                'status' => $task['status'] ?? 'unknown',
                'state' => match ($task['status'] ?? null) {
                    'failed' => 'fail',
                    'running' => 'info',
                    default => 'ok',
                },
                'at' => $this->firstTime($task, ['started_at', 'updated_at', 'created_at']),
            ])->all())
            ->sortByDesc('at')
            ->take(10)
            ->values();

        return [
            'fetched_at' => now()->toIso8601String(),
            'hosts' => $hosts->all(),
            'summary' => [
                'total' => $hosts->count(),
                'up' => $hosts->where('status', '!=', 'down')->count(),
                'critical' => $hosts->where('status', 'crit')->count(),
                'warning' => $hosts->where('status', 'warn')->count(),
                'sites' => $sites->count(),
                'failed_deploys' => $sites->where('latest_deployment.status', 'failed')->count(),
                'reboots' => $hosts->where('reboot_required', true)->count(),
                'certs_expiring' => $certificates->where('expires_in', '<=', 30)->count(),
            ],
            'deployments' => $deployments->all(),
            'certificates' => $certificates->all(),
            'jobs' => $jobs->all(),
            'activity' => $activity->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $server
     * @param  Collection<int, array<string, mixed>>  $sites
     * @param  Collection<int, ServerReading>  $readings
     * @return array<string, mixed>
     */
    private function host(array $server, Collection $sites, Collection $readings): array
    {
        $usage = $server['usage'] ?? null;
        $readAt = filled($usage['read_at'] ?? null) ? CarbonImmutable::parse($usage['read_at']) : null;
        $cores = max(1, (int) ($server['cpu_cores'] ?? 1));
        $load = array_map('floatval', array_pad(array_slice($usage['load'] ?? [], 0, 3), 3, 0));
        $failedTasks = collect($server['recent_failed_tasks'] ?? [])->pluck('name')->filter()->values()->all();
        $notRenewing = collect($server['certificates_not_renewing'] ?? [])->map(fn (mixed $certificate): string => is_array($certificate) ? ($certificate['domain'] ?? 'certificate') : (string) $certificate)->all();

        $hostSites = $sites
            ->filter(fn (array $site): bool => $site['source'] === $server['source'] && $this->siteServerId($site) === (int) $server['id'])
            ->map(fn (array $site): array => [
                'domain' => $site['domain'],
                'url' => $this->siteUrl($site),
                'certificate_url' => $this->siteUrl($site, 'ssl'),
                'php' => $site['php_version'] ?? '',
                'repository' => data_get($site, 'repository.repository', ''),
                'branch' => data_get($site, 'repository.branch', ''),
                'deploy_status' => data_get($site, 'latest_deployment.status'),
                'deploy_state' => $this->deploymentState(data_get($site, 'latest_deployment.status')),
                'deployed_at' => $this->firstTime($site['latest_deployment'] ?? [], ['finished_at', 'started_at', 'created_at', 'at']),
                'cert_days' => $this->certificateExpiry($site) !== null ? $this->daysUntil($this->certificateExpiry($site)) : null,
                'cert_overdue' => (bool) data_get($site, 'certificate.renewal_overdue', false),
            ])
            ->values();

        $host = [
            'key' => $server['source'].'/'.$server['id'],
            'id' => (int) $server['id'],
            'source' => $server['source'],
            'source_name' => $server['source_name'],
            'url' => $this->panelUrl($server['source'], "servers/{$server['id']}"),
            'links' => collect(['sites', 'daemons', 'scheduler', 'activity'])
                ->mapWithKeys(fn (string $page): array => [$page => $this->panelUrl($server['source'], "servers/{$server['id']}/{$page}")])
                ->all(),
            'hostname' => $server['name'] ?? 'server-'.$server['id'],
            'type' => $server['type'] ?? 'app',
            'swage_status' => $server['status'] ?? 'unknown',
            'ip' => $server['ip_address'] ?? '',
            'private_ip' => $server['private_ip_address'] ?? null,
            'os' => filled($server['os_version'] ?? null) ? 'Ubuntu '.$server['os_version'] : 'Ubuntu',
            'cores' => $cores,
            'ram' => round(($server['memory_mb'] ?? 0) / 1024, 1),
            'php' => $server['php_versions'] ?? array_values(array_filter([$server['default_php_version'] ?? null])),
            'databases' => $server['database_engines'] ?? [],
            'redis' => (bool) ($server['has_redis'] ?? false),
            'uptime_seconds' => (int) ($usage['uptime_seconds'] ?? 0),
            'has_usage' => $usage !== null,
            'read_at' => $readAt?->toIso8601String(),
            'cpu' => (float) ($usage['cpu_percent'] ?? 0),
            'memory' => (float) ($usage['memory_percent'] ?? 0),
            'memory_used' => (int) ($usage['memory_used_bytes'] ?? 0),
            'memory_total' => (int) ($usage['memory_total_bytes'] ?? 0),
            'disk' => (float) ($usage['disk_percent'] ?? 0),
            'disk_used' => (int) ($usage['disk_used_bytes'] ?? 0),
            'disk_total' => (int) ($usage['disk_total_bytes'] ?? 0),
            'swap' => $usage === null ? null : $this->swapPercent($usage),
            'load' => $load,
            'load_is_host' => (bool) ($usage['load_is_host'] ?? false),
            'reboot_required' => (bool) ($server['reboot_required'] ?? false),
            'reboot_since' => $server['reboot_required_since'] ?? null,
            'reboot_packages' => array_values($server['reboot_required_packages'] ?? []),
            'failed_tasks' => $failedTasks,
            'certificates_not_renewing' => array_values(array_unique([
                ...$notRenewing,
                ...$hostSites->where('cert_overdue', true)->pluck('domain')->all(),
            ])),
            'daemons' => collect($server['daemons'] ?? [])->map(fn (array $daemon): array => [
                'name' => $daemon['command'] ?? $daemon['name'] ?? 'daemon #'.($daemon['id'] ?? '?'),
                'user' => $daemon['user'] ?? '',
                'processes' => $daemon['processes'] ?? 1,
                'state' => $daemon['status'] ?? 'installed',
            ])->values()->all(),
            'sites' => $hostSites->all(),
            'history' => $this->history($server['hourly'] ?? [], $readings),
        ];

        $host['status'] = $this->status($host, $readAt, $cores);

        return $host;
    }

    /**
     * @param  array<string, mixed>  $host
     */
    private function status(array $host, ?CarbonImmutable $readAt, int $cores): string
    {
        $isStale = $host['has_usage'] && ($readAt === null || $readAt->lt(now()->subMinutes((int) config('swage.stale_after_minutes', 15))));

        if ($host['swage_status'] !== 'active' || $isStale) {
            return 'down';
        }

        /* A container reports its host's load, which says nothing about this server's own cores. */
        $loadPerCore = $host['load_is_host'] ? 0 : $host['load'][0] / $cores;
        $failedDeploy = collect($host['sites'])->contains('deploy_state', 'fail');

        if ($host['cpu'] >= 90 || $host['memory'] >= 92 || $host['disk'] >= 90 || $loadPerCore >= 1 || $host['failed_tasks'] !== []) {
            return 'crit';
        }

        if ($host['cpu'] >= 75 || $host['memory'] >= 80 || $host['disk'] >= 80 || $loadPerCore >= .7
            || $host['reboot_required'] || $host['certificates_not_renewing'] !== [] || $failedDeploy) {
            return 'warn';
        }

        return 'ok';
    }

    /**
     * Chart history: Swage's hourly averages up to the first locally stored 5-minute reading, then those readings,
     * so the charts cover the whole period from the first page load.
     *
     * @param  list<array<string, mixed>>  $hourly
     * @param  Collection<int, ServerReading>  $readings
     * @return array{time: list<int>, cpu: list<float>, memory: list<float>, disk: list<float>, load: list<float>}
     */
    private function history(array $hourly, Collection $readings): array
    {
        $firstReading = $readings->first()?->read_at;

        $points = collect($hourly)
            ->filter(fn (array $hour): bool => ($hour['readings'] ?? 0) > 0 && filled($hour['hour'] ?? null))
            ->map(fn (array $hour): array => [
                'at' => CarbonImmutable::parse($hour['hour'])->addMinutes(30),
                'cpu' => (float) ($hour['cpu_percent_average'] ?? 0),
                'memory' => (float) ($hour['memory_percent_average'] ?? 0),
                'disk' => (float) ($hour['disk_percent'] ?? 0),
                'load' => (float) ($hour['load_peak'] ?? 0),
            ])
            ->filter(fn (array $point): bool => $firstReading === null || $point['at']->lt($firstReading))
            ->concat($readings->map(fn (ServerReading $reading): array => [
                'at' => $reading->read_at,
                'cpu' => $reading->cpu_percent,
                'memory' => $reading->memory_percent,
                'disk' => $reading->disk_percent,
                'load' => $reading->load_1,
            ]))
            ->values();

        return [
            'time' => $points->map(fn (array $point): int => $point['at']->getTimestampMs())->all(),
            'cpu' => $points->pluck('cpu')->all(),
            'memory' => $points->pluck('memory')->all(),
            'disk' => $points->pluck('disk')->all(),
            'load' => $points->pluck('load')->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $site
     */
    private function siteServerId(array $site): int
    {
        return (int) data_get($site, 'server.id', $site['server_id'] ?? 0);
    }

    /**
     * @param  array<string, mixed>  $site
     */
    private function certificateExpiry(array $site): ?string
    {
        return data_get($site, 'certificate.expires_at') ?? ($site['certificate_expires_at'] ?? null);
    }

    private function daysUntil(string $date): int
    {
        return (int) floor(now()->diffInDays(CarbonImmutable::parse($date), false));
    }

    /**
     * @param  array<string, mixed>  $usage
     */
    private function swapPercent(array $usage): ?float
    {
        if (isset($usage['swap_percent']) && (int) ($usage['swap_total_bytes'] ?? 1) > 0) {
            return (float) $usage['swap_percent'];
        }

        $total = (int) ($usage['swap_total_bytes'] ?? 0);

        return $total > 0 ? round(($usage['swap_used_bytes'] ?? 0) / $total * 100, 1) : null;
    }

    private function deploymentState(?string $status): string
    {
        return match ($status) {
            'finished' => 'ok',
            'failed' => 'fail',
            'pending', 'running' => 'info',
            default => 'down',
        };
    }

    /**
     * @param  array<string, mixed>  $deployment
     */
    private function commit(array $deployment): string
    {
        $commit = $deployment['commit'] ?? $deployment['commit_hash'] ?? null;
        $hash = is_array($commit) ? ($commit['hash'] ?? $commit['sha'] ?? '') : (string) $commit;
        $message = is_array($commit) ? ($commit['message'] ?? '') : ($deployment['commit_message'] ?? '');

        return trim(Str::substr($hash, 0, 7).' '.Str::limit(Str::before((string) $message, "\n"), 60));
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $keys
     */
    private function firstTime(array $item, array $keys): ?string
    {
        $value = collect($keys)->map(fn (string $key): mixed => Arr::get($item, $key))->first(fn (mixed $value): bool => filled($value));

        return $value ? CarbonImmutable::parse($value)->toIso8601String() : null;
    }
}
