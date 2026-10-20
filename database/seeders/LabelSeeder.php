<?php

namespace Database\Seeders;

use App\Models\Label;
use Illuminate\Database\Seeder;

class LabelSeeder extends Seeder
{
    public function run(): void
    {
        $labels = [
            ['name' => 'True Positive', 'color' => '#f43f5e', 'description' => 'Confirmed malicious activity', 'is_system' => true],
            ['name' => 'False Positive', 'color' => '#8b5cf6', 'description' => 'Benign or expected activity', 'is_system' => true],
            ['name' => 'Suspicious', 'color' => '#fbbf24', 'description' => 'Needs further triage', 'is_system' => true],
            ['name' => 'Malware', 'color' => '#dc2626', 'description' => 'Malware or ransomware activity', 'is_system' => true],
            ['name' => 'Brute Force', 'color' => '#fb923c', 'description' => 'Repeated authentication failures', 'is_system' => true],
            ['name' => 'Lateral Movement', 'color' => '#e879f9', 'description' => 'Movement between hosts', 'is_system' => true],
            ['name' => 'Privilege Escalation', 'color' => '#f97316', 'description' => 'Elevation of privileges', 'is_system' => true],
            ['name' => 'Data Exfiltration', 'color' => '#ef4444', 'description' => 'Suspected data theft', 'is_system' => true],
            ['name' => 'Policy Violation', 'color' => '#38bdf8', 'description' => 'Breach of internal policy', 'is_system' => true],
            ['name' => 'Needs Escalation', 'color' => '#22d3ee', 'description' => 'Escalate to senior analyst', 'is_system' => true],
        ];

        foreach ($labels as $label) {
            Label::updateOrCreate(['name' => $label['name']], $label);
        }
    }
}
