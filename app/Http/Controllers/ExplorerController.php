<?php

namespace App\Http\Controllers;

use App\Models\AlertAnnotation;
use App\Models\Label;
use App\Models\User;
use App\Services\AnnotationService;
use App\Services\OpenSearch\AlertRepository;
use App\Support\AlertStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ExplorerController extends Controller
{
    public function __construct(
        private AlertRepository $alerts,
        private AnnotationService $annotations,
    ) {}

    public function index(Request $request): View
    {
        $ranges = config('opensearch.time_ranges');
        $range = isset($ranges[$request->query('range')]) ? $request->query('range') : '24h';

        $filters = [
            'from' => $ranges[$range]['from'],
            'q' => trim((string) $request->query('q', '')),
            'agent' => $request->query('agent'),
            'group' => $request->query('group'),
            'level_min' => $request->query('level_min'),
            'level_max' => $request->query('level_max'),
            'mitre' => $request->query('mitre'),
            'compliance_field' => $request->query('compliance_field'),
            'compliance_value' => $request->query('compliance_value'),
            'status' => $request->query('status'),
            'label' => $request->query('label'),
        ];

        // Sorting is optional: no `sort` query param means the default newest
        // first order. `dir` only matters when a column is chosen.
        $sort = (string) $request->query('sort', '');
        $sort = array_key_exists($sort, AlertRepository::SORTS) ? $sort : '';
        $dir = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        if ($sort !== '') {
            $filters['sort'] = $sort;
            $filters['dir'] = $dir;
        }

        // Deep link to a single alert (used by the overview and report pages).
        // Constraining by the alert's own id guarantees it is on page 1.
        $focusedUid = trim((string) $request->query('alert', ''));
        if ($focusedUid !== '') {
            $filters['uid_include'] = [$focusedUid];
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;

        $result = null;
        $options = ['agents' => [], 'groups' => [], 'mitre' => []];
        $error = null;

        try {
            // Handling state lives in Postgres, so resolve matching alert UIDs
            // first and constrain the OpenSearch query with them. This keeps
            // totals and pagination correct instead of filtering one page.
            $constraint = $this->annotations->uidConstraint(
                $filters['status'],
                $filters['label'] !== null ? (int) $filters['label'] : null
            );

            $queryFilters = $filters;
            if ($constraint !== null) {
                if (array_key_exists('include', $constraint)) {
                    $queryFilters['uid_include'] = $constraint['include'];
                }
                if (array_key_exists('exclude', $constraint)) {
                    $queryFilters['uid_exclude'] = $constraint['exclude'];
                }
            }

            $result = $this->alerts->searchText($queryFilters, $page, $perPage);
            $options = $this->alerts->filterOptions('now-7d');
            $result['items'] = $this->annotations->attach($result['items']);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        return view('explorer.index', [
            'range' => $range,
            'ranges' => $ranges,
            'filters' => $filters,
            'result' => $result,
            'options' => $options,
            'error' => $error,
            'sort' => $sort,
            'dir' => $dir,
            'sortColumns' => AlertRepository::SORTS,
            'labels' => Label::orderBy('name')->get(['id', 'name', 'color']),
            'statuses' => AlertStatus::all(),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'triageStats' => $this->annotations->statusBreakdown(),
        ]);
    }

    /**
     * Alert detail for the off-canvas drawer. Returns the complete document so
     * the drawer can render raw JSON, event data and compliance mappings
     * without a full page navigation.
     */
    public function detail(Request $request)
    {
        $data = $request->validate([
            'index' => ['required', 'string'],
            'id' => ['required', 'string'],
        ]);

        $alert = $this->alerts->find($data['index'], $data['id']);

        if ($alert === null) {
            return response()->json([
                'ok' => false,
                'error' => 'Alert not found. It may have rotated out of the retention window.',
            ], 404);
        }

        $uid = $this->annotations->uidFor($alert);
        $annotation = null;

        if ($uid !== null) {
            $annotation = AlertAnnotation::with([
                'labels', 'assignee:id,name', 'ticket:id,reference,title',
                'comments.user:id,name',
            ])->where('alert_uid', $uid)->first();
        }

        $rule = $alert['rule'] ?? [];

        return response()->json([
            'ok' => true,
            'index' => $data['index'],
            'id' => $data['id'],
            'uid' => $uid,
            'source' => $alert,
            'annotation' => $annotation ? [
                'id' => $annotation->id,
                'status' => $annotation->status,
                'assignee_id' => $annotation->assignee_id,
                'assignee' => $annotation->assignee->name ?? null,
                'ticket' => $annotation->ticket ? [
                    'id' => $annotation->ticket->id,
                    'reference' => $annotation->ticket->reference,
                    'title' => $annotation->ticket->title,
                ] : null,
                'labels' => $annotation->labels->map(fn ($l) => [
                    'id' => $l->id, 'name' => $l->name, 'color' => $l->color,
                ])->all(),
                'comments' => $annotation->comments->map(fn ($c) => [
                    'user' => $c->user->name ?? 'system',
                    'body' => $c->body,
                    'at' => $c->created_at?->format('d M Y H:i'),
                ])->all(),
            ] : null,
            'compliance' => [
                'PCI DSS' => $rule['pci_dss'] ?? [],
                'HIPAA' => $rule['hipaa'] ?? [],
                'GDPR' => $rule['gdpr'] ?? [],
                'NIST 800-53' => $rule['nist_800_53'] ?? [],
                'TSC' => $rule['tsc'] ?? [],
            ],
            'options' => [
                'statuses' => collect(AlertStatus::all())->map(fn ($s, $k) => [
                    'value' => $k, 'label' => $s['label'], 'color' => $s['color'],
                ])->values()->all(),
                'labels' => Label::orderBy('name')->get(['id', 'name', 'color'])
                    ->map(fn ($l) => ['id' => $l->id, 'name' => $l->name, 'color' => $l->color])->all(),
                'users' => User::orderBy('name')->get(['id', 'name'])
                    ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->all(),
                'reports' => collect($this->annotations->reportOptions())
                    ->map(fn ($i) => ['id' => $i->id, 'reference' => $i->reference, 'title' => $i->title])->all(),
                // Agent list for the optional multi-agent grouping in the
                // report form. Cached for a week of activity.
                'agents' => $this->alerts->filterOptions('now-7d')['agents'] ?? [],
                'can_triage' => (bool) $request->user()?->can('triage'),
            ],
        ]);
    }
}
