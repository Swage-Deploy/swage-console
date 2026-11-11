<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSwagePanelRequest;
use App\Models\SwagePanel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Throwable;

class SwagePanelController
{
    /**
     * List the Swage panels with a form to add another.
     */
    public function index(): View
    {
        return view('settings.panels.index', ['panels' => SwagePanel::orderBy('name')->get()]);
    }

    /**
     * Save a new panel and test its credentials straight away.
     */
    public function store(SaveSwagePanelRequest $request): RedirectResponse
    {
        $panel = SwagePanel::create($request->panelAttributes());
        $this->checkConnection($panel);

        return to_route('panels.index')->with('status', "Added {$panel->name}.");
    }

    /**
     * Show the form to change a panel.
     */
    public function edit(SwagePanel $panel): View
    {
        return view('settings.panels.edit', ['panel' => $panel]);
    }

    /**
     * Save changes to a panel, retesting it when its URL or token changed.
     */
    public function update(SaveSwagePanelRequest $request, SwagePanel $panel): RedirectResponse
    {
        $panel->fill($request->panelAttributes());
        $credentialsChanged = $panel->isDirty(['url', 'token']);
        $panel->save();

        if ($credentialsChanged) {
            $this->checkConnection($panel);
        }

        return to_route('panels.index')->with('status', "Saved {$panel->name}.");
    }

    /**
     * Remove a panel along with the usage history recorded for its servers.
     */
    public function destroy(SwagePanel $panel): RedirectResponse
    {
        $panel->delete();

        return to_route('panels.index')->with('status', "Removed {$panel->name}.");
    }

    /**
     * Test a panel's URL and token against its API.
     */
    public function check(SwagePanel $panel): RedirectResponse
    {
        $this->checkConnection($panel);

        return to_route('panels.index')->with('status', "Tested {$panel->name}.");
    }

    private function checkConnection(SwagePanel $panel): void
    {
        try {
            $user = $panel->client()->user();
            $abilities = collect(data_get($user, 'abilities', []))->implode(', ');
            $who = data_get($user, 'name') ?? data_get($user, 'email');

            $panel->forceFill([
                'check_ok' => true,
                'check_message' => Str::limit(trim(($who ? "Connected as {$who}" : 'Connected').($abilities ? " · {$abilities}" : '')), 250),
            ]);
        } catch (Throwable $exception) {
            $panel->forceFill([
                'check_ok' => false,
                'check_message' => Str::limit($exception->getMessage(), 250),
            ]);
        }

        $panel->forceFill(['checked_at' => now()])->save();
    }
}
