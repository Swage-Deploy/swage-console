<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Readings and caching
    |--------------------------------------------------------------------------
    |
    | How Swage Console reads your Swage panels. Panels and their API tokens
    | are managed on the settings page and stored in the database.
    |
    */

    // Seconds to cache each panel's API responses (the API allows 120 requests a minute per key).
    'cache_seconds' => 60,

    // Hours of usage history the charts show (Swage's metrics endpoint allows up to 168).
    'history_hours' => 24,

    // A server whose latest reading is older than this is shown as down.
    'stale_after_minutes' => 15,

    // Days of locally recorded 5-minute readings to keep.
    'keep_readings_days' => 7,

];
