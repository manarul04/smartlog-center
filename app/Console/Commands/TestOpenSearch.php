<?php

namespace App\Console\Commands;

use App\Services\OpenSearch\AgentRepository;
use App\Services\OpenSearch\AlertRepository;
use App\Services\OpenSearch\OpenSearchClient;
use Illuminate\Console\Command;

class TestOpenSearch extends Command
{
    protected $signature = 'wazuh:test';

    protected $description = 'Sanity-check the OpenSearch integration';

    public function handle(OpenSearchClient $client, AlertRepository $alerts, AgentRepository $agents): int
    {
        $this->info('Health: ' . ($client->isHealthy() ? 'OK' : 'FAIL'));

        $t = microtime(true);
        $overview = $alerts->overview('now-24h', '1h');
        $this->line(sprintf('Overview 24h: %d alerts | %d timeline buckets | %.0f ms',
            $overview['total'], count($overview['timeline']), (microtime(true) - $t) * 1000));
        $this->line('  critical=' . $overview['counters']['critical']
            . ' high=' . $overview['counters']['high']
            . ' auth_failed=' . $overview['counters']['auth_failed']
            . ' agents=' . $overview['counters']['unique_agents']);
        $this->line('  top agent: ' . ($overview['agents'][0]['key'] ?? '-'));

        $t = microtime(true);
        $search = $alerts->search(['from' => 'now-7d'], 1, 5);
        $this->line(sprintf('Explorer: total=%d returned=%d %.0f ms',
            $search['total'], count($search['items']), (microtime(true) - $t) * 1000));

        $t = microtime(true);
        $mitre = $alerts->mitre('now-60d');
        $this->line(sprintf('MITRE: tactics=%d techniques=%d hits=%d %.0f ms',
            count($mitre['tactics']), count($mitre['techniques']), $mitre['total'], (microtime(true) - $t) * 1000));

        $t = microtime(true);
        $compliance = $alerts->compliance('now-30d');
        foreach ($compliance as $key => $fw) {
            $this->line(sprintf('  %-12s total=%-7d controls=%d', $key, $fw['total'], $fw['controls_hit']));
        }
        $this->line(sprintf('  compliance %.0f ms', (microtime(true) - $t) * 1000));

        $t = microtime(true);
        $status = $agents->latestStatus();
        $this->line(sprintf('Agents: %d (%.0f ms)', count($status), (microtime(true) - $t) * 1000));
        foreach (array_slice($status, 0, 8) as $agent) {
            $this->line(sprintf('  %-22s %-8s %s', $agent['name'], $agent['status'], $agent['os'] ?? ''));
        }

        $vulns = $agents->vulnerabilities();
        $this->line(sprintf('Vulnerabilities: available=%s total=%d cves=%d',
            $vulns['available'] ? 'yes' : 'no', $vulns['total'], count($vulns['cves'] ?? [])));

        $sca = $agents->scaSummary();
        $this->line('SCA policies: ' . count($sca));
        foreach (array_slice($sca, 0, 5) as $row) {
            $this->line(sprintf('  %-22s score=%d%% passed=%d failed=%d', $row['agent'], $row['score'], $row['passed'], $row['failed']));
        }

        return self::SUCCESS;
    }
}
