<?php

declare(strict_types=1);

namespace App\Application\Actions\Api\Admin;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Core\Service\AuthService;
use App\Modules\Identity\Domain\UserRepositoryInterface;

/**
 * Action um die Release Notes für einen Administrator dauerhaft auszublenden.
 */
#[Route('POST', '/api/mark_changelog_read')]
#[RequiresAuth]
final readonly class MarkChangelogReadAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $version = \trim((string) ($request->post['version'] ?? ''));
        if ($version === '') {
            return JsonResponse::error('Keine Version übergeben.', 400);
        }

        $userId = $this->auth->getUserId();

        // Virtuelle System-Accounts (Backdoor, Superadmin aus .php Dateien) ignorieren
        if (\str_starts_with($userId, 'sys_')) {
            return JsonResponse::success(['message' => 'Für System-Accounts übersprungen.']);
        }

        $user = $this->userRepository->findById($userId);
        if ($user !== null) {
            $user->markChangelogAsRead($version);
            $this->userRepository->save($user);
        }

        return JsonResponse::success();
    }
}
