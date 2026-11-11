<?php

namespace App\Models;

use Database\Factories\ServerReadingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;

/**
 * A usage snapshot of a Swage server, kept locally so the dashboard can chart history.
 */
#[Fillable(['source', 'server_id', 'cpu_percent', 'memory_percent', 'disk_percent', 'swap_percent', 'load_1', 'read_at'])]
class ServerReading extends Model
{
    /** @use HasFactory<ServerReadingFactory> */
    use HasFactory, MassPrunable;

    /**
     * Keep readings for as long as config('swage.keep_readings_days') says.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::where('read_at', '<', now()->subDays((int) config('swage.keep_readings_days', 7)));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cpu_percent' => 'float',
            'memory_percent' => 'float',
            'disk_percent' => 'float',
            'swap_percent' => 'float',
            'load_1' => 'float',
            'read_at' => 'immutable_datetime',
        ];
    }
}
