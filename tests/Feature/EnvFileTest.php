<?php

namespace Tests\Feature;

use App\Support\EnvFile;
use Tests\TestCase;

class EnvFileTest extends TestCase
{
    public function test_it_reads_plain_quoted_and_commented_values(): void
    {
        $path = app()->environmentFilePath();
        $original = file_get_contents($path);

        try {
            file_put_contents($path, implode("\n", [
                'APP_NAME="Smart Log Center"',
                'DB_DATABASE=wazuh_logging',
                "DB_PASSWORD='p@ss word'",
                'EMPTY_VALUE=',
                'WITH_COMMENT=value # a note',
                '# COMMENTED=ignored',
                'not a pair',
            ]));

            $values = EnvFile::values();

            $this->assertSame('Smart Log Center', $values['APP_NAME']);
            $this->assertSame('wazuh_logging', $values['DB_DATABASE']);
            $this->assertSame('p@ss word', $values['DB_PASSWORD']);
            $this->assertSame('', $values['EMPTY_VALUE']);
            $this->assertSame('value', $values['WITH_COMMENT']);
            $this->assertArrayNotHasKey('COMMENTED', $values);
        } finally {
            file_put_contents($path, $original);
        }
    }
}
