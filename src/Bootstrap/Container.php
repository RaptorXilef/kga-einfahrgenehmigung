<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Bootstrap\Providers\ControllerServiceProvider;
use App\Bootstrap\Providers\CoreServiceProvider;
use App\Bootstrap\Providers\EventServiceProvider;
use App\Bootstrap\Providers\InfrastructureServiceProvider;
use App\Contracts\Config\ConfigInterface;
use App\Infrastructure\Config\Config;

/**
 * Dependency Injection (DI) Container der Anwendung.
 *
 * Verwaltet den Objekt-Lifecycle durch Lazy Loading. Registriert Infrastruktur-Komponenten,
 * Core-Services und Controller und injiziert benötigte Abhängigkeiten.
 * Kontext: Zentraler Registry- und Inversion-of-Control (IoC) Knotenpunkt der Applikation.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 *
 * @copyright (c) 2026 Felix Maywald. All rights reserved.
 * @license   https://github.com/RaptorXilef/kga-einfahrgenehmigung/blob/main/LICENSE
 *
 * @link      https://github.com/RaptorXilef/kga-einfahrgenehmigung/
 *
 * @author    Felix Maywald (@RaptorXilef)
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class Container
{
    /**
     * @var array<string, \Closure>
     */
    private array $services = [];

    /**
     * @var array<string, object>
     */
    private array $instances = [];

    public function __construct(private readonly Config $config)
    {
        $this->setup();
    }

    /**
     * Initialisiert den internen Registrierungsprozess.
     * Mappt die Konfigurations-Instanzen und stößt die funktionsspezifischen Register-Methoden an.
     */
    private function setup(): void
    {
        // Konfiguration (Wird direkt als Instanz übergeben, da sie schon existiert)
        $this->instances[Config::class]          = $this->config;
        $this->instances[ConfigInterface::class] = $this->config;

        // Provider registrieren
        $providers = [
            new InfrastructureServiceProvider(),
            new CoreServiceProvider(),
            new EventServiceProvider(),
            new ControllerServiceProvider(),
        ];

        foreach ($providers as $provider) {
            $provider->register($this);
        }
    }

    /**
     * Registriert einen Service im Container.
     *
     * @param string   $id       Der Identifikator (Klassenname oder Interface).
     * @param \Closure $resolver Die Factory-Funktion zur Erstellung der Instanz.
     */
    public function bind(string $id, \Closure $resolver): void
    {
        $this->services[$id] = $resolver;
    }

    /**
     * Löst eine Abhängigkeit auf und liefert eine shared (Singleton) Instanz zurück.
     * Erstellt das Objekt beim ersten Aufruf über die hinterlegte Closure (Lazy Loading)
     * und cached es für nachfolgende Zugriffe im System.
     *
     * @param string $id Die vollqualifizierte Klasse oder der Identifikations-String des Services.
     *
     * @return mixed Die instanziierte Service- oder Controller-Komponente.
     */
    public function get(string $id): mixed
    {
        if (! isset($this->instances[$id])) {
            $this->instances[$id] = ($this->services[$id])();
        }

        return $this->instances[$id];
    }
}
