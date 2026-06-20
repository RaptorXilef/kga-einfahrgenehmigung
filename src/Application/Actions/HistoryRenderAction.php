<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\ViewRenderRequest;
use App\Application\Http\ServerRequest;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Service\PermitService;
use App\Infrastructure\Storage\JsonHelper;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class HistoryRenderAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private PermitService $permitService,
        private SessionManager $sessionManager,
        private StorageInterface $storage,
        private TemplateRenderer $renderer,
    ) {
    }

    // TODO DOCBLOCK
    /**
     * Bereitet die Benutzeroberfläche (Login oder Datenliste) vor und lädt optionale Archivdaten.
     * Kombiniert Live-Daten mit historischen JSON-Jahresarchiven bei Bedarf.
     */
    public function execute(ServerRequest $request): mixed
    {
        $dto            = ViewRenderRequest::fromArray($request->get);
        $emailInSession = (string) $this->sessionManager->getHistoryEmail();
        $message        = $dto->message;
        $isSuccess      = $dto->isSuccess;
        $step           = $dto->step;

        if ($emailInSession === '') {
            $this->renderer->render('history_login', ['isSuccess' => $isSuccess, 'message' => $message, 'step' => $step]);

            return null;
        }

        $permits    = $this->permitService->getHistoryByEmail($emailInSession);
        $loadedYear = $dto->loadArchive;

        if ($loadedYear > 0) {
            $arcCfg      = $this->config->get('storage_config')['permits_archive'];
            $yearFile    = \str_replace('{YEAR}', (string) $loadedYear, $arcCfg['file_pattern'] ?? $arcCfg['file']);
            $archivePath = $this->config->getStoragePath($yearFile);

            if (\file_exists($archivePath)) {
                $archiveData = JsonHelper::read($archivePath);
                foreach ($archiveData as $item) {
                    if (\strtolower((string) $item['email']) === \strtolower($emailInSession)) {
                        $permits[] = $this->storage->mapToEntity($item);
                    }
                }
            }
        }

        \usort($permits, fn ($a, $b): int => $b->getCreatedAt() <=> $a->getCreatedAt());

        $overdueLevels = [];
        foreach ($permits as $permit) {
            $overdueLevels[$permit->code] = $this->permitService->getOverdueLevel($permit);
        }

        $this->renderer->render('history_list', [
            'currentArchiveYear' => $loadedYear,
            'email'              => $emailInSession,
            'isSuccess'          => $isSuccess,
            'message'            => $message,
            'overdueLevels'      => $overdueLevels,
            'permits'            => $permits,
        ]);

        return null;
    }
}
