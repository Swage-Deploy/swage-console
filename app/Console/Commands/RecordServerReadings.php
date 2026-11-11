<?php

namespace App\Console\Commands;

use App\Services\SwageClient;
use App\Services\SwageDashboard;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('swage:record-readings')]
#[Description('Store every Swage panel\'s latest server usage readings so the dashboard can chart history')]
class RecordServerReadings extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SwageDashboard $dashboard): int
    {
        $clients = SwageClient::all();

        if ($clients->isEmpty()) {
            $this->components->error('No Swage panels yet. Add one in Settings.');

            return self::FAILURE;
        }

        $failed = false;

        foreach ($clients as $client) {
            if (! $client->isConfigured()) {
                $this->components->warn("{$client->name}: no URL or API token, skipped.");

                continue;
            }

            try {
                $stored = $dashboard->recordReadings($client->key, $client->servers());
                $this->components->info("{$client->name}: stored {$stored} new reading(s).");
            } catch (Throwable $exception) {
                report($exception);
                $this->components->error("{$client->name}: {$exception->getMessage()}");
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
