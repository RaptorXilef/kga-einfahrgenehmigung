<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetGeneratorToolsData;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Application\Query\QueryHandlerInterface;
use PDO;

/**
 * @implements QueryHandlerInterface<GetGeneratorToolsDataQuery, GeneratorToolsViewDto>
 */
final readonly class GetGeneratorToolsDataHandler implements QueryHandlerInterface
{
    public function __construct(
        private PDO $pdo,
        private ConfigInterface $config,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param GetGeneratorToolsDataQuery $query
     */
    public function handle(mixed $query): GeneratorToolsViewDto
    {
        // 1. Templates basierend auf Berechtigungen auflösen
        $allowedTemplates = [];
        $tplMetadata = [];
        foreach ($this->config->get('permit_templates', []) as $key => $tpl) {
            if ($query->auth->hasPermission("template.$key")) {
                $allowedTemplates[] = ['value' => $key, 'label' => $tpl['label']];
            }
            $tplMetadata[$key] = [
                'days' => $tpl['days'],
                'type' => $tpl['type'],
                'prices' => $tpl['prices'],
            ];
        }

        // 2. Eigene Zwecke aus der Datenbank holen (für Autocomplete)
        $stmt = $this->pdo->query("SELECT DISTINCT zweck FROM permits WHERE zweck != ''");
        $dbPurposes = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $standardPurposes = $this->config->get('purposes', []);
        $customPurposes = [];

        foreach ($dbPurposes as $z) {
            // Die DB speichert die Labels. Wir filtern jene aus, die nicht in den Standard-Labels auftauchen.
            if (\in_array($z, $standardPurposes, true)) {
                continue;
            }

            $customPurposes[] = $z;
        }         \sort($customPurposes);

        $standardPurposesOptions = [];
        foreach ($standardPurposes as $val => $label) {
            $standardPurposesOptions[] = ['value' => $val, 'label' => $label];
        }

        // 3. Fahrzeugtypen
        $vehicleOptions = [];
        foreach ($this->config->get('vehicle_types', []) as $val => $vData) {
            if (!($vData['active'] ?? true)) {
                continue;
            }

            $vehicleOptions[] = ['value' => $val, 'label' => $vData['label']];
        }

        $reasons = $this->config->get('internal_reasons', ['Barzahlung vor Ort', 'Vorstandsbeschluss']);

        return new GeneratorToolsViewDto(
            hasAnyTemplate: $allowedTemplates !== [],
            allowedTemplateOptions: $allowedTemplates,
            vehicleOptions: $vehicleOptions,
            standardPurposesOptions: $standardPurposesOptions,
            customPurposes: $customPurposes,
            voucherReasons: $reasons,
            defaultDateVon: $this->clock->now()->format('Y-m-d'),
            tplMetadataJson: \json_encode($tplMetadata, \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT) ?: '{}',
        );
    }
}
