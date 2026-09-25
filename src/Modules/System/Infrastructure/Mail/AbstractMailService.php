<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Mail;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\System\Domain\MailLogEntry;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
use DateTimeImmutable;
use Exception;
use Override;
use PDO;
use RuntimeException;

/**
 * Abstrakte Basisklasse für alle E-Mail-Transports (SMTP, Graph, OAuth).
 */
abstract class AbstractMailService implements MailLogInterface, MailServiceInterface
{
    public function __construct(
        protected ?PDO $pdo,
        protected ConfigInterface $config,
        protected JsonHelperInterface $jsonHelper,
        protected ClockInterface $clock,
    ) {
    }

    #[Override]
    public function sendTemplate(string $recipient, string $subject, string $template, array $data, ?string $replyTo = null, int $priority = 50, array $attachments = []): bool|string
    {
        if (\in_array(\trim($recipient), ['', '0'], true)) {
            $this->logEmail('System', $subject, clone new TemplateKey($template), 'Übersprungen: Kein Empfänger angegeben', null, $data);

            return true;
        }

        $mailConfig = $this->config->getMailSettings();
        $isTestMode = $this->config->isTestMode();
        $isDebugMode = $this->config->getBool('debug_mode', false);
        $actualRecipient = $recipient;

        if ($isTestMode) {
            $actualRecipient = (string) ($mailConfig['catch_all_recipient'] ?? 'sandbox@example.com');
            $subject = '[TEST] ' . $subject;
        }

        $body = $this->render($template, $data);

        if ($isDebugMode) {
            $spoolDir = \rtrim($this->config->getString('root_path'), '/\\') . '/storage/debug_mails';
            if (!\is_dir($spoolDir)) {
                @\mkdir($spoolDir, 0o755, true);
            }

            $fileNameOnly = $this->clock->now()->format('Ymd_His') . '_' . \bin2hex(\random_bytes(8)) . '.html';
            $filename = $spoolDir . '/' . $fileNameOnly;

            $debugHeader = "<div style=\"background: #f8d7da; color: #721c24; padding: 15px; margin-bottom: 20px; font-family: sans-serif; border: 1px solid #f5c6cb; border-radius: 5px;\">\n";
            $debugHeader .= "<strong>[DEBUG MODE SPOOLER]</strong><br>\n";
            $debugHeader .= '<strong>Original Recipient:</strong> ' . \htmlspecialchars($recipient) . "<br>\n";
            $debugHeader .= '<strong>Actual Recipient:</strong> ' . \htmlspecialchars($actualRecipient) . "<br>\n";
            $debugHeader .= '<strong>Subject:</strong> ' . \htmlspecialchars($subject) . "<br>\n";
            $debugHeader .= '<strong>Attachments:</strong> ' . \count($attachments) . "<br>\n";
            $debugHeader .= "</div>\n\n";

            @\file_put_contents($filename, $debugHeader . $body);

            foreach ($attachments as $i => $att) {
                $attFileName = $spoolDir . '/' . $fileNameOnly . '_attach_' . $i . '_' . $att['name'];
                @\file_put_contents($attFileName, $att['content']);
            }

            $data['_debug_file'] = $fileNameOnly;
            $this->logEmail($recipient, $subject, clone new TemplateKey($template), 'Erfolg (Debug-Spool)', $replyTo, $data);

            return true;
        }

        $transportConfig = $this->getTransportConfig($mailConfig);
        $status = $this->dispatch($actualRecipient, $subject, $body, $transportConfig, $replyTo, $attachments);
        $logStatus = $status === true && $isTestMode ? 'Erfolg (Test-Routing an ' . $actualRecipient . ')' : $status;
        $this->logEmail($recipient, $subject, clone new TemplateKey($template), $logStatus, $replyTo, $data);

        return $status;
    }

