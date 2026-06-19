<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;

/**
 * Service für den reinen Datenexport.
 * Formatiert Arrays zu CSV-Strings oder JSON. Greift NICHT in die HTTP-Schicht ein.
 *
 * Path: src/Core/Service/ExportService.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class ExportService
{
    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    public function generateCsv(array $filteredPermits): string
    {
        $output = \fopen('php://temp', 'r+');
        if (! $output) {
            return '';
        }

        // BOM für Excel
        \fwrite($output, \chr(0xEF) . \chr(0xBB) . \chr(0xBF));
        \fputcsv($output, ['Kennung', 'Name', 'E-Mail', 'Parzelle', 'Typ', 'Kennzeichen', 'Firma', 'Zweck', 'Einnahme (€)', 'Status', 'Erstellt am'], ';', '"', '\\');

        $vehicleTypes = $this->config->get('vehicle_types', []);

        foreach ($filteredPermits as $permit) {
            $typKey = $permit->getVehicleType();
            $row    = [
                $permit->code,
                $permit->getOwnerName(),
                $permit->getOwnerEmail(),
                $permit->getPlotNumber(),
                $vehicleTypes[$typKey]['label'] ?? \strtoupper($typKey),
                $permit->getLicensePlate(),
                $permit->getCompany() ?? '',
                $permit->getPurpose(),
                \number_format($permit->getPrice(), 2, ',', ''),
                \strtoupper($permit->getStatus()),
                $permit->getCreatedAt()->format('d.m.Y H:i'),
            ];

            // Schutz vor CSV-Injection
            foreach ($row as &$cell) {
                $firstChar = \substr((string) $cell, 0, 1);
                if ($cell !== '' && \in_array($firstChar, ['=', '+', '-', '@', "\t", "\r"], true)) {
                    $cell = "'" . $cell;
                }
            }
            unset($cell);

            \fputcsv($output, $row, ';', '"', '\\');
        }

        \rewind($output);
        $csvContent = \stream_get_contents($output);
        \fclose($output);

        return (string) $csvContent;
    }

    public function generateJson(array $filteredPermits): string
    {
        return \json_encode(\array_values($filteredPermits), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE) ?: '';
    }

    public function generateFilename(string $format, string $start, string $end): string
    {
        $slug = \strtolower((string) \preg_replace('/[^A-Za-z0-9]/', '_', (string) $this->config->get('vereins_name', 'export')));

        return "export_{$slug}_{$start}_bis_{$end}.{$format}";
    }
}
