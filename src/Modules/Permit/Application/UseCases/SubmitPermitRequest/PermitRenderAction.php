<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SubmitPermitRequest;

use App\Application\Attribute\Route;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\HtmlResponse;
use App\Application\Session\SessionManager;
use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Voucher\Application\UseCases\CheckAvailableVouchers\CheckAvailableVouchersHandler;
use App\Modules\Voucher\Application\UseCases\CheckAvailableVouchers\CheckAvailableVouchersQuery;
use App\Modules\Voucher\Application\UseCases\GetVoucherPrefill\GetVoucherPrefillHandler;
use App\Modules\Voucher\Application\UseCases\GetVoucherPrefill\GetVoucherPrefillQuery;
use App\Modules\Voucher\Application\UseCases\GetVoucherPrefill\VoucherPrefillDto;

#[Route('GET', '/')]
final readonly class PermitRenderAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private SessionManager $sessionManager,
        private TemplateRenderer $renderer,
        private CheckAvailableVouchersHandler $checkVouchersHandler,
        private GetVoucherPrefillHandler $prefillHandler,
        private ClockInterface $clock,
    ) {
    }

    public function execute(ServerRequest $request): mixed
    {
        $dto = ViewRenderRequest::fromArray($request->get);
        $flashes = $this->sessionManager->getFlashes();
        $successMessage = '';

        if ($dto->isSuccess) {
            if (!empty($flashes['success'])) {
                $successMessage = $flashes['success'][0];
            } else {
                $successMessage = 'Bestätigung erforderlich! Wir haben Ihnen eine E-Mail gesendet. Bitte klicken Sie auf den Link darin, um Ihren Antrag zu aktivieren.';
            }
        } else {
            // Zeiterfassung für Bot-Schutz nun Time-Safe über das ClockInterface
            $this->sessionManager->setFormStartTime($this->clock->now()->getTimestamp());
        }

        // Gutschein-Daten über CQRS Schnittstelle auflösen
        $voucherCode = \trim((string) ($request->get['voucher'] ?? ''));
        $prefillDto = null;
        if ($voucherCode !== '') {
            $prefillDto = $this->prefillHandler->handle(new GetVoucherPrefillQuery($voucherCode));
        }

        // Formulardaten vereinen
        $formData = $this->sessionManager->getFormData();
        $prefillData = $prefillDto instanceof VoucherPrefillDto ? $prefillDto->data : [];

        $permitTemplates = $this->config->get('permit_templates', []);
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
        foreach ($this->config->get('vehicle_types', []) as $val => $vData) {
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
        foreach ($this->config->get('purposes', []) as $val => $label) {
            $purposeOptions[] = [
                'value' => $val,
                'label' => $label,
                'selected' => $activePurpose === $val,
            ];
        }

        // Flaches View-DTO für das PHTML erzeugen
        $viewDto = new PermitFormViewDto(
            name: (string) ($formData['name'] ?? $prefillData['name'] ?? ''),
            isNameLocked: !empty($prefillData['name']),
            email: (string) ($formData['email'] ?? $prefillData['email'] ?? ''),
            isEmailLocked: !empty($prefillData['email']),
            parzelle: (string) ($formData['parzelle'] ?? $prefillData['parzelle'] ?? ''),
            isParzelleLocked: !empty($prefillData['parzelle']),
            typ: $activeVehicleType,
            isTypLocked: !empty($prefillData['typ']),
            kennzeichen: (string) ($formData['kennzeichen'] ?? $prefillData['kennzeichen'] ?? ''),
            isKennzeichenLocked: !empty($prefillData['kennzeichen']),
            firma: (string) ($formData['firma'] ?? $prefillData['firma'] ?? ''),
            isFirmaLocked: !empty($prefillData['firma']),
            zweck: $activePurpose,
            isZweckLocked: !empty($prefillData['zweck']),
            templateKey: $activeTemplateKey,
            isTemplateKeyLocked: !empty($prefillDto?->templateKey),
            datumVon: (string) ($formData['datum_von'] ?? $prefillData['datum_von'] ?? $this->clock->now()->format('Y-m-d')),
            isDatumVonLocked: !empty($prefillData['datum_von']),
            datumBis: (string) ($formData['datum_bis'] ?? $prefillData['datum_bis'] ?? ''),
            isDatumBisLocked: !empty($prefillData['datum_bis']),
            voucherInput: (string) ($formData['voucher'] ?? $request->get['voucher'] ?? ''),
            voucherCode: $prefillDto instanceof VoucherPrefillDto ? $prefillDto->code : '',
            voucherReason: $prefillDto instanceof VoucherPrefillDto ? $prefillDto->reason : '',
            hasActiveVoucher: $prefillDto instanceof VoucherPrefillDto,
            agreementsChecked: (array) ($formData['agreements'] ?? []),
            templateOptions: $templateOptions,
            vehicleOptions: $vehicleOptions,
            purposeOptions: $purposeOptions,
            agreements: $this->getParsedAgreements(),
            tplMetadataJson: $tplMetadataJson,
        );

        $html = $this->renderer->render('frontend/formular', [
            'viewDto' => $viewDto,
            'hasActiveVouchers' => $this->checkVouchersHandler->handle(new CheckAvailableVouchersQuery()),
            'success' => $dto->isSuccess,
            'message' => $successMessage,
            'flashes' => $flashes,
        ]);

        return new HtmlResponse($html);
    }

    private function getParsedAgreements(): array
    {
        $agreementsConfig = $this->config->get('agreements', []);
        $baseUrl = $this->config->getBaseUrl() ?? '/';
        $parsed = [];

        foreach ($agreementsConfig as $key => $agree) {
            $cleanLabel = \htmlspecialchars($agree['label']);
            if (!empty($agree['link'])) {
                if (\filter_var($agree['link'], \FILTER_VALIDATE_URL)) {
                    $finalLink = $agree['link'];
                } else {
                    $finalLink = \rtrim($baseUrl, '/') . '/' . \ltrim($agree['link'], '/');
                }
                $linkHtml = '<a href="' . \htmlspecialchars($finalLink) .
                '" target="_blank" style="color: var(--primary-color); text-decoration: underline; font-weight: 500;">$1</a>';
                $renderedLabel = \preg_replace('/\[(.*?)\]/', $linkHtml, $cleanLabel);
            } else {
                $renderedLabel = \preg_replace('/\[(.*?)\]/', '$1', $cleanLabel);
            }

            $parsed[$key] = [
                'label_html' => $renderedLabel,
                'required' => $agree['required'] ?? false,
            ];
        }

        return $parsed;
    }
}
