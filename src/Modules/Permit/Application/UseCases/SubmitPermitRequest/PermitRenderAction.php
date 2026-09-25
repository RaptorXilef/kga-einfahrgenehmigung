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
            $isSelected = $activeTemplateKey === $key;
            $templateOptions[] = [
                'value' => (string) $key,
                'label' => (string) $tpl['label'],
                'selected' => $isSelected,
                'selectedAttr' => $isSelected ? 'selected' : '',
            ];
            $tplMetadata[$key] = ['days' => $tpl['days'], 'type' => $tpl['type']];
        }

        $tplMetadataJson = \json_encode($tplMetadata, \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT) ?: '{}';

        $vehicleOptions = [];
        foreach ($this->config->getArray('vehicle_types') as $val => $vData) {
            if (!($vData['active'] ?? true) && $activeVehicleType !== $val) {
                continue;
            }
            $isSelected = $activeVehicleType === $val;
            $vehicleOptions[] = [
                'value' => (string) $val,
                'label' => (string) $vData['label'],
                'selected' => $isSelected,
                'selectedAttr' => $isSelected ? 'selected' : '',
            ];
        }

        $purposeOptions = [];
        foreach ($this->config->getArray('purposes') as $val => $label) {
            $isSelected = $activePurpose === $val;
            $purposeOptions[] = [
                'value' => (string) $val,
                'label' => (string) $label,
                'selected' => $isSelected,
                'selectedAttr' => $isSelected ? 'selected' : '',
            ];
        }

        $isNameLocked = $this->hasNonEmptyString($prefillData, 'name');
        $isEmailLocked = $this->hasNonEmptyString($prefillData, 'email');
        $isParzelleLocked = $this->hasNonEmptyString($prefillData, 'parzelle');
        $isTypLocked = $this->hasNonEmptyString($prefillData, 'typ');
        $isKennzeichenLocked = $this->hasNonEmptyString($prefillData, 'kennzeichen');
        $isFirmaLocked = $this->hasNonEmptyString($prefillData, 'firma');
        $isZweckLocked = $this->hasNonEmptyString($prefillData, 'zweck');
        $isTemplateKeyLocked = $prefillDto instanceof VoucherPrefillResult && $prefillDto->templateKey !== '';
        $isDatumVonLocked = $this->hasNonEmptyString($prefillData, 'datum_von');
        $isDatumBisLocked = $this->hasNonEmptyString($prefillData, 'datum_bis');
        $hasActiveVoucher = $prefillDto instanceof VoucherPrefillResult;
        $agreementsChecked = (array) ($formData['agreements'] ?? []);

        // Flaches View-DTO für das PHTML erzeugen
        $viewDto = new PermitFormViewDto(
            name: (string) ($formData['name'] ?? $prefillData['name'] ?? ''),
            isNameLocked: $isNameLocked,
            nameReadonlyAttr: $isNameLocked ? 'readonly' : '',
            email: (string) ($formData['email'] ?? $prefillData['email'] ?? ''),
            isEmailLocked: $isEmailLocked,
            emailReadonlyAttr: $isEmailLocked ? 'readonly' : '',
            parzelle: (string) ($formData['parzelle'] ?? $prefillData['parzelle'] ?? ''),
            isParzelleLocked: $isParzelleLocked,
            parzelleReadonlyAttr: $isParzelleLocked ? 'readonly' : '',
            typ: $activeVehicleType,
            isTypLocked: $isTypLocked,
            typReadonlyAttr: $isTypLocked ? 'readonly tabindex="-1"' : '',
            kennzeichen: (string) ($formData['kennzeichen'] ?? $prefillData['kennzeichen'] ?? ''),
            isKennzeichenLocked: $isKennzeichenLocked,
            kennzeichenReadonlyAttr: $isKennzeichenLocked ? 'readonly' : '',
            firma: (string) ($formData['firma'] ?? $prefillData['firma'] ?? ''),
            isFirmaLocked: $isFirmaLocked,
            firmaReadonlyAttr: $isFirmaLocked ? 'readonly' : '',
            zweck: $activePurpose,
            isZweckLocked: $isZweckLocked,
            zweckReadonlyAttr: $isZweckLocked ? 'readonly tabindex="-1"' : '',
            templateKey: $activeTemplateKey,
            isTemplateKeyLocked: $isTemplateKeyLocked,
            templateKeyReadonlyAttr: $isTemplateKeyLocked ? 'readonly tabindex="-1"' : '',
            datumVon: (string) ($formData['datum_von'] ?? $prefillData['datum_von'] ?? $this->clock->now()->format('Y-m-d')),
            isDatumVonLocked: $isDatumVonLocked,
            datumVonReadonlyAttr: $isDatumVonLocked ? 'readonly' : '',
            datumBis: (string) ($formData['datum_bis'] ?? $prefillData['datum_bis'] ?? ''),
            isDatumBisLocked: $isDatumBisLocked,
            datumBisReadonlyAttr: $isDatumBisLocked ? 'readonly' : '',
            voucherInput: (string) ($formData['voucher'] ?? $request->get['voucher'] ?? ''),
            voucherCode: $prefillDto instanceof VoucherPrefillResult ? $prefillDto->code : '',
            voucherReason: $prefillDto instanceof VoucherPrefillResult ? $prefillDto->reason : '',
            hasActiveVoucher: $hasActiveVoucher,
            submitButtonText: $hasActiveVoucher ? 'Genehmigung jetzt aktivieren' : 'E-Mail bestätigen & Antrag stellen',
            hasMultipleTemplates: \count($templateOptions) > 1,
            singleTemplateKey: $templateOptions[0]['value'] ?? '',
            agreementsChecked: $agreementsChecked,
            templateOptions: $templateOptions,
            vehicleOptions: $vehicleOptions,
            purposeOptions: $purposeOptions,
            agreements: $this->getParsedAgreements($agreementsChecked),
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

    /**
     * @param array<string, mixed> $agreementsChecked
     *
     * @return array<string, array{label_html: string, required: bool, requiredAttr: string, checkedAttr: string}>
     */
    private function getParsedAgreements(array $agreementsChecked): array
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
                $renderedLabel = (string) \preg_replace('/\[(.*?)\]/', $linkHtml, $cleanLabel);
            } else {
                $renderedLabel = (string) \preg_replace('/\[(.*?)\]/', '$1', $cleanLabel);
            }

            $isRequired = (bool) ($agree['required'] ?? false);
            $keyStr = (string) $key;

            $parsed[$keyStr] = [
                'label_html' => $renderedLabel,
                'required' => $isRequired,
                'requiredAttr' => $isRequired ? 'required' : '',
                'checkedAttr' => isset($agreementsChecked[$keyStr]) ? 'checked' : '',
            ];
        }

        return $parsed;
    }
}
