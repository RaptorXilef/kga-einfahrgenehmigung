<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;

/**
 * TODO DOCBLOCK
 * Service für den Export von Domain-Entitäten in verschiedene Dateiformate.
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
    public function __construct(private ConfigInterface $config)
    {
    }

    // TODO DOCBLOCK
    public function export(string $format, array $filteredPermits, string $start, string $end): void
    {
        $slug = \strtolower(
            (string) \preg_replace('/[^A-Za-z0-9]/', '_', (string) $this->config->get('vereins_name', 'export')),
        );
        $filename = "export_{$slug}_{$start}_bis_{$end}.{$format}";

        if ($format === 'csv') {
            $this->streamCsv($filename, $filteredPermits);

            return;
        }

        if ($format === 'json') {
            $this->streamJson($filename, $filteredPermits);

            return;
        }
    }

    // TODO DOCBLOCK
    private function streamCsv(string $filename, array $permits): void
    {
        \header('Content-Type: text/csv; charset=utf-8');
        \header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = \fopen('php://output', 'w');
        if (! $output) {
            return;
        }

        // UTF-8 BOM für Excel Kompatibilität
        \fprintf($output, \chr(0xEF) . \chr(0xBB) . \chr(0xBF));

        \fputcsv($output, [
            'Kennung', 'Name', 'E-Mail', 'Parzelle', 'Typ', 'Kennzeichen',
            'Firma', 'Zweck', 'Einnahme (€)', 'Status', 'Erstellt am',
        ], ';', '"', '\\');

        $vehicleTypes = $this->config->get('vehicle_types', []);

        foreach ($permits as $permit) {
            $typKey = $permit->vehicle->typ;
            $row    = [
                $permit->code,
                $permit->owner->name,
                $permit->owner->email,
                $permit->owner->parzelle,
                $vehicleTypes[$typKey]['label'] ?? \strtoupper($typKey), // Sauberere Typ-Auflösung
                $permit->vehicle->kennzeichen,
                $permit->vehicle->firma ?? '',
                $permit->validity->zweck,
                \number_format($permit->validity->preis, 2, ',', ''),
                \strtoupper($permit->status->current),
                $permit->erstellt->format('d.m.Y H:i'),
            ];

            // CSV-Injection-Schutz
            foreach ($row as &$cell) {
                $firstChar = \substr((string) $cell, 0, 1);
                if ($cell !== '' && \in_array($firstChar, ['=', '+', '-', '@', '\t', '\r'], true)) {
                    $cell = "'" . $cell;
                }
            }
            unset($cell);

            \fputcsv($output, $row, ';', '"', '\\');
        }

        \fclose($output);
    }

    // TODO DOCBLOCK
    private function streamJson(string $filename, array $permits): void
    {
        \header('Content-Type: application/json');
        \header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo \json_encode(\array_values($permits), \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);
    }
}
