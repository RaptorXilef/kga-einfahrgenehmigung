<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Storage\LockManagerInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\Storage\VerificationRepositoryInterface;
use App\Core\Entity\Owner;
use App\Core\Entity\Permit;
use App\Core\Entity\Status;
use App\Core\Entity\Validity;
use App\Core\Entity\Vehicle;
use App\Core\Event\PermitCreatedEvent;
use App\Core\Event\VerificationRequestedEvent;
use App\Core\Utils\DateRangeHelper;
use App\Infrastructure\Storage\JsonHelper;

/**
 * Haupt-Service für die Erstellung, Prüfung und Verwaltung von Einfahrtsgenehmigungen.
 * Handhabt den Workflow von der initialen Anfrage bis zur finalen Genehmigung.
 *
 * Steuert Kollisionsprüfungen, Validierungsketten, Kennzeichen-Formatierung, E-Mail-Verifikationen,
 * Rechnungsstellungen, PayPal-Zahlungsabschlüsse und automatisierte Archivierungsprozesse.
 *
 * Path: src/Core/Service/PermitService.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class PermitService
{
    public function __construct(
        private ConfigInterface $config,
        private EventDispatcherInterface $eventDispatcher,
        private LicensePlateFormatter $plateFormatter,
        private LockManagerInterface $lockManager,
        private PermitArchiveRepositoryInterface $archiveRepository,
        private StorageInterface $storage,
        private VerificationRepositoryInterface $verificationRepository,
        private VoucherService $voucherService,
    ) {
    }

    public function createPendingVerification(array $data): string
    {
        $this->validateNoCollisions(
            (string) ($data['parzelle'] ?? ''),
            new \DateTimeImmutable((string) ($data['datum_von'] ?? 'now')),
            new \DateTimeImmutable((string) ($data['datum_bis'] ?? 'now')),
        );

        $tKey          = $data['template_key'] ?? 'std_7';
        $templates     = (array) $this->config->get('permit_templates', []);
        $template      = $templates[$tKey] ?? $templates['std_7'];
        $vehicleTypes  = (array) $this->config->get('vehicle_types', []);
        $defaultType   = $vehicleTypes === [] ? 'pkw' : \array_key_first($vehicleTypes);
        $typ           = $data['typ'] ?? $defaultType;
        $data['preis'] = (float) (
            $template['prices'][$typ] ?? ($template['prices'][$defaultType] ?? 0.0)
        );
        $token                      = \bin2hex(\random_bytes(32));
        $shortCode                  = \strtoupper(\substr(\bin2hex(\random_bytes(4)), 0, 6));
        $data['verification_token'] = $token;
        $data['verification_code']  = $shortCode;
        $hours                      = (int) $this->config->get('hours_pending_verify', 24);
        $data['expires']            = \date('Y-m-d H:i:s', APP_REQUEST_TIME + (3600 * $hours));
        $allPending                 = $this->verificationRepository->loadPending();
        $allPending[$token]         = $data;
        $this->verificationRepository->savePending($allPending);

        // ENTKOPPELT: Statt direktem Mail-Versand werfen wir jetzt ein Event!
        $this->eventDispatcher->dispatch(new VerificationRequestedEvent($data, $token, $shortCode));

        return $token;
    }

    public function updateVerifiedRequest(string $token, string $sessionEmail, array $newData): string
    {
        $oldData = $this->getVerifiedRequest($token);

        // Fallback, falls Token abgelaufen: Ganz neu anfangen
        if ($oldData === null || \strtolower($newData['email']) !== \strtolower(\trim($sessionEmail))) {
            $this->createPendingVerification($newData);

            return 'redirect_verify';
        }

        $priceRelevantChanged = ($oldData['template_key'] ?? '') !== $newData['template_key']
            || ($oldData['typ'] ?? '') !== $newData['typ']
            || ($oldData['voucher'] ?? '') !== $newData['voucher'];

        if (! $priceRelevantChanged) {
            // Nur Name/Kennzeichen geändert -> Alter Preis bleibt erhalten!
            $merged           = \array_merge($oldData, $newData);
            $merged['preis']  = $oldData['preis'] ?? 0;
            $merged['status'] = 'offen';

            $allVerified         = $this->verificationRepository->loadVerified();
            $allVerified[$token] = $merged;
            $this->verificationRepository->saveVerified($allVerified);

            return 'redirect_checkout';
        }

        // Preisrelevante Änderung -> Alten Token löschen und komplett neu verifizieren
        $allVerified = $this->verificationRepository->loadVerified();
        if (isset($allVerified[$token])) {
            unset($allVerified[$token]);
            $this->verificationRepository->saveVerified($allVerified);
        }

        $this->createPendingVerification($newData);

        return 'redirect_verify';
    }

    public function confirmEmail(string $input): ?array
    {
        $allPending   = $this->verificationRepository->loadPending();
        $input        = \strtoupper(\trim($input));
        $matchedToken = null;

        foreach ($allPending as $t => $d) {
            if (\strtoupper($t) === $input || \strtoupper((string) ($d['verification_code'] ?? '')) === $input) {
                $matchedToken = $t;

                break;
            }
        }

        if ($matchedToken === null) {
            $allVerified = $this->verificationRepository->loadVerified();
            foreach ($allVerified as $t => $d) {
                if (\strtoupper($t) === $input || \strtoupper((string) ($d['verification_code'] ?? '')) === $input) {
                    return $d;
                }
            }

            return null;
        }

        $token = $matchedToken;
        $data  = (array) $allPending[$token];
        unset($allPending[$token]);
        $this->verificationRepository->savePending($allPending);

        $hours               = (int) $this->config->get('hours_pending_finalize', 48);
        $data['verified_at'] = APP_REQUEST_TIME_STR;
        $data['expires']     = \date('Y-m-d H:i:s', APP_REQUEST_TIME + (3600 * $hours));

        $voucherCode = \strtoupper(\trim((string) ($data['voucher'] ?? '')));
        if ($voucherCode !== '') {
            $voucher = $this->voucherService->useVoucher($voucherCode, $data);

            if ($voucher !== null) {
                $finalPrice = $this->calculateDiscountedPrice((float) $data['preis'], $voucher);

                if ($finalPrice <= 0.0) {
                    $data['preis']  = 0.0;
                    $data['status'] = 'bezahlt';

                    $allVerified         = $this->verificationRepository->loadVerified();
                    $allVerified[$token] = $data;
                    $this->verificationRepository->saveVerified($allVerified);

                    return ['finalised' => $this->finaliseRequest(
                        $token,
                        'bezahlt',
                        'Gutschein (Voll-Rabatt): ' . $voucherCode,
                    )];
                }

                $data['preis']           = $finalPrice;
                $data['voucher_applied'] = $voucherCode;
                $data['voucher_details'] = ['type' => $voucher['type'], 'value' => $voucher['value']];
            }
        }

        $allVerified         = $this->verificationRepository->loadVerified();
        $allVerified[$token] = $data;
        $this->verificationRepository->saveVerified($allVerified);

        $data['actual_token'] = $token;

        return $data;
    }

    public function finaliseRequest(string $token, string $status = 'offen', ?string $kommentar = null): Permit
    {
        return $this->lockManager->executeWithLock('checkout', function () use ($token, $status, $kommentar) {
            $allVerified = $this->verificationRepository->loadVerified();

            if (! isset($allVerified[$token])) {
                throw new \RuntimeException('Antragssitzung abgelaufen oder bereits abgeschlossen.');
            }

            $data                       = (array) $allVerified[$token];
            $data['status']             = $status;
            $data['interner_kommentar'] = $kommentar;
            $permit                     = $this->createPermit($data, true);

            unset($allVerified[$token]);
            $this->verificationRepository->saveVerified($allVerified);

            return $permit;
        });
    }

    public function createPermit(array $data, bool $sendMails = true): Permit
    {
        $this->validateEmail((string) ($data['email'] ?? ''));

        $tKey      = (string) ($data['template_key'] ?? 'std_7');
        $templates = (array) $this->config->get('permit_templates', []);
        $template  = (array) ($templates[$tKey] ?? $templates['std_7']);
        $startDate = new \DateTimeImmutable((string) ($data['datum_von'] ?? 'now'));

        if ($template['days'] === 'custom') {
            $endDate = new \DateTimeImmutable((string) ($data['datum_bis'] ?? 'now'));
        } else {
            $daysToAdd = \max(0, (int) $template['days'] - 1);
            $endDate   = $startDate->modify('+' . $daysToAdd . ' days');
        }

        $vehicleTypes = $this->config->get('vehicle_types', []);
        $defaultType  = empty($vehicleTypes) ? 'pkw' : \array_key_first($vehicleTypes);
        $typ          = (string) ($data['typ'] ?? $defaultType);
        $preis        = isset($data['manual_price'])
            ? (float) $data['manual_price']
            : (float) ($template['prices'][$typ] ?? 0.0);

        do {
            $randomId        = $this->generateV4Suffix();
            $displayPlate    = $this->plateFormatter->format((string) ($data['kennzeichen'] ?? ''));
            $identifierPlate = \str_replace(' ', '-', $displayPlate);
            $platePart       = $identifierPlate !== '' ? $identifierPlate : \strtoupper($typ);
            $useLongCode     = (bool) $this->config->get('use_long_permit_code', false);

            if ($useLongCode) {
                $fullIdentifier = \sprintf(
                    '%s-%s-%s-%s',
                    $this->config->get('prefix', 'ML'),
                    \str_pad((string) ($data['parzelle'] ?? '0'), 4, '0', \STR_PAD_LEFT),
                    $platePart,
                    $randomId,
                );
            } else {
                $fullIdentifier = $randomId;
            }
        } while (! $this->isCodeGloballyUnique($fullIdentifier));

        $purposes = (array) $this->config->get('purposes', []);
        $zweck    = (string) ($purposes[(string) ($data['zweck'] ?? '')] ?? 'Privat');

        $permit = new Permit(
            code: $fullIdentifier,
            template_key: $tKey,
            owner: new Owner(
                \strip_tags((string) $data['name']),
                (string) $data['email'],
                \str_pad((string) $data['parzelle'], 4, '0', \STR_PAD_LEFT),
            ),
            vehicle: new Vehicle(
                $typ,
                $displayPlate,
                isset($data['firma']) ? \strip_tags((string) $data['firma']) : null,
            ),
            validity: new Validity($startDate, $endDate, $preis, $zweck),
            status: new Status((string) ($data['status'] ?? 'offen')),
            erstellt: new \DateTimeImmutable(),
            interner_kommentar: $data['interner_kommentar'] ?? null,
            agreements: $data['agreements'] ?? [],
        );

        if (! $this->storage->save($permit)) {
            throw new \RuntimeException('Speicherfehler.');
        }

        if ($sendMails) {
            // EREIGNIS GESTEUERTE ARCHITEKTUR: Nur noch Event feuern, statt selbst zu verarbeiten!
            $this->eventDispatcher->dispatch(new PermitCreatedEvent($permit, $randomId));
        }

        return $permit;
    }

    public function manualActivate(string $code, ?string $grund = null): bool
    {
        $permit = $this->storage->findByHash($code);
        if (! $permit instanceof Permit) {
            return false;
        }

        $updated = new Permit(
            $permit->code,
            $permit->template_key,
            $permit->owner,
            $permit->vehicle,
            $permit->validity,
            new Status(
                'bezahlt',
                $permit->isSuspended(),
                $permit->getSuspensionReason(),
            ),
            $permit->getCreatedAt(),
            $grund ?? $permit->interner_kommentar,
        );

        return $this->storage->save($updated);
    }

    public function toggleSuspension(string $code, bool $status, ?string $reason = null): bool
    {
        $permit = $this->storage->findByHash($code);
        if (! $permit instanceof Permit) {
            return false;
        }

        $updated = new Permit(
            $permit->code,
            $permit->template_key,
            $permit->owner,
            $permit->vehicle,
            $permit->validity,
            new Status(
                $permit->getStatus(),
                $status,
                $reason,
            ),
            $permit->getCreatedAt(),
            $permit->interner_kommentar,
        );

        return $this->storage->save($updated);
    }

    public function searchAndPaginate(string $query, string $tab, string $templateType, int $page, int $limit): array
    {
        $allActive = $this->storage->getAll();
        $archived  = [];

        if (\in_array($tab, ['all', 'archive'], true)) {
            $arcCfg      = $this->config->get('storage_config')['permits_archive'];
            $archivePath = $this->config->getStoragePath($arcCfg['file'] ?? 'permits_archive.json');

            if (\file_exists($archivePath)) {
                $rawArchive = JsonHelper::read($archivePath);
                foreach ($rawArchive as $item) {
                    $archived[] = $this->storage->mapToEntity($item);
                }
            }
        }

        $combined   = \array_merge($allActive, $archived);
        $filtered   = [];
        $queryLower = \strtolower($query);
        $now        = new \DateTimeImmutable();

        $permitTemplates = $this->config->get('permit_templates', []);

        foreach ($combined as $permit) {
            if ($templateType !== 'all') {
                $tplType = $permitTemplates[$permit->template_key]['type'] ?? 'standard';
                if ($tplType !== $templateType) {
                    continue;
                }
            }

            $isArchived = $this->archiveRepository->isCodeInArchive($permit->code);
            $isExpired  = $permit->isExpired($now);

            if ($tab === 'active' && ($isArchived || $isExpired)) {
                continue;
            }
            if ($tab === 'expired' && (! $isExpired || $isArchived)) {
                continue;
            }
            if ($tab === 'archive' && ! $isArchived) {
                continue;
            }

            if (! $permit->matchesSearch($queryLower)) {
                continue;
            }

            $filtered[] = $permit;
        }

        \usort($filtered, fn ($a, $b): int => $b->getCreatedAt() <=> $a->getCreatedAt());

        $total  = \count($filtered);
        $offset = ($page - 1) * $limit;
        $items  = \array_slice($filtered, $offset, $limit);

        $formattedItems = \array_map(fn ($permit): array => [
            'bis'          => $permit->getValidUntil()->format('d.m.Y'),
            'code'         => $permit->code,
            'email'        => $permit->getOwnerEmail(),
            'erstellt'     => $permit->getCreatedAt()->format('d.m.Y H:i'),
            'is_archived'  => $this->archiveRepository->isCodeInArchive($permit->code),
            'kennzeichen'  => $permit->getLicensePlate(),
            'name'         => $permit->getOwnerName(),
            'parzelle'     => $permit->getPlotNumber(),
            'preis'        => $permit->getPrice(),
            'status'       => $permit->getStatus(),
            'template_key' => $permit->template_key,
            'von'          => $permit->getValidFrom()->format('d.m.Y'),
            'zweck'        => $permit->getPurpose(),
        ], $items);

        return [
            'items' => $formattedItems,
            'total' => $total,
        ];
    }

    public function getHistoryByEmail(string $email): array
    {
        $all = $this->storage->getAll();

        return \array_filter(
            $all,
            fn (Permit $permit): bool => \strtolower($permit->getOwnerEmail()) === \strtolower($email),
        );
    }

    public function getVerifiedRequest(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        $all = $this->verificationRepository->loadVerified();

        return (array) ($all[$token] ?? null) ?: null;
    }

    public function getOverdueLevel(Permit $permit): int
    {
        if ($permit->getStatus() === 'bezahlt') {
            return 0;
        }

        $now                 = new \DateTimeImmutable();
        $dueDays             = (int) $this->config->get('payment_due_days', 14);
        $notifyDays          = (int) $this->config->get('payment_due_days_notify', 2);
        $userDeadline        = $permit->getCreatedAt()->modify("+{$dueDays} days");
        $staffAlertThreshold = $userDeadline->modify("+{$notifyDays} days");

        if ($now > $staffAlertThreshold) {
            return 2;
        }

        if ($now > $userDeadline) {
            return 1;
        }

        return 0;
    }

    public function autoArchiveExpiredPermits(int $graceDays = 0): int
    {
        $allPermits    = $this->storage->getAll();
        $toArchive     = [];
        $codesToDelete = [];
        $cutoffDate    = (new \DateTimeImmutable())->modify("-{$graceDays} days")->setTime(0, 0, 0);

        foreach ($allPermits as $permit) {
            if ($permit->getValidUntil() < $cutoffDate) {
                if (\in_array($permit->getStatus(), ['bezahlt', 'storniert'], true)) {
                    $toArchive[]     = $permit;
                    $codesToDelete[] = $permit->code;
                }
            }
        }

        if (! empty($toArchive)) {
            $this->archiveRepository->archivePermits(0, $toArchive);
            $this->storage->deleteMultiple($codesToDelete);
        }

        return \count($toArchive);
    }

    private function validateNoCollisions(string $parzelle, \DateTimeImmutable $start, \DateTimeImmutable $end): void
    {
        $parzelleFormatted = \str_pad($parzelle, 4, '0', \STR_PAD_LEFT);

        foreach ($this->storage->getAll() as $permit) {
            if ($permit->hasCollision($parzelleFormatted, $start, $end)) {
                throw new \RuntimeException(
                    "Kollision: Für Parzelle {$parzelle} existiert bereits eine Genehmigung vom " .
                        $permit->getValidFrom()->format('d.m.Y') . ' bis ' .
                        $permit->getValidUntil()->format('d.m.Y') . '.',
                );
            }
        }

        $allPending = $this->verificationRepository->loadPending();
        $nowStr     = APP_REQUEST_TIME_STR;

        foreach ($allPending as $pending) {
            $pPlot   = \str_pad((string) ($pending['parzelle'] ?? ''), 4, '0', \STR_PAD_LEFT);
            $pStart  = new \DateTimeImmutable((string) ($pending['datum_von'] ?? 'now'));
            $pEnd    = new \DateTimeImmutable((string) ($pending['datum_bis'] ?? 'now'));
            $expires = $pending['expires'] ?? '';

            if (
                $pPlot === $parzelleFormatted
                && $expires > $nowStr
                && DateRangeHelper::overlaps($pStart, $pEnd, $start, $end)
            ) {
                throw new \RuntimeException(
                    "Hinweis: Für Parzelle {$parzelle} läuft bereits eine Anfrage für diesen Zeitraum. " .
                        'Bitte warten Sie 24h oder wählen Sie andere Daten.',
                );
            }
        }
    }

    private function generateV4Suffix(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $res   = '';

        for ($i = 0; $i < 8; ++$i) {
            $res .= $chars[\random_int(0, \strlen($chars) - 1)];
        }

        return $res;
    }

    private function validateEmail(string $email): void
    {
        if (\trim($email) === '') {
            return;
        }

        if (! \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Die eingegebene E-Mail-Adresse ist ungültig.');
        }
    }

    private function isCodeGloballyUnique(string $fullIdentifier): bool
    {
        if ($this->storage->findByHash($fullIdentifier) instanceof Permit) {
            return false;
        }

        return ! $this->archiveRepository->isCodeInArchive($fullIdentifier);
    }

    public function getCoveredQuarters(Permit $permit): array
    {
        $startQ = (int) \ceil((int) $permit->getValidFrom()->format('n') / 3);
        $endQ   = (int) \ceil((int) $permit->getValidUntil()->format('n') / 3);

        return \range($startQ, $endQ);
    }

    public function calculateDiscountedPrice(float $originalPrice, array $voucher): float
    {
        $type  = $voucher['type'] ?? 'free';
        $value = (float) ($voucher['value'] ?? 0.0);

        $newPrice = match ($type) {
            'fixed'   => $value,
            'free'    => 0.0,
            'percent' => $originalPrice * (1 - ($value / 100)),
            default   => $originalPrice,
        };

        return \max(0.0, $newPrice);
    }
}
