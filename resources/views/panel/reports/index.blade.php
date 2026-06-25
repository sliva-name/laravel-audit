@extends('laravel-audit::panel.layout')

@section('title', 'Reports · Laravel Audit')

@section('content')
    <h1 class="page-title">All Reports</h1>
    <p class="page-subtitle">Browse saved audit runs.</p>

    <div class="card">
        <div style="margin-bottom: 16px;">
            <a class="btn" href="{{ route('laravel-audit.reports.create') }}">Run new analysis</a>
        </div>

        @if ($reports === [])
            <p class="muted">No reports stored yet.</p>
        @else
            <table>
                <thead>
                <tr>
                    <th>Date</th>
                    <th>UUID</th>
                    <th>Issues</th>
                    <th>Patterns</th>
                    <th>Duration</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($reports as $report)
                    <tr>
                        <td>{{ $report->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td class="muted">{{ $report->uuid }}</td>
                        <td>{{ $report->issues_count }}</td>
                        <td>{{ $report->pattern_count }}</td>
                        <td>{{ number_format((float) $report->duration_seconds, 2) }}s</td>
                        <td><a class="link" href="{{ route('laravel-audit.reports.show', $report->uuid) }}">View</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
