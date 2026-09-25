<?php

declare(strict_types=1);

namespace App\Contracts\Bootstrap;

use App\Contracts\DependencyInjection\ContainerInterface;

/**
 * Interface für alle Service Provider im Dependency Injection Container.
 */
interface ServiceProviderInterface
{
    /**
     * Registriert Services, Repositories oder Controller im Container.
     *
     * @param ContainerInterface $container Die Instanz des DI-Containers.
     */
    public function register(ContainerInterface $container): void;
}
