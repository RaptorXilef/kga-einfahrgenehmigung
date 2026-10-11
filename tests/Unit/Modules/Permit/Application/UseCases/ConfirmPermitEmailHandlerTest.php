<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Permit\Application\UseCases;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Integration\VoucherDiscountResult;
use App\Contracts\Integration\VoucherIntegrationInterface;
use App\Modules\Permit\Application\UseCases\ConfirmPermitEmail\ConfirmPermitEmailCommand;
use App\Modules\Permit\Application\UseCases\ConfirmPermitEmail\ConfirmPermitEmailHandler;
use App\Modules\Permit\Application\UseCases\FinalizePermit\FinalizePermitHandler;
use App\Modules\Permit\Domain\VerificationRepositoryInterface;
use App\Modules\Permit\Domain\VerificationRequest;
use App\SharedKernel\Infrastructure\Utils\SystemClock;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConfirmPermitEmailHandler::class)]
final class ConfirmPermitEmailHandlerTest extends TestCase
{
    #[Test]
    public function itMovesRequestFromPendingToVerifiedAndReturnsToken(): void
    {
        $req = new VerificationRequest('token123', new DateTimeImmutable(), ['preis' => 10.0]);

        $repo = $this->createMock(VerificationRepositoryInterface::class);
        $repo->method('findPendingByTokenOrCode')->with('123456')->willReturn($req);
        $repo->expects($this->once())->method('deletePending')->with('token123');
        $repo->expects($this->once())->method('saveVerifiedOne');

        $voucher = $this->createStub(VoucherIntegrationInterface::class);
        $config = $this->createStub(ConfigInterface::class);

        $handler = new ConfirmPermitEmailHandler($repo, $config, new SystemClock(), $voucher, $this->createStub(FinalizePermitHandler::class));

        $token = $handler->handle(new ConfirmPermitEmailCommand('123456', '127.0.0.1'));
        $this->assertSame('token123', $token);
    }

    #[Test]
    public function itRedeemsVoucherAndAutoFinalizesIfDiscountIs100Percent(): void
    {
        $req = new VerificationRequest('token123', new DateTimeImmutable(), ['preis' => 10.0, 'voucher' => 'V-100', 'name' => 'Max']);

        $repo = $this->createMock(VerificationRepositoryInterface::class);
        $repo->method('findPendingByTokenOrCode')->willReturn($req);
        $repo->expects($this->once())->method('saveVerifiedOne');

        $voucher = $this->createMock(VoucherIntegrationInterface::class);
        $voucher->method('calculateDiscount')->willReturn(new VoucherDiscountResult(0.0, true, '100% Free', ''));
        // Erwartet, dass der Gutschein eingelöst wird
        $voucher->expects($this->once())->method('redeemVoucher')->with('V-100', 'Max', '0');

        $finalize = $this->createMock(FinalizePermitHandler::class);
        // Erwartet Auto-Finalisierung, da Preis = 0.0
        $finalize->expects($this->once())->method('handle')->willReturn('PERMIT-123');

        $config = $this->createStub(ConfigInterface::class);

        $handler = new ConfirmPermitEmailHandler($repo, $config, new SystemClock(), $voucher, $finalize);

        $result = $handler->handle(new ConfirmPermitEmailCommand('123456', '127.0.0.1'));
        $this->assertSame('PERMIT-123', $result); // Gibt den finalen Code zurück, da Auto-Finalize!
    }

    #[Test]
    public function itThrowsExceptionIfTokenIsInvalid(): void
    {
        $repo = $this->createStub(VerificationRepositoryInterface::class);
        $repo->method('findPendingByTokenOrCode')->willReturn(null);
        $repo->method('findVerifiedByTokenOrCode')->willReturn(null);

        $handler = new ConfirmPermitEmailHandler(
            $repo,
            $this->createStub(ConfigInterface::class),
            new SystemClock(),
            $this->createStub(VoucherIntegrationInterface::class),
            $this->createStub(FinalizePermitHandler::class),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Sitzung abgelaufen oder Bestätigungscode ungültig.');

        $handler->handle(new ConfirmPermitEmailCommand('invalid', '127.0.0.1'));
    }
}
