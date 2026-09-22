<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Listeners;

use App\Contracts\Config\ConfigInterface;
use App\Modules\Identity\Domain\Events\RoleDeletedEvent;

final readonly class DeleteGroupImageListener
{
    public function __construct(private ConfigInterface $config)
    {
    }

    public function handle(RoleDeletedEvent $event): void
    {
        $iconPath = \rtrim(
            (string) $this->config->get('root_path'),
            '/\\',
        ) . '/public/assets/img/role/' . $event->roleId . '.webp';

        if (!\file_exists($iconPath)) {
            return;
        }

        @\unlink($iconPath);
    }
}
