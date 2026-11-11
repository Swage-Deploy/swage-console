<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * The first launch: whoever opens a fresh Swage Console creates its account. Once an account exists, setup is gone.
 */
class SetupController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (User::query()->exists()) {
            return to_route('login');
        }

        return view('auth.setup');
    }

    public function store(Request $request, CreateNewUser $createNewUser): RedirectResponse
    {
        /* Two people submitting at once on a fresh install must not both get an account. */
        $user = Cache::lock('swage-console-setup', 10)->block(5, function () use ($request, $createNewUser): ?User {
            if (User::query()->exists()) {
                return null;
            }

            return $createNewUser->create($request->only('name', 'email', 'password', 'password_confirmation'));
        });

        if ($user === null) {
            return to_route('login');
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->passwordConfirmed();

        return to_route('security')->with('status', 'account-created');
    }
}
