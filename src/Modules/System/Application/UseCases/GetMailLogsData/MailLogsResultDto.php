<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\GetMailLogsData;

final readonly class MailLogsResultDto
{
    public function __construct(
        /**
         * @var MailLogViewDto[]
         */
        public array $items,
        public int $total,
    ) {
    }
}
