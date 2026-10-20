<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consolidate the three legacy handling tables into tickets.
     *
     * Status vocabularies differed per table, so each is mapped onto the single
     * ticket vocabulary. Rows are matched into ticket_items by durable identity
     * (kind + ref_key) so re-running is harmless.
     */
    public function up(): void
    {
        $statusMap = [
            'acknowledged' => 'in_review',
            'accepted_risk' => 'risk_accepted',
            'resolved' => 'verified',
            'investigating' => 'in_review',
            'contained' => 'remediating',
        ];

        $nextId = (int) (DB::table('tickets')->max('id') ?? 0);

        $reference = function () use (&$nextId) {
            $nextId++;

            return 'RPT-' . str_pad((string) $nextId, 6, '0', STR_PAD_LEFT);
        };

        // ---- Vulnerabilities ------------------------------------------------
        if (Schema::hasTable('vulnerability_reviews')) {
            foreach (DB::table('vulnerability_reviews')->orderBy('id')->get() as $row) {
                $status = $statusMap[$row->status] ?? $row->status;
                $title = $row->cve_id . ' on ' . $row->agent_name;

                $ticketId = DB::table('tickets')->insertGetId([
                    'reference' => $reference(),
                    'type' => 'vulnerability',
                    'title' => mb_substr($title, 0, 200),
                    'description' => $row->note,
                    'resolution' => $row->resolution,
                    'status' => $status,
                    'priority' => $row->priority ?? 'medium',
                    'severity' => $row->severity,
                    'assignee_id' => $row->assignee_id,
                    'created_by' => $row->created_by,
                    'due_at' => $row->due_at,
                    'resolved_at' => $row->resolved_at,
                    'last_verified_at' => $row->last_verified_at,
                    'still_present' => (bool) ($row->still_present ?? true),
                    'meta' => json_encode([
                        'cve_id' => $row->cve_id,
                        'cvss' => $row->cvss,
                        'package_name' => $row->package_name,
                        'package_version' => $row->package_version,
                    ]),
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);

                DB::table('ticket_items')->insert([
                    'ticket_id' => $ticketId,
                    'kind' => 'vulnerability',
                    'ref_key' => implode('|', [
                        $row->agent_name ?? '',
                        $row->cve_id ?? '',
                        $row->package_name ?? '',
                    ]),
                    'agent_name' => $row->agent_name,
                    'title' => $row->cve_id,
                    'severity' => $row->severity,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }

        // ---- Incidents ------------------------------------------------------
        if (Schema::hasTable('incidents')) {
            foreach (DB::table('incidents')->orderBy('id')->get() as $row) {
                $status = $statusMap[$row->status] ?? $row->status;

                $ticketId = DB::table('tickets')->insertGetId([
                    'reference' => $reference(),
                    'type' => 'incident',
                    'title' => mb_substr((string) $row->title, 0, 200),
                    'description' => $row->description,
                    'status' => $status,
                    'priority' => $row->severity ?? 'medium',
                    'severity' => $row->severity,
                    'assignee_id' => $row->assignee_id,
                    'created_by' => $row->created_by,
                    'closed_at' => $row->closed_at,
                    'group_agent' => $row->group_agent ?? null,
                    'group_rule_id' => $row->group_rule_id ?? null,
                    'group_window_from' => $row->group_window_from ?? null,
                    'group_window_to' => $row->group_window_to ?? null,
                    'group_counted_at' => $row->group_counted_at ?? null,
                    'meta' => json_encode(['legacy_reference' => $row->reference]),
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);

                // Re-link the alerts that pointed at this incident.
                if (Schema::hasTable('alert_annotations')) {
                    DB::table('alert_annotations')
                        ->where('incident_id', $row->id)
                        ->update(['ticket_id' => $ticketId]);
                }
            }
        }

        // ---- SCA checks -----------------------------------------------------
        if (Schema::hasTable('sca_check_states')) {
            foreach (DB::table('sca_check_states')->orderBy('id')->get() as $row) {
                $status = $statusMap[$row->status] ?? $row->status;
                $title = 'SCA ' . $row->check_id . ' on ' . $row->agent_name;

                $ticketId = DB::table('tickets')->insertGetId([
                    'reference' => $reference(),
                    'type' => 'sca',
                    'title' => mb_substr($title, 0, 200),
                    'description' => $row->note,
                    'status' => $status,
                    'priority' => 'medium',
                    'assignee_id' => $row->assignee_id,
                    'created_by' => $row->updated_by,
                    'resolved_at' => $row->resolved_at,
                    'meta' => json_encode([
                        'check_id' => $row->check_id,
                        'policy' => $row->policy,
                    ]),
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);

                DB::table('ticket_items')->insert([
                    'ticket_id' => $ticketId,
                    'kind' => 'sca_check',
                    'ref_key' => implode('|', [
                        $row->agent_name ?? '',
                        $row->policy ?? '',
                        $row->check_id ?? '',
                    ]),
                    'agent_name' => $row->agent_name,
                    'title' => $row->check_id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Data consolidation is one-way; the legacy tables are dropped by the
        // following migration and cannot be reconstructed here.
    }
};
