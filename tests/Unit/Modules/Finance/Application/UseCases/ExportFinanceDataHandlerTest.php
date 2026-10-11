<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Finance\Application\UseCases;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Integration\PermitIntegrationInterface;
use App\Contracts\System\CsvExporterInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Finance\Application\UseCases\ExportFinanceData\ExportFinanceDataHandler;
use App\Modules\Finance\Application\UseCases\ExportFinanceData\ExportFinanceDataQuery;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExportFinanceDataHandler::class)]
final class ExportFinanceDataHandlerTest extends TestCase
{
    private function getIntegrationStub(): PermitIntegrationInterface
    {
        $integration = $this->createStub(PermitIntegrationInterface::class);
        $integration->method('yieldPermitsForFinanceExport')->willReturn((function () {
            yield [
                'code' => 'EFG-1', 'bezahlt_am' => '2026-10-01 12:00:00', 'erstellt' => '2026-10-01 10:00:00',
                'name' => 'Max', 'parzelle' => 42, 'zweck' => 'Bau', 'kennzeichen' => 'B-XX 1',
                'preis' => 10.0, 'status' => 'bezahlt', 'template_key' => 'std_7',
            ];
            yield [
                'code' => 'EFG-2', 'bezahlt_am' => null, 'erstellt' => '2026-10-02 10:00:00',
                'name' => 'Erika', 'parzelle' => 99, 'zweck' => 'Miete', 'kennzeichen' => 'B-YY 2',
                'preis' => 15.0, 'status' => 'offen', 'template_key' => 'std_14',
            ];
        })());

        return $integration;
    }

    #[Test]
    public function itGeneratesJsonExport(): void
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-15'));
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getString')->willReturn('KGA Berlin');

        $handler = new ExportFinanceDataHandler($this->getIntegrationStub(), $config, $clock, $this->createStub(CsvExporterInterface::class));
        $result = $handler->handle(new ExportFinanceDataQuery('json', '2026-01-01', '2026-12-31', 'all', ''));

        $this->assertSame('application/json', $result->contentType);
        $this->assertStringContainsString('kga_berlin_finanzexport', $result->filename);
        $this->assertStringContainsString('"erwarteter_umsatz_eur": 25', $result->content);
        $this->assertStringContainsString('"bezahlter_umsatz_eur": 10', $result->content);
    }

    #[Test]
    public function itGeneratesCsvExportViaExporter(): void
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-15'));

        $exporter = $this->createMock(CsvExporterInterface::class);
        // Exporter fängt den Stream ab und macht CSV draus
        $exporter->expects($this->once())->method('export')->willReturn('CSV_CONTENT');

        $handler = new ExportFinanceDataHandler($this->getIntegrationStub(), $this->createStub(ConfigInterface::class), $clock, $exporter);
        $result = $handler->handle(new ExportFinanceDataQuery('csv', '2026-01-01', '2026-12-31', 'all', ''));

        $this->assertSame('text/csv; charset=utf-8', $result->contentType);
        $this->assertSame('CSV_CONTENT', $result->content);
    }

    #[Test]
    public function itGeneratesCsvStatsExportViaExporter(): void
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-15'));

        $exporter = $this->createMock(CsvExporterInterface::class);
        $exporter->expects($this->once())->method('export')->willReturn('CSV_STATS_CONTENT');

        $handler = new ExportFinanceDataHandler($this->getIntegrationStub(), $this->createStub(ConfigInterface::class), $clock, $exporter);
        $result = $handler->handle(new ExportFinanceDataQuery('csv_stats', '2026-01-01', '2026-12-31', 'all', ''));

        $this->assertSame('text/csv; charset=utf-8', $result->contentType);
        $this->assertStringContainsString('statistik', $result->filename);
    }
}
