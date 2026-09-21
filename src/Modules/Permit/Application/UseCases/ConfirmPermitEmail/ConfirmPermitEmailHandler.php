<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\ConfirmPermitEmail;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\VerificationRepositoryInterface;
use App\Contracts\Utils\ClockInterface;
use App\Core\Entity\VerificationRequest;
use App\Modules\Permit\Application\UseCases\FinalizePermit\FinalizePermitCommand;
use App\Modules\Permit\Application\UseCases\FinalizePermit\FinalizePermitHandler;
use App\Modules\Permit\Domain\PermitStatus;
use App\Modules\Voucher\Application\UseCases\CalculateVoucherDiscount\CalculateVoucherDiscountHandler;
use App\Modules\Voucher\Application\UseCases\CalculateVoucherDiscount\CalculateVoucherDiscountQuery;
use App\Modules\Voucher\Application\UseCases\RedeemVoucher\RedeemVoucherCommand;
use App\Modules\Voucher\Application\UseCases\RedeemVoucher\RedeemVoucherHandler;

final readonly class ConfirmPermitEmailHandler
{
    public function __construct(
        private VerificationRepositoryInterface $verificationRepository,
        private ConfigInterface $config,
        private ClockInterface $clock,
        private CalculateVoucherDiscountHandler $calculateDiscountHandler,
        private RedeemVoucherHandler $redeemVoucherHandler,
        private FinalizePermitHandler $finalizePermitHandler,
    ) {
    }

    public function handle(ConfirmPermitEmailCommand $command): ConfirmPermitEmailResult
    {
        $allPending = $this->verificationRepository->loadPending();
        $input = \strtoupper(\trim($command->tokenOrCode));

        $matchedToken = null;
        foreach ($allPending as $t => $req) {
            $strToken = (string) $t;
            if (\strtoupper($strToken) === $input || \strtoupper((string) ($req->data['verification_code'] ?? '')) === $input) {
                $matchedToken = $strToken;
                break;
            }
        }

        if ($matchedToken === null) {
            $allVerified = $this->verificationRepository->loadVerified();
            foreach ($allVerified as $t => $req) {
                $strToken = (string) $t;
                if (\strtoupper($strToken) === $input || \strtoupper((string) ($req->data['verification_code'] ?? '')) === $input) {
                    return new ConfirmPermitEmailResult(true, null, $strToken, $req->data);
                }
            }

            return new ConfirmPermitEmailResult(false);
        }

        $token = $matchedToken;
        $req = $allPending[$token];
        $data = $req->data;

        unset($allPending[$token]);
        $this->verificationRepository->savePending($allPending);

        $hours = (int) $this->config->get('hours_pending_finalize', 48);
        $expires = $this->clock->now()->modify("+{$hours} hours");
        $data['verified_at'] = $this->clock->nowAsString();

        // Voucher Orchestration
        $voucherCodeStr = \strtoupper(\trim((string) ($data['voucher'] ?? '')));
        if ($voucherCodeStr !== '') {
            $discountQuery = new CalculateVoucherDiscountQuery($voucherCodeStr, (float) $data['preis']);
            $discountDto = $this->calculateDiscountHandler->handle($discountQuery);

            if ($discountDto->isValid) {
                // Einlösen!
                $redeemCmd = new RedeemVoucherCommand($voucherCodeStr, $data['name'] ?? 'Unbekannt', (string) ($data['parzelle'] ?? '0'));
                $this->redeemVoucherHandler->handle($redeemCmd);

                $finalPrice = $discountDto->finalPrice;

                if ($finalPrice <= 0.0) {
                    $data['preis'] = 0.0;
                    $data['status'] = PermitStatus::Bezahlt->value;

                    $allVerified = $this->verificationRepository->loadVerified();
                    $allVerified[$token] = new VerificationRequest($token, $expires, $data);
                    $this->verificationRepository->saveVerified($allVerified);

                    // Auto-Finalize
                    $permit = $this->finalizePermitHandler->handle(new FinalizePermitCommand($token, PermitStatus::Bezahlt, 'Gutschein (Voll-Rabatt): ' . $voucherCodeStr));

                    return new ConfirmPermitEmailResult(true, $permit);
                }

                $data['preis'] = $finalPrice;
                $data['voucher_applied'] = $voucherCodeStr;
                $data['voucher_details'] = ['type' => 'discount', 'value' => $discountDto->discountText];
            }
        }

        $allVerified = $this->verificationRepository->loadVerified();
        $allVerified[$token] = new VerificationRequest($token, $expires, $data);
        $this->verificationRepository->saveVerified($allVerified);

        $data['actual_token'] = $token;

        return new ConfirmPermitEmailResult(true, null, $token, $data);
    }
}
