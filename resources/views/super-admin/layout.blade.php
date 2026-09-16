<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Bemuss Booking Super Admin')</title>
    <style>
        :root {
            --bg: #07111f;
            --bg-soft: #0d1b2e;
            --panel: #101d33;
            --panel-2: #16243e;
            --border: rgba(255, 255, 255, 0.08);
            --text: #f5f7fb;
            --muted: #94a3b8;
            --accent: #f4c66a;
            --accent-2: #7dd3fc;
            --danger: #fb7185;
            --success: #34d399;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at top left, rgba(244, 198, 106, 0.14), transparent 30%),
                radial-gradient(circle at top right, rgba(125, 211, 252, 0.10), transparent 26%),
                linear-gradient(180deg, #07111f 0%, #081220 55%, #050b13 100%);
            color: var(--text);
            min-height: 100vh;
        }

        a { color: inherit; text-decoration: none; }
        .shell { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
        .sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            padding: 28px 20px;
            background: rgba(8, 16, 29, 0.92);
            border-right: 1px solid var(--border);
            backdrop-filter: blur(16px);
        }
        .brand {
            display: flex;
            flex-direction: column;
            gap: 6px;
            padding-bottom: 20px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }
        .brand .eyebrow { color: var(--accent); font-size: 12px; letter-spacing: .16em; text-transform: uppercase; }
        .brand .name { font-size: 20px; font-weight: 700; line-height: 1.1; }
        .brand .sub { color: var(--muted); font-size: 13px; }
        .nav { display: grid; gap: 8px; }
        .nav a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            border: 1px solid transparent;
            border-radius: 14px;
            color: var(--muted);
            background: transparent;
        }
        .nav a:hover,
        .nav a.active {
            color: var(--text);
            border-color: var(--border);
            background: rgba(255, 255, 255, 0.04);
        }
        .content { padding: 28px; }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }
        .page-title { font-size: 30px; font-weight: 750; margin: 0; }
        .page-subtitle { color: var(--muted); margin: 8px 0 0; }
        .grid { display: grid; gap: 16px; }
        .cards { grid-template-columns: repeat(12, minmax(0, 1fr)); }
        .card {
            background: linear-gradient(180deg, rgba(22, 36, 62, 0.92), rgba(14, 23, 40, 0.96));
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 18px;
            box-shadow: 0 20px 55px rgba(0, 0, 0, 0.25);
        }
        .metric { grid-column: span 3; }
        .metric .label { color: var(--muted); font-size: 13px; }
        .metric .value { font-size: 30px; font-weight: 750; margin-top: 10px; }
        .metric .hint { color: var(--muted); font-size: 12px; margin-top: 6px; }
        .section { margin-top: 24px; }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }
        .section-title { margin: 0; font-size: 18px; }
        .section-note { margin: 0; color: var(--muted); font-size: 13px; }
        table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
        }
        th, td {
            text-align: left;
            padding: 14px 12px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }
        th { color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: .08em; }
        td { color: var(--text); }
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            background: rgba(255,255,255,0.06);
            border: 1px solid var(--border);
        }
        .badge.active { color: var(--success); }
        .badge.suspended { color: var(--danger); }
        .badge.inactive { color: #fbbf24; }
        .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .button, button.button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 10px 14px;
            background: rgba(255,255,255,0.05);
            color: var(--text);
            cursor: pointer;
            font: inherit;
        }
        .button.primary {
            background: linear-gradient(135deg, var(--accent), #f7dca2);
            color: #1a1a1a;
            border-color: transparent;
            font-weight: 700;
        }
        .button.ghost { background: transparent; }
        .button.danger { background: rgba(251, 113, 133, 0.12); color: #fecdd3; }
        .button.success { background: rgba(52, 211, 153, 0.12); color: #bbf7d0; }
        .form-grid { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 16px; }
        .field { grid-column: span 6; }
        .field.full { grid-column: 1 / -1; }
        label { display: block; margin-bottom: 8px; font-size: 13px; color: #dbe4f0; }
        input, select, textarea {
            width: 100%;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.04);
            color: var(--text);
            padding: 12px 14px;
            outline: none;
        }
        textarea { min-height: 108px; resize: vertical; }
        .help { color: var(--muted); font-size: 12px; margin-top: 6px; }
        .error { color: #fca5a5; font-size: 12px; margin-top: 6px; }
        .notice {
            border: 1px solid rgba(52, 211, 153, 0.20);
            background: rgba(52, 211, 153, 0.08);
            color: #bbf7d0;
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 18px;
        }
        .split { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .pre {
            white-space: pre-wrap;
            word-break: break-word;
            background: rgba(0,0,0,0.24);
            border: 1px solid var(--border);
            padding: 16px;
            border-radius: 16px;
            color: #dbe4f0;
        }
        .muted { color: var(--muted); }
        .stack { display: grid; gap: 12px; }
        @media (max-width: 1100px) {
            .shell { grid-template-columns: 1fr; }
            .sidebar { position: relative; height: auto; }
            .metric { grid-column: span 6; }
        }
        @media (max-width: 720px) {
            .content { padding: 18px; }
            .topbar, .section-header, .split { grid-template-columns: 1fr; flex-direction: column; align-items: flex-start; }
            .metric { grid-column: 1 / -1; }
            .field { grid-column: 1 / -1; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">
                <div class="eyebrow">Bemuss Booking</div>
                <div class="name">Super Admin</div>
                <div class="sub">Panel interno de administración global</div>
            </div>

            <nav class="nav">
                <a href="{{ route('super-admin.dashboard') }}" class="{{ request()->routeIs('super-admin.dashboard') ? 'active' : '' }}">
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('super-admin.businesses.index') }}" class="{{ request()->routeIs('super-admin.businesses.*') ? 'active' : '' }}">
                    <span>Negocios</span>
                </a>
                <a href="{{ route('super-admin.businesses.create') }}" class="{{ request()->routeIs('super-admin.businesses.create') ? 'active' : '' }}">
                    <span>Crear negocio</span>
                </a>
            </nav>

            <div class="section" style="margin-top: 24px;">
                <div class="muted" style="font-size: 12px; text-transform: uppercase; letter-spacing: .12em;">Acceso</div>
                <div style="margin-top: 10px; color: #dbe4f0; font-size: 14px;">
                    Requiere sesión autenticada y rol global <strong>admin</strong>.
                </div>
            </div>
        </aside>

        <main class="content">
            <div class="topbar">
                <div>
                    <h1 class="page-title">@yield('page_title')</h1>
                    <p class="page-subtitle">@yield('page_subtitle')</p>
                </div>
                <div class="actions">
                    @yield('page_actions')
                </div>
            </div>

            @if (session('status'))
                <div class="notice">{{ session('status') }}</div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
