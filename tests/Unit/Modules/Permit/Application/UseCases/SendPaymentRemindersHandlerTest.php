<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Permit\Application\UseCases;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Application\UseCases\SendPaymentReminders\SendPaymentRemindersCommand;
use App\Modules\Permit\Application\UseCases\SendPaymentReminders\SendPaymentRemindersHandler;
use App\Modules\Permit\Domain\Owner;
use App\Modules\Permit\Domain\Permit;
use App\Modules\Permit\Domain\PermitFinancialCalculator;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\Modules\Permit\Domain\PermitStatus;
use App\Modules\Permit\Domain\Status;
use App\Modules\Permit\Domain\Validity;
use App\Modules\Permit\Domain\Vehicle;
use App\SharedKernel\Domain\ValueObject\LicensePlate;
use App\SharedKernel\Domain\ValueObject\PermitCode;
use App\SharedKernel\Domain\ValueObject\PlotNumber;
use App\SharedKernel\Domain\ValueObject\Price;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SendPaymentRemindersHandler::class)]
final class SendPaymentRemindersHandlerTest extends TestCase
{
    #[Test]
    public function itIteratesThroughUnpaidPermitsAndDispatchesRemindersIfNotOnCooldown(): void
    {
        $permit = new Permit(
            new PermitCode('T-123'),
            new TemplateKey('std_7'),
            new Owner('Max', null, new PlotNumber(42)),
            new Vehicle('pkw', new LicensePlate('B')),
            new Validity(new DateTimeImmutable(), new DateTimeImmutable(), new Price(10.0), ''),
            new Status(PermitStatus::Offen),
            new DateTimeImmutable('2026-10-01'), // Erstellt vor 14 Tagen -> Ist jetzt fällig!
        );

        $repo = $this->createMock(PermitRepositoryInterface::class);
        $repo->method('yieldUnpaid')->willReturn((function () use ($permit) { yield $permit; })());
        $repo->expects($this->once())->method('save');

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-15 12:00:00'));

        $config = $this->createStub(ConfigInterface::class);
        $config->method('getInt')->willReturnCallback(fn (string $key): int => match ($key) {
            'payment_due_days' => 14,
            'payment_due_days_before_validity' => 2,
            'payment_reminder_cooldown_days' => 7,
            default => 0
        });

        // Echte Klasse instanziieren statt zu mocken
        $calculator = new PermitFinancialCalculator($config, $clock);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())->method('dispatch'); // PaymentReminderEvent

        $handler = new SendPaymentRemindersHandler($repo, $config, $clock, $dispatcher, $calculator);
        $handler->handle(new SendPaymentRemindersCommand(null, false));
    }
}
