<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Listeners;

use App\Contracts\System\ImageStorageInterface;
use App\Modules\Identity\Domain\Events\RoleDeletedEvent;

/**
 * Löscht das zugehörige Rollen-Icon über das ImageStorageInterface, wenn eine Rolle entfernt wird.
 */
final readonly class DeleteGroupImageListener
{
    public function __construct(private ImageStorageInterface $imageStorage)
    {
    }

    public function handle(RoleDeletedEvent $event): void
    {
        $this->imageStorage->deleteImage('role', $event->roleId);
    }
}
