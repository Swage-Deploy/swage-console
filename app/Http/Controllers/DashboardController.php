<?php

namespace App\Http\Controllers;

use App\Services\SwageDashboard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class DashboardController
{
    /**
     * Show the console shell straight away; it loads its data from data() in the background.
     */
    public function show(): View
    {
        return view('dashboard');
    }

    /**
     * Everything the console shows, merged from every enabled Swage panel.
     */
    public function data(SwageDashboard $dashboard): JsonResponse
    {
        return response()->json($dashboard->build())
            ->header('Cache-Control', 'no-store');
    }
}
