@extends('laravel-audit::panel.layout')

@section('title', 'Jobs · Laravel Audit')

@section('content')
    <h1 class="page-title">Jobs</h1>
    <p class="page-subtitle">Background audit runs and their current status.</p>

    <div class="card">
        @if ($runs === [])
            <p class="muted">No jobs yet. <a class="link" href="{{ route('laravel-audit.reports.create') }}">Start an analysis</a>.</p>
        @else
            <table>
                <thead>
                <tr>
                    <th>Status</th>
                    <th>Progress</th>
                    <th>Message</th>
                    <th>Options</th>
                    <th>Started</th>
                    <th>Updated</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @foreach ($runs as $run)
                    @php
                        $status = (string) ($run['status'] ?? 'queued');
                        $options = is_array($run['options'] ?? null) ? $run['options'] : [];
                        $flags = collect([
                            ! empty($options['no_tools']) ? 'no tools' : null,
                            ! empty($options['patterns']) ? 'patterns' : null,
                            ! empty($options['llm']) ? 'llm' : null,
                        ])->filter()->values();
                    @endphp
                    <tr>
                        <td>
                            <span @class([
                                'badge',
                                'badge-queued' => $status === 'queued',
                                'badge-running' => $status === 'running',
                                'badge-completed' => $status === 'completed',
                                'badge-failed' => $status === 'failed',
                            ])>{{ strtoupper($status) }}</span>
                        </td>
                        <td>{{ (int) ($run['progress'] ?? 0) }}%</td>
                        <td>{{ $run['message'] ?? '—' }}</td>
                        <td class="muted">
                            @if ($flags->isEmpty())
                                default
                            @else
                                {{ $flags->implode(', ') }}
                            @endif
                        </td>
                        <td class="muted">@include('laravel-audit::panel.partials.time', ['value' => $run['created_at'] ?? null])</td>
                        <td class="muted">@include('laravel-audit::panel.partials.time', ['value' => $run['updated_at'] ?? null])</td>
                        <td>
                            @if ($status === 'completed' && ! empty($run['report_uuid']))
                                <a class="link" href="{{ route('laravel-audit.reports.show', $run['report_uuid']) }}">Report</a>
                            @elseif (in_array($status, ['queued', 'running'], true))
                                <a class="link" href="{{ route('laravel-audit.runs.show', $run['uuid']) }}">Watch</a>
                            @else
                                <a class="link" href="{{ route('laravel-audit.runs.show', $run['uuid']) }}">Details</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
