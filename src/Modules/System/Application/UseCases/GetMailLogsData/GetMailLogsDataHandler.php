<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\GetMailLogsData;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\AssetHelperInterface;
use App\Contracts\System\JsonHelperInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use App\SharedKernel\Application\Query\QueryInterface;
use DateTimeImmutable;
use Override;
use PDO;

/**
 * Holt die Mail-Logs per SQL-Paginierung und Cursor-Iteration.
 * Verhindert, dass Tausende E-Mails auf einmal in den RAM geladen werden.
 *
 * @implements QueryHandlerInterface<GetMailLogsDataQuery, MailLogsResultDto>
 */
final readonly class GetMailLogsDataHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
        private AssetHelperInterface $assetHelper,
        private JsonHelperInterface $jsonHelper,
    ) {
    }

    /**
     * @param GetMailLogsDataQuery $query
     */
    #[Override]
    public function handle(QueryInterface $query): MailLogsResultDto
    {
        $cfg = $this->config->getArray('storage_config')['mail_log'] ?? [];
        $table = $cfg['table'] ?? 'mail_logs';

        $stmtCount = $this->pdo->query("SELECT COUNT(*) FROM `{$table}`");
        $total = $stmtCount !== false ? (int) $stmtCount->fetchColumn() : 0;

        $offset = ($query->page - 1) * $query->limit;
        $limit = $query->limit;

        $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` ORDER BY timestamp DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $dtos = [];
        $isDebugMode = $this->config->getBool('debug_mode', false);
        $baseUrl = \rtrim($this->config->getBaseUrl(), '/') . '/';

        while (\is_array($r = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $dt = new DateTimeImmutable((string) $r['timestamp']);
            $data = \is_string($r['data']) ? $this->jsonHelper->decode($r['data']) : [];
            $status = (string) $r['status'];
            $isSuccess = $status === 'Erfolg' || \str_starts_with($status, 'Erfolg');

            $debugUrl = '';
            $debugFile = \trim((string) ($data['_debug_file'] ?? ''));
            if ($isDebugMode && $debugFile !== '') {
                $debugUrl = $baseUrl . 'debug_mail?file=' . \urlencode($debugFile);
            }

            $dtos[] = new MailLogViewDto(
                dateFormatted: $dt->format('d.m.Y'),
                timeFormatted: $dt->format('H:i'),
                recipientHtml: \str_replace('@', '<wbr>@', \htmlspecialchars((string) $r['recipient'])),
                recipientRaw: (string) $r['recipient'],
                subject: (string) $r['subject'],
                templateKey: (string) $r['template'],
                isSuccess: $isSuccess,
                rowClass: !$isSuccess ? 'c-table__row--danger' : '',
                statusText: $isSuccess ? 'GESENDET' : 'FEHLER',
                statusBadgeClass: $isSuccess ? 'success' : 'danger',
                statusIconUrl: $this->assetHelper->url('assets/img/icons/' . ($isSuccess ? 'success.webp' : 'error.webp')),
                errorMessage: !$isSuccess ? $status : '',
                debugUrl: $debugUrl,
                timestampRaw: $dt->format('Y-m-d H:i:s'),
            );
        }

        return new MailLogsResultDto($dtos, $total);
    }
}
