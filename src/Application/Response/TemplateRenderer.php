<?php

declare(strict_types=1);

namespace App\Application\Response;

use App\Application\Session\SessionManager;

final readonly class TemplateRenderer
{
    public function __construct(
        private string $basePath,
        private SessionManager $sessionManager,
    ) {
    }

    public function render(string $template, array $data = []): void
    {
        $path = $this->basePath . '/' . \ltrim($template, '/') . '.phtml';
        if (! \file_exists($path)) {
            throw new \RuntimeException("Template nicht gefunden: $path");
        }

        // Lade alle Flashes automatisch in die View-Daten!
        $data['flashes'] = $this->sessionManager->getFlashes();

        \extract($data, \EXTR_SKIP);
        require $path;
    }
}
