<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\GetBackupsData;

use App\SharedKernel\Application\Query\QueryInterface;

/**
 * Query zum Abrufen aller Backup-Snapshots und Backup-Optionen für das Admin-Dashboard.
 */
final readonly class GetBackupsDataQuery implements QueryInterface
{
}
