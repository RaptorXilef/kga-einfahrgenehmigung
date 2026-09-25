<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ConfirmPermitEmail;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Integration\VoucherIntegrationInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Application\UseCases\FinalizePermit\FinalizePermitCommand;
use App\Modules\Permit\Application\UseCases\FinalizePermit\FinalizePermitHandler;
use App\Modules\Permit\Domain\PermitStatus;
use App\Modules\Permit\Domain\VerificationRepositoryInterface;
use App\Modules\Permit\Domain\VerificationRequest;
use App\SharedKernel\Application\Command\CommandWithResultHandlerInterface;
use DomainException;
use Override;

/**
 * Orchestriert die Bestätigung atomar und gibt den resultierenden Identifikator (Token oder Code) zurück.
 *
 * @implements CommandWithResultHandlerInterface<ConfirmPermitEmailCommand, string>
 */
final readonly class ConfirmPermitEmailHandler implements CommandWithResultHandlerInterface
{
    public function __construct(
        private VerificationRepositoryInterface $verificationRepository,
        private ConfigInterface $config,
        private ClockInterface $clock,
        private VoucherIntegrationInterface $voucherIntegration,
        private FinalizePermitHandler $finalizePermitHandler,
    ) {
    }

    /**
     * @param ConfirmPermitEmailCommand $command
     *
     * @return string Gibt entweder den Checkout-Token oder (bei sofortiger Freischaltung) den Genehmigungscode zurück.
     * @throws DomainException Wenn der Code ungültig ist.
     */
    #[Override]
    public function handle(mixed $command): string
    {
        $pendingReq = $this->verificationRepository->findPendingByTokenOrCode($command->tokenOrCode);

        if (!$pendingReq instanceof VerificationRequest) {
            $verifiedReq = $this->verificationRepository->findVerifiedByTokenOrCode($command->tokenOrCode);
            if ($verifiedReq instanceof VerificationRequest) {
                return $verifiedReq->token;
            }

            throw new DomainException('Sitzung abgelaufen oder Bestätigungscode ungültig.');
        }

        $token = $pendingReq->token;
        $data = $pendingReq->data;

        $this->verificationRepository->deletePending($token);

        $hours = (int) $this->config->get('hours_pending_finalize', 48);
        $expires = $this->clock->now()->modify("+{$hours} hours");
        $data['verified_at'] = $this->clock->nowAsString();

        // Voucher Orchestration via Integration Service
        $voucherCodeStr = \strtoupper(\trim((string) ($data['voucher'] ?? '')));
        if ($voucherCodeStr !== '') {
            $discountResult = $this->voucherIntegration->calculateDiscount($voucherCodeStr, (float) $data['preis']);

            if ($discountResult->isValid) {
                // Einlösen!
                $this->voucherIntegration->redeemVoucher($voucherCodeStr, (string) ($data['name'] ?? 'Unbekannt'), (string) ($data['parzelle'] ?? '0'));

                $finalPrice = $discountResult->finalPrice;

                if ($finalPrice <= 0.0) {
                    $data['preis'] = 0.0;
                    $data['status'] = PermitStatus::Bezahlt->value;

                    $this->verificationRepository->saveVerifiedOne(new VerificationRequest($token, $expires, $data));

                    // Auto-Finalize durchführen
                    return $this->finalizePermitHandler->handle(new FinalizePermitCommand($token, PermitStatus::Bezahlt, 'Gutschein (Voll-Rabatt): ' . $voucherCodeStr));
                }

                $data['preis'] = $finalPrice;
                $data['voucher_applied'] = $voucherCodeStr;
                $data['voucher_details'] = ['type' => 'discount', 'value' => $discountResult->discountText];
            }
        }

        $this->verificationRepository->saveVerifiedOne(new VerificationRequest($token, $expires, $data));

        return $token;
    }
}
