<?php

declare(strict_types=1);

namespace App\Application\Routing;

use App\Bootstrap\Container;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\ViewActionInterface;

final readonly class UniversalActionFactory
{
    public function __construct(
        private Container $container,
        private ActionRegistry $registry,
    ) {
    }

    public function create(string $actionKey): ActionInterface|ViewActionInterface|null
    {
        $class = $this->registry->getActionClass($actionKey);

        if ($class !== null) {
            return $this->container->get($class);
        }

        return null;
    }
}
