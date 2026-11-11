<x-layouts.auth title="Confirm it's you" heading="Confirm it's you" description="This area changes how you sign in, so confirm your password or use a passkey first." passkeys>
    @if (auth()->user()->hasPasskeysEnabled())
        <div class="stack" data-passkey-supported hidden>
            <button type="button" class="btn block" data-passkey-verify data-options="{{ route('passkey.confirm-options') }}" data-submit="{{ route('passkey.confirm') }}" data-redirect="{{ session('url.intended', route('security')) }}" data-error="passkey-error">
                <x-icon name="finger-print" /> Confirm with a passkey
            </button>
            <div class="error" id="passkey-error" hidden style="text-align:center"></div>
            <div class="divider">or use your password</div>
        </div>
    @endif

    <form method="post" action="{{ route('password.confirm.store') }}" class="stack">
        @csrf
        <div class="field">
            <label class="label" for="password">Password</label>
            <input class="input" type="password" id="password" name="password" required autofocus autocomplete="current-password">
            @error('password')<div class="error">{{ $message }}</div>@enderror
        </div>
        <button class="btn primary block">Confirm</button>
    </form>

    <x-slot:footer><a class="link" href="{{ route('dashboard') }}">Back to the console</a></x-slot:footer>
</x-layouts.auth>
