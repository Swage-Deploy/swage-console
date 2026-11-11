<x-layouts.app :title="'Edit '.$panel->name" current="panels">
    <div class="page-head">
        <div>
            <div class="crumbs" style="display:flex;gap:6px;font-size:14px;color:var(--zinc-400);margin-bottom:8px">
                <a class="link" href="{{ route('panels.index') }}">Swage panels</a><span style="color:var(--zinc-600)">/</span><span>{{ $panel->name }}</span>
            </div>
            <h1 class="h-xl">Edit {{ $panel->name }}</h1>
        </div>
    </div>

    <x-panel icon="pencil-square" heading="Panel details" description="Changing the URL or the token tests the connection again.">
        <form method="post" action="{{ route('panels.update', $panel) }}">
            @csrf @method('put')
            @include('settings.panels._form', ['panel' => $panel])
            <div style="display:flex;gap:8px;margin-top:20px">
                <button class="btn primary">Save</button>
                <a class="btn" href="{{ route('panels.index') }}">Cancel</a>
            </div>
        </form>
    </x-panel>
</x-layouts.app>
