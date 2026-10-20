<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\AnnotationService;
use App\Services\OpenSearch\AgentRepository;
use App\Services\OpenSearch\AlertRepository;
use App\Support\AlertStatus;
use App\Support\Severity;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class OverviewController extends Controller
{
    public function __construct(
        private AlertRepository $alerts,
        private AgentRepository $agents,
        private AnnotationService $annotations,
    ) {}

    public function index(Request $request): View
    {
        $range = $this->resolveRange($request->query('range', '24h'));
        $ranges = config('opensearch.time_ranges');

        $data = null;
        $error = null;

        try {
            $data = $this->alerts->overview($ranges[$range]['from'], $ranges[$range]['interval']);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        $agentStatus = [];
        try {
            $agentStatus = $this->agents->latestStatus();
        } catch (RuntimeException) {
            // Agent status is supplementary; the dashboard still renders without it.
        }

        $triage = $this->annotations->statusBreakdown();

        // Latest high-severity activity and latest authentication failures.
        $latestCritical = [];
        $latestAuthFailures = [];

        if ($data !== null) {
            try {
                $latestCritical = $this->alerts->latestEvents(['level_min' => 10], 5);
            } catch (RuntimeException) {
                // Supplementary card; the dashboard still renders.
            }

            try {
                $latestAuthFailures = $this->alerts->latestEvents(['group' => 'authentication_failed'], 5);
            } catch (RuntimeException) {
                // Same here.
            }
        }

        return view('overview.index', [
            'range' => $range,
            'ranges' => $ranges,
            'data' => $data,
            'error' => $error,
            'agentStatus' => $agentStatus,
            'bands' => Severity::bands(),
            'triage' => $triage,
            'statuses' => AlertStatus::all(),
            'openReports' => Ticket::open()->count(),
            'openIncidents' => Ticket::open()->where('type', 'incident')->count(),
            'latestCritical' => $latestCritical,
            'latestAuthFailures' => $latestAuthFailures,
        ]);
    }

    public function data(Request $request)
    {
        $range = $this->resolveRange($request->query('range', '24h'));
        $ranges = config('opensearch.time_ranges');

        $data = $this->alerts->overview($ranges[$range]['from'], $ranges[$range]['interval']);

        return response()->json($data);
    }

    private function resolveRange(?string $range): string
    {
        $ranges = config('opensearch.time_ranges');

        return isset($ranges[$range]) ? $range : '24h';
    }
}
