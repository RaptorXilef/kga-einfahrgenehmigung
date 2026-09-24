<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\GetMailLogsData;

use App\SharedKernel\Application\Query\QueryInterface;

final readonly class GetMailLogsDataQuery implements QueryInterface
{
    public function __construct(
        public int $page,
        public int $limit,
    ) {
    }
}
