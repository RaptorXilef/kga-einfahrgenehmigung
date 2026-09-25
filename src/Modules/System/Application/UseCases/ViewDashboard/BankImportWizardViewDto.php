<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ViewDashboard;

/**
 * 100% logikfreies View-DTO für den CSV-Bank-Import-Tab (inkl. Spalten-Zuordnungs-Wizard).
 */
final readonly class BankImportWizardViewDto
{
    /**
     * @param array<int, array{index: int, number: int, label: string, selectedAttr: string}> $idColumnOptions
     * @param array<int, array{index: int, number: int, label: string, selectedAttr: string}> $amountColumnOptions
     * @param array<int, array{index: int, number: int, label: string, selectedAttr: string}> $dateColumnOptions
     */
    public function __construct(
        public bool $isSimpleMode,
        public bool $hasHeaders,
        public string $tempFile,
        public string $previewJson,
        public array $idColumnOptions,
        public array $amountColumnOptions,
        public array $dateColumnOptions,
    ) {
    }
}
