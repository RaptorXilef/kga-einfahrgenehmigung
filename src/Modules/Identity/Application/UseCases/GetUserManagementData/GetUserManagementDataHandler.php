<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\GetUserManagementData;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\AssetHelperInterface;
use App\Contracts\System\ImageStorageInterface;
use App\Modules\Identity\Presentation\View\PermissionTreePresenter;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use Override;
use PDO;

/**
 * @implements QueryHandlerInterface<GetUserManagementDataQuery, UserManagementViewDto>
 */
final readonly class GetUserManagementDataHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
        private ImageStorageInterface $imageStorage,
        private AssetHelperInterface $assetHelper,
    ) {
    }

    /**
     * @param GetUserManagementDataQuery $query
     */
    #[Override]
    public function handle(mixed $query): UserManagementViewDto
    {
        // 1. Rollen speicherschonend laden & parsen
        $stmtRoles = $this->pdo->query('SELECT * FROM roles ORDER BY name ASC');

        $rolesMap = [];
        $globalRoleOptions = [];
        $roleDtos = [];
        $structure = $this->config->getArray('structure');

        if ($stmtRoles !== false) {
            while (\is_array($r = $stmtRoles->fetch(PDO::FETCH_ASSOC))) {
                $id = (string) $r['id'];
                $name = (string) $r['name'];
                $perms = \is_string($r['permissions']) ? (\json_decode($r['permissions'], true) ?: []) : [];
                $rolesMap[$id] = $name;

                $globalRoleOptions[] = [
                    'value' => $id,
                    'label' => $name,
                    'selectedAttr' => '',
                ];

                $isMasterActive = \in_array('*', $perms, true);

                $roleDtos[] = new RoleListDto(
                    id: $id,
                    idHash: \md5($id),
                    name: $name,
                    iconUrl: $this->imageStorage->getImageUrl('role', $id, 'shield.webp'),
                    canBeDeleted: $id !== 'admin',
                    masterCheckboxAttr: $isMasterActive ? 'checked' : '',
                    treeWrapperClass: $isMasterActive ? 'is-master-active' : '',
                    treeHtml: PermissionTreePresenter::renderTree($structure, $perms, 0, $this->assetHelper),
                );
            }
        }

        // 2. Benutzer speicherschonend laden & DTOs mappen
        $stmtUsers = $this->pdo->query('SELECT * FROM users ORDER BY username ASC');

        $userDtos = [];
        if ($stmtUsers !== false) {
            while (\is_array($u = $stmtUsers->fetch(PDO::FETCH_ASSOC))) {
                $uid = (string) $u['id'];
                $roleId = (string) ($u['role_id'] ?? $u['group'] ?? 'guest');
                $username = (string) $u['username'];
                $displayName = $username !== '' ? $username : $uid;

                $userRoleOptions = [];
                foreach ($globalRoleOptions as $opt) {
                    $opt['selectedAttr'] = $opt['value'] === $roleId ? 'selected' : '';
                    $userRoleOptions[] = $opt;
                }

                $userDtos[] = new UserListDto(
                    id: $uid,
                    idHash: \md5($uid),
                    username: $username,
                    displayName: $displayName,
                    roleName: $rolesMap[$roleId] ?? 'Unbekannt',
                    avatarUrl: $this->imageStorage->getImageUrl('user', $uid, 'user.webp'),
                    roleOptions: $userRoleOptions,
                    confirmDeleteMsg: "Soll der Benutzer '{$displayName}' wirklich gelöscht werden?",
                );
            }
        }

        return new UserManagementViewDto(
            users: $userDtos,
            roles: $roleDtos,
            globalRoleOptions: $globalRoleOptions,
            userCount: \count($userDtos),
            canManageSystem: $query->auth->hasPermission('system.manage'),
            canManageUsers: $query->auth->hasPermission('system.users.manage'),
            canManageRoles: $query->auth->hasPermission('system.roles.manage'),
            hasGodMode: $query->auth->hasPermission('*'),
        );
    }
}
