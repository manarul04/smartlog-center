<?php

namespace App\Console\Commands;

use App\Services\KbCatalogService;
use App\Services\OpenSearch\AgentRepository;
use Illuminate\Console\Command;

class SyncKbCatalog extends Command
{
    protected $signature = 'kb:sync
        {--agent= : Only the KBs reported by this agent name}
        {--limit=0 : Stop after this many lookups (0 = all)}
        {--force : Re-resolve titles that are already cached}';

    protected $description = 'Backfill Windows KB titles from the Microsoft Update Catalog into the local cache';

    public function handle(KbCatalogService $catalog, AgentRepository $agents): int
    {
        if ($this->option('agent')) {
            $inventory = $agents->inventory($this->option('agent'), 500);
            $kbs = $inventory['hotfixes']['items'] ?? [];
        } else {
            $kbs = $agents->allHotfixNames();
        }

        if ($kbs === []) {
            $this->warn('No Windows KBs found in the hotfix inventory.');

            return self::SUCCESS;
        }

        if ($this->option('force')) {
            \App\Models\KbCatalog::query()->whereIn('kb', $kbs)->delete();
        }

        $this->info(sprintf('%d KBs in inventory, resolving missing titles…', count($kbs)));

        $total = count($catalog->pending($kbs));

        if ((int) $this->option('limit') > 0) {
            $total = min($total, (int) $this->option('limit'));
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $stats = $catalog->syncAll($kbs, function () use ($bar) {
            $bar->advance();
        }, (int) $this->option('limit'));

        $bar->finish();
        $this->newLine(2);

        $this->table(['Resolved', 'Not in catalog', 'Already cached'], [[
            $stats['fetched'], $stats['failed'], $stats['skipped'],
        ]]);

        return self::SUCCESS;
    }
}
