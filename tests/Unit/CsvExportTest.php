<?php

namespace Tests\Unit;

use App\Modules\Reporting\Actions\CsvExport;
use PHPUnit\Framework\TestCase;

class CsvExportTest extends TestCase
{
    public function test_formula_cells_are_prefixed_without_mutating_numbers_or_normal_text(): void
    {
        $csv = app(CsvExport::class)->build(['value'], [
            ['=SUM(A1:A2)'],
            ['+cmd'],
            ['-1'],
            ['@import'],
            ['normal'],
            [123],
        ]);

        $lines = explode("\n", trim($csv));
        $this->assertSame("'=SUM(A1:A2)", trim($lines[1], '"'));
        $this->assertSame("'+cmd", trim($lines[2], '"'));
        $this->assertSame("'-1", trim($lines[3], '"'));
        $this->assertSame("'@import", trim($lines[4], '"'));
        $this->assertSame('normal', trim($lines[5], '"'));
        $this->assertSame('123', trim($lines[6], '"'));
    }
}
