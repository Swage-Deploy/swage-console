<x-layouts.auth title="Set up" heading="Welcome to Swage Console" description="Create the account you'll sign in with. You can add a passkey and two-factor authentication next.">
    <form method="post" action="{{ route('setup.store') }}" class="stack">
        @csrf
        <div class="field">
            <label class="label" for="name">Name</label>
            <input class="input" id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
            @error('name')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label class="label" for="email">Email address</label>
            <input class="input" type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="you@example.com">
            @error('email')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label class="label" for="password">Password</label>
            <input class="input" type="password" id="password" name="password" required autocomplete="new-password">
            @error('password')<div class="error">{{ $message }}</div>@enderror
        </div>
        <div class="field">
            <label class="label" for="password_confirmation">Confirm password</label>
            <input class="input" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
        </div>
        <button class="btn primary block">Create account</button>
    </form>
</x-layouts.auth>
