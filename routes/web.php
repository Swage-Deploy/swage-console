<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SwagePanelController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('setup', [SetupController::class, 'show'])->name('setup');
    Route::post('setup', [SetupController::class, 'store'])->middleware('throttle:6,1')->name('setup.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'show'])->name('dashboard');
    Route::get('data', [DashboardController::class, 'data'])->name('data');

    Route::prefix('settings')->group(function () {
        Route::resource('panels', SwagePanelController::class)->except(['create', 'show']);
        Route::post('panels/{panel}/check', [SwagePanelController::class, 'check'])->name('panels.check');
        Route::view('security', 'settings.security')->middleware('password.confirm')->name('security');
    });
});
