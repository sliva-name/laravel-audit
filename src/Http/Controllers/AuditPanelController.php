<?php

declare(strict_types=1);

namespace LaravelAudit\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LaravelAudit\Audit\AuditOptions;
use LaravelAudit\Audit\AuditProgressTracker;
use LaravelAudit\Audit\AuditRunDispatcher;
use LaravelAudit\Audit\AuditRunExecutor;
use LaravelAudit\Repositories\AuditReportRepository;

final class AuditPanelController extends Controller
{
    public function __construct(
        private readonly AuditReportRepository $reports,
        private readonly AuditProgressTracker $runs,
        private readonly AuditRunExecutor $executor,
        private readonly AuditRunDispatcher $dispatcher,
    ) {}

    public function dashboard(): View
    {
        $reports = $this->reports->latest(10);

        return view('laravel-audit::panel.dashboard', [
            'reports' => $reports,
            'menu' => $this->menu('dashboard'),
        ]);
    }

    public function index(): View
    {
        return view('laravel-audit::panel.reports.index', [
            'reports' => $this->reports->latest(100),
            'menu' => $this->menu('reports'),
        ]);
    }

    public function show(string $uuid): View
    {
        $record = $this->reports->findByUuid($uuid);

        abort_if($record === null, 404);

        return view('laravel-audit::panel.reports.show', [
            'record' => $record,
            'report' => $record->payload,
            'menu' => $this->menu('reports'),
        ]);
    }

    public function create(): View
    {
        return view('laravel-audit::panel.reports.create', [
            'menu' => $this->menu('run'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $options = $this->optionsFromRequest($request);
        $runUuid = $this->runs->create($options);
        $this->dispatcher->dispatch($runUuid);

        return redirect()
            ->route('laravel-audit.runs.show', $runUuid)
            ->with('status', 'Audit started in background.');
    }

    public function runShow(string $uuid): View
    {
        $run = $this->runs->get($uuid);

        abort_if($run === null, 404);

        return view('laravel-audit::panel.runs.show', [
            'run' => $run,
            'runUuid' => $uuid,
            'statusUrl' => route('laravel-audit.runs.status', $uuid),
            'kickUrl' => route('laravel-audit.runs.kick', $uuid),
            'executeUrl' => route('laravel-audit.runs.execute', $uuid),
            'menu' => $this->menu('run'),
        ]);
    }

    public function runStatus(string $uuid): JsonResponse
    {
        $run = $this->runs->get($uuid);

        abort_if($run === null, 404);

        return response()->json($this->runPayload($run));
    }

    public function runKick(string $uuid): JsonResponse
    {
        $run = $this->runs->get($uuid);

        abort_if($run === null, 404);

        if (($run['status'] ?? 'queued') === 'queued') {
            $this->dispatcher->dispatch($uuid);
        }

        $run = $this->runs->get($uuid) ?? $run;

        return response()->json($this->runPayload($run));
    }

    public function runExecute(string $uuid): JsonResponse
    {
        $run = $this->runs->get($uuid);

        abort_if($run === null, 404);

        if (($run['status'] ?? 'queued') === 'queued') {
            $this->executor->execute($uuid);
            $run = $this->runs->get($uuid) ?? $run;
        }

        return response()->json($this->runPayload($run));
    }

    /**
     * @param  array<string, mixed>  $run
     * @return array<string, mixed>
     */
    private function runPayload(array $run): array
    {
        $reportUuid = $run['report_uuid'] ?? null;

        return [
            'status' => $run['status'] ?? 'queued',
            'progress' => (int) ($run['progress'] ?? 0),
            'message' => (string) ($run['message'] ?? ''),
            'log' => is_array($run['log'] ?? null) ? $run['log'] : [],
            'error' => $run['error'] ?? null,
            'report_url' => is_string($reportUuid) && $reportUuid !== ''
                ? route('laravel-audit.reports.show', $reportUuid)
                : null,
        ];
    }

    private function optionsFromRequest(Request $request): AuditOptions
    {
        return new AuditOptions(
            categories: $this->categories($request),
            noTools: $request->boolean('no_tools'),
            patterns: $request->boolean('patterns'),
            llm: $request->boolean('llm'),
        );
    }

    /**
     * @return list<string>
     */
    private function categories(Request $request): array
    {
        $only = $request->string('only')->trim()->toString();

        if ($only === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $only))));
    }

    /**
     * @return list<array{label: string, route: string, active: bool}>
     */
    private function menu(string $active): array
    {
        return [
            [
                'label' => 'Overview',
                'route' => route('laravel-audit.dashboard'),
                'active' => $active === 'dashboard',
            ],
            [
                'label' => 'All Reports',
                'route' => route('laravel-audit.reports.index'),
                'active' => $active === 'reports',
            ],
            [
                'label' => 'Run Analysis',
                'route' => route('laravel-audit.reports.create'),
                'active' => $active === 'run',
            ],
        ];
    }
}
