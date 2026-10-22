<?php

namespace Tests\Feature;

use App\Models\KbCatalog;
use App\Services\KbCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KbCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function catalogHtml(): string
    {
        $row = function (string $id, string $title, string $product, string $date) {
            return <<<HTML
            <tr id="{$id}_R0" style="border-width:0px;">
                <td class="resultsbottomBorder resultspadding resultsIconWidth "></td>
                <td class="resultsbottomBorder resultspadding">
                    <a id='{$id}_link' href="javascript:void(0);" onclick='goToDetails("{$id}");' class="contentTextItemSpacerNoBreakLink">
                        {$title}
                    </a>
                </td>
                <td class="resultsbottomBorder resultspadding">{$product}</td>
                <td class="resultsbottomBorder resultspadding">Definition Updates</td>
                <td class="resultsbottomBorder resultspadding ">{$date}</td>
                <td class="resultsbottomBorder resultspadding">n/a</td>
                <td class="resultsbottomBorder resultspadding resultsSizeWidth"><span>44.0 MB</span></td>
                <td class="resultsbottomBorder resultsButtonWidth"><input id="{$id}" type="button" value='Download' /></td>
            </tr>
            HTML;
        };

        return '<table id="ctl00_catalogBody_updateMatches">'
            . $row('a32ca1d0-ddd4-486b-b708-d941db4f1141', 'Update for Windows Security platform - KB5007651 (Version 10.0.29628.1000)', 'Windows Security platform', '7/30/2026')
            . $row('a32ca1d0-ddd4-486b-b708-d941db4f1051', 'Update for Windows Security platform - KB5007651 (Version 10.0.27840.1000)', 'Windows Security platform', '5/23/2025')
            . $row('b9a50e37-4f50-4f50-bc24-9e7b1e1d4b70', '2026-10 Cumulative Update for Windows 11, version 25H2 for arm64-based Systems (KB5127648) (26200.9711)', 'Windows 11', '10/13/2026')
            . $row('c1b60f28-5a61-4a61-cd35-0f8c2f2e5c81', '2026-10 Cumulative Update for Windows 11, version 25H2 for x64-based Systems (KB5127648) (26200.9711)', 'Windows 11', '10/13/2026')
            . '</table>';
    }

    public function test_the_catalog_page_is_parsed_into_titles(): void
    {
        $variants = (new KbCatalogService())->parseCatalog($this->catalogHtml());

        $this->assertCount(4, $variants);
        $this->assertSame(
            'Update for Windows Security platform - KB5007651 (Version 10.0.29628.1000)',
            $variants[0]['title']
        );
        $this->assertSame('Windows Security platform', $variants[0]['product']);
        $this->assertSame('7/30/2026', $variants[0]['last_updated']);
        $this->assertSame('a32ca1d0-ddd4-486b-b708-d941db4f1141', $variants[0]['update_id']);
        $this->assertSame('arm64', $variants[2]['arch']);
        $this->assertSame('x64', $variants[3]['arch']);
    }

    public function test_the_variant_matching_the_installed_version_wins(): void
    {
        $variants = (new KbCatalogService())->parseCatalog($this->catalogHtml());
        $security = array_slice($variants, 0, 2);

        // The Windows Security package reports 1000.29628.1000.0, which maps to
        // the 10.0.29628.1000 catalog entry even though it is not the newest.
        $best = (new KbCatalogService())->pickVariant(
            $security,
            'Microsoft Windows 11 Pro',
            'x86_64',
            '1000.29628.1000.0'
        );

        $this->assertStringContainsString('10.0.29628.1000', $best['title']);
    }

    public function test_the_variant_matching_the_architecture_wins_without_a_version(): void
    {
        $variants = (new KbCatalogService())->parseCatalog($this->catalogHtml());
        $cumulative = array_slice($variants, 2);

        $best = (new KbCatalogService())->pickVariant($cumulative, 'Microsoft Windows 11 Pro', 'x86_64');

        $this->assertSame('x64', $best['arch']);
        $this->assertStringContainsString('x64-based Systems', $best['title']);
    }

    public function test_server_builds_are_not_picked_for_client_windows(): void
    {
        $service = new KbCatalogService();

        $variants = [
            ['title' => '2026-10 Cumulative Update for Windows 11 for x64-based Systems (KB1) (26200.1)', 'last_updated' => '10/13/2026', 'arch' => 'x64'],
            ['title' => '2026-10 Cumulative Update for Microsoft server operating system for x64-based Systems (KB1) (26100.1)', 'last_updated' => '10/13/2026', 'arch' => 'x64'],
        ];

        $client = $service->pickVariant($variants, 'Microsoft Windows 11 Pro', 'x86_64');
        $server = $service->pickVariant($variants, 'Microsoft Windows Server 2025 Standard', 'x86_64');

        $this->assertStringContainsString('Windows 11', $client['title']);
        $this->assertStringContainsString('server operating system', $server['title']);
    }

    public function test_cached_titles_are_read_without_touching_the_network(): void
    {
        KbCatalog::create([
            'kb' => 'KB5007651',
            'title' => 'Update for Windows Security platform - KB5007651 (Version 10.0.29628.1000)',
            'product' => 'Windows Security platform',
            'status' => 'ok',
            'source' => 'catalog',
            'attempts' => 1,
            'fetched_at' => now(),
        ]);

        KbCatalog::create([
            'kb' => 'KB2267602',
            'title' => null,
            'status' => 'not_found',
            'source' => 'none',
            'attempts' => 1,
            'fetched_at' => now(),
        ]);

        $service = new KbCatalogService();
        $titles = $service->titles(['KB5007651', 'KB2267602']);

        $this->assertStringContainsString('KB5007651', $titles['KB5007651']['title']);
        $this->assertNull($titles['KB2267602']['title']);
        $this->assertSame('not_found', $titles['KB2267602']['status']);
        $this->assertSame([], $service->pending(['KB5007651', 'KB2267602']));
    }

    public function test_missing_articles_are_marked_not_found_instead_of_retried_forever(): void
    {
        KbCatalog::create([
            'kb' => 'KB0000001',
            'status' => 'not_found',
            'source' => 'none',
            'attempts' => 3,
            'fetched_at' => now(),
        ]);

        $this->assertSame([], (new KbCatalogService())->pending(['KB0000001']));
    }
}
