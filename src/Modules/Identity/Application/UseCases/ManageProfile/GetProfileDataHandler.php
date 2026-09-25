<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageProfile;

use App\Contracts\System\ImageStorageInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use Override;
use PDO;

/**
 * Löst die Profildaten direkt über PDO auf, ohne die Domain-Entities zu bemühen (Pragmatic CQRS).
 *
 * @implements QueryHandlerInterface<GetProfileDataQuery, ProfileViewDto>
 */
final readonly class GetProfileDataHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
        private ImageStorageInterface $imageStorage,
    ) {
    }

    /**
     * @param GetProfileDataQuery $query
     */
    #[Override]
    public function handle(mixed $query): ProfileViewDto
    {
        $sql = '
            SELECT u.username, u.role_id, r.name AS role_name
            FROM users u
            LEFT JOIN roles r ON r.id = u.role_id
            WHERE u.id = :id
            LIMIT 1
        ';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $query->userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $username = \is_array($row) && isset($row['username']) ? (string) $row['username'] : 'Unbekannt';
        $roleId = \is_array($row) && isset($row['role_id']) ? (string) $row['role_id'] : 'guest';
        $roleName = \is_array($row) && isset($row['role_name']) && (string) $row['role_name'] !== ''
            ? (string) $row['role_name']
            : $roleId;

        return new ProfileViewDto(
            roleName: $roleName,
            userId: $query->userId,
            userImage: $this->imageStorage->getImageUrl('user', $query->userId, 'user.webp'),
            username: $username,
        );
    }
}
