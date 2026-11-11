<x-layouts.app title="Servers" current="dashboard">
    <x-slot:head>
        <style>
            /* ---------- summary tiles ---------- */
            .summary { display:grid; grid-template-columns:repeat(8, minmax(0, 1fr)); gap:12px; }
            .sum { display:flex; flex-direction:column; gap:4px; padding:12px 14px; border:1px solid var(--line); border-radius:10px; background:var(--surface); min-width:0; }
            .sum span { font-size:12px; font-weight:500; color:var(--zinc-400); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
            .sum b { font-family:var(--mono); font-size:20px; font-weight:600; letter-spacing:-.02em; color:#fff; }
            .sum.c-red b { color:var(--red-text); } .sum.c-amber b { color:var(--ember-400); } .sum.c-amber { animation:none; }
            @media (max-width:1279px) { .summary { grid-template-columns:repeat(4, minmax(0, 1fr)); } }
            @media (max-width:520px) { .summary { grid-template-columns:repeat(2, minmax(0, 1fr)); } }

            /* ---------- server header (Swage's server layout card) ---------- */
            .server-card { position:relative; isolation:isolate; overflow:hidden; padding:20px 24px; border:1px solid var(--line); border-radius:16px; background:var(--surface); }
            .server-card .glow { position:absolute; top:-96px; right:-96px; z-index:-1; width:256px; height:256px; border-radius:999px; background:color-mix(in oklab, var(--ember-500) 10%, transparent); filter:blur(64px); }
            .crumbs { display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin-bottom:16px; font-size:14px; color:var(--zinc-400); }
            .crumbs .sep { color:var(--zinc-600); }
            .crumbs .here { color:#fff; font-weight:500; }
            .site-domain { color:#fff; font-weight:500; }
            .crumbs a[href]:hover { color:var(--ember-400); }
            .server-row { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px 24px; }
            .server-id { display:flex; align-items:center; gap:16px; min-width:0; }
            .server-icon {
                display:flex; align-items:center; justify-content:center; flex-shrink:0; width:48px; height:48px; border-radius:12px; color:var(--zinc-950);
                background-image:linear-gradient(to bottom right, var(--ember-400), var(--ember-700)); box-shadow:0 0 24px -6px color-mix(in oklab, var(--ember-500) 70%, transparent);
            }
            .server-icon.down { background-image:linear-gradient(to bottom right, var(--zinc-500), var(--zinc-700)); box-shadow:none; }
            .server-name { display:flex; flex-wrap:wrap; align-items:center; gap:12px; }
            .facts { display:flex; flex-wrap:wrap; align-items:center; gap:4px 16px; margin-top:4px; font-family:var(--mono); font-size:12px; color:var(--zinc-500); }
            .facts span { display:inline-flex; align-items:center; gap:4px; }
            .facts .copy { cursor:pointer; } .facts .copy:hover { color:var(--zinc-200); }

            /* ---------- metric tiles (x-metric-tile) ---------- */
            .metric { display:flex; flex-direction:column; gap:12px; min-width:0; padding:16px; border:1px solid var(--line); border-radius:8px; }
            .metric-top { display:flex; align-items:center; justify-content:space-between; gap:8px; font-size:12px; font-weight:500; color:var(--zinc-400); }
            .metric-top .high { display:inline-flex; align-items:center; gap:4px; }
            .metric-value { font-family:var(--mono); font-size:24px; font-weight:600; letter-spacing:-.02em; color:#fff; line-height:1.3; }
            .metric-detail { margin-top:2px; font-family:var(--mono); font-size:12px; color:var(--zinc-400); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
            .meter { height:6px; width:100%; overflow:hidden; border-radius:999px; background:rgba(255,255,255,.05); }
            .meter i { display:block; height:100%; border-radius:999px; background:var(--ember-500); }
            .meter i.warning { background:var(--amber); } .meter i.critical { background:var(--red); }
            .spark { position:relative; margin-top:4px; }
            .spark svg { display:block; width:100%; height:40px; overflow:visible; }
            .spark .base { stroke:rgba(255,255,255,.1); }
            .spark .area { fill:color-mix(in oklab, var(--ember-500) 10%, transparent); }
            .spark .line { stroke:var(--ember-500); fill:none; }
            .spark .cursor { position:absolute; top:0; bottom:0; width:1px; background:rgba(255,255,255,.3); pointer-events:none; }
            .spark .pt { position:absolute; width:8px; height:8px; border-radius:999px; background:var(--ember-500); box-shadow:0 0 0 2px var(--zinc-900); transform:translate(-50%, -50%); pointer-events:none; }
            .spark .tip { position:absolute; top:-28px; z-index:5; transform:translateX(-50%); padding:4px 8px; border-radius:6px; background:#fff; color:var(--zinc-900); font-family:var(--mono); font-size:11px; white-space:nowrap; pointer-events:none; box-shadow:0 1px 2px rgba(0,0,0,.2); }
            .spark-none { height:40px; display:flex; align-items:center; font-size:12px; color:var(--zinc-500); }
            .footnote { margin:16px 0 0; font-size:12px; color:rgba(255,255,255,.7); }
            .footnote .stale { color:var(--amber-text); }

            .updated { display:inline-flex; align-items:center; gap:8px; font-size:13px; color:var(--zinc-400); }
            .updated.failed { color:var(--amber-text); }
            .loading { color:rgba(255,255,255,.6); padding:48px 0; text-align:center; }
            .spin { animation:spin 1s linear infinite; } @keyframes spin { to { transform:rotate(360deg); } }
        </style>
    </x-slot:head>

    <x-slot:sidebar>
        <div class="input-wrap">
            <x-icon name="magnifying-glass" class="sm" />
            <input id="q" class="input" placeholder="Search servers, sites, IPs…" autocomplete="off" aria-label="Search servers">
        </div>
        <div class="segmented" id="envs" hidden></div>
        <div class="nav-scroll">
            <nav class="nav" id="hostlist"><div class="nav-heading">Loading servers…</div></nav>
        </div>
        <div class="sidebar-foot">
            <span><kbd>j</kbd> <kbd>k</kbd> next / previous · <kbd>/</kbd> search</span>
        </div>
    </x-slot:sidebar>

    <div class="page-head">
        <div>
            <h1 class="h-xl">Servers</h1>
            <p class="subheading" id="lede">Reading your Swage panels…</p>
        </div>
        <div style="display:flex;align-items:center;gap:12px">
            <span class="updated" id="fetched"><span class="dot"></span>Loading…</span>
            <button class="btn sm" type="button" id="refresh"><x-icon name="arrow-path" /> Refresh</button>
        </div>
    </div>

    <div id="banners" style="display:flex;flex-direction:column;gap:12px" hidden></div>

    <div class="summary" id="summary"></div>

    <div id="h-empty" class="loading"><x-icon name="arrow-path" class="lg spin" /><div>Loading servers from Swage…</div></div>

    <div id="h-detail" style="display:flex;flex-direction:column;gap:24px" hidden>
        <div class="server-card">
            <div class="forge-grid"></div>
            <div class="glow"></div>
            <div class="crumbs" id="h-crumbs"></div>
            <div class="server-row">
                <div class="server-id">
                    <span class="server-icon" id="h-icon"><x-icon name="server" class="lg" /></span>
                    <div style="min-width:0">
                        <div class="server-name"><h2 class="h-xl" style="min-width:0"><a class="ext" id="h-name" target="_blank" rel="noopener" title="Open in Swage"></a></h2><span id="h-status"></span></div>
                        <div class="facts" id="h-facts"></div>
                    </div>
                </div>
                <div class="badges" id="h-badges"></div>
            </div>
        </div>

        <x-panel icon="chart-bar" heading="System monitor" link="h-monitor-link" description="Swage reads the server's usage every five minutes. Trends cover the last 24 hours.">
            <div id="h-monitor"></div>
        </x-panel>

        <div class="grid grid-2">
            <x-panel icon="cog-6-tooth" heading="Daemons" link="h-daemons-link" description="Long-running processes Swage keeps alive with Supervisor." flush>
                <x-slot:actions><span id="h-daemon-sum"></span></x-slot:actions>
                <div class="table-wrap"><table><tbody id="h-daemons"></tbody></table></div>
            </x-panel>
            <x-panel icon="clock" heading="Scheduler" link="h-jobs-link" description="Cron jobs on this server." flush>
                <div class="table-wrap"><table><tbody id="h-jobs"></tbody></table></div>
            </x-panel>
        </div>

        <x-panel icon="globe-alt" heading="Sites" link="h-sites-link" description="Sites on this server, their latest deployment and certificate." flush>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Domain</th><th>Repository</th><th>PHP</th><th>Last deployment</th><th class="end">Certificate</th></tr></thead>
                    <tbody id="h-sites"></tbody>
                </table>
            </div>
        </x-panel>
    </div>

    <h2 class="section-label">Across all panels</h2>

    <div class="grid grid-2">
        <x-panel icon="exclamation-triangle" heading="Needs attention" description="Unreachable servers, reboots, failed tasks and deployments, and certificates not renewing.">
            <div class="list" id="attention"><div class="none">Loading…</div></div>
        </x-panel>
        <x-panel icon="rocket-launch" heading="Deployments" description="The latest deployment of every site.">
            <div class="list" id="deployments"><div class="none">Loading…</div></div>
        </x-panel>
        <x-panel icon="lock-closed" heading="Certificates" description="Days until each site's certificate expires.">
            <div class="list" id="certificates"><div class="none">Loading…</div></div>
        </x-panel>
        <x-panel icon="queue-list" heading="Recent activity" description="Tasks Swage ran on your servers.">
            <div class="list" id="activity"><div class="none">Loading…</div></div>
        </x-panel>
    </div>

    <x-slot:scripts>
    <script>
        const DATA_URL = @json(route('data'));
        const SETTINGS_URL = @json(route('panels.index'));
        const ICONS = @json(app('icons'));
        const REFRESH_MS = 60000;

        let D = null;
        const $ = id => document.getElementById(id);
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[c]));
        const icon = (name, cls = '') => `<svg class="icon ${cls}" viewBox="0 0 20 20" aria-hidden="true">${ICONS[name] ?? ''}</svg>`;
        /* The dashboard's states (ok, warn, crit, down, fail, info) mapped onto Swage's status colours. */
        const tone = s => ({ok:'green', warn:'amber', crit:'red', fail:'red', err:'red', down:'zinc', info:'sky'})[s] ?? 'zinc';
        const status = (s, label = '') => `<span class="status c-${tone(s)}"><span class="dot"></span>${label ? `<span>${esc(label)}</span>` : ''}</span>`;
        const level = (v, warn, crit) => v >= crit ? 'crit' : v >= warn ? 'warn' : 'ok';
        const fileSize = b => { const u = ['B', 'KB', 'MB', 'GB', 'TB']; let i = 0; b = Number(b) || 0; while (b >= 1024 && i < 4) { b /= 1024; i++; } return (i === 0 ? b : b.toFixed(1)) + ' ' + u[i]; };
        const num = (v, p = 1) => Number(v ?? 0).toFixed(p);
        const ago = iso => {
            if (!iso) return 'never';
            const s = Math.max(0, (Date.now() - new Date(iso)) / 1000);
            const unit = (n, w) => `${n} ${w}${n === 1 ? '' : 's'} ago`;
            return s < 45 ? 'just now' : s < 3600 ? unit(Math.max(1, Math.round(s / 60)), 'minute') : s < 86400 ? unit(Math.round(s / 3600), 'hour') : unit(Math.round(s / 86400), 'day');
        };
        const uptime = s => {
            const parts = [[86400, 'day'], [3600, 'hour'], [60, 'minute']].map(([n, w]) => { const v = Math.floor(s / n); s %= n; return v ? `${v} ${w}${v === 1 ? '' : 's'}` : null; }).filter(Boolean);
            return parts.slice(0, 2).join(' ') || 'less than a minute';
        };
        /* A link into a Swage panel, or plain text when the panel's address is unknown. */
        const ext = (href, html, cls = '') => href
            ? `<a class="ext ${cls}" href="${esc(href)}" target="_blank" rel="noopener" title="Open in Swage">${html}${icon('arrow-top-right-on-square', 'sm')}</a>`
            : `<span class="${cls}">${html}</span>`;
        const setLink = (id, href) => href ? $(id).setAttribute('href', href) : $(id).removeAttribute('href');
        const list = (items, empty, fn) => items.length ? items.map(fn).join('') : `<div class="none">${esc(empty)}</div>`;
        const item = ({state, title, sub, end, host, href, attrs = ''}) => `
            <div class="item ${host ? 'clickable' : ''}" ${host ? `data-host="${esc(host)}"` : ''} ${attrs}>
                ${status(state)}
                <div class="item-main"><div class="item-title truncate">${href ? ext(href, esc(title)) : esc(title)}</div>${sub ? `<div class="item-sub truncate">${esc(sub)}</div>` : ''}</div>
                ${end ? `<div class="item-end">${end}</div>` : ''}
            </div>`;

        let selectedKey = decodeURIComponent(location.hash.slice(1)) || null;
        let sourceFilter = 'all', query = '';
        const selected = () => D?.hosts.find(h => h.key === selectedKey);

        /* ---------------- header, banners, summary, filters ---------------- */
        function renderChrome() {
            const sm = D.summary, okPanels = D.sources.filter(s => s.ok).length;
            $('lede').textContent = D.sources.length
                ? `${sm.total} ${sm.total === 1 ? 'server' : 'servers'} across ${D.sources.length} Swage ${D.sources.length === 1 ? 'panel' : 'panels'}.`
                : 'No Swage panels yet.';

            $('summary').innerHTML = [
                ['Panels', `${okPanels}/${D.sources.length}`, okPanels < D.sources.length ? 'red' : ''],
                ['Servers up', `${sm.up}/${sm.total}`, sm.up < sm.total ? 'red' : ''],
                ['Critical', sm.critical, sm.critical ? 'red' : ''],
                ['Warning', sm.warning, sm.warning ? 'amber' : ''],
                ['Sites', sm.sites, ''],
                ['Failed deploys', sm.failed_deploys, sm.failed_deploys ? 'red' : ''],
                ['Reboots needed', sm.reboots, sm.reboots ? 'amber' : ''],
                ['Certs ≤ 30 days', sm.certs_expiring, sm.certs_expiring ? 'amber' : ''],
            ].map(([label, value, c]) => `<div class="sum ${c ? 'c-' + c : ''}"><span>${label}</span><b>${esc(value)}</b></div>`).join('');

            $('banners').hidden = !D.errors.length;
            $('banners').innerHTML = D.errors.map(e => `<div class="callout amber">${icon('exclamation-triangle')}<div>${esc(e)} <a href="${esc(SETTINGS_URL)}">Open Swage panels</a></div></div>`).join('');

            if (!D.sources.some(s => s.key === sourceFilter)) sourceFilter = 'all';
            $('envs').hidden = D.sources.length < 2;
            $('envs').innerHTML = [{key:'all', name:'All', ok:true}, ...D.sources].map(s =>
                `<button type="button" class="${s.key === sourceFilter ? 'on' : ''} ${s.ok ? '' : 'bad'}" data-env="${esc(s.key)}" title="${s.ok ? '' : 'Unreachable'}">${esc(s.name)}</button>`).join('');
        }

        /* ---------------- sidebar: servers grouped by panel ---------------- */
        const visibleHosts = () => (D?.hosts ?? []).filter(h =>
            (sourceFilter === 'all' || h.source === sourceFilter) &&
            (!query || [h.hostname, h.source_name, h.os, h.ip, h.type, ...h.sites.map(s => s.domain)].join(' ').toLowerCase().includes(query)));

        function renderList() {
            const groups = {};
            visibleHosts().forEach(h => (groups[h.source_name] ??= []).push(h));
            $('hostlist').innerHTML = Object.entries(groups).map(([panel, hosts]) => {
                const flagged = hosts.filter(h => h.status !== 'ok').length;
                return `<div class="nav-heading"><span class="truncate">${esc(panel)}</span>${flagged ? `<span class="badge c-red" style="padding:0 6px">${flagged}</span>` : ''}</div>
                    ${hosts.map(h => `<button type="button" class="nav-item ${h.key === selectedKey ? 'current' : ''}" data-host="${esc(h.key)}" title="${esc(h.type)} · ${esc(h.ip)}">
                        <span class="slot">${status(h.status)}</span><span class="truncate">${esc(h.hostname)}</span>
                        <span class="end">${h.has_usage && h.status !== 'down' ? Math.round(h.cpu) + '%' : ''}</span></button>`).join('')}`;
            }).join('') || `<div class="nav-heading">${D.hosts.length ? 'No matching servers' : 'No servers'}</div>`;
        }

        /* ---------------- sparklines (x-sparkline) ---------------- */
        const sparks = new Map();
        function sparkline(id, time, values, max, label) {
            const end = Date.now(), start = end - 24 * 3600e3, span = end - start;
            max = Math.max(max, 0.0001);
            const pts = time.map((t, i) => ({
                x: Math.max(0, Math.min(1, (t - start) / span)) * 100,
                y: 32 - Math.max(0, Math.min(1, values[i] / max)) * 30,
                t, text: new Date(t).toLocaleString(undefined, {weekday:'short', hour:'2-digit', minute:'2-digit'}) + ' · ' + label(values[i]),
            })).filter(p => p.t >= start);
            if (!pts.length) return '<div class="spark-none">Collecting readings…</div>';
            sparks.set(id, pts);
            /* Readings missed for more than about an hour and a half, such as while the server was down, break the line. */
            const segments = [];
            pts.forEach((p, i) => { if (i === 0 || p.t - pts[i - 1].t > 90 * 60e3) segments.push([]); segments.at(-1).push(p); });
            const paths = segments.map(seg => seg.length > 1
                ? `<path class="area" d="M${seg[0].x},32 L${seg.map(p => p.x + ',' + p.y).join(' L')} L${seg.at(-1).x},32 Z"/>
                   <polyline class="line" points="${seg.map(p => p.x + ',' + p.y).join(' ')}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"/>`
                : `<line class="line" x1="${seg[0].x}" y1="${seg[0].y}" x2="${seg[0].x}" y2="${seg[0].y}" stroke-width="4" stroke-linecap="round" vector-effect="non-scaling-stroke"/>`).join('');
            return `<div class="spark" data-spark="${id}"><svg viewBox="0 0 100 32" preserveAspectRatio="none" aria-hidden="true">
                <line class="base" x1="0" y1="32" x2="100" y2="32" stroke-width="1" vector-effect="non-scaling-stroke"/>${paths}</svg></div>`;
        }
        document.addEventListener('mousemove', e => {
            const el = e.target.closest('.spark');
            document.querySelectorAll('.spark .hover').forEach(h => { if (h.parentElement !== el) h.remove(); });
            if (!el) return;
            const pts = sparks.get(el.dataset.spark) ?? [], rect = el.getBoundingClientRect(), f = (e.clientX - rect.left) / rect.width * 100;
            if (!pts.length) return;
            const p = pts.reduce((best, q) => Math.abs(q.x - f) < Math.abs(best.x - f) ? q : best);
            let hover = el.querySelector('.hover');
            if (!hover) { hover = document.createElement('div'); hover.className = 'hover'; el.append(hover); }
            hover.innerHTML = `<div class="cursor" style="left:${p.x}%"></div><div class="pt" style="left:${p.x}%;top:${p.y / 32 * 100}%"></div>
                <div class="tip" style="left:min(max(${p.x}%, 4rem), calc(100% - 4rem))">${esc(p.text)}</div>`;
        });

        function metric({label, value, detail, percent, warn, crit, high = 'High', meter = true, spark}) {
            const lvl = !meter ? 'normal' : percent >= crit ? 'critical' : percent >= warn ? 'warning' : 'normal';
            return `<div class="metric">
                <div class="metric-top"><span>${esc(label)}</span>${lvl !== 'normal' ? `<span class="high ${lvl === 'critical' ? 'c-red' : 'c-amber'}" style="animation:none">${icon('exclamation-triangle', 'sm')}${esc(high)}</span>` : ''}</div>
                <div><div class="metric-value">${esc(value)}</div>${detail ? `<div class="metric-detail">${esc(detail)}</div>` : ''}</div>
                ${meter ? `<div class="meter" role="meter" aria-label="${esc(label)}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${Math.round(percent)}"><i class="${lvl}" style="width:${Math.max(0, Math.min(100, percent))}%"></i></div>` : ''}
                ${spark}
            </div>`;
        }

        /* ---------------- selected server ---------------- */
        const swageStatus = h => h.status === 'down' ? (h.swage_status !== 'active' ? h.swage_status : 'Unreachable')
            : h.status === 'crit' ? 'Critical' : h.status === 'warn' ? 'Warning' : 'Active';

        function renderHost() {
            const h = selected();
            $('h-detail').hidden = !h;
            $('h-empty').hidden = !!h;
            if (!h) {
                $('h-empty').className = '';
                $('h-empty').innerHTML = `<div class="panel"><div class="empty"><div class="forge-grid"></div><span class="tile-icon">${icon('server-stack', 'lg')}</span>
                    <h3 class="h-lg">No servers to show</h3><p>Add or check your Swage panels, and make sure each API key can read Servers.</p>
                    <a class="btn primary sm" href="${esc(SETTINGS_URL)}" style="margin-top:8px">Open Swage panels</a></div></div>`;
                return;
            }
            const down = h.status === 'down';
            const panel = D.sources.find(s => s.key === h.source);
            $('h-crumbs').innerHTML = `<span>Servers</span><span class="sep">/</span>${panel?.url ? `<a href="${esc(panel.url)}" target="_blank" rel="noopener" title="Open ${esc(h.source_name)}">${esc(h.source_name)}</a>` : `<span>${esc(h.source_name)}</span>`}<span class="sep">/</span><span class="here">${esc(h.hostname)}</span>`;
            $('h-icon').classList.toggle('down', down);
            $('h-name').innerHTML = `<span class="truncate">${esc(h.hostname)}</span>${icon('arrow-top-right-on-square', 'sm')}`;
            setLink('h-name', h.url);
            setLink('h-monitor-link', h.url);
            setLink('h-daemons-link', h.links.daemons);
            setLink('h-jobs-link', h.links.scheduler);
            setLink('h-sites-link', h.links.sites);
            $('h-status').innerHTML = status(h.status, swageStatus(h));
            $('h-facts').innerHTML = [
                ['globe-alt', h.ip + (h.private_ip ? ' / ' + h.private_ip : ''), 'copy'],
                ['cpu-chip', `${h.cores} vCPU${h.cores === 1 ? '' : 's'}`],
                ['circle-stack', `${h.ram} GB`],
                ['code-bracket', h.php.length ? 'PHP ' + h.php.join(', ') : 'No PHP'],
                ...(h.databases.length ? [['circle-stack', h.databases.join(', ')]] : []),
                ...(h.redis ? [['bolt', 'Redis']] : []),
                ['server', h.os],
                ['signal', h.type],
            ].map(([i, text, cls = '']) => `<span class="${cls}" ${cls ? `data-copy="${esc(h.ip)}" title="Copy IP address"` : ''}>${icon(i, 'sm')}${esc(text)}</span>`).join('');

            const badges = [];
            if (!h.has_usage) badges.push(['zinc', 'No metrics']);
            if (h.reboot_required) badges.push(['amber', 'Reboot required' + (h.reboot_since ? ' · ' + ago(h.reboot_since) : '')]);
            if (h.failed_tasks.length) badges.push(['red', `${h.failed_tasks.length} failed ${h.failed_tasks.length === 1 ? 'task' : 'tasks'}`]);
            if (h.certificates_not_renewing.length) badges.push(['amber', 'Certificate not renewing']);
            $('h-badges').innerHTML = badges.map(([c, t]) => `<span class="badge c-${c}">${esc(t)}</span>`).join('');

            renderMonitor(h, down);

            const failed = h.daemons.filter(s => ['failed', 'error'].includes(s.state)).length;
            $('h-daemon-sum').textContent = h.daemons.length ? `${h.daemons.length - failed} running · ${failed} failed` : '';
            $('h-daemons').innerHTML = h.daemons.length ? h.daemons.map(s => {
                const st = down ? 'down' : ['failed', 'error'].includes(s.state) ? 'fail' : ['installing', 'pending', 'restarting'].includes(s.state) ? 'warn' : 'ok';
                return `<tr><td style="width:100%;white-space:normal"><div class="mono" style="font-size:13px;color:var(--zinc-100)">${esc(s.name)}</div><div class="item-sub">${esc(s.user)} · ${esc(s.processes)} ${Number(s.processes) === 1 ? 'process' : 'processes'}</div></td>
                    <td class="end">${status(st, s.state)}</td></tr>`;
            }).join('') : '<tr><td class="none">No daemons on this server.</td></tr>';

            const jobs = D.jobs.filter(j => j.source_name === h.source_name && j.host === h.hostname);
            $('h-jobs').innerHTML = jobs.length ? jobs.map(j => `<tr><td style="width:100%;white-space:normal"><div class="mono" style="font-size:13px;color:var(--zinc-100);overflow-wrap:anywhere">${esc(j.command)}</div><div class="item-sub">${esc(j.user || 'root')}</div></td>
                <td class="end"><span class="badge">${esc(j.frequency)}</span></td></tr>`).join('') : '<tr><td class="none">No scheduled jobs on this server.</td></tr>';

            $('h-sites').innerHTML = h.sites.length ? h.sites.map(s => {
                const cs = s.cert_days === null ? 'down' : s.cert_overdue || s.cert_days <= 14 ? 'crit' : s.cert_days <= 30 ? 'warn' : 'ok';
                return `<tr>
                    <td><div style="display:flex;align-items:center;gap:10px">${status(s.deploy_state)}${ext(s.url, esc(s.domain), 'site-domain')}</div></td>
                    <td class="mono" style="font-size:12px;color:var(--zinc-400)">${esc(s.repository || '—')}${s.branch ? '<span class="faint">@' + esc(s.branch) + '</span>' : ''}</td>
                    <td class="mono" style="font-size:12px">${esc(s.php || '—')}</td>
                    <td>${status(s.deploy_state, s.deploy_status ?? 'Never deployed')} <span class="faint" style="font-size:12px" ${s.deployed_at ? `data-ago="${esc(s.deployed_at)}"` : ''}>${s.deployed_at ? ago(s.deployed_at) : ''}</span></td>
                    <td class="end">${ext(s.certificate_url, status(cs, s.cert_days === null ? 'None' : s.cert_days + ' days'))}</td></tr>`;
            }).join('') : '<tr><td colspan="5" class="none">No sites on this server.</td></tr>';
        }

        function renderMonitor(h, down) {
            sparks.clear();
            if (down || !h.has_usage) {
                $('h-monitor').innerHTML = `<div class="empty" style="padding:32px 24px"><div class="forge-grid"></div><span class="tile-icon">${icon('chart-bar', 'lg')}</span>
                    <h3 class="h-lg">${down ? 'Swage can\'t read this server' : 'No readings from this panel'}</h3>
                    <p>${down ? (h.read_at ? 'Its last reading was ' + ago(h.read_at) + '.' : 'Swage has no readings for it yet.') : 'This panel doesn\'t report usage. Update Swage to see CPU, memory and disk here.'}</p></div>`;
                return;
            }
            const t = h.history.time, cores = Math.max(1, h.cores), pct = v => num(v, 1) + '%';
            const loadMax = Math.max(cores, ...h.history.load);
            $('h-monitor').innerHTML = `<div class="grid grid-4">
                ${metric({label:'CPU', value:pct(h.cpu), detail:`${cores} ${cores === 1 ? 'core' : 'cores'}`, percent:h.cpu, warn:75, crit:90, spark:sparkline('cpu', t, h.history.cpu, 100, pct)})}
                ${metric({label:h.load_is_host ? 'Host load' : 'Load', value:num(h.load[0], 2), detail:`5 min ${num(h.load[1], 2)} · 15 min ${num(h.load[2], 2)}`, percent:h.load[0] / cores * 100, warn:70, crit:100, meter:!h.load_is_host, spark:sparkline('load', t, h.history.load, loadMax, v => num(v, 2))})}
                ${metric({label:'Memory', value:Math.round(h.memory) + '%', detail:`${fileSize(h.memory_used)} of ${fileSize(h.memory_total)}` + (h.swap !== null ? ` · swap ${Math.round(h.swap)}%` : ''), percent:h.memory, warn:80, crit:92, spark:sparkline('memory', t, h.history.memory, 100, pct)})}
                ${metric({label:'Disk', value:Math.round(h.disk) + '%', detail:`${fileSize(h.disk_total - h.disk_used)} free of ${fileSize(h.disk_total)}`, percent:h.disk, warn:80, crit:90, high:'Filling up', spark:sparkline('disk', t, h.history.disk, 100, pct)})}
            </div>
            <p class="footnote">${h.load_is_host ? 'This server is a container, so its load is its host\'s, shared with everything else running there. ' : ''}Up ${esc(uptime(h.uptime_seconds))}. Read <span data-ago="${esc(h.read_at)}">${ago(h.read_at)}</span>.</p>`;
        }

        /* ---------------- across all panels ---------------- */
        function renderChecks() {
            const attention = D.hosts.flatMap(h => [
                ...(h.status === 'down' ? [['crit', h, 'Unreachable', h.read_at ? 'last reading ' + ago(h.read_at) : 'no readings']] : []),
                ...(h.reboot_required ? [['warn', h, 'Reboot required', h.reboot_packages.length ? 'for ' + h.reboot_packages.join(', ') : '']] : []),
                ...h.failed_tasks.map(t => ['crit', h, 'Task failed', t]),
                ...h.certificates_not_renewing.map(c => ['warn', h, 'Certificate not renewing', c]),
                ...h.sites.filter(s => s.deploy_state === 'fail').map(s => ['crit', h, 'Deployment failed', s.domain]),
            ]);
            $('attention').innerHTML = attention.length
                ? attention.map(([state, h, what, detail]) => item({state, title:`${h.hostname} · ${what}`, sub:[h.source_name, detail].filter(Boolean).join(' · '), host:h.key}))
                    .join('')
                : `<div class="item">${status('ok')}<div class="item-main"><div class="item-title">All clear</div><div class="item-sub">Nothing needs you right now.</div></div></div>`;

            $('deployments').innerHTML = list(D.deployments, 'No deployments yet.', d => item({
                state:d.state, title:d.site, href:d.url, sub:[d.source_name, d.status, d.commit].filter(Boolean).join(' · '),
                end:`<span data-ago="${esc(d.at)}">${ago(d.at)}</span>`,
            }));

            $('certificates').innerHTML = list(D.certificates, 'No certificates.', c => item({
                state:c.expires_in <= 14 ? 'crit' : c.expires_in <= 30 ? 'warn' : 'ok', title:c.name, href:c.url, sub:`${c.source_name} · ${c.server}`,
                end:`<span class="${c.expires_in <= 14 ? 'c-red' : c.expires_in <= 30 ? 'c-amber' : ''}" style="animation:none">${esc(c.expires_in)} days</span>`,
            }));

            $('activity').innerHTML = list(D.activity, 'No activity yet.', t => item({
                state:t.state, title:t.name, href:t.url, sub:`${t.source_name} · ${t.host} · ${t.status}`, end:`<span data-ago="${esc(t.at)}">${ago(t.at)}</span>`,
            }));
        }

        function renderAll() {
            if (!selected()) selectedKey = (D.hosts.find(h => h.status === 'crit') || D.hosts.find(h => h.status === 'down') || D.hosts[0])?.key ?? null;
            renderChrome(); renderList(); renderHost(); renderChecks(); tick();
        }

        /* ---------------- loading: fetch in the background, never reload the page ---------------- */
        let lastError = null, timer = null, loading = false;
        async function load() {
            if (loading) return;
            loading = true;
            clearTimeout(timer);
            $('refresh').querySelector('.icon').classList.add('spin');
            try {
                const response = await fetch(DATA_URL, {headers:{Accept:'application/json'}, credentials:'same-origin', cache:'no-store'});
                /* Signed out, for example when the session expired: reload so the sign-in page takes over. */
                if (response.status === 401 || response.status === 419) { window.location.reload(); return; }
                if (!response.ok) throw new Error('HTTP ' + response.status);
                D = await response.json();
                lastError = null;
                renderAll();
            } catch (error) {
                lastError = error.message;
                if (!D) $('h-empty').innerHTML = `<div class="callout amber">${icon('exclamation-triangle')}<div>Could not load data: ${esc(error.message)}. Retrying…</div></div>`;
                tick();
            } finally {
                loading = false;
                $('refresh').querySelector('.icon').classList.remove('spin');
                timer = setTimeout(load, document.hidden ? REFRESH_MS * 2 : REFRESH_MS);
            }
        }
        document.addEventListener('visibilitychange', () => { if (!document.hidden && D && Date.now() - new Date(D.fetched_at) > REFRESH_MS) load(); });
        $('refresh').addEventListener('click', load);

        /* ---------------- interactions ---------------- */
        function select(key) {
            if (!D?.hosts.some(h => h.key === key)) return;
            selectedKey = key;
            history.replaceState(null, '', '#' + encodeURIComponent(key));
            renderList(); renderHost();
        }
        document.addEventListener('click', e => {
            const row = e.target.closest('[data-host]');
            if (row) {
                select(row.dataset.host);
                document.body.classList.remove('nav-open');
                if (!row.closest('.sidebar')) scrollTo({top:0, behavior:'smooth'});
            }
            const env = e.target.closest('#envs [data-env]');
            if (env) { sourceFilter = env.dataset.env; renderChrome(); renderList(); }
            const copy = e.target.closest('[data-copy]');
            if (copy && navigator.clipboard) navigator.clipboard.writeText(copy.dataset.copy).then(() => { copy.title = 'Copied'; });
        });
        $('q').addEventListener('input', e => { query = e.target.value.trim().toLowerCase(); if (D) renderList(); });
        document.addEventListener('keydown', e => {
            if (e.target.tagName === 'INPUT') { if (e.key === 'Escape') e.target.blur(); return; }
            if (e.metaKey || e.ctrlKey || e.altKey) return;
            if (e.key === '/') { e.preventDefault(); $('q').focus(); }
            if ((e.key === 'j' || e.key === 'k') && D) {
                const hosts = visibleHosts(), i = hosts.findIndex(h => h.key === selectedKey);
                const next = hosts[Math.max(0, Math.min(hosts.length - 1, (i < 0 ? 0 : i) + (e.key === 'j' ? 1 : -1)))];
                if (next) { select(next.key); document.querySelector('#hostlist .current')?.scrollIntoView({block:'nearest'}); }
            }
        });

        /* ---------------- relative times ---------------- */
        function tick() {
            const f = $('fetched');
            f.className = 'updated' + (lastError ? ' failed' : '');
            f.innerHTML = lastError ? `${icon('exclamation-triangle', 'sm')}Update failed, retrying`
                : D ? `${status(D.errors.length ? 'warn' : 'ok')}Updated ${ago(D.fetched_at)}` : `${status('down')}Loading…`;
            document.querySelectorAll('[data-ago]').forEach(el => el.textContent = ago(el.dataset.ago));
        }
        setInterval(tick, 15000);
        tick();
        load();
    </script>
    </x-slot:scripts>
</x-layouts.app>
