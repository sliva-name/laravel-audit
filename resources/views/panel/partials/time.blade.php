@php
    $formatted = \LaravelAudit\Support\PanelTime::format($value ?? null);
@endphp

@if ($formatted === '—')
    —
@else
    <time datetime="{{ \LaravelAudit\Support\PanelTime::datetime($value) }}" title="{{ \LaravelAudit\Support\PanelTime::tooltip($value) }}">{{ $formatted }}</time>
@endif
