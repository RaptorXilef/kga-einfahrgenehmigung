<?php

declare(strict_types=1);

namespace App\Contracts\DependencyInjection;

use Closure;

interface ContainerInterface
{
    /**
     * Löst eine Klasse oder einen Service-Bezeichner über den DI-Container auf.
     *
     * @template T of object
     *
     * @param class-string<T>|string $id
     *
     * @return ($id is class-string<T> ? T : mixed)
     */
    public function get(string $id): mixed;

    public function bind(string $id, Closure $resolver): void;
}
