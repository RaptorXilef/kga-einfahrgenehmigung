<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\HistoryCancelPermitRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ViewActionInterface;
use App\Core\Service\PermitService;

final readonly class HistoryCancelPermitAction implements ViewActionInterface
{
    public function __construct(
        private PermitService $permitService,
        private SessionManager $sessionManager,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = HistoryCancelPermitRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('history.php');
        }

        $email = (string) $this->sessionManager->getHistoryEmail();
        if ($email === '') {
            return new RedirectResponse('history.php');
        }

        try {
            $this->permitService->cancelPermit($dto->code, $email);
            $this->sessionManager->addFlash('success', 'Genehmigung wurde erfolgreich storniert.');

            return new RedirectResponse('history.php');
        } catch (\DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('history.php');
        }
    }
}
