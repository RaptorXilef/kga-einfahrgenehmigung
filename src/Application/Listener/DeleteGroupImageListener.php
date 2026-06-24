<?php

declare(strict_types=1);

namespace App\Application\Listener;

use App\Contracts\Config\ConfigInterface;
use App\Core\Event\GroupDeletedEvent;

final readonly class DeleteGroupImageListener
{
    public function __construct(private ConfigInterface $config)
    {
    }

    public function handle(GroupDeletedEvent $event): void
    {
        $iconPath = \rtrim(
            (string) $this->config->get('root_path'),
            '/\\',
        ) . '/public/assets/img/group_images/' . $event->groupId . '.webp';

        if (\file_exists($iconPath)) {
            @\unlink($iconPath);
        }
    }
}
