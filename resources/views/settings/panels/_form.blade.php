@php($editing = isset($panel))
<div class="grid grid-2" style="gap:20px">
    <div class="field">
        <label class="label" for="name">Name</label>
        <input class="input" type="text" id="name" name="name" value="{{ old('name', $panel->name ?? '') }}" placeholder="Production" required maxlength="100">
        @error('name')<div class="error">{{ $message }}</div>@enderror
    </div>
    <div class="field">
        <label class="label" for="url">Panel URL</label>
        <input class="input mono" type="url" id="url" name="url" value="{{ old('url', $panel->url ?? '') }}" placeholder="https://swage.example.com" required>
        @error('url')<div class="error">{{ $message }}</div>@enderror
    </div>
    <div class="field" style="grid-column:1/-1">
        <label class="label" for="token">API token</label>
        <input class="input mono" type="password" id="token" name="token" autocomplete="new-password" {{ $editing ? '' : 'required' }}
               placeholder="{{ $editing ? 'Saved '.$panel->tokenHint().' — leave blank to keep it' : 'Paste the key Swage showed you' }}">
        @error('token')<div class="error">{{ $message }}</div>@enderror
        <div class="description">
            Create it in Swage under Settings → API keys with read access to Servers, Sites, Deployments, Certificates and Daemons.
            It is stored encrypted and never shown again.@if ($editing) Changing the URL needs the token entered again.@endif
        </div>
    </div>
    <div style="grid-column:1/-1">
        <input type="hidden" name="enabled" value="0">
        <label class="checkbox"><input type="checkbox" name="enabled" value="1" {{ old('enabled', $panel->enabled ?? true) ? 'checked' : '' }}> Show this panel's servers on the console</label>
    </div>
</div>
