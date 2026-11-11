<x-layouts.auth title="Two-factor authentication" heading="Two-factor authentication" description="Enter the 6-digit code from your authenticator app.">
    <form method="post" action="{{ route('two-factor.login.store') }}" class="stack" id="code-form" @if ($errors->has('recovery_code')) hidden @endif>
        @csrf
        <div class="field">
            <label class="label" for="code">Authentication code</label>
            <input class="input mono" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="7" autofocus placeholder="123456" style="letter-spacing:.2em;text-align:center;font-size:18px">
            @error('code')<div class="error">{{ $message }}</div>@enderror
        </div>
        <button class="btn primary block">Continue</button>
    </form>

    <form method="post" action="{{ route('two-factor.login.store') }}" class="stack" id="recovery-form" @unless ($errors->has('recovery_code')) hidden @endunless>
        @csrf
        <div class="field">
            <label class="label" for="recovery_code">Recovery code</label>
            <input class="input mono" id="recovery_code" name="recovery_code" autocomplete="one-time-code" placeholder="abcde12345-fghij67890">
            @error('recovery_code')<div class="error">{{ $message }}</div>@enderror
            <div class="description">Each recovery code works once.</div>
        </div>
        <button class="btn primary block">Continue</button>
    </form>

    <x-slot:footer>
        <button type="button" class="linkish" id="toggle-recovery">Use a recovery code instead</button>
    </x-slot:footer>

    <script>
        const codeForm = document.getElementById('code-form'), recoveryForm = document.getElementById('recovery-form'), toggle = document.getElementById('toggle-recovery');
        const label = () => toggle.textContent = codeForm.hidden ? 'Use your authenticator app instead' : 'Use a recovery code instead';
        toggle.addEventListener('click', () => {
            codeForm.hidden = ! codeForm.hidden;
            recoveryForm.hidden = ! recoveryForm.hidden;
            (codeForm.hidden ? recoveryForm : codeForm).querySelector('input:not([type=hidden])').focus();
            label();
        });
        label();
    </script>
</x-layouts.auth>
