<?php

// Path: src\Application\SuccessController.php
declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Service\BankQrGenerator;

// TODO DOCBLOCK
final readonly class SuccessController
{
    public function __construct(
        private BankQrGenerator $bankQrGenerator,
        private ConfigInterface $config,
        private StorageInterface $storage,
    ) {
    }

    // TODO DOCBLOCK
    public function handleRequest(array $get): void
    {
        $code   = (string) ($get['code'] ?? '');
        $method = (string) ($get['method'] ?? 'wire');

        $permit = $this->storage->findByHash($code);
        if (! $permit) {
            \header('Location: index.php');
            exit;
        }

        $epcData = '';
        $usage   = '';
        if ($method === 'wire' && $permit->status->current !== 'bezahlt') {
            // Verwendungszweck aus dem Code generieren (letzte 6 Zeichen)
            $shortCode = \substr($permit->code, -6);
            $nameParts = \explode(' ', $permit->owner->name);
            $vorname   = $nameParts[0] ?? 'Unbekannt';
            $nachname  = $nameParts[\count($nameParts) - 1] ?? 'Unbekannt';
            $usage     = "EFG-{$nachname}-{$vorname}-{$shortCode}";

            $epcData = $this->bankQrGenerator->generate($permit->validity->preis, $usage);
        }

        // Dynamische Zahlungslogik
        $requirePayment = (bool) $this->config->get('require_payment_for_validity', false);
        $dueDays        = (int) $this->config->get('payment_due_days', 14);
        // Frist ab Erstellungsdatum berechnen (wie in der Mail)
        $dueDate = $permit->erstellt->modify("+$dueDays days")->format('d.m.Y');

        $this->render('checkout/success', [
            'permit'         => $permit,
            'method'         => $method,
            'usage'          => $usage,
            'epcData'        => \urlencode($epcData),
            'requirePayment' => $requirePayment, // NEU übergeben
            'dueDate'        => $dueDate,        // NEU übergeben
            'settings'       => [
                'vereins_name' => $this->config->get('vereins_name'),
                'base_url'     => $this->config->getBaseUrl(),
                'iban'         => $this->config->get('iban'),
                'kontoinhaber' => $this->config->get('kontoinhaber'),
                'bic'          => $this->config->get('bic'),
            ],
            'appRoot' => $this->config->get('root_path'),
        ]);
    }

    // TODO DOCBLOCK
    private function render(string $templatePath, array $data = []): void
    {
        \extract($data);
        include $this->config->get('root_path') . "/templates/pages/{$templatePath}.phtml";
    }
}
