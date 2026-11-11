@php
    $user = auth()->user();
    $twoFactorPending = filled($user->two_factor_secret) && $user->two_factor_confirmed_at === null;
    $twoFactorOn = $user->hasEnabledTwoFactorAuthentication();
    $status = session('status');
    $showCodes = $twoFactorOn && (in_array($status, ['two-factor-authentication-confirmed', 'recovery-codes-generated'], true) || request()->boolean('codes'));
    $messages = [
        'profile-information-updated' => 'Your profile was saved.',
        'password-updated' => 'Your password was changed.',
        'two-factor-authentication-confirmed' => 'Two-factor authentication is on. Save your recovery codes somewhere safe.',
        'two-factor-authentication-disabled' => 'Two-factor authentication is off.',
        'recovery-codes-generated' => 'New recovery codes were made. The old ones no longer work.',
        'passkey-deleted' => 'The passkey was removed.',
        'passkey-registered' => 'The passkey was added.',
        'account-created' => 'Your account is ready. Add a passkey or turn on two-factor authentication to protect it.',
    ];
@endphp
<x-layouts.app title="Security" current="security">
    <x-slot:head>@vite('resources/js/passkeys.js')</x-slot:head>

    <div class="page-head">
        <div>
            <h1 class="h-xl">Security</h1>
            <p class="subheading">How you sign in to Swage Console.</p>
        </div>
    </div>

    @if (isset($messages[$status]))
        <div class="callout green"><x-icon name="check-circle" /><div>{{ $messages[$status] }}</div></div>
    @endif

    <x-panel icon="finger-print" heading="Passkeys" description="Sign in with Touch ID, Face ID, Windows Hello or a security key instead of a password." flush>
        @if ($user->passkeys->isNotEmpty())
            <div class="table-wrap">
                <table>
                    <tbody>
                        @foreach ($user->passkeys->sortByDesc('created_at') as $passkey)
                            <tr>
                                <td style="width:100%;white-space:normal">
                                    <div style="color:#fff;font-weight:500">{{ $passkey->name }}</div>
                                    <div class="item-sub">Added {{ $passkey->created_at->diffForHumans() }} · {{ $passkey->last_used_at ? 'last used '.$passkey->last_used_at->diffForHumans() : 'not used yet' }}</div>
                                </td>
                                <td class="end">
                                    <form method="post" action="{{ route('passkey.destroy', $passkey) }}" style="margin:0"
                                          onsubmit="return confirm({{ \Illuminate\Support\Js::from('Remove the passkey "'.$passkey->name.'"? You can no longer sign in with it.') }})">
                                        @csrf @method('delete')
                                        <button class="btn ghost sm" aria-label="Remove {{ $passkey->name }}"><x-icon name="trash" /></button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <div class="panel-body" @if ($user->passkeys->isNotEmpty()) style="border-top:1px solid var(--line)" @endif>
            <form method="post" action="{{ route('passkey.store') }}" data-passkey-register data-passkey-supported hidden data-options="{{ route('passkey.registration-options') }}" data-error="passkey-error">
                <div class="field">
                    <label class="label" for="passkey-name">Add a passkey</label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <input class="input" id="passkey-name" name="name" maxlength="255" placeholder="e.g. MacBook Pro" style="flex:1;min-width:12rem">
                        <button type="submit" class="btn primary"><x-icon name="plus" /> Add passkey</button>
                    </div>
                    <div class="error" id="passkey-error" hidden></div>
                    <div class="description">Your browser asks you to confirm with your device. Name it so you can tell your passkeys apart.</div>
                </div>
            </form>
            <p class="none" data-passkey-unsupported hidden style="margin:0">This browser doesn't support passkeys.</p>
        </div>
    </x-panel>

    <x-panel icon="device-phone-mobile" heading="Two-factor authentication" :description="$twoFactorOn ? 'On. Signing in with a password also asks for a code from your authenticator app.' : 'Ask for a code from an authenticator app, such as 1Password or Google Authenticator, after your password.'">
        <x-slot:actions>
            <x-status :color="$twoFactorOn ? 'green' : ($twoFactorPending ? 'amber' : 'zinc')" :label="$twoFactorOn ? 'On' : ($twoFactorPending ? 'Finish setting up' : 'Off')" />
        </x-slot:actions>

        @if ($twoFactorOn)
            @if ($showCodes)
                <div class="field" style="margin-bottom:20px">
                    <div class="label">Recovery codes</div>
                    <div class="description">If you lose your device, each of these signs you in once. Store them in your password manager.</div>
                    <pre class="mono" style="margin:0;padding:16px;border:1px solid var(--line);border-radius:8px;background:var(--zinc-950);color:var(--zinc-200);font-size:13px;line-height:1.8;columns:2;white-space:pre-wrap">{{ implode("\n", $user->recoveryCodes()) }}</pre>
                </div>
            @endif
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                @unless ($showCodes)
                    <a class="btn sm" href="{{ route('security', ['codes' => 1]) }}"><x-icon name="eye" /> Show recovery codes</a>
                @endunless
                <form method="post" action="{{ route('two-factor.regenerate-recovery-codes') }}" style="margin:0">
                    @csrf
                    <button class="btn sm"><x-icon name="arrow-path" /> New recovery codes</button>
                </form>
                <form method="post" action="{{ route('two-factor.disable') }}" style="margin:0" onsubmit="return confirm('Turn off two-factor authentication?')">
                    @csrf @method('delete')
                    <button class="btn ghost sm">Turn off</button>
                </form>
            </div>
        @elseif ($twoFactorPending)
            <div class="grid grid-2" style="gap:24px;align-items:start">
                <div class="field">
                    <div class="label">1. Scan this code</div>
                    <div style="width:192px;padding:12px;border-radius:8px;background:#fff;line-height:0">{!! $user->twoFactorQrCodeSvg() !!}</div>
                    <div class="description">Or enter this key: <span class="mono" style="color:var(--zinc-200);overflow-wrap:anywhere">{{ decrypt($user->two_factor_secret) }}</span></div>
                </div>
                <form method="post" action="{{ route('two-factor.confirm') }}" class="field">
                    @csrf
                    <label class="label" for="code">2. Enter the 6-digit code it shows</label>
                    <input class="input mono" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required autofocus placeholder="123456" style="letter-spacing:.2em;font-size:16px;max-width:12rem">
                    @error('code', 'confirmTwoFactorAuthentication')<div class="error">{{ $message }}</div>@enderror
                    <div style="display:flex;gap:8px;margin-top:4px">
                        <button class="btn primary sm">Turn on</button>
                        <button class="btn ghost sm" form="cancel-two-factor">Cancel</button>
                    </div>
                </form>
            </div>
            <form method="post" action="{{ route('two-factor.disable') }}" id="cancel-two-factor" hidden>@csrf @method('delete')</form>
        @else
            <form method="post" action="{{ route('two-factor.enable') }}" style="margin:0">
                @csrf
                <button class="btn primary sm"><x-icon name="shield-check" /> Set up two-factor authentication</button>
            </form>
        @endif
    </x-panel>

    <div class="grid grid-2">
        <x-panel icon="user-circle" heading="Profile" description="Your name and the email address you sign in with.">
            <form method="post" action="{{ route('user-profile-information.update') }}" class="stack">
                @csrf @method('put')
                <div class="field">
                    <label class="label" for="name">Name</label>
                    <input class="input" id="name" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name">
                    @error('name', 'updateProfileInformation')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label class="label" for="email">Email address</label>
                    <input class="input" type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required autocomplete="email">
                    @error('email', 'updateProfileInformation')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div><button class="btn primary sm">Save</button></div>
            </form>
        </x-panel>

        <x-panel icon="key" heading="Password" description="Use a long password you don't use anywhere else.">
            <form method="post" action="{{ route('user-password.update') }}" class="stack">
                @csrf @method('put')
                <div class="field">
                    <label class="label" for="current_password">Current password</label>
                    <input class="input" type="password" id="current_password" name="current_password" required autocomplete="current-password">
                    @error('current_password', 'updatePassword')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label class="label" for="password">New password</label>
                    <input class="input" type="password" id="password" name="password" required autocomplete="new-password">
                    @error('password', 'updatePassword')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label class="label" for="password_confirmation">Confirm new password</label>
                    <input class="input" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                </div>
                <div><button class="btn primary sm">Change password</button></div>
            </form>
        </x-panel>
    </div>
</x-layouts.app>
