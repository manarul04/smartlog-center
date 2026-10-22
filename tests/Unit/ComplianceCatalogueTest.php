<?php

namespace Tests\Unit;

use App\Support\Compliance;
use Tests\TestCase;

class ComplianceCatalogueTest extends TestCase
{
    public function test_a_known_control_returns_its_explanation(): void
    {
        $control = Compliance::describe('PCI DSS', '10.6.1');

        $this->assertTrue($control['found']);
        $this->assertNotSame('', trim($control['title']));
        $this->assertGreaterThan(40, strlen($control['summary']));
    }

    public function test_every_catalogued_control_has_a_title_and_a_summary(): void
    {
        foreach (Compliance::CATALOG as $framework => $controls) {
            foreach ($controls as $code => $entry) {
                $message = $framework . ' / ' . $code;

                $this->assertArrayHasKey('title', $entry, $message . ' is missing a title.');
                $this->assertArrayHasKey('summary', $entry, $message . ' is missing a summary.');
                $this->assertNotSame('', trim($entry['title']), $message . ' has an empty title.');
                $this->assertNotSame('', trim($entry['summary']), $message . ' has an empty summary.');
                $this->assertGreaterThan(
                    40,
                    strlen($entry['summary']),
                    $message . ' has a summary shorter than 41 characters.'
                );
            }
        }
    }

    public function test_every_framework_in_the_catalogue_is_described(): void
    {
        foreach (array_keys(Compliance::CATALOG) as $framework) {
            $this->assertArrayHasKey(
                $framework,
                Compliance::FRAMEWORKS,
                $framework . ' has catalogue entries but no framework description.'
            );
            $this->assertNotSame('', trim(Compliance::FRAMEWORKS[$framework]));
        }
    }

    public function test_an_unknown_control_falls_back_instead_of_throwing(): void
    {
        $control = Compliance::describe('PCI DSS', '99.99');

        $this->assertFalse($control['found']);
        $this->assertSame('99.99', $control['code']);
        $this->assertNotSame('', trim($control['summary']));
    }

    public function test_an_unknown_framework_falls_back(): void
    {
        $control = Compliance::describe('SOC 1', 'X');

        $this->assertFalse($control['found']);
        $this->assertSame('SOC 1', $control['framework']);
        $this->assertSame('X', $control['code']);
        $this->assertNotSame('', trim($control['summary']));
    }

    public function test_catalogue_exposes_both_halves(): void
    {
        $catalogue = Compliance::catalogue();

        $this->assertArrayHasKey('frameworks', $catalogue);
        $this->assertArrayHasKey('controls', $catalogue);
        $this->assertSame(Compliance::FRAMEWORKS, $catalogue['frameworks']);
        $this->assertSame(Compliance::CATALOG, $catalogue['controls']);
    }
}
