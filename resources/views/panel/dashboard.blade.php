@extends('laravel-audit::panel.layout')

@section('title', 'Overview · Laravel Audit')

@section('content')
    <h1 class="page-title">Overview</h1>
    <p class="page-subtitle">Recent audit reports and severity summary.</p>

    @php
        $latest = $reports[0] ?? null;
    @endphp

    @if ($latest)
        <div class="card">
            <div class="grid">
                <div class="metric">
                    <div class="metric-label">Latest Critical</div>
                    <div class="metric-value">{{ $latest->critical_count }}</div>
                </div>
                <div class="metric">
                    <div class="metric-label">Latest Errors</div>
                    <div class="metric-value">{{ $latest->error_count }}</div>
                </div>
                <div class="metric">
                    <div class="metric-label">Latest Warnings</div>
                    <div class="metric-value">{{ $latest->warning_count }}</div>
                </div>
                <div class="metric">
                    <div class="metric-label">Patterns</div>
                    <div class="metric-value">{{ $latest->pattern_count }}</div>
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <h2 style="margin-top:0;">Recent reports</h2>
        @if ($reports === [])
            <p class="muted">No reports yet. <a class="link" href="{{ route('laravel-audit.reports.create') }}">Run your first analysis</a>.</p>
        @else
            <table>
                <thead>
                <tr>
                    <th>Date</th>
                    <th>Issues</th>
                    <th>Severity</th>
                    <th>Duration</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($reports as $report)
                    <tr>
                        <td>{{ $report->created_at?->format('Y-m-d H:i') }}</td>
                        <td>{{ $report->issues_count }}</td>
                        <td>
                            @if ($report->critical_count)<span class="badge badge-critical">{{ $report->critical_count }} critical</span>@endif
                            @if ($report->error_count)<span class="badge badge-error">{{ $report->error_count }} error</span>@endif
                            @if ($report->warning_count)<span class="badge badge-warning">{{ $report->warning_count }} warning</span>@endif
                            @if ($report->info_count)<span class="badge badge-info">{{ $report->info_count }} info</span>@endif
                        </td>
                        <td>{{ number_format((float) $report->duration_seconds, 2) }}s</td>
                        <td><a class="link" href="{{ route('laravel-audit.reports.show', $report->uuid) }}">Open</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
