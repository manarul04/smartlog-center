<?php

namespace App\Http\Controllers;

use App\Services\OpenSearch\AlertRepository;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ComplianceController extends Controller
{
    public function __construct(private AlertRepository $alerts) {}

    public function index(Request $request): View
    {
        $ranges = config('opensearch.time_ranges');
        $range = in_array($request->query('range'), ['7d', '30d', '90d'], true)
            ? $request->query('range')
            : '30d';

        $data = [];
        $error = null;

        try {
            $data = $this->alerts->compliance($ranges[$range]['from']);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        return view('compliance.index', [
            'range' => $range,
            'ranges' => $ranges,
            'data' => $data,
            'error' => $error,
        ]);
    }
}
