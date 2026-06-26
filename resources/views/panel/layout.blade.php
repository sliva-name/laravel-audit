<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Laravel Audit')</title>
    <style>
        :root {
            color-scheme: dark;
            --bg: #0f1419;
            --panel: #151b23;
            --panel-hover: #1c2430;
            --border: #2a3441;
            --text: #e7ecf3;
            --muted: #93a1b3;
            --accent: #5b8cff;
            --accent-soft: rgba(91, 140, 255, 0.12);
            --critical: #ff6b6b;
            --error: #ff8787;
            --warning: #feca57;
            --info: #74c0fc;
            --success: #51cf66;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        .layout {
            display: grid;
            grid-template-columns: 240px 1fr;
            min-height: 100vh;
        }

        .sidebar {
            background: var(--panel);
            border-right: 1px solid var(--border);
            padding: 24px 16px;
        }

        .brand {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .brand-sub {
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 28px;
        }

        .menu a {
            display: block;
            padding: 10px 12px;
            border-radius: 8px;
            color: var(--text);
            text-decoration: none;
            margin-bottom: 4px;
        }

        .menu a.active,
        .menu a:hover {
            background: var(--panel-hover);
        }

        .menu a.active {
            background: var(--accent-soft);
            color: #dbeafe;
        }

        .content {
            padding: 28px 32px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 8px;
        }

        .page-subtitle {
            color: var(--muted);
            margin: 0 0 24px;
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
        }

        .metric {
            background: var(--panel-hover);
            border-radius: 10px;
            padding: 16px;
        }

        .metric-label {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .metric-value {
            font-size: 28px;
            font-weight: 700;
            margin-top: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            text-align: left;
            padding: 12px 10px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }

        th {
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        a.link {
            color: var(--accent);
            text-decoration: none;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-critical { background: rgba(255, 107, 107, 0.15); color: var(--critical); }
        .badge-error { background: rgba(255, 135, 135, 0.15); color: var(--error); }
        .badge-warning { background: rgba(254, 202, 87, 0.15); color: var(--warning); }
        .badge-info { background: rgba(116, 192, 252, 0.15); color: var(--info); }
        .badge-heuristic { background: rgba(91, 140, 255, 0.15); color: #9ec5ff; }
        .badge-confirmed { background: rgba(81, 207, 102, 0.15); color: var(--success); }
        .badge-queued { background: rgba(116, 192, 252, 0.15); color: var(--info); }
        .badge-running { background: rgba(91, 140, 255, 0.15); color: #9ec5ff; }
        .badge-completed { background: rgba(81, 207, 102, 0.15); color: var(--success); }
        .badge-failed { background: rgba(255, 107, 107, 0.15); color: var(--critical); }

        .btn {
            display: inline-block;
            background: var(--accent);
            color: white;
            border: 0;
            border-radius: 8px;
            padding: 10px 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }

        .status {
            background: rgba(81, 207, 102, 0.12);
            border: 1px solid rgba(81, 207, 102, 0.35);
            color: var(--success);
            padding: 12px 14px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .issue, .pattern {
            padding: 14px 0;
            border-bottom: 1px solid var(--border);
        }

        .issue:last-child, .pattern:last-child { border-bottom: 0; }

        .muted { color: var(--muted); }

        label { display: block; margin-bottom: 12px; }

        input[type="checkbox"] { margin-right: 8px; }

        .form-row { margin-bottom: 18px; }
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">Laravel Audit</div>
        <div class="brand-sub">Code analysis panel</div>
        <nav class="menu">
            @foreach ($menu as $item)
                <a href="{{ $item['route'] }}" @class(['active' => $item['active']])>{{ $item['label'] }}</a>
            @endforeach
        </nav>
    </aside>
    <main class="content">
        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif
        @yield('content')
    </main>
</div>
</body>
</html>
