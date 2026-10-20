<?php

namespace App\Http\Controllers;

use App\Models\AlertAnnotation;
use App\Models\AlertComment;
use App\Models\AuditLog;
use App\Services\AnnotationService;
use App\Services\OpenSearch\AlertRepository;
use App\Support\AlertStatus;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlertActionController extends Controller
{
    public function __construct(
        private AnnotationService $annotations,
        private AlertRepository $alerts,
    ) {}

    /**
     * Apply a handling update to one alert from the detail page.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'index' => ['required', 'string'],
            'id' => ['required', 'string'],
            'status' => ['required', Rule::in(AlertStatus::keys())],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['integer', 'exists:labels,id'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'ticket_id' => ['nullable', 'integer', 'exists:tickets,id'],
            'comment' => ['nullable', 'string', 'max:4000'],
        ]);

        $alert = $this->alerts->find($data['index'], $data['id']);

        if ($alert === null) {
            return back()->with('error', 'Alert could not be loaded from OpenSearch; annotation not saved.');
        }

        $hit = ['index' => $data['index'], 'source' => $alert];

        $annotation = $this->annotations->upsert($hit, [
            'status' => $data['status'],
            'label_ids' => $data['label_ids'] ?? [],
            'assignee_id' => $data['assignee_id'] ?? null,
            'ticket_id' => $data['ticket_id'] ?? null,
        ]);

        if (! empty(trim((string) ($data['comment'] ?? '')))) {
            AlertComment::create([
                'alert_annotation_id' => $annotation->id,
                'user_id' => auth()->id(),
                'body' => trim($data['comment']),
            ]);
        }

        AuditLog::record('alert.updated', $annotation->alert_uid, [
            'status' => $annotation->status,
            'labels' => $annotation->labels->pluck('name')->all(),
            'ticket' => $annotation->ticket?->reference,
        ]);

        return back()->with('status', 'Alert handling updated.');
    }

    /**
     * Bulk status change from the explorer selection.
     */
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(AlertStatus::keys())],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.index' => ['required', 'string'],
            'items.*.id' => ['required', 'string'],
            'back' => ['nullable', 'string', 'max:2048'],
        ]);

        $hits = [];
        $missing = 0;

        foreach ($data['items'] as $item) {
            $alert = $this->alerts->find($item['index'], $item['id']);
            if ($alert === null) {
                $missing++;
                continue;
            }
            $hits[] = ['index' => $item['index'], 'source' => $alert];
        }

        $count = $this->annotations->bulkStatus($hits, $data['status'], $data['assignee_id'] ?? null);

        AuditLog::record('alert.bulk_status', null, [
            'status' => $data['status'],
            'count' => $count,
        ]);

        $message = "{$count} alert diubah ke status " . AlertStatus::label($data['status']) . '.';
        if ($missing > 0) {
            $message .= " {$missing} alert tidak ditemukan di OpenSearch.";
        }

        return back()->with('status', $message);
    }

    public function comment(Request $request, AlertAnnotation $annotation)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        AlertComment::create([
            'alert_annotation_id' => $annotation->id,
            'user_id' => auth()->id(),
            'body' => $data['body'],
        ]);

        return back()->with('status', 'Comment added.');
    }
}
