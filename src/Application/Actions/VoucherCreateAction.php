<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Contracts\ActionInterface;
use App\Application\DTO\VoucherCreateRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\AuthService;
use App\Core\Service\VoucherService;

/**
 * Action zum Erstellen eines neuen Gutscheins.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('create_voucher')]
final readonly class VoucherCreateAction implements ActionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private AuthService $auth,
        private SessionManager $sessionManager,
        private VoucherService $voucherService,
    ) {
    }

    /**
     * Erstellt einen neuen Gutschein mit spezifischen Konditionen über VoucherService.
     *
     * Kontext: Beinhaltet Sicherheitsprüfung (hasPermission). Übergibt diverse Gutschein-Parameter.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = VoucherCreateRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            // UX-Rettung für die Gutschein-Erstellung
            $postData = $request->post;
            unset($postData['csrf_token']);
            $this->sessionManager->setFormData($postData);

            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('admin.php?focus=tab-tools');
        }

        try {
            $code = $this->voucherService->createVoucher(
                $dto->reason,
                $this->auth->getUserId(),
                $dto->templateKey,
                $dto->prefillData,
                $dto->type,
                $dto->value,
                $dto->isMultiUse,
                $dto->maxUses,
                $dto->customCode,
                $dto->expiresAt,
                $dto->dateMode,
            );

            // LOG SCHREIBEN
            $this->auditLogger->log('VOUCHER_CREATE', "Gutscheincode '{$code}' erstellt. Grund/Notiz: {$dto->reason}");

            $this->sessionManager->addFlash('success', "Gutschein erstellt: <strong>$code</strong>");

            // Wenn erfolgreich, direkt zum Gutschein-Reiter springen
            return new RedirectResponse('admin.php?focus=tab-vouchers');

        } catch (\Exception $e) {
            $postData = $request->post;
            unset($postData['csrf_token']);
            $this->sessionManager->setFormData($postData);
            $this->sessionManager->addFlash('error', 'Fehler: ' . $e->getMessage());

            return new RedirectResponse('admin.php?focus=tab-tools');
        }
    }
}
