@extends('laravel-audit::panel.layout')

@section('title', 'Running Audit · Laravel Audit')

@section('content')
    <h1 class="page-title">Running audit</h1>
    <p class="page-subtitle" id="run-message">{{ $run['message'] ?? 'Starting...' }}</p>

    <div class="card">
        <div class="progress-wrap">
            <div class="progress-bar">
                <div class="progress-fill" id="run-progress" style="width: {{ (int) ($run['progress'] ?? 0) }}%;"></div>
            </div>
            <div class="progress-meta">
                <span id="run-percent">{{ (int) ($run['progress'] ?? 0) }}%</span>
                <span class="muted" id="run-status">{{ strtoupper((string) ($run['status'] ?? 'queued')) }}</span>
            </div>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">Activity log</h2>
        <div class="run-log" id="run-log">
            @foreach (($run['log'] ?? []) as $line)
                <div class="run-log-line">{{ $line }}</div>
            @endforeach
        </div>
    </div>

    <div class="card hidden" id="run-error">
        <strong>Audit failed.</strong>
        <div class="muted" id="run-error-text"></div>
        <div style="margin-top:16px;">
            <a class="btn" href="{{ route('laravel-audit.reports.create') }}">Try again</a>
        </div>
    </div>

    <style>
        .progress-wrap { display: grid; gap: 10px; }
        .progress-bar {
            height: 12px;
            background: var(--panel-hover);
            border-radius: 999px;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #5b8cff);
            transition: width 0.35s ease;
        }
        .progress-meta {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
        }
        .run-log {
            max-height: 420px;
            overflow: auto;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 13px;
        }
        .run-log-line {
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
        }
        .run-log-line:last-child { border-bottom: 0; }
        .hidden { display: none; }
    </style>

    <script>
        (function () {
            const statusUrl = @json($statusUrl);
            const executeUrl = @json($executeUrl);
            const csrfToken = @json(csrf_token());
            const progressEl = document.getElementById('run-progress');
            const percentEl = document.getElementById('run-percent');
            const messageEl = document.getElementById('run-message');
            const statusEl = document.getElementById('run-status');
            const logEl = document.getElementById('run-log');
            const errorBox = document.getElementById('run-error');
            const errorText = document.getElementById('run-error-text');
            let executionStarted = false;
            let polling = true;

            function renderLog(lines) {
                logEl.innerHTML = lines.map(function (line) {
                    return '<div class="run-log-line">' + line.replace(/</g, '&lt;') + '</div>';
                }).join('');
                logEl.scrollTop = logEl.scrollHeight;
            }

            function applyStatus(data) {
                progressEl.style.width = data.progress + '%';
                percentEl.textContent = data.progress + '%';
                messageEl.textContent = data.message;
                statusEl.textContent = String(data.status).toUpperCase();
                renderLog(data.log || []);

                if (data.status === 'completed' && data.report_url) {
                    polling = false;
                    window.location.href = data.report_url;
                    return true;
                }

                if (data.status === 'failed') {
                    polling = false;
                    errorText.textContent = data.error || 'Unknown error';
                    errorBox.classList.remove('hidden');
                    return true;
                }

                return false;
            }

            async function poll() {
                if (!polling) {
                    return;
                }

                try {
                    const response = await fetch(statusUrl, {
                        headers: { 'Accept': 'application/json' },
                    });

                    if (response.ok) {
                        const data = await response.json();
                        if (applyStatus(data)) {
                            return;
                        }
                    }
                } catch (error) {
                    messageEl.textContent = 'Connecting to audit worker...';
                }

                window.setTimeout(poll, 1000);
            }

            async function executeAudit() {
                if (executionStarted) {
                    return;
                }

                executionStarted = true;
                messageEl.textContent = 'Starting audit...';

                try {
                    const response = await fetch(executeUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (response.ok) {
                        const data = await response.json();
                        applyStatus(data);
                    } else {
                        throw new Error('Unable to start audit.');
                    }
                } catch (error) {
                    messageEl.textContent = 'Failed to start audit. Retrying status checks...';
                }
            }

            poll();
            executeAudit();
        })();
    </script>
@endsection
