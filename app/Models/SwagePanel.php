<?php

namespace App\Models;

use App\Services\SwageClient;
use Database\Factories\SwagePanelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A Swage panel the console reads servers from, with its API token encrypted at rest.
 */
#[Fillable(['name', 'url', 'token', 'enabled'])]
#[Hidden(['token'])]
class SwagePanel extends Model
{
    /** @use HasFactory<SwagePanelFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(fn (self $panel) => Cache::forget($panel->cacheKey()));
        static::deleted(function (self $panel): void {
            Cache::forget($panel->cacheKey());
            ServerReading::where('source', $panel->sourceKey())->delete();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'enabled' => 'boolean',
            'check_ok' => 'boolean',
            'checked_at' => 'immutable_datetime',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('enabled', true);
    }

    /**
     * The key readings and cached payloads are stored under.
     */
    public function sourceKey(): string
    {
        return (string) $this->id;
    }

    public function cacheKey(): string
    {
        return 'swage.dashboard.'.$this->sourceKey();
    }

    public function client(): SwageClient
    {
        return new SwageClient($this->sourceKey(), $this->name, $this->url, $this->token);
    }

    /**
     * The last few characters of the token, enough to tell keys apart without exposing them.
     */
    public function tokenHint(): string
    {
        return '…'.substr((string) $this->token, -4);
    }
}
