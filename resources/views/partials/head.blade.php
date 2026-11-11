    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ filled($title ?? null) ? $title.' - Swage Console' : 'Swage Console' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* Swage's palette: soot and ash greys warmed toward the forge, and ember for accents. */
        :root {
            color-scheme: dark;
            --zinc-50:#fafaf9; --zinc-100:#f5f4f2; --zinc-200:#e8e5e1; --zinc-300:#d6d1cb; --zinc-400:#a8a099; --zinc-500:#7a716a;
            --zinc-600:#5a524c; --zinc-700:#3d3632; --zinc-800:#26211e; --zinc-900:#1a1614; --zinc-950:#0f0c0b;
            --ember-300:#fdba74; --ember-400:#fb923c; --ember-500:#f97316; --ember-600:#ea580c; --ember-700:#c2410c;
            --green:#10b981; --green-text:#34d399; --red:#ef4444; --red-text:#f87171; --amber:#f59e0b; --amber-text:#fbbf24; --sky:#0ea5e9; --sky-text:#38bdf8;
            --line:rgba(255,255,255,.08); --line-soft:rgba(255,255,255,.06); --surface:rgba(26,22,20,.7);
            --sans:'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji';
            --mono:'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
        }
        *, ::before, ::after { box-sizing:border-box; border-color:var(--zinc-800); }
        ::selection { background:color-mix(in oklab, var(--ember-500) 35%, transparent); }
        html { background:var(--zinc-950); }
        body {
            margin:0; min-height:100vh; display:flex; font-family:var(--sans); font-size:14px; line-height:1.5; color:var(--zinc-300);
            -webkit-font-smoothing:antialiased; background-color:var(--zinc-950); background-attachment:fixed;
            background-image:radial-gradient(ellipse 80% 50% at 50% -10%, color-mix(in oklab, var(--ember-500) 14%, transparent), transparent 70%);
        }
        a { color:inherit; text-decoration:none; }
        /* Links out to a Swage panel: an arrow shows on hover. */
        a.ext { display:inline-flex; align-items:center; gap:6px; max-width:100%; color:inherit; transition:color .15s; }
        a.ext .icon { opacity:0; color:var(--ember-400); transition:opacity .15s; }
        a.ext[href]:hover, a.ext[href]:focus-visible { color:var(--ember-400); }
        a.ext[href]:hover .icon, a.ext[href]:focus-visible .icon { opacity:1; }
        a.ext:not([href]) .icon { display:none; }
        button, input { font:inherit; color:inherit; }
        [hidden] { display:none !important; }
        .mono { font-family:var(--mono); }
        .muted { color:rgba(255,255,255,.7); }
        .faint { color:var(--zinc-500); }
        .truncate { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; min-width:0; }
        .icon { width:16px; height:16px; flex-shrink:0; fill:currentColor; }
        .icon.sm { width:14px; height:14px; }
        .icon.lg { width:24px; height:24px; }
        ::-webkit-scrollbar { width:8px; height:8px; } ::-webkit-scrollbar-thumb { background:var(--zinc-800); border-radius:8px; } ::-webkit-scrollbar-track { background:transparent; }

        /* ---------- sidebar ---------- */
        .sidebar {
            position:sticky; top:0; height:100dvh; width:256px; flex-shrink:0; display:flex; flex-direction:column; gap:16px; padding:16px;
            border-right:1px solid var(--line); background:rgba(0,0,0,.3); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); overflow:hidden; z-index:30;
        }
        .brand { display:flex; align-items:center; gap:10px; height:32px; padding:0 8px; color:#fff; font-weight:600; letter-spacing:-.01em; }
        .brand svg { height:20px; width:auto; }
        .nav { display:flex; flex-direction:column; gap:2px; }
        .nav-heading { display:flex; justify-content:space-between; align-items:center; gap:8px; padding:12px 12px 6px; font-size:12px; line-height:1; color:var(--zinc-400); }
        .nav-item {
            display:flex; align-items:center; gap:12px; height:32px; padding:0 12px; border:1px solid transparent; border-radius:8px; width:100%;
            background:none; text-align:left; font-size:14px; font-weight:500; color:var(--zinc-400); cursor:pointer;
        }
        .nav-item:hover { background:rgba(255,255,255,.07); color:#fff; }
        .nav-item.current { background:rgba(255,255,255,.07); color:var(--ember-400); }
        .nav-item .end { margin-left:auto; font-family:var(--mono); font-size:11px; color:var(--zinc-500); }
        .nav-item .slot { display:flex; width:16px; height:16px; align-items:center; justify-content:center; flex-shrink:0; }
        .nav-scroll { flex:1; min-height:0; overflow-y:auto; margin:0 -8px; padding:0 8px; }
        .sidebar-foot { display:flex; flex-direction:column; gap:6px; padding:12px 8px 0; border-top:1px solid var(--line); font-size:12px; color:var(--zinc-500); }
        .user-menu { display:flex; align-items:center; gap:10px; padding:12px 8px 0; border-top:1px solid var(--line); font-size:14px; }
        .avatar { display:flex; align-items:center; justify-content:center; flex-shrink:0; width:32px; height:32px; border-radius:8px; background:var(--zinc-700); color:#fff; font-size:12px; font-weight:600; text-transform:uppercase; }
        .mobilebar { display:none; }
        .scrim { display:none; }

        /* ---------- main ---------- */
        .main { flex:1; min-width:0; padding:32px; }
        .container { max-width:80rem; margin:0 auto; display:flex; flex-direction:column; gap:24px; }
        .page-head { display:flex; flex-wrap:wrap; align-items:flex-end; justify-content:space-between; gap:16px; }
        h1, h2, h3 { margin:0; font-weight:500; color:#fff; }
        .h-xl { font-size:24px; line-height:1.25; letter-spacing:-.02em; }
        .h-lg { font-size:16px; letter-spacing:-.01em; }
        .h-base { font-size:14px; }
        .subheading { margin:4px 0 0; font-size:14px; color:rgba(255,255,255,.7); }
        .section-label { margin:8px 0 -8px; font-size:12px; font-weight:500; letter-spacing:.04em; text-transform:uppercase; color:var(--zinc-500); }

        /* ---------- panel (x-panel) ---------- */
        .panel { overflow:hidden; border:1px solid var(--line); border-radius:12px; background:var(--surface); box-shadow:0 1px 2px rgba(0,0,0,.05); min-width:0; }
        .panel-head { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px; padding:16px 20px; border-bottom:1px solid var(--line); }
        .panel-title { display:flex; align-items:flex-start; gap:12px; min-width:0; }
        .panel-title p { margin:2px 0 0; font-size:14px; color:rgba(255,255,255,.7); }
        .tile-icon {
            display:flex; align-items:center; justify-content:center; flex-shrink:0; width:32px; height:32px; margin-top:2px; border-radius:8px;
            background:color-mix(in oklab, var(--ember-500) 10%, transparent); color:var(--ember-400); box-shadow:inset 0 0 0 1px color-mix(in oklab, var(--ember-500) 20%, transparent);
        }
        .panel-actions { display:flex; align-items:center; gap:8px; flex-shrink:0; font-size:12px; color:var(--zinc-500); }
        .panel-body { padding:20px; }
        .panel-body.flush { padding:0; }
        .grid { display:grid; gap:16px; }
        .grid-2 { grid-template-columns:repeat(2, minmax(0, 1fr)); }
        .grid-4 { grid-template-columns:repeat(4, minmax(0, 1fr)); }

        /* ---------- status (x-status) ---------- */
        .status { display:inline-flex; align-items:center; gap:8px; font-size:12px; font-weight:500; white-space:nowrap; color:var(--zinc-400); }
        .dot { width:8px; height:8px; flex-shrink:0; border-radius:999px; background:var(--zinc-500); }
        .c-green { color:var(--green-text); } .c-green .dot, .dot.c-green { background:var(--green); box-shadow:0 0 8px 1px color-mix(in oklab, var(--green) 70%, transparent); }
        .c-amber { color:var(--ember-400); } .c-amber .dot, .dot.c-amber { background:var(--ember-500); box-shadow:0 0 8px 1px color-mix(in oklab, var(--ember-500) 80%, transparent); animation:ember 2s ease-in-out infinite; }
        .c-red { color:var(--red-text); } .c-red .dot, .dot.c-red { background:var(--red); box-shadow:0 0 8px 1px color-mix(in oklab, var(--red) 70%, transparent); }
        .c-sky { color:var(--sky-text); } .c-sky .dot, .dot.c-sky { background:var(--sky); box-shadow:0 0 8px 1px color-mix(in oklab, var(--sky) 70%, transparent); }
        .c-zinc { color:var(--zinc-400); }
        @keyframes ember { 0%, 100% { opacity:1; } 50% { opacity:.35; } }

        /* ---------- badges, callouts, buttons, inputs ---------- */
        .badge { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:6px; font-size:12px; font-weight:500; white-space:nowrap; background:rgba(168,160,153,.2); color:var(--zinc-200); }
        .badge.c-red { background:color-mix(in oklab, var(--red) 22%, transparent); color:#fecaca; }
        .badge.c-amber { background:color-mix(in oklab, var(--ember-500) 20%, transparent); color:var(--ember-300); animation:none; }
        .badge.c-green { background:color-mix(in oklab, var(--green) 20%, transparent); color:#a7f3d0; }
        .badges { display:flex; flex-wrap:wrap; gap:6px; }
        .callout { display:flex; gap:12px; padding:14px 16px; border:1px solid; border-radius:12px; font-size:14px; color:var(--zinc-200); }
        .callout .icon { margin-top:2px; }
        .callout.amber { border-color:color-mix(in oklab, var(--amber) 30%, transparent); background:color-mix(in oklab, var(--amber) 8%, transparent); }
        .callout.amber .icon { color:var(--amber-text); }
        .callout.green { border-color:color-mix(in oklab, var(--green) 30%, transparent); background:color-mix(in oklab, var(--green) 8%, transparent); }
        .callout.green .icon { color:var(--green-text); }
        .callout a { color:var(--ember-400); font-weight:500; text-decoration:underline; text-decoration-color:color-mix(in oklab, var(--ember-500) 40%, transparent); text-underline-offset:4px; }
        .link { color:var(--ember-400); font-weight:500; }
        .link:hover { text-decoration:underline; text-underline-offset:4px; }
        .btn {
            display:inline-flex; align-items:center; justify-content:center; gap:6px; height:40px; padding:0 16px; border:1px solid var(--zinc-600); border-radius:8px;
            background:var(--zinc-700); color:#fff; font-size:14px; font-weight:500; white-space:nowrap; cursor:pointer; box-shadow:0 1px 2px rgba(0,0,0,.05);
        }
        .btn:hover { background:color-mix(in oklab, var(--zinc-600) 75%, transparent); }
        .btn.sm { height:32px; padding:0 12px; border-radius:6px; }
        .btn.primary { border-color:transparent; background:var(--ember-500); color:var(--zinc-950); }
        .btn.primary:hover { background:color-mix(in oklab, var(--ember-500) 90%, transparent); }
        .btn.danger { border-color:transparent; background:#dc2626; color:#fff; }
        .btn.danger:hover { background:var(--red); }
        .btn.ghost { border-color:transparent; background:transparent; color:var(--zinc-400); box-shadow:none; }
        .btn.ghost:hover { background:rgba(255,255,255,.15); color:#fff; }
        .btn:focus-visible, .input:focus, .nav-item:focus-visible { outline:none; box-shadow:0 0 0 2px var(--zinc-950), 0 0 0 4px var(--ember-500); }
        .input {
            display:block; width:100%; height:40px; padding:0 12px; border:1px solid rgba(255,255,255,.1); border-radius:8px; background:rgba(255,255,255,.1);
            color:var(--zinc-200); font-size:14px; box-shadow:0 1px 2px rgba(0,0,0,.05);
        }
        .input::placeholder { color:var(--zinc-400); }
        .input-wrap { position:relative; }
        .input-wrap .icon { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--zinc-400); pointer-events:none; }
        .input-wrap .input { padding-left:34px; height:32px; font-size:13px; }
        .field { display:grid; gap:8px; }
        .label { font-size:14px; font-weight:500; color:#fff; line-height:1.25; }
        .description { font-size:13px; color:rgba(255,255,255,.6); line-height:1.5; }
        .error { font-size:13px; font-weight:500; color:var(--red-text); }
        .checkbox { display:flex; align-items:center; gap:10px; font-size:14px; color:var(--zinc-200); cursor:pointer; }
        .checkbox input { width:16px; height:16px; accent-color:var(--ember-500); }
        kbd { display:inline-block; min-width:18px; padding:0 4px; border:1px solid var(--line); border-bottom-width:2px; border-radius:4px; font-family:var(--mono); font-size:11px; text-align:center; color:var(--zinc-400); }
        .segmented { display:flex; flex-wrap:wrap; gap:2px; padding:2px; border-radius:8px; background:rgba(255,255,255,.05); }
        .segmented button { flex:1; height:26px; padding:0 8px; border:0; border-radius:6px; background:none; font-size:12px; font-weight:500; color:var(--zinc-400); cursor:pointer; white-space:nowrap; }
        .segmented button:hover { color:#fff; }
        .segmented button.on { background:var(--zinc-700); color:#fff; box-shadow:0 1px 2px rgba(0,0,0,.2); }
        .segmented button.bad { color:var(--red-text); }

        /* ---------- tables and lists ---------- */
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; }
        th { padding:12px; text-align:left; font-size:14px; font-weight:500; color:#fff; white-space:nowrap; border-bottom:1px solid rgba(255,255,255,.2); }
        td { padding:12px; font-size:14px; color:var(--zinc-300); white-space:nowrap; border-bottom:1px solid rgba(255,255,255,.1); }
        tr:last-child td { border-bottom:0; }
        th:first-child, td:first-child { padding-left:20px; } th:last-child, td:last-child { padding-right:20px; }
        .end { text-align:right; }
        .list { display:flex; flex-direction:column; }
        .item { display:flex; align-items:center; gap:12px; padding:10px 0; border-bottom:1px solid var(--line-soft); min-width:0; }
        .item:first-child { padding-top:0; } .item:last-child { border-bottom:0; padding-bottom:0; }
        .item.clickable { cursor:pointer; } .item.clickable:hover .item-title { color:var(--ember-400); }
        .item-main { flex:1; min-width:0; }
        .item-title { font-size:14px; color:var(--zinc-100); }
        .item-sub { font-family:var(--mono); font-size:12px; color:var(--zinc-500); }
        .item-end { flex-shrink:0; font-family:var(--mono); font-size:12px; color:var(--zinc-400); text-align:right; }
        .none { font-size:14px; color:rgba(255,255,255,.5); }

        /* ---------- empty state ---------- */
        .forge-grid {
            position:absolute; inset:0; z-index:-1; color:#fff;
            background-image:linear-gradient(to right, color-mix(in oklab, currentColor 7%, transparent) 1px, transparent 1px), linear-gradient(to bottom, color-mix(in oklab, currentColor 7%, transparent) 1px, transparent 1px);
            background-size:24px 24px; -webkit-mask-image:radial-gradient(ellipse at top, black 20%, transparent 75%); mask-image:radial-gradient(ellipse at top, black 20%, transparent 75%);
        }
        .empty { position:relative; isolation:isolate; overflow:hidden; display:flex; flex-direction:column; align-items:center; gap:12px; padding:48px 24px; text-align:center; }
        .empty .tile-icon { width:48px; height:48px; border-radius:12px; margin:0; }
        .empty p { margin:0; max-width:28rem; color:rgba(255,255,255,.7); }

        /* ---------- sign-in pages ---------- */
        .auth { position:relative; isolation:isolate; display:flex; flex:1; min-height:100svh; flex-direction:column; align-items:center; justify-content:center; gap:24px; overflow:hidden; padding:40px 24px; }
        .auth .ember-glow { position:absolute; bottom:0; left:50%; z-index:-1; width:48rem; height:20rem; transform:translate(-50%, 50%); border-radius:999px; background:color-mix(in oklab, var(--ember-500) 20%, transparent); filter:blur(64px); }
        .auth-inner { display:flex; width:100%; max-width:24rem; flex-direction:column; gap:24px; }
        .auth-brand { display:flex; flex-direction:column; align-items:center; gap:12px; color:#fff; font-size:18px; font-weight:600; letter-spacing:-.01em; }
        .auth-brand svg { height:48px; width:auto; filter:drop-shadow(0 0 24px rgb(236 92 14 / .45)); }
        .auth-card { display:flex; flex-direction:column; gap:24px; padding:32px; border:1px solid var(--line); border-radius:16px; background:rgba(26,22,20,.8); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); box-shadow:0 20px 25px -5px rgba(0,0,0,.4); }
        .auth-head { text-align:center; }
        .auth-head p { margin:6px 0 0; color:rgba(255,255,255,.7); font-size:14px; }
        .stack { display:flex; flex-direction:column; gap:20px; }
        .btn.block { width:100%; }
        .divider { display:flex; align-items:center; gap:12px; font-size:13px; color:var(--zinc-500); }
        .divider::before, .divider::after { content:''; flex:1; height:1px; background:var(--line); }
        .auth-foot { text-align:center; font-size:14px; color:rgba(255,255,255,.7); }
        .linkish { padding:0; border:0; background:none; color:var(--ember-400); font-weight:500; cursor:pointer; }
        .linkish:hover { text-decoration:underline; text-underline-offset:4px; }

        @media (max-width:1023px) {
            body { display:block; }
            .mobilebar { position:sticky; top:0; z-index:20; display:flex; align-items:center; gap:12px; height:56px; padding:0 16px; border-bottom:1px solid var(--line); background:rgba(15,12,11,.85); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); }
            .sidebar { position:fixed; left:0; top:0; transform:translateX(-100%); transition:transform .2s ease; background:rgba(15,12,11,.97); }
            body.nav-open .sidebar { transform:none; }
            body.nav-open .scrim { display:block; position:fixed; inset:0; z-index:25; background:rgba(0,0,0,.5); }
            .main { padding:24px 16px; }
        }
        @media (max-width:900px) { .grid-2 { grid-template-columns:minmax(0, 1fr); } .grid-4 { grid-template-columns:repeat(2, minmax(0, 1fr)); } }
        @media (max-width:520px) { .grid-4 { grid-template-columns:minmax(0, 1fr); } }
    </style>
