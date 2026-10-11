<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Finance\Application\UseCases;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Modules\Finance\Application\Contracts\BankImportInfrastructureInterface;
use App\Modules\Finance\Application\Contracts\UnpaidPermitProviderInterface;
use App\Modules\Finance\Application\DTO\UnpaidPermitsDto;
use App\Modules\Finance\Application\UseCases\ProcessBankImport\ProcessBankImportCommand;
use App\Modules\Finance\Application\UseCases\ProcessBankImport\ProcessBankImportHandler;
use App\SharedKernel\Domain\Event\BankPaymentAssignedEvent;
use App\SharedKernel\Infrastructure\Utils\SystemClock;
use League\Csv\Reader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProcessBankImportHandler::class)]
final class ProcessBankImportHandlerTest extends TestCase
{
    #[Test]
    public function itProcessesCsvAndResolvesAllFourPrioritiesAndAnomalies(): void
    {
        // CSV Aufbau: Datum(0); Zweck(1); Sender(2); Betrag(3); Waehrung(4)
        $csvContent = "datum;zweck;sender;betrag;waehrung\n"
            // 1. Success (PRIO 1 Exakt)
            . "01.10.26;EFG-1111;Max;10,00;EUR\n"
            // 2. Anomaly: Währung falsch (USD)
            . "01.10.26;EFG-1111;Max;10,00;USD\n"
            // 3. Anomaly: Unterzahlung (5€ statt 10€)
            . "01.10.26;EFG-2222;Max;5.00;EUR\n"
            // 4. Anomaly: Bereits storniert
            . "01.10.26;EFG-3333;Max;10.00;EUR\n"
            // 5. Anomaly: Doppelzahlung (bereits bezahlt)
            . "02.10.26;EFG-4444;Max;10.00;EUR\n"
            // 6. PRIO 2: Kennzeichen
            . "01.10.26;Miete B-XX 123;Max;10.00;EUR\n"
            // 7. PRIO 3: Name
            . "01.10.26;Spende;Erika Musterfrau;10.00;EUR\n"
            // 8. PRIO 4: Parzelle
            . "01.10.26;Parzelle 42;Unbekannt;10.00;EUR\n";

        $reader = Reader::createFromString($csvContent);
        $reader->setDelimiter(';');

        $infrastructure = $this->createMock(BankImportInfrastructureInterface::class);
        $infrastructure->method('normalizeAndOpenCsv')->willReturn($reader);

        $provider = $this->createStub(UnpaidPermitProviderInterface::class);
        $provider->method('getPermitDataForImport')->willReturn(new UnpaidPermitsDto(
            allCodes: [
                'EFG-1111' => true, 'EFG-2222' => true, 'EFG-3333' => true, 'EFG-4444' => true,
                'EFG-5555' => true, 'EFG-6666' => true, 'EFG-7777' => true,
            ],
            unpaidCodes: [
                'EFG-1111' => 'Max', 'EFG-2222' => 'Max', 'EFG-5555' => 'Max', 'EFG-6666' => 'Erika Musterfrau', 'EFG-7777' => 'Unbekannt',
            ],
            unpaidPlates: [
                'EFG-5555' => 'B-XX 123',
            ],
            prices: [
                'EFG-1111' => 10.0, 'EFG-2222' => 10.0, 'EFG-3333' => 10.0, 'EFG-4444' => 10.0,
                'EFG-5555' => 10.0, 'EFG-6666' => 10.0, 'EFG-7777' => 10.0,
            ],
            records: [
                'EFG-1111' => ['code' => 'EFG-1111', 'shortCode' => '1111', 'status' => 'offen', 'name' => 'Max', 'parzelle' => 1, 'kennzeichen' => 'B-AA 1', 'preis' => 10.0],
                'EFG-2222' => ['code' => 'EFG-2222', 'shortCode' => '2222', 'status' => 'offen', 'name' => 'Max', 'parzelle' => 2, 'kennzeichen' => 'B-BB 2', 'preis' => 10.0],
                'EFG-3333' => ['code' => 'EFG-3333', 'shortCode' => '3333', 'status' => 'storniert', 'name' => 'Max', 'parzelle' => 3, 'kennzeichen' => 'B-CC 3', 'preis' => 10.0],
                'EFG-4444' => ['code' => 'EFG-4444', 'shortCode' => '4444', 'status' => 'bezahlt', 'bezahltAmFormatted' => '01.10.2026', 'name' => 'Max', 'parzelle' => 4, 'kennzeichen' => 'B-DD 4', 'preis' => 10.0],
                'EFG-5555' => ['code' => 'EFG-5555', 'shortCode' => '5555', 'status' => 'offen', 'name' => 'Max', 'parzelle' => 5, 'kennzeichen' => 'B-XX 123', 'preis' => 10.0], // Prio 2 Match
                'EFG-6666' => ['code' => 'EFG-6666', 'shortCode' => '6666', 'status' => 'offen', 'name' => 'Erika Musterfrau', 'parzelle' => 6, 'kennzeichen' => 'B-FF 6', 'preis' => 10.0], // Prio 3 Match
                'EFG-7777' => ['code' => 'EFG-7777', 'shortCode' => '7777', 'status' => 'offen', 'name' => 'Unbekannt', 'parzelle' => 42, 'kennzeichen' => 'B-GG 7', 'preis' => 10.0, 'plotFormatted' => '0042'], // Prio 4 Match
            ],
        ));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        // Exakt EIN Event für die EFG-1111 Zahlung
        $dispatcher->expects($this->once())->method('dispatch')->with($this->isInstanceOf(BankPaymentAssignedEvent::class));

        $config = $this->createStub(ConfigInterface::class);
        $config->method('getBool')->willReturn(true); // allow_legacy_6char_suffix_match

        $handler = new ProcessBankImportHandler($provider, $config, $dispatcher, $infrastructure, new SystemClock());

        $command = new ProcessBankImportCommand('/tmp/bank.csv', 1, 3, 0, 2, 4);
        $result = $handler->handle($command);

        $this->assertTrue($result->success);

        // EFG-1111 wurde freigeschaltet
        $this->assertSame(1, $result->successCount);
        $this->assertStringContainsString('EFG-1111', $result->successDetails[0]);

        // EFG-2222 (Unterzahlung)
        $this->assertSame(1, \count($result->errorDetails['Zu geringer Betrag']));

        // Anomaly / Sammeltransfers:
        // USD Mismatch, Cancelled, Duplicate, Plate, Name, Plot, Underpaid = 7 Stück
        $this->assertCount(7, $result->collectiveTransfers);

        $types = \array_column($result->collectiveTransfers, 'type');
        $this->assertContains('currency_mismatch', $types);
        $this->assertContains('cancelled_paid', $types);
        $this->assertContains('duplicate_paid', $types);
        $this->assertContains('underpaid', $types);
        $this->assertContains('kennzeichen', $types);
        $this->assertContains('name', $types);
        $this->assertContains('parzelle', $types);
    }
}
