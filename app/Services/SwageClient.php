<?php

namespace App\Services;

use App\Models\SwagePanel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Read-only client for one Swage panel's JSON API (/api/v1).
 */
class SwageClient
{
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        private readonly ?string $url,
        private readonly ?string $token,
    ) {}

    /**
     * A client for every enabled panel saved in Settings, keyed by source key.
     *
     * @return Collection<string, self>
     */
    public static function all(): Collection
    {
        return SwagePanel::enabled()->orderBy('name')->get()
            ->mapWithKeys(fn (SwagePanel $panel): array => [$panel->sourceKey() => $panel->client()]);
    }

    public function isConfigured(): bool
    {
        return filled($this->url) && filled($this->token);
    }

    /**
     * The panel's web address for a page, such as "servers/3/daemons".
     */
    public function webUrl(string $page = ''): ?string
    {
        if (blank($this->url)) {
            return null;
        }

        return rtrim($this->url, '/').($page === '' ? '' : '/'.ltrim($page, '/'));
    }

    /**
     * The key's user and its abilities.
     *
     * @return array<string, mixed>
     */
    public function user(): array
    {
        return $this->get('user');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function servers(): array
    {
        return $this->get('servers');
    }

    /**
     * A server with what needs attention on it: failing certificate renewals and recent failed tasks.
     *
     * @return array<string, mixed>
     */
    public function server(int $serverId): array
    {
        return $this->get("servers/{$serverId}");
    }

    /**
     * A server's latest usage and hourly averages and peaks over the past hours (up to 168).
     *
     * @return array{latest?: array<string, mixed>, hourly?: list<array<string, mixed>>}
     */
    public function metrics(int $serverId, int $hours = 24): array
    {
        return $this->get("servers/{$serverId}/metrics", ['hours' => $hours]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sites(): array
    {
        return $this->get('sites');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function deployments(int $serverId, int $siteId): array
    {
        return $this->get("servers/{$serverId}/sites/{$siteId}/deployments");
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activity(int $serverId): array
    {
        return $this->get("servers/{$serverId}/activity");
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function daemons(int $serverId): array
    {
        return $this->get("servers/{$serverId}/daemons");
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function scheduledJobs(int $serverId): array
    {
        return $this->get("servers/{$serverId}/scheduled-jobs");
    }

    /**
     * Fetch an endpoint and unwrap its `data` envelope.
     *
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     *
     * @throws RequestException|ConnectionException
     */
    private function get(string $path, array $query = []): array
    {
        $response = $this->ensureJson($this->request()->get($path, $query)->throw());

        return $response->json('data') ?? [];
    }

    /**
     * A sign-in page in place of JSON (Cloudflare Access, another proxy, a wrong URL) is a failure,
     * not an empty result.
     *
     * @throws RuntimeException
     */
    private function ensureJson(Response $response): Response
    {
        if (is_array($response->json())) {
            return $response;
        }

        $body = $response->body();
        $reason = match (true) {
            str_contains($body, 'Cloudflare Access') => 'the panel is behind Cloudflare Access. Bypass /api/* for it in Access',
            stripos($body, '<html') !== false => 'the panel returned a web page instead of JSON. Check the URL points at Swage',
            default => 'the panel did not return JSON',
        };

        throw new RuntimeException("{$reason}.");
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) $this->url, '/').'/api/v1')
            ->withToken((string) $this->token)
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(8)
            ->retry([200, 600], 0, fn (Throwable $exception): bool => $exception instanceof ConnectionException
                || ($exception instanceof RequestException
                    && ($exception->response->serverError() || $exception->response->status() === 429)), throw: false);
    }
}
