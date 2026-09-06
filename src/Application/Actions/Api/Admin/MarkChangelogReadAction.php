<?php

declare(strict_types=1);

namespace App\Application\Actions\Api\Admin;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\JsonResponse;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Entity\User;
use App\Core\Service\AuthService;

/**
 * Action um die Release Notes für einen Administrator dauerhaft auszublenden.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
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

        $users = $this->userRepository->loadAll();
        if (isset($users[$userId])) {
            $u = $users[$userId];
            // Wir aktualisieren den User mit der neuen Version
            $users[$userId] = new User(
                $u->id,
                $u->username,
                $u->roleId,
                $u->passwordHash,
                $version,
            );
            $this->userRepository->saveAll($users);
        }

        return JsonResponse::success();
    }
}
