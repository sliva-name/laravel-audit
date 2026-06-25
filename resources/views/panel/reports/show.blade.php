@extends('laravel-audit::panel.layout')

@section('title', 'Report · Laravel Audit')

@section('content')
    <h1 class="page-title">Report</h1>
    <p class="page-subtitle">{{ $record->created_at?->format('Y-m-d H:i:s') }} · {{ number_format((float) $record->duration_seconds, 2) }}s</p>

    <div class="card">
        <div class="grid">
            <div class="metric"><div class="metric-label">Critical</div><div class="metric-value">{{ $record->critical_count }}</div></div>
            <div class="metric"><div class="metric-label">Error</div><div class="metric-value">{{ $record->error_count }}</div></div>
            <div class="metric"><div class="metric-label">Warning</div><div class="metric-value">{{ $record->warning_count }}</div></div>
            <div class="metric"><div class="metric-label">Info</div><div class="metric-value">{{ $record->info_count }}</div></div>
        </div>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">Issues</h2>
        @forelse ($report['issues'] ?? [] as $issue)
            <div class="issue">
                <div>
                    <span class="badge badge-{{ $issue['severity'] }}">{{ strtoupper($issue['severity']) }}</span>
                    <strong>{{ $issue['title'] }}</strong>
                    <span class="muted">[{{ $issue['ruleId'] }}]</span>
                </div>
                <div class="muted">{{ $issue['location']['file'] ?? '' }}:{{ $issue['location']['line'] ?? '' }}</div>
                <div>{{ $issue['message'] }}</div>
                @if (! empty($issue['recommendation']))
                    <div class="muted">Fix: {{ $issue['recommendation'] }}</div>
                @endif
            </div>
        @empty
            <p class="muted">No issues found.</p>
        @endforelse
    </div>

    @if (! empty($report['patternSuggestions']))
        <div class="card">
            <h2 style="margin-top:0;">Pattern suggestions</h2>
            @foreach ($report['patternSuggestions'] as $pattern)
                <div class="pattern">
                    <div>
                        <span @class([
                            'badge',
                            'badge-confirmed' => ($pattern['source'] ?? '') === 'confirmed',
                            'badge-heuristic' => ($pattern['source'] ?? 'heuristic') === 'heuristic',
                        ])>{{ strtoupper($pattern['source'] ?? 'heuristic') }}</span>
                        <strong>{{ $pattern['title'] ?? $pattern['pattern'] }}</strong>
                        <span class="muted">({{ number_format(($pattern['confidence'] ?? 0) * 100, 0) }}%)</span>
                    </div>
                    <div class="muted">{{ $pattern['location']['class'] ?? '' }}::{{ $pattern['location']['method'] ?? '' }}()</div>
                    <div>{{ $pattern['description'] ?? '' }}</div>
                    @if (! empty($pattern['llmRationale']))
                        <div class="muted">LLM: {{ $pattern['llmRationale'] }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection
