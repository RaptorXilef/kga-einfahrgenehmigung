<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\GetMailLogsData;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\AssetHelperInterface;
use App\Contracts\System\JsonHelperInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use DateTimeImmutable;
use PDO;

/**
 * Holt die Mail-Logs per SQL-Paginierung.
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
    public function handle(mixed $query): MailLogsResultDto
    {
        $cfg = $this->config->get('storage_config')['mail_log'];
        $table = $cfg['table'] ?? 'mail_logs';

        $stmtCount = $this->pdo->query("SELECT COUNT(*) FROM `{$table}`");
        $total = (int) $stmtCount->fetchColumn();

        $offset = ($query->page - 1) * $query->limit;
        $limit = $query->limit;

        $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` ORDER BY timestamp DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $dtos = [];
        $isDebugMode = $this->config->get('debug_mode', false) === true;
        $baseUrl = \rtrim($this->config->getBaseUrl(), '/') . '/';

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $dt = new DateTimeImmutable($r['timestamp']);
            $data = \is_string($r['data']) ? $this->jsonHelper->decode($r['data']) : [];
            $status = (string) $r['status'];
            $isSuccess = $status === 'Erfolg' || \str_starts_with($status, 'Erfolg');

            $debugUrl = '';
            if ($isDebugMode && !empty($data['_debug_file'])) {
                $debugUrl = $baseUrl . 'debug_mail?file=' . \urlencode($data['_debug_file']);
            }

            $dtos[] = new MailLogViewDto(
                dateFormatted: $dt->format('d.m.Y'),
                timeFormatted: $dt->format('H:i'),
                recipientHtml: \str_replace('@', '<wbr>@', \htmlspecialchars((string) $r['recipient'])),
                recipientRaw: (string) $r['recipient'],
                subject: (string) $r['subject'],
                templateKey: (string) $r['template'],
                isSuccess: $isSuccess,
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
