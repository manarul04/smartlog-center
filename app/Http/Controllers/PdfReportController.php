<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\AnnotationService;
use App\Services\ReportService;
use App\Support\AlertStatus;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use RuntimeException;

class PdfReportController extends Controller
{
    public function __construct(
        private ReportService $reports,
        private AnnotationService $annotations,
    ) {}

    /**
     * Export the current explorer view as a PDF report. The title and scope are
     * derived from the filters that are actually applied.
     */
    public function alerts(Request $request)
    {
        $ranges = config('opensearch.time_ranges');
        $range = isset($ranges[$request->query('range')]) ? $request->query('range') : '24h';

        $filters = [
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

        try {
            $meta = $this->reports->describe($filters, $range);
            $data = $this->reports->collect($filters, $range);
        } catch (RuntimeException $e) {
            return back()->with('error', 'Report could not be generated: ' . $e->getMessage());
        }

        AuditLog::record('report.exported', 'alerts', [
            'scope' => $meta['scope'],
            'range' => $range,
            'total' => $data['total'],
        ]);

        $pdf = Pdf::loadView('reports.alerts', [
            'meta' => $meta,
            'data' => $data,
            'bands' => config('opensearch.severity_bands'),
            'statuses' => AlertStatus::all(),
            'generatedBy' => $request->user(),
        ])->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download($meta['filename']);
    }
}
