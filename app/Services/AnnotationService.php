<?php

namespace App\Services;

use App\Models\AlertAnnotation;
use App\Models\Ticket;
use App\Models\Label;
use Illuminate\Support\Facades\DB;

class AnnotationService
{
    /**
     * Attach annotations to a page of OpenSearch hits, keyed by the alert's
     * own stable `id` field rather than the index-scoped document id.
     */
    public function attach(array $items): array
    {
        $uids = [];
        foreach ($items as $item) {
            $uid = $this->uidFor($item['source'] ?? []);
            if ($uid !== null) {
                $uids[] = $uid;
            }
        }

        $annotations = AlertAnnotation::forUids(array_values(array_unique($uids)));

        foreach ($items as &$item) {
            $uid = $this->uidFor($item['source'] ?? []);
            $item['uid'] = $uid;
            $item['annotation'] = $uid !== null ? ($annotations[$uid] ?? null) : null;
        }
        unset($item);

        return $items;
    }

    public function uidFor(array $source): ?string
    {
        $id = $source['id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * Create or update the annotation row for an alert hit.
     */
    public function upsert(array $hit, array $data): AlertAnnotation
    {
        $source = $hit['source'] ?? [];
        $uid = $this->uidFor($source);

        if ($uid === null) {
            throw new \RuntimeException('Alert has no stable id field; cannot annotate.');
        }

        $annotation = AlertAnnotation::firstOrNew(['alert_uid' => $uid]);

        $annotation->fill([
            'alert_index' => $hit['index'] ?? $annotation->alert_index,
            'agent_name' => $source['agent']['name'] ?? $annotation->agent_name,
            'rule_id' => $source['rule']['id'] ?? $annotation->rule_id,
            'rule_description' => mb_substr((string) ($source['rule']['description'] ?? ''), 0, 255) ?: $annotation->rule_description,
            'rule_level' => isset($source['rule']['level']) ? (int) $source['rule']['level'] : $annotation->rule_level,
            'alert_timestamp' => isset($source['timestamp']) ? \Illuminate\Support\Carbon::parse($source['timestamp']) : $annotation->alert_timestamp,
            'status' => $data['status'] ?? $annotation->status ?? \App\Support\AlertStatus::NOT_REVIEWED,
            'assignee_id' => array_key_exists('assignee_id', $data) ? $data['assignee_id'] : $annotation->assignee_id,
            'ticket_id' => array_key_exists('ticket_id', $data) ? $data['ticket_id'] : $annotation->ticket_id,
        ]);

        $annotation->save();

        if (array_key_exists('label_ids', $data)) {
            $annotation->labels()->sync($data['label_ids'] ?? []);
        }

        return $annotation->fresh(['labels', 'assignee', 'ticket']);
    }

    /**
     * Bulk status update, creating annotation rows for alerts that have none.
     */
    public function bulkStatus(array $hits, string $status, ?int $assigneeId = null): int
    {
        $count = 0;

        DB::transaction(function () use ($hits, $status, $assigneeId, &$count) {
            foreach ($hits as $hit) {
                $data = ['status' => $status];
                if ($assigneeId !== null) {
                    $data['assignee_id'] = $assigneeId;
                }
                $this->upsert($hit, $data);
                $count++;
            }
        });

        return $count;
    }

    /**
     * Load the alert hits referenced by a list of UIDs, preserving input order.
     * Used by report detail views where only annotation rows are stored.
     */
    public function hydrate(array $uids, int $limit = 200): array
    {
        return array_slice($uids, 0, $limit);
    }

    /**
     * Aggregate handling state for the dashboard, over a given alert UID set
     * or over all time when no set is supplied.
     */
    public function statusBreakdown(?array $uids = null): array
    {
        $query = AlertAnnotation::query();

        if ($uids !== null) {
            $query->whereIn('alert_uid', $uids);
        }

        $counts = $query->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $open = 0;
        foreach (\App\Support\AlertStatus::openKeys() as $key) {
            $open += $counts[$key] ?? 0;
        }

        return [
            'counts' => $counts,
            'open' => $open,
            'total' => array_sum($counts),
        ];
    }

    /**
     * Resolve the alert UIDs that match a handling filter so the caller can
     * push the constraint into the OpenSearch query instead of filtering only
     * the already-paginated page.
     *
     * Returns ['include' => [...]] or ['exclude' => [...]] or null when the
     * filter cannot be expressed (no annotations exist at all).
     */
    public function uidConstraint(?string $status, ?int $labelId): ?array
    {
        if (($status === null || $status === '') && ($labelId === null || $labelId === 0)) {
            return null;
        }

        $query = AlertAnnotation::query();

        if ($labelId !== null && $labelId > 0) {
            $query->whereHas('labels', fn ($q) => $q->where('labels.id', $labelId));
        }

        // "Not reviewed" means the alert has no annotation row at all, which is
        // the complement of every annotated UID.
        if ($status === \App\Support\AlertStatus::NOT_REVIEWED) {
            $annotated = AlertAnnotation::pluck('alert_uid')->all();

            return $annotated === [] ? null : ['exclude' => $annotated];
        }

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        return ['include' => $query->pluck('alert_uid')->all()];
    }

    public function labelOptions(): array
    {
        return Label::orderBy('name')->get(['id', 'name', 'color'])->all();
    }

    /**
     * Open reports that an alert can be linked to.
     */
    public function reportOptions(): array
    {
        return Ticket::open()
            ->orderByDesc('id')
            ->get(['id', 'reference', 'title'])
            ->all();
    }
}
