<?php

use App\Models\ServerReading;
use Illuminate\Support\Facades\Schedule;

Schedule::command('swage:record-readings')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('model:prune', ['--model' => [ServerReading::class]])->daily();
