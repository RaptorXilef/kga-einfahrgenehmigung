<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Security\AuthorizationInterface;
use Override;

#[Route('POST', '/api/mark_changelog_read')]
#[RequiresAuth]
final readonly class MarkChangelogReadAction implements ActionInterface
{
    public function __construct(
        private AuthorizationInterface $auth,
        private MarkChangelogReadHandler $markChangelogHandler,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        try {
            $dto = MarkChangelogReadRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            return JsonResponse::error($e->getMessage(), 400);
        }

        $userId = $this->auth->getUserId();

        if (\str_starts_with($userId, 'sys_')) {
            return JsonResponse::success(['message' => 'Für System-Accounts übersprungen.']);
        }

        $this->markChangelogHandler->handle(new MarkChangelogReadCommand(
            userId: $userId,
            version: $dto->version,
        ));

        return JsonResponse::success();
    }
}
