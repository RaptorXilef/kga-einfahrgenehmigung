<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetVerifiedRequest;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\DTO\SimpleTokenRequest;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;

#[Route('GET', '/permit_edit')]
#[Route('POST', '/permit_edit')]
final readonly class PermitEditAction implements ViewActionInterface
{
    public function __construct(
        private GetVerifiedRequestHandler $getVerifiedHandler,
        private SessionManager $sessionManager,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $dto = SimpleTokenRequest::fromArray($request->get);
        $tempData = $this->getVerifiedHandler->handle(new GetVerifiedRequestQuery($dto->token));

        if ($tempData !== null) {
            $this->sessionManager->setFormData($tempData);
            $this->sessionManager->setEditState($tempData['email'] ?? '', $dto->token);
        }

        return new RedirectResponse('./?edit=1');
    }
}
