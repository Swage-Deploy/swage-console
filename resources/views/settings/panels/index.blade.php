<x-layouts.app title="Swage panels" current="panels">
    <div class="page-head">
        <div>
            <h1 class="h-xl">Swage panels</h1>
            <p class="subheading">The Swage panels the console reads servers from. Each panel's servers are listed under its name.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="callout green"><x-icon name="check-circle" /><div>{{ session('status') }}</div></div>
    @endif

    <x-panel icon="server-stack" heading="Panels" :description="trans_choice('{0} None yet|{1} :count panel|[2,*] :count panels', $panels->count())" flush>
        @if ($panels->isEmpty())
            <div class="empty">
                <div class="forge-grid"></div>
                <span class="tile-icon"><x-icon name="server-stack" class="lg" /></span>
                <h3 class="h-lg">No panels yet</h3>
                <p>Add your first Swage panel below with an API key that can read its servers.</p>
            </div>
        @else
            <div class="table-wrap">
                <table>
                    <tbody>
                        @foreach ($panels as $panel)
                            @php($color = ! $panel->enabled ? 'zinc' : ($panel->checked_at === null ? 'amber' : ($panel->check_ok ? 'green' : 'red')))
                            <tr>
                                <td style="width:100%;white-space:normal">
                                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                                        <x-status :color="$color" />
                                        <span style="color:#fff;font-weight:500">{{ $panel->name }}</span>
                                        @unless ($panel->enabled)<span class="badge">Disabled</span>@endunless
                                    </div>
                                    <div class="item-sub" style="margin-top:4px;overflow-wrap:anywhere">{{ $panel->url }} · token {{ $panel->tokenHint() }}</div>
                                    <div @class(['c-'.$color => $panel->checked_at, 'faint' => ! $panel->checked_at]) style="margin-top:4px;font-size:13px;overflow-wrap:anywhere;animation:none">
                                        @if ($panel->checked_at)
                                            {{ $panel->check_message }}
                                            <span class="faint">· tested {{ $panel->checked_at->diffForHumans() }}</span>
                                        @else
                                            Not tested yet
                                        @endif
                                    </div>
                                </td>
                                <td class="end">
                                    <div style="display:flex;gap:8px;justify-content:flex-end">
                                        <form method="post" action="{{ route('panels.check', $panel) }}" style="margin:0">
                                            @csrf
                                            <button class="btn sm"><x-icon name="signal" /> Test</button>
                                        </form>
                                        <a class="btn sm" href="{{ route('panels.edit', $panel) }}"><x-icon name="pencil-square" /> Edit</a>
                                        <form method="post" action="{{ route('panels.destroy', $panel) }}" style="margin:0"
                                              onsubmit="return confirm({{ \Illuminate\Support\Js::from('Remove '.$panel->name.' and its recorded usage history?') }})">
                                            @csrf @method('delete')
                                            <button class="btn ghost sm" aria-label="Remove {{ $panel->name }}"><x-icon name="trash" /></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-panel>

    <x-panel icon="plus" heading="Add a panel" description="Swage tests the connection as soon as the panel is saved.">
        <form method="post" action="{{ route('panels.store') }}">
            @csrf
            @include('settings.panels._form', ['panel' => null])
            <div style="display:flex;gap:8px;margin-top:20px"><button class="btn primary">Add and test</button></div>
        </form>
    </x-panel>
</x-layouts.app>
