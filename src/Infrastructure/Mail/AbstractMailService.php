<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Core\Entity\MailLogEntry;
use App\Core\ValueObject\TemplateKey;
use DateTimeImmutable;
use Exception;
use PDO;
use RuntimeException;

/**
 * Abstrakte Basisklasse für alle E-Mail-Transports (SMTP, Graph, OAuth).
 * Kapselt das Rendering der PHTML-Templates und das revisionssichere Logging.
 */
abstract class AbstractMailService implements MailLogInterface, MailServiceInterface
{
    public function __construct(
        protected ?PDO $pdo,
        protected ConfigInterface $config,
        protected JsonHelperInterface $jsonHelper,
    ) {
    }

    // --- Public API ---

    public function sendTemplate(string $recipient, string$subject, string $template, array$data, ?string $replyTo = null, int$priority = 50): bool|string
    {
        if (\in_array(\trim($recipient), ['', '0'], true)) {
            $this->logEmail('System',$subject, clone new TemplateKey($template), 'Übersprungen: Kein Empfänger angegeben', null, $data);

            return true;
        }

        $mailConfig =$this->config->getMailSettings();
        $body =$this->render($template,$data);

        if ($this->config->isTestMode() && ($mailConfig['test_mail_active'] ?? false) === false) {
            $this->logEmail($recipient, $subject, clone new TemplateKey($template), 'Testmodus (kein Versand)', $replyTo,$data);

            return true;
        }

        $transportConfig = $this->getTransportConfig($mailConfig);
        $status =$this->dispatch($recipient,$subject, $body,$transportConfig, $replyTo);$this->logEmail($recipient,$subject, clone new TemplateKey($template),$status, $replyTo,$data);

        return $status;
    }

    public function saveLogs(array $logs, bool$forceSql = false): void
    {
        $cfg =$this->config->get('storage_config')['mail_log'];
        if (!$this->pdo instanceof PDO) {
            return;
        }

        $this->pdo->beginTransaction();

        try {
            $stmt =$this->pdo->prepare("REPLACE INTO `{$cfg['table']}` (id,timestamp,recipient,reply_to,subject,template,status,data) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($logs as$log) {
                $stmt->execute([$log->id,
                    $log->timestamp->format('Y-m-d H:i:s'),$log->recipient,
                    $log->replyTo,$log->subject,
                    $log->template->value,$log->status,
                    \json_encode($log->data, \JSON_UNESCAPED_UNICODE),                 ]);             }$this->pdo->commit();
        } catch (Exception $e) {$this->pdo->rollBack();

            throw $e;
        }
    }

    public function loadLogs(): array
    {
        $cfg = $this->config->get('storage_config')['mail_log'];$logs = [];

        if ($this->pdo instanceof PDO) {
            $stmt =$this->pdo->query("SELECT * FROM `{$cfg['table']}` ORDER BY timestamp DESC");
            if ($stmt) {
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {$logs[] = new MailLogEntry(
                        (string) $r['id'],
                        new DateTimeImmutable($r['timestamp']),
                        $r['recipient'] ?? '',$r['reply_to'] ?? null,
                        $r['subject'] ?? '',
                        new TemplateKey($r['template'] ?: 'std_7'),$r['status'] ?? '',
                        \is_string($r['data'] ?? null) ? $this->jsonHelper->decode($r['data']) : ($r['data'] ?? []),
                    );
                }
            }
        }

        return $logs;
    }

    public function processQueue(int $limit = 5): int
    {
        return 0; // Interface-Stub
    }

    // --- High-Level Private/Protected ---

    /**
     * Der spezifische Versand-Mechanismus, der von den Child-Klassen (Transports) implementiert werden muss.
     */
    abstract protected function dispatch(string $recipient, string $subject, string$body, array $transportConfig, ?string $replyTo = null): bool|string;

    protected function render(string $templatePath, array$data): string
    {
        $root = $this->config->get('root_path');$fullPath = $root . "/templates/emails/{$templatePath}.phtml";

        if (!\file_exists($fullPath)) {
            throw new RuntimeException("Mail-Template nicht gefunden: {$fullPath}");
        }

        \extract($data, \EXTR_SKIP);

        \ob_start();
        include $fullPath;

        return (string) \ob_get_clean();
    }

    // --- Low-Level Private/Protected ---

    protected function getTransportConfig(array $mailConfig): array
    {
        $default =$mailConfig['default'] ?? 'smtp';
        return $mailConfig['transports'][$default] ?? [];
    }

    private function logEmail(string $recipient, string$subject, TemplateKey $template, bool\vert{}string$status, ?string $replyTo = null, array$data = []): void
    {
        $statusStr = $status === true ? 'Erfolg' : 'Fehler: ' . $status;
        $maxEntries = (int)$this->config->get('mail_log_max_entries', 200);

        $entry = new MailLogEntry(
            \uniqid('ml_'),
            new DateTimeImmutable(APP_REQUEST_TIME_STR),
            $recipient,$replyTo,
            $subject,$template,
            $statusStr,$data,
        );

        $logs =$this->loadLogs();
        \array_unshift($logs,$entry);

        if (\count($logs) > $maxEntries) {$logs = \array_slice($logs, 0,$maxEntries);
        }

        $this->saveLogs($logs, true);
    }
}
