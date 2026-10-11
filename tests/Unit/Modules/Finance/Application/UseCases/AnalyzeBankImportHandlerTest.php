<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Finance\Application\UseCases;

use App\Modules\Finance\Application\Contracts\BankImportInfrastructureInterface;
use App\Modules\Finance\Application\UseCases\AnalyzeBankImport\AnalyzeBankImportHandler;
use App\Modules\Finance\Application\UseCases\AnalyzeBankImport\AnalyzeBankImportQuery;
use League\Csv\Reader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AnalyzeBankImportHandler::class)]
final class AnalyzeBankImportHandlerTest extends TestCase
{
    #[Test]
    public function itAnalyzesCsvHeadersAndGuessesColumnIndexes(): void
    {
        $csvContent = "unwichtig;buchungstag;name;zweck;betrag;waehrung\n" .
                      '1;01.10.2026;Max;EFG-123;10,00;EUR';

        $reader = Reader::createFromString($csvContent);
        $reader->setDelimiter(';');

        $infrastructure = $this->createStub(BankImportInfrastructureInterface::class);
        $infrastructure->method('normalizeAndOpenCsv')->willReturn($reader);

        $handler = new AnalyzeBankImportHandler($infrastructure);
        $dto = $handler->handle(new AnalyzeBankImportQuery('/tmp/test.csv'));

        $this->assertSame(['unwichtig', 'buchungstag', 'name', 'zweck', 'betrag', 'waehrung'], $dto->headers);
        $this->assertSame(['1', '01.10.2026', 'Max', 'EFG-123', '10,00', 'EUR'], $dto->previewRow);

        // Die Indizes müssen entsprechend der Spaltennamen erraten werden
        $this->assertSame(3, $dto->guessedId);
        $this->assertSame(4, $dto->guessedAmount);
        $this->assertSame(1, $dto->guessedDate);
        $this->assertSame(2, $dto->guessedSender);
        $this->assertSame(5, $dto->guessedCurrency);
    }

    #[Test]
    public function itReturnsEmptyDtoIfCsvIsInvalid(): void
    {
        $infrastructure = $this->createStub(BankImportInfrastructureInterface::class);
        $infrastructure->method('normalizeAndOpenCsv')->willReturn(null);

        $handler = new AnalyzeBankImportHandler($infrastructure);
        $dto = $handler->handle(new AnalyzeBankImportQuery('/tmp/invalid.csv'));

        $this->assertEmpty($dto->headers);
        $this->assertEmpty($dto->previewRow);
    }
}
