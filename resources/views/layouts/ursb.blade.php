<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'URSB') · URSB Platform</title>
<style>
    :root {
        --bg:#0f1117; --panel:#171a23; --panel2:#12151d; --line:#262b38; --text:#e6e9f0;
        --muted:#8b93a7; --accent:#00a6a6; --primary:#2f75b5; --gold:#d6a14a; --danger:#c0504a;
    }
    * { box-sizing: border-box; }
    body { margin:0; background:var(--bg); color:var(--text);
        font:15px/1.55 ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,sans-serif; }
    a { color:var(--primary); text-decoration:none; }
    a:hover { text-decoration:underline; }
    nav.top { display:flex; align-items:center; gap:22px; padding:0 28px; height:58px;
        background:linear-gradient(180deg,#11141c,#0f1117); border-bottom:1px solid var(--line); position:sticky; top:0; z-index:10; }
    nav.top .brand { font-weight:700; letter-spacing:.3px; }
    nav.top .brand span { color:var(--accent); }
    nav.top a.link { color:var(--muted); font-size:14px; }
    nav.top a.link.active, nav.top a.link:hover { color:var(--text); text-decoration:none; }
    nav.top .spacer { flex:1; }
    nav.top .who { color:var(--muted); font-size:13px; }
    .btn { display:inline-block; background:var(--accent); color:#08121a; border:none; border-radius:8px;
        padding:8px 14px; font-size:13.5px; font-weight:600; cursor:pointer; }
    .btn:hover { filter:brightness(1.08); text-decoration:none; }
    .btn.ghost { background:transparent; border:1px solid var(--line); color:var(--text); }
    .btn.sm { padding:5px 10px; font-size:12.5px; }
    .wrap { max-width:1100px; margin:0 auto; padding:26px 28px 72px; }
    h1.page { font-size:22px; margin:0 0 4px; }
    .sub { color:var(--muted); font-size:13px; margin:0 0 22px; }
    section { margin-bottom:30px; }
    h2.sec { font-size:12px; text-transform:uppercase; letter-spacing:.8px; color:var(--muted);
        border-bottom:1px solid var(--line); padding-bottom:8px; margin:0 0 14px;
        display:flex; align-items:center; justify-content:space-between; }
    .panel { background:var(--panel); border:1px solid var(--line); border-radius:10px; padding:16px; }
    .cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:12px; margin-bottom:26px; }
    .card { background:var(--panel); border:1px solid var(--line); border-radius:10px; padding:14px; }
    .card .n { font-size:25px; font-weight:650; }
    .card .l { color:var(--muted); font-size:11px; text-transform:uppercase; letter-spacing:.6px; }
    table { width:100%; border-collapse:collapse; font-size:14px; }
    th,td { text-align:left; padding:9px 10px; border-bottom:1px solid var(--line); }
    th { color:var(--muted); font-weight:500; font-size:11px; text-transform:uppercase; letter-spacing:.5px; }
    tr:last-child td { border-bottom:none; }
    .pill { font-size:11px; padding:3px 9px; border-radius:999px; border:1px solid var(--line); color:var(--muted); background:var(--panel2); }
    .pill.on { color:#08121a; background:var(--accent); border-color:var(--accent); font-weight:600; }
    .pill.warn { color:var(--gold); border-color:var(--gold); }
    code { font-family:ui-monospace,monospace; color:var(--accent); }
    .empty { color:var(--muted); font-style:italic; }
    .flash { background:rgba(0,166,166,.12); border:1px solid var(--accent); color:#bdeaea;
        padding:10px 14px; border-radius:8px; margin-bottom:18px; font-size:13.5px; }
    .flash.err { background:rgba(192,80,74,.12); border-color:var(--danger); color:#e9bdbd; }
    form.inline { display:inline; }
    label { display:block; font-size:12px; color:var(--muted); margin:12px 0 5px; text-transform:uppercase; letter-spacing:.5px; }
    input,select,textarea { width:100%; background:var(--panel2); border:1px solid var(--line); color:var(--text);
        border-radius:8px; padding:9px 11px; font-size:14px; }
    input:focus,select:focus,textarea:focus { outline:none; border-color:var(--accent); }
    .row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .err-text { color:#e9bdbd; font-size:12px; margin-top:5px; }
</style>
</head>
<body>
<nav class="top">
    <div class="brand">URS<span>B</span></div>
    @auth
        @php($u = auth()->user())
        <a class="link {{ request()->routeIs('portfolio.index') || request()->routeIs('portfolio.show') ? 'active' : '' }}" href="{{ route('portfolio.index') }}">Portfolio</a>
        <a class="link {{ request()->routeIs('portfolio.dashboard') ? 'active' : '' }}" href="{{ route('portfolio.dashboard') }}">Dashboard</a>
        <a class="link {{ request()->routeIs('ursb.*') ? 'active' : '' }}" href="{{ route('ursb.dashboard') }}">Graph</a>
        @if ($u->role === 'admin')
            <a class="link {{ request()->routeIs('admin.*') ? 'active' : '' }}" href="{{ route('admin.acl.index') }}">Admin</a>
        @endif
        <div class="spacer"></div>
        <span class="who">{{ $u->name }} · {{ $u->role }}</span>
        <form class="inline" method="POST" action="{{ route('logout') }}">@csrf
            <button class="btn ghost sm" type="submit">Logout</button>
        </form>
    @endauth
</nav>
<div class="wrap">
    @if (session('status'))<div class="flash">{{ session('status') }}</div>@endif
    @if (session('error'))<div class="flash err">{{ session('error') }}</div>@endif
    @yield('content')
</div>
</body>
</html>
