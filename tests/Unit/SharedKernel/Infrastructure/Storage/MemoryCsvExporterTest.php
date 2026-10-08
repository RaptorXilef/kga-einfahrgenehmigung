<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Storage;

use App\SharedKernel\Infrastructure\Storage\MemoryCsvExporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MemoryCsvExporter::class)]
final class MemoryCsvExporterTest extends TestCase
{
    #[Test]
    public function itExportsUtf8BomCsvAndSanitizesFormulaInjectionPayloads(): void
    {
        $exporter = new MemoryCsvExporter();

        $headers = ['Text', 'Int', 'Float', 'Leer', 'FormelGleich', 'FormelPlus', 'FormelMinus', 'FormelAt', 'Tab', 'CR'];
        $rows = [
            [
                'Normaler Text',
                -42,
                -15.5,
                '',
                '=SUM(A1:A2)',
                '+12345',
                '-12345',
                '@cmd',
                "\tTabulator",
                "\rCarriageReturn",
            ],
        ];

        // Aufruf ohne 3. Parameter testet gleichzeitig den Default-Delimiter ';'
        $csv = $exporter->export($headers, $rows);

        $expectedCsv = "\xEF\xBB\xBF"
            . "Text;Int;Float;Leer;FormelGleich;FormelPlus;FormelMinus;FormelAt;Tab;CR\n"
            . "\"Normaler Text\";-42;-15.5;;'=SUM(A1:A2);'+12345;'-12345;'@cmd;\"'\tTabulator\";\"'\rCarriageReturn\"\n";

        $this->assertSame($expectedCsv, $csv);
    }
}
