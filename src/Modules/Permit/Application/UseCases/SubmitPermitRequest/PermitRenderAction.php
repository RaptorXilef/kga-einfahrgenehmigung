<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SubmitPermitRequest;

use App\Application\Attribute\Route;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Integration\VoucherIntegrationInterface;
use App\Contracts\Integration\VoucherPrefillResult;
use App\Contracts\Utils\ClockInterface;
use Override;

#[Route('GET', '/')]
final readonly class PermitRenderAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private SessionManager $sessionManager,
        private TemplateRenderer $renderer,
        private VoucherIntegrationInterface $voucherIntegration,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        $dto = ViewRenderRequest::fromArray($request->get);
        $flashes = $this->sessionManager->getFlashes();
        $successMessage = '';

        if ($dto->isSuccess) {
            if (isset($flashes['success']) && \is_array($flashes['success']) && $flashes['success'] !== []) {
                $successMessage = (string) $flashes['success'][0];
            } else {
                $successMessage = 'Bestätigung erforderlich! Wir haben Ihnen eine E-Mail gesendet. Bitte klicken Sie auf den Link darin, um Ihren Antrag zu aktivieren.';
            }
        } else {
            // Zeiterfassung für Bot-Schutz nun Time-Safe über das ClockInterface
            $this->sessionManager->setFormStartTime($this->clock->now()->getTimestamp());
        }

        // Gutschein-Daten über das modulsichere VoucherIntegrationInterface auflösen (Deptrac-konform)
        $voucherCode = \trim((string) ($request->get['voucher'] ?? ''));
        $prefillDto = null;
        if ($voucherCode !== '') {
            $prefillDto = $this->voucherIntegration->getVoucherPrefill($voucherCode);
        }

        // Formulardaten vereinen
        $formData = $this->sessionManager->getFormData();
        $prefillData = $prefillDto instanceof VoucherPrefillResult ? $prefillDto->data : [];

        $permitTemplates = $this->config->getArray('permit_templates');
        $publicTemplates = \array_filter($permitTemplates, fn (array $t): bool => ($t['public'] ?? false) === true);
        $defaultTemplateKey = \array_key_first($publicTemplates) ?? 'std_7';

        $activeTemplateKey = (string) ($formData['template_key'] ?? $prefillDto?->templateKey ?? $defaultTemplateKey);
        $activeVehicleType = (string) ($formData['typ'] ?? $prefillData['typ'] ?? '');
        $activePurpose = (string) ($formData['zweck'] ?? $prefillData['zweck'] ?? '');

        // Dropdown-Optionen vorbereiten (Logik aus der View verbannt)
        $templateOptions = [];
        $tplMetadata = [];
        foreach ($publicTemplates as $key => $tpl) {
            $templateOptions[] = [
                'value' => $key,
                'label' => $tpl['label'],
                'selected' => $activeTemplateKey === $key,
            ];
            $tplMetadata[$key] = ['days' => $tpl['days'], 'type' => $tpl['type']];
        }

        $tplMetadataJson = \json_encode($tplMetadata, \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT) ?: '{}';

        $vehicleOptions = [];
        foreach ($this->config->getArray('vehicle_types') as $val => $vData) {
            if (!($vData['active'] ?? true) && $activeVehicleType !== $val) {
                continue;
            }
            $vehicleOptions[] = [
                'value' => $val,
                'label' => $vData['label'],
                'selected' => $activeVehicleType === $val,
            ];
        }

        $purposeOptions = [];
        foreach ($this->config->getArray('purposes') as $val => $label) {
            $purposeOptions[] = [
                'value' => $val,
                'label' => $label,
                'selected' => $activePurpose === $val,
            ];
        }

        // Flaches View-DTO für das PHTML erzeugen
        $viewDto = new PermitFormViewDto(
            name: (string) ($formData['name'] ?? $prefillData['name'] ?? ''),
            isNameLocked: $this->hasNonEmptyString($prefillData, 'name'),
            email: (string) ($formData['email'] ?? $prefillData['email'] ?? ''),
            isEmailLocked: $this->hasNonEmptyString($prefillData, 'email'),
            parzelle: (string) ($formData['parzelle'] ?? $prefillData['parzelle'] ?? ''),
            isParzelleLocked: $this->hasNonEmptyString($prefillData, 'parzelle'),
            typ: $activeVehicleType,
            isTypLocked: $this->hasNonEmptyString($prefillData, 'typ'),
            kennzeichen: (string) ($formData['kennzeichen'] ?? $prefillData['kennzeichen'] ?? ''),
            isKennzeichenLocked: $this->hasNonEmptyString($prefillData, 'kennzeichen'),
            firma: (string) ($formData['firma'] ?? $prefillData['firma'] ?? ''),
            isFirmaLocked: $this->hasNonEmptyString($prefillData, 'firma'),
            zweck: $activePurpose,
            isZweckLocked: $this->hasNonEmptyString($prefillData, 'zweck'),
            templateKey: $activeTemplateKey,
            isTemplateKeyLocked: $prefillDto instanceof VoucherPrefillResult && $prefillDto->templateKey !== '',
            datumVon: (string) ($formData['datum_von'] ?? $prefillData['datum_von'] ?? $this->clock->now()->format('Y-m-d')),
            isDatumVonLocked: $this->hasNonEmptyString($prefillData, 'datum_von'),
            datumBis: (string) ($formData['datum_bis'] ?? $prefillData['datum_bis'] ?? ''),
            isDatumBisLocked: $this->hasNonEmptyString($prefillData, 'datum_bis'),
            voucherInput: (string) ($formData['voucher'] ?? $request->get['voucher'] ?? ''),
            voucherCode: $prefillDto instanceof VoucherPrefillResult ? $prefillDto->code : '',
            voucherReason: $prefillDto instanceof VoucherPrefillResult ? $prefillDto->reason : '',
            hasActiveVoucher: $prefillDto instanceof VoucherPrefillResult,
            agreementsChecked: (array) ($formData['agreements'] ?? []),
            templateOptions: $templateOptions,
            vehicleOptions: $vehicleOptions,
            purposeOptions: $purposeOptions,
            agreements: $this->getParsedAgreements(),
            tplMetadataJson: $tplMetadataJson,
        );

        $html = $this->renderer->render('frontend/formular', [
            'viewDto' => $viewDto,
            'hasActiveVouchers' => $this->voucherIntegration->hasAvailableVouchers(),
            'success' => $dto->isSuccess,
            'message' => $successMessage,
            'flashes' => $flashes,
        ]);

        return new HtmlResponse($html);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hasNonEmptyString(array $data, string $key): bool
    {
        return isset($data[$key]) && \trim((string) $data[$key]) !== '';
    }

    private function getParsedAgreements(): array
    {
        $agreementsConfig = $this->config->getArray('agreements');
        $baseUrl = $this->config->getBaseUrl();
        $parsed = [];

        foreach ($agreementsConfig as $key => $agree) {
            $cleanLabel = \htmlspecialchars((string) ($agree['label'] ?? ''));
            $rawLink = \trim((string) ($agree['link'] ?? ''));

            if ($rawLink !== '') {
                if (\filter_var($rawLink, \FILTER_VALIDATE_URL)) {
                    $finalLink = $rawLink;
                } else {
                    $finalLink = \rtrim($baseUrl, '/') . '/' . \ltrim($rawLink, '/');
                }
                $linkHtml = '<a href="' . \htmlspecialchars($finalLink) .
                    '" target="_blank" class="u-text-link u-font-semibold">$1</a>';
                $renderedLabel = \preg_replace('/\[(.*?)\]/', $linkHtml, $cleanLabel);
            } else {
                $renderedLabel = \preg_replace('/\[(.*?)\]/', '$1', $cleanLabel);
            }

            $parsed[$key] = [
                'label_html' => $renderedLabel,
                'required' => (bool) ($agree['required'] ?? false),
            ];
        }

        return $parsed;
    }
}
