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

        $csv = $exporter->export($headers, $rows, ';');

        // 1. Prüft auf exakten UTF-8 BOM Header am Anfang
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        // 2. Prüft Kopfzeile
        $this->assertStringContainsString("Text;Int;Float;Leer;FormelGleich;FormelPlus;FormelMinus;FormelAt;Tab;CR\n", $csv);

        // 3. Echte Zahlen (-42, -15.5) dürfen NICHT mit ' maskiert werden, Strings mit gefährlichen Startzeichen schon!
        $this->assertStringContainsString(
            "\"Normaler Text\";-42;-15.5;\"\";'=SUM(A1:A2);'+12345;'-12345;'@cmd;\"'\tTabulator\";\"'\rCarriageReturn\"\n",
            $csv,
        );
    }
}
