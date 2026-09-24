<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\GetMailLogsData;

/**
 * 100% logikfreies DTO für eine Zeile in der Mail-Log-Tabelle.
 */
final readonly class MailLogViewDto
{
    public function __construct(
        public string $dateFormatted,
        public string $timeFormatted,
        public string $recipientHtml,
        public string $recipientRaw,
        public string $subject,
        public string $templateKey,
        public bool $isSuccess,
        public string $statusText,
        public string $statusBadgeClass,
        public string $statusIconUrl,
        public string $errorMessage,
        public string $debugUrl,
        public string $timestampRaw,
    ) {
    }
}