    #[Override]
    public function saveLogs(array $logs, bool $forceSql = false): void
    {
        $cfg = $this->config->getArray('storage_config')['mail_log'] ?? [];
        $table = $cfg['table'] ?? 'mail_logs';
        if (!$this->pdo instanceof PDO) {
            return;
        }

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("REPLACE INTO `{$table}` (id,timestamp,recipient,reply_to,subject,template,status,data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($logs as $log) {
                $stmt->execute([
                    $log->id,
                    $log->timestamp->format('Y-m-d H:i:s'),
                    $log->recipient,
                    $log->replyTo,
                    $log->subject,
                    $log->template->value,
                    $log->status,
                    \json_encode($log->data, \JSON_UNESCAPED_UNICODE),
                ]);
            }
            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    #[Override]
    public function loadLogs(): array
    {
        $cfg = $this->config->getArray('storage_config')['mail_log'] ?? [];
        $table = $cfg['table'] ?? 'mail_logs';
        $logs = [];

        if ($this->pdo instanceof PDO) {
            $stmt = $this->pdo->query("SELECT * FROM `{$table}` ORDER BY timestamp DESC");
            if ($stmt !== false) {
                while (\is_array($r = $stmt->fetch(PDO::FETCH_ASSOC))) {
                    $logs[] = new MailLogEntry(
                        (string) $r['id'],
                        new DateTimeImmutable((string) $r['timestamp']),
                        (string) ($r['recipient'] ?? ''),
                        isset($r['reply_to']) ? (string) $r['reply_to'] : null,
                        (string) ($r['subject'] ?? ''),
                        new TemplateKey((string) ($r['template'] ?: 'std_7')),
                        (string) ($r['status'] ?? ''),
                        \is_string($r['data'] ?? null) ? $this->jsonHelper->decode($r['data']) : (\is_array($r['data'] ?? null) ? $r['data'] : []),
                    );
                }
            }
        }

        return $logs;
    }

    #[Override]
    public function getDebugMailContent(string $filename): ?string
    {
        if (!\preg_match('/^[a-zA-Z0-9_]+\.html$/', $filename)) {
            return null;
        }

        $path = \rtrim($this->config->getString('root_path'), '/\\') . '/storage/debug_mails/' . $filename;
        if (!\file_exists($path)) {
            return null;
        }

        $content = \file_get_contents($path);

        return $content !== false ? $content : null;
    }

    #[Override]
    public function processQueue(int $limit = 5): int
    {
        return 0;
    }

    abstract protected function dispatch(string $recipient, string $subject, string $body, array $transportConfig, ?string $replyTo = null, array $attachments = []): bool|string;

    protected function render(string $templatePath, array $data): string
    {
        $root = $this->config->getString('root_path');
        $fullPath = $root . "/templates/emails/{$templatePath}.phtml";

        if (!\file_exists($fullPath)) {
            throw new RuntimeException("Mail-Template nicht gefunden: {$fullPath}");
        }

        \extract($data, \EXTR_SKIP);

        \ob_start();
        include $fullPath;

        return (string) \ob_get_clean();
    }

    protected function getTransportConfig(array $mailConfig): array
    {
        $default = (string) ($mailConfig['default'] ?? 'smtp');

        return \is_array($mailConfig['transports'][$default] ?? null) ? $mailConfig['transports'][$default] : [];
    }

    private function logEmail(string $recipient, string $subject, TemplateKey $template, bool|string $status, ?string $replyTo = null, array $data = []): void
    {
        $statusStr = $status === true ? 'Erfolg' : 'Fehler: ' . $status;
        $maxEntries = $this->config->getInt('mail_log_max_entries', 200);

        $entry = new MailLogEntry(
            'ml_' . \bin2hex(\random_bytes(8)),
            $this->clock->now(),
            $recipient,
            $replyTo,
            $subject,
            $template,
            $statusStr,
            $data,
        );

        $logs = $this->loadLogs();
        \array_unshift($logs, $entry);

        if (\count($logs) > $maxEntries) {
            $logs = \array_slice($logs, 0, $maxEntries);
        }

        $this->saveLogs($logs, true);
    }
}
