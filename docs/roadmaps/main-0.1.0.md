## 🗺️ Der finale Schlachtplan (v2.0.0)

### Phase 1: Die Infrastruktur & Konfiguration

Wir nutzen die PSR-4-Struktur (`App\`) aus deiner `composer.json`

- **Config-Service:** Wir erstellen ein `Config`-Objekt, das die `config.php` ersetzt und Mail- sowie Bezahldaten sicher verwaltet.
- **Service Container:** Finalisierung des Dependency Injectors in `bootstrap/`.

### Phase 2: Core & Contracts (Die Regeln)

- **Contracts:** Definition von `StorageInterface` und `PaymentProviderInterface` in `src/Contracts/`
- **DTOs:** Erstellen der `Permit`-Entität in `src/Core/` für typsichere Datenverarbeitung.

### Phase 3: Data & Security (Storage Layer)

- **Persistence:** Implementierung von `JsonStorage` und Vorbereitung von `MySqlStorage`.
- **Security:** Einbau von `htmlpurifier`
- **Migration:** Entwicklung der Admin-Logik für den JSON-zu-MySQL-Export.

### Phase 4: Payment & Mail (Application Layer)

- **Secure Payment:** Implementierung der serverseitigen PayPal-Verifizierung (kein JS-only Approval!).
- **Mail Engine:** Ein Service, der HTML-Templates aus `templates/` nutzt (leicht anpassbar).

### Phase 5: UI & Frontend (Resources)

- **SCSS:** Aufbau der ITCSS-Struktur in `resources/scss/` basierend auf deiner Referenz
- **Build:** Nutzung von `npm run make` zur Generierung der minifizierten Assets.
