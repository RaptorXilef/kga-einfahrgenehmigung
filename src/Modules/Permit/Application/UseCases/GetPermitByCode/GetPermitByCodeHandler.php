<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetPermitByCode;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use DateTimeImmutable;
use Exception;
use Override;
use PDO;

/**
 * Löst einen Genehmigungscode (und optional ein Kfz-Kennzeichen) direkt über PDO
 * über alle Tabellen (Aktiv, Archiv, Storniert) in ein flaches Read-DTO auf.
 *
 * @implements QueryHandlerInterface<GetPermitByCodeQuery, ?PermitReadDto>
 */
final readonly class GetPermitByCodeHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param GetPermitByCodeQuery $query
     */
    #[Override]
    public function handle(mixed $query): ?PermitReadDto
    {
        $hash = \strtoupper(\trim($query->code));
        if ($hash === '') {
            return null;
        }

        $storageConfig = $this->config->getArray('storage_config');
        $permitsTable = (string) ($storageConfig['permits']['table'] ?? 'permits');
        $archiveTable = (string) ($storageConfig['permits_archive']['table'] ?? 'permits_archive');
        $cancelledTable = (string) ($storageConfig['permits_cancelled']['table'] ?? 'permits_cancelled');

        // 1. Exakte Suche in der aktiven Tabelle
        $row = $this->findExactByCode($permitsTable, $hash);
        if ($row !== null) {
            return $this->mapRowToDto($row);
        }

        // 2. Suche im Archiv (Exakt + ShortCode-Suffix)
        $row = $this->findInHistoricalTable($archiveTable, $hash);
        if ($row !== null) {
            return $this->mapRowToDto($row);
        }

        // 3. Suche in den Stornierungen (Exakt + ShortCode-Suffix)
        $row = $this->findInHistoricalTable($cancelledTable, $hash);
        if ($row !== null) {
            return $this->mapRowToDto($row);
        }

        // 4. Optionaler Fallback: Suche nach Kfz-Kennzeichen in der aktiven Tabelle
        if ($query->allowLicensePlateFallback) {
            $row = $this->findBestMatchByLicensePlate($permitsTable, $hash);
            if ($row !== null) {
                return $this->mapRowToDto($row);
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findExactByCode(string $table, string $code): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE code = :code LIMIT 1");
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return \is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findInHistoricalTable(string $table, string $hash): ?array
    {
        $exact = $this->findExactByCode($table, $hash);
        if ($exact !== null) {
            return $exact;
        }

        $searchParts = \explode('-', $hash);
        $searchId = \end($searchParts);

        $stmt = $this->pdo->prepare("SELECT * FROM `{$table}` WHERE code LIKE ? OR code = ? LIMIT 1");
        $stmt->execute(['%-' . $searchId, $searchId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return \is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findBestMatchByLicensePlate(string $table, string $plate): ?array
    {
        $searchPlate = \preg_replace('/[^A-Z0-9]/', '', \strtoupper($plate));
        if ($searchPlate === null || $searchPlate === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            "SELECT * FROM `{$table}` WHERE REPLACE(REPLACE(kennzeichen, ' ', ''), '-', '') = ?",
        );
        $stmt->execute([$searchPlate]);

        $candidates = [];
        while (\is_array($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
            $candidates[] = $row;
        }

        if ($candidates === []) {
            return null;
        }

        $now = $this->clock->now();

        // Sortierung: 1. Aktuell gültige Genehmigungen zuerst, 2. nach dem Enddatum (neueste zuerst)
        \usort($candidates, function (array $a, array $b) use ($now): int {
            $aValid = $this->isRowCurrentlyValid($a, $now);
            $bValid = $this->isRowCurrentlyValid($b, $now);

            if ($aValid && !$bValid) {
                return -1;
            }
            if (!$aValid && $bValid) {
                return 1;
            }

            return (string) ($b['bis'] ?? '') <=> (string) ($a['bis'] ?? '');
        });

        return $candidates[0];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function isRowCurrentlyValid(array $row, DateTimeImmutable $now): bool
    {
        if ((bool) ($row['is_suspended'] ?? false) || ($row['status'] ?? '') === 'storniert') {
            return false;
        }

        try {
            $von = new DateTimeImmutable((string) ($row['von'] ?? ''));
            $bis = (new DateTimeImmutable((string) ($row['bis'] ?? '')))->setTime(23, 59, 59);

            return $now >= $von && $now <= $bis;
        } catch (Exception) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function mapRowToDto(array $row): PermitReadDto
    {
        $now = $this->clock->now();
        $code = \strtoupper(\trim((string) ($row['code'] ?? '')));

        $templateKey = \strtolower(\str_replace('.', '_', \trim((string) ($row['template_key'] ?? 'std_7'))));
        if ($templateKey === '') {
            $templateKey = 'std_7';
        }

        $ownerName = (string) ($row['name'] ?? 'Unbekannt');
        $emailRaw = \trim((string) ($row['email'] ?? ''));
        if (\in_array($emailRaw, ['[ANONYMISIERT]', 'ANONYMISIERT', '0'], true)) {
            $emailRaw = '';
        }

        $plotInt = (int) ($row['parzelle'] ?? 0);
        $plotFormatted = \str_pad((string) $plotInt, 4, '0', \STR_PAD_LEFT);

        $vehicleType = (string) ($row['typ'] ?? 'pkw');
        $licensePlate = \strtoupper(\trim((string) ($row['kennzeichen'] ?? '')));
        if (\in_array($licensePlate, ['', '[ANONYMISIERT]', 'ANONYMISIERT'], true)) {
            $licensePlate = 'XXX-XX 9999';
        }

        $companyRaw = \trim((string) ($row['firma'] ?? ''));
        $company = $companyRaw !== '' ? $companyRaw : null;

        $purpose = (string) ($row['zweck'] ?? 'Privat');
        $price = (float) ($row['preis'] ?? 0.0);
        $priceFormatted = \number_format($price, 2, ',', '.') . ' €';

        try {
            $dtVon = new DateTimeImmutable((string) ($row['von'] ?? $now->format('Y-m-d')));
        } catch (Exception) {
            $dtVon = $now;
        }

        try {
            $dtBis = new DateTimeImmutable((string) ($row['bis'] ?? $now->format('Y-m-d')));
        } catch (Exception) {
            $dtBis = $now;
        }

        try {
            $dtCreated = new DateTimeImmutable((string) ($row['erstellt'] ?? $now->format('Y-m-d H:i:s')));
        } catch (Exception) {
            $dtCreated = $now;
        }

        $status = \strtolower(\trim((string) ($row['status'] ?? 'offen')));
        $isPaid = $status === 'bezahlt';
        $isSuspended = (bool) ($row['is_suspended'] ?? false);
        $suspReasonRaw = \trim((string) ($row['suspension_reason'] ?? ''));
        $suspensionReason = $suspReasonRaw !== '' ? $suspReasonRaw : null;

        // Zahlungsziel (Due Date) berechnen
        $dueDays = $this->config->getInt('payment_due_days', 14);
        $daysBeforeValidity = $this->config->getInt('payment_due_days_before_validity', 2);
        $fallbackDueDate = $dtCreated->modify("+{$dueDays} days")->setTime(23, 59, 59);
        $dynamicDueDate = $dtVon->modify("-{$daysBeforeValidity} days")->setTime(23, 59, 59);
        $dueDate = $dynamicDueDate > $fallbackDueDate ? $dynamicDueDate : $fallbackDueDate;

        // Verwendungszweck (Usage Text) berechnen
        $pattern = $this->config->getString('usage_pattern', 'EFG-{{code}}-{{nachname}}');
        $codeParts = \explode('-', $code);
        $shortCode = \end($codeParts);
        $nameParts = \explode(' ', $ownerName);
        $vorname = $nameParts[0] ?? '';
        $nachname = $nameParts[\count($nameParts) - 1] ?? '';

        $usageText = \str_replace(
            ['{{code}}', '{{nachname}}', '{{vorname}}', '{{name}}'],
            [$shortCode, $nachname, $vorname, $ownerName],
            $pattern,
        );

        return new PermitReadDto(
            code: $code,
            templateKey: $templateKey,
            ownerName: $ownerName,
            ownerEmail: $emailRaw,
            plotNumber: $plotFormatted,
            vehicleType: $vehicleType,
            licensePlate: $licensePlate,
            company: $company,
            purpose: $purpose,
            price: $price,
            priceFormatted: $priceFormatted,
            validFrom: $dtVon,
            validUntil: $dtBis,
            validFromFormatted: $dtVon->format('d.m.Y'),
            validUntilFormatted: $dtBis->format('d.m.Y'),
            createdAt: $dtCreated,
            createdAtFormatted: $dtCreated->format('d.m.Y H:i'),
            status: $status,
            isPaid: $isPaid,
            isSuspended: $isSuspended,
            suspensionReason: $suspensionReason,
            usageText: $usageText,
            paymentDueDateFormatted: $dueDate->format('d.m.Y'),
        );
    }
}
