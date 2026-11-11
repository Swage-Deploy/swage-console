<x-layouts.auth title="Sign in" heading="Sign in to Swage Console" description="Use your passkey, or your email and password." passkeys>
    <div class="stack" data-passkey-supported hidden>
        <button type="button" class="btn block" data-passkey-verify data-options="{{ route('passkey.login-options') }}" data-submit="{{ route('passkey.login') }}" data-redirect="{{ route('dashboard') }}" data-error="passkey-error">
            <x-icon name="finger-print" /> Sign in with a passkey
        </button>
        <div class="error" id="passkey-error" hidden style="text-align:center"></div>
        <div class="divider">or continue with email</div>
    </div>

    <form method="post" action="{{ route('login.store') }}" class="stack">
        @csrf
        <div class="field">
            <label class="label" for="email">Email address</label>
            <input class="input" type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username webauthn">
            @error('email')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label class="label" for="password">Password</label>
            <input class="input" type="password" id="password" name="password" required autocomplete="current-password">
            @error('password')<div class="error">{{ $message }}</div>@enderror
        </div>
        <label class="checkbox"><input type="checkbox" name="remember" value="1" @checked(old('remember'))> Remember me</label>
        <button class="btn primary block">Sign in</button>
    </form>
</x-layouts.auth>
