# Technische Dokumentation: Implementierte Clean-Code-Standards

Diese Dokumentation beschreibt die zentralen Architektur-Verbesserungen, die in der Codebasis umgesetzt wurden, um die Wartbarkeit, Typsicherheit und Benutzererfahrung (UX) zu erhöhen.

---

## 1. Typsichere Status-Verwaltung mittels PHP 8.1 Enums

### Problemstellung

Vor der Umstellung basierte die Status-Logik auf sogenannten "Magic Strings" (z. B. `'bezahlt'`, `'offen'`). Dies führte zu einem hohen Risiko für Tippfehler, die nicht zur Laufzeit durch Fehler erkannt wurden, sondern zu logischen Fehlern im Programmablauf führten (z. B. durch `'bezahlt '` mit Leerzeichen).

### Lösung

Wir setzen konsequent auf **PHP Enums**. Damit werden Status-Werte als feste Typen definiert.

**Implementierung:**
Alle Status-Werte sind in der Klasse `PermitStatus` gekapselt:

```php
enum PermitStatus: string {
    case Offen = 'offen';
    case Bezahlt = 'bezahlt';
    case Storniert = 'storniert';
}
```

### Vorteile

- **Typsicherheit:** Die IDE erkennt ungültige Status-Werte sofort.
- **Refactoring-Sicherheit:** Änderungen an den Werten sind zentral an einer Stelle möglich.
- **Vermeidung von Fehlern:** Die Verwendung von nicht existierenden Status-Werten führt zu einem sofortigen fatalen Fehler statt zu unvorhersehbarem Verhalten.

## 2. Flash-Messages (Post-Redirect-Get Pattern)

### Problemstellung

Frühere Implementierungen übergaben Erfolgs- oder Fehlermeldungen direkt über Query-Parameter in der URL (z. B. `?msg=...`). Dies führte zu unsauberen URLs und dem Problem, dass Meldungen beim Neuladen der Seite (F5) mehrfach angezeigt wurden.

### Lösung

Die Einführung von **Flash-Messages über den `SessionManager`**. Dies folgt dem bewährten *Post-Redirect-Get (PRG) Pattern*.

**Implementierung:**

- **Setzen der Nachricht:**

```php
$this->sessionManager->addFlash('success', 'Genehmigung gesperrt!');
return new RedirectResponse('admin.php');
```

- **Verhalten:** Die Nachricht wird in der Session gespeichert, in der View gerendert und anschließend sofort wieder aus der Session entfernt.

### Vorteile

- **Saubere URLs:** Keine technischen Status-Parameter in der Adresszeile.
- **UX-Verbesserung:** Nachrichten erscheinen genau einmal und verschwinden nach einem Refresh.

## 3. Automatisches Action-Routing via PHP-Attribute

### Problemstellung

Bisher mussten neue Actions manuell in einer zentralen Factory (`AdminActionFactory.php`) in einem `match`-Block registriert werden. Dies war fehleranfällig, da Entwickler beim Erstellen einer neuen Action leicht vergessen konnten, den entsprechenden Key in der Factory hinzuzufügen.

### Lösung

Wir nutzen nun **PHP-Attribute** zur automatischen Registrierung. Actions werden durch Annotationen direkt als solche gekennzeichnet.

**Implementierung:**Jede Action erhält ein Attribut, welches die Route definiert:

```php
#[Route('export')]
class ExportAction {
    // ...
}
```

Die Factory scannt nun vollautomatisch nach Klassen mit diesem Attribut.

### Vorteile

- **Zero-Configuration:** Neue Action-Dateien werden automatisch erkannt, ohne dass bestehende Konfigurationsdateien oder Factory-Switches modifiziert werden müssen.
- **Skalierbarkeit:** Das System ist nun modularer und weniger wartungsintensiv bei Erweiterungen des Funktionsumfangs.

## 4: Data Transfer Objects (DTOs) & Request Validation (Nie wieder wilde Arrays)

### Problem

Oft greift man direkt auf `$_POST` oder `$_GET` zu und prüft quer durch den Code verstreut mit `isset()` oder `empty()`, ob Daten vorhanden sind. Wenn man Pech hat, rutschen fehlerhafte Daten bis in die Datenbank durch, weil die Validierung an einer Stelle vergessen wurde. Der Controller ist mit unzähligen `if/else`-Blöcken zur Validierung aufgebläht.

### Die Clean-Code-Lösung

Wir nutzen **DTOs (Data Transfer Objects)**. Bevor Daten unsere Kernlogik (Domain) berühren, werden sie an der "Grenze" in ein DTO gegossen (z.B. `PermitSubmitRequest` oder `UserSaveRequest`).

**Implementierung:**
Die Methode `fromArray()` nimmt das rohe Request-Array entgegen, bereinigt es, validiert es und wirft sofort eine `ValidationException`, wenn etwas nicht stimmt.

```php
final readonly class PermitSubmitRequest {
    private function __construct(
        public string $name,
        public string $email,
        // ...
    ) {}

    public static function fromArray(array $post): self {
        $name = \trim(\strip_tags($post['name'] ?? ''));
        if ($name === '') {
            throw ValidationException::withMessage('Bitte geben Sie einen Namen ein.');
        }
        return new self($name, /* ... */);
    }
}
```

**Der riesige Vorteil:**In deiner Action arbeitest du nur noch mit strikt typisierten Objekten (z.B. `$dto->name`). Du hast eine 100%ige Garantie, dass die Daten valide sind, wenn das Objekt existiert. Der Code ist sauber und die IDE bietet vollständige Autovervollständigung.

## 5: Die Middleware-Pipeline (Der "Türsteher" für Actions)

### Problem

In jedem Controller oder Action-Skript muss man prüfen: Ist der Nutzer eingeloggt? Hat er die richtigen Rechte? Ist der Wartungsmodus aktiv? Stimmt das CSRF-Token? Das führt zu massivem Code-Duplikat und man vergisst leicht eine Prüfung.

### Die Clean-Code-Lösung

Wir nutzen eine **Middleware-Pipeline**. Das ist wie eine Reihe von Türstehern vor einem Club. Die Request muss an allen Türstehern vorbei, bevor sie zur eigentlichen Action durchgelassen wird.

**Implementierung:**Im Controller (`AdminController.php`) werden Middlewares wie Schichten um die Action gelegt:

```php
$pipeline = new MiddlewarePipeline();
$pipeline->add($this->securityHeaders)
         ->add($this->maintenanceGuard)
         ->add(new CsrfMiddleware($this->sessionManager, 'admin.php'))
         ->add($this->authGuard);

if ($action instanceof RequiresPermissionInterface) {
    $pipeline->add(new PermissionMiddleware($this->auth, $action->getRequiredPermission(), ...));
}
```

**Der riesige Vorteil:**Deine Actions (z.B. `DashboardRenderAction`) wissen *nichts* von Login-Checks oder CSRF-Tokens. Sie kümmern sich nur um das Dashboard. Das Single Responsibility Principle (SRP) wird perfekt eingehalten. Sicherheit wird zentral an einer Stelle erzwungen.

## 6: EventDispatcher & Domain Events (Entkoppelte Logik)

### Problem

Nachdem eine Genehmigung in der Datenbank gespeichert wurde, musst du eine Bestätigungs-E-Mail senden, einen QR-Code generieren und den Vorstand informieren. Wenn du all das in die `createPermit()`-Methode schreibst, wird diese Methode riesig, langsam und test-feindlich. Fällt der Mail-Server aus, stürzt die ganze Genehmigungs-Erstellung ab.

### Die Clean-Code-Lösung

Wir implementieren das **Observer-Pattern** mit einem `EventDispatcher`. Wenn etwas Wichtiges passiert, "schreit" die Applikation das als Event in den Raum. Wer darauf reagieren möchte (Listener), tut das.

**Implementierung:**Der `PermitService` feuert nur das Event:

```php
$this->eventDispatcher->dispatch(new PermitCreatedEvent($permit, $randomId));
```

Ein eigenständiger Listener (`SendPermitMailListener`) "hört" dieses Event und verschickt die E-Mails im Hintergrund.

**Der riesige Vorteil:**Die Module kennen sich nicht gegenseitig. Du kannst beliebig viele neue Aktionen bei Genehmigungserstellung hinzufügen (z.B. eine Slack-Benachrichtigung senden), ohne den `PermitService` jemals wieder anfassen zu müssen.

## 7: Dependency Injection (DI) & Autowiring (Das Ende von "new Class()")

### Problem

Wenn eine Klasse (z.B. ein Controller) die Datenbank (`PDO`), die Konfiguration (`Config`) und einen Mail-Service braucht, musstest du früher mühsam `new Controller(new PDO(...), new Config(...), new MailService(...))` schreiben. Das führt zu stark gekoppeltem "Spaghetti-Code".

### Die Clean-Code-Lösung

Wir verwenden einen zentralen **DI-Container mit Autowiring** (`src/Bootstrap/Container.php`).

**Implementierung:**Der Container liest per PHP-Reflection den Konstruktor deiner Klassen aus. Wenn eine Action den `PermitService` verlangt, sucht der Container den `PermitService`, sieht, dass dieser die `StorageInterface` braucht, baut diese zusammen und reicht das fertige Objekt in deine Action rein.

```php
public function __construct(
    private AuthService $auth,
    private PermitService $permitService,
) {}
// Der Container liefert all das vollautomatisch!
```

**Der riesige Vorteil:**Du deklarierst nur noch, *was* deine Klasse braucht (im Konstruktor). Du musst dich nie wieder darum kümmern, *wie* es instanziiert wird. Das System wird extrem modular.

## 8: Action-Domain-Responder (ADR) & Strict Typing (PHP 8.3 Power)

### Problem

Traditionelle MVC-Controller werden oft zu groß ("Fat Controllers"). Zudem führt lose Typisierung in PHP oft zu versteckten Bugs, wenn ein String als Integer behandelt wird, oder versehentlich Eigenschaften eines Objekts überschrieben werden.

### Die Clean-Code-Lösung

1. **ADR-Pattern:** Wir zerlegen Controller in einzelne, spezifische `Actions` (z. B. `BankImportProcessAction`). Jede Action macht genau *eine* Sache (SRP). Sie ruft die Domain (Services) auf und gibt einen Responder (z. B. `JsonResponse` oder `RedirectResponse`) zurück.
2. **Strict Typing & Immutability:** Wir nutzen konsequent `declare(strict_types=1);` und PHP 8.3 `final readonly class`.

**Implementierung:**

```php
declare(strict_types=1);

final readonly class SystemClearCacheAction implements ActionInterface {
    public function __construct(private MigrationService $migrationService) {}

    public function execute(ServerRequest $request): mixed {
        $msg = $this->migrationService->clearCache();
        return new RedirectResponse('admin.php?msg=' . \urlencode($msg));
    }
}
```

**Der riesige Vorteil:**`readonly` verhindert, dass Eigenschaften nach der Instanziierung versehentlich überschrieben werden (Immutability). Jede Datei ist extrem kurz, leicht zu lesen und macht exakt eine Sache. `Strict Types` zwingt PHP dazu, Typ-Fehler sofort werfen, statt "leise" falsche Datenformate zu akzeptieren.

## 9: Value Objects (Vermeidung von "Primitive Obsession")

### Problem

In klassischem Code werden Werte wie E-Mail-Adressen, KFZ-Kennzeichen oder Parzellennummern einfach als normale Strings (`string`) übergeben. Das Problem: Ein String kann alles sein. Jeder Teil des Codes muss sich selbst darum kümmern zu prüfen, ob der String wirklich eine E-Mail ist, was zu duplizierter Validierungslogik führt ("Primitive Obsession").

### Die Clean-Code-Lösung

Wir nutzen **Value Objects (Wertobjekte)** aus dem Domain-Driven Design (DDD). Ein Kennzeichen oder eine E-Mail-Adresse ist bei uns kein einfacher `string` mehr, sondern eine eigene Klasse (z. B. `EmailAddress`, `LicensePlate`).

**Implementierung:**
Ein Value Object validiert und formatiert sich bei seiner Erstellung selbst.

```php
final readonly class EmailAddress {
    public string $value;

    public function __construct(string $value) {
        $value = \trim($value);
        if ($value !== '' && ! \filter_var($value, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Ungültiges E-Mail-Format: {$value}");
        }
        $this->value = \strtolower($value);
    }
}
```

**Der riesige Vorteil:**Sobald du in deinem Code ein `EmailAddress`-Objekt in den Händen hältst, hast du die **100%ige mathematische Garantie**, dass diese E-Mail formell gültig und sauber in Kleinbuchstaben formatiert ist. Du musst sie nie wieder an einer anderen Stelle validieren.

## 10: Repository Pattern & Dependency Inversion (Infrastruktur-Unabhängigkeit)

### Problem

Oftmals schreiben Entwickler SQL-Queries (`SELECT * FROM users`) direkt in ihre Controller oder Services. Wenn man sich später entscheidet, die Datenbank zu wechseln (z. B. von MySQL auf reine JSON-Dateien), muss man das gesamte System umschreiben.

### Die Clean-Code-Lösung

Wir nutzen das **Repository Pattern** in Kombination mit dem **Dependency Inversion Principle** (das "D" in SOLID). Die Kernlogik kennt die Datenbank nicht mehr, sie kennt nur noch "Verträge" (Interfaces).

**Implementierung:**Wir definieren ein Interface, z. B. `UserRepositoryInterface`. Die Services verlangen nur dieses Interface. Im Hintergrund (via Dependency Injection) haben wir zwei konkrete Implementierungen gebaut: `MySqlUserRepository` und `JsonUserRepository`.

```php
// Der Service weiß nicht, WO die Daten gespeichert werden:
public function __construct(private UserRepositoryInterface $userRepository) {}

// In der ServiceProvider-Konfiguration wird entschieden:
$container->bind(UserRepositoryInterface::class, function () use ($container) {
    return $config->get('storage_config')['users']['type'] === 'mysql'
        ? new MySqlUserRepository(...)
        : new JsonUserRepository(...);
});
```

**Der riesige Vorteil:**Maximale Flexibilität. Du kannst den Speichertyp via Konfiguration im laufenden Betrieb umschalten, ohne auch nur eine einzige Zeile deiner Business-Logik (`UserService` oder Actions) anfassen zu müssen.

## 11: Zentrales Exception Handling (Keine unschönen "White Screens")

### Problem

Wenn ein Fehler tief im Code passiert (z.B. Datenbank weg, JSON kaputt), stürzt PHP ab. Dem Endnutzer wird eine hässliche Fehlerseite (oft mit sicherheitskritischen Stack-Traces) angezeigt oder die API antwortet fehlerhaft.

### Die Clean-Code-Lösung

Wir nutzen den `GlobalExceptionHandler`. Dieser fängt **alle** ungeplanten Fehler zentral ab, bevor sie den Nutzer erreichen.

**Implementierung:**Mit `\set_exception_handler()` greifen wir Fehler global ab. Der Handler prüft, ob die App im Entwicklermodus (`admin_dev_mode`) läuft oder im produktiven Einsatz ist, sowie ob die Anfrage über eine API oder ein normales Webformular kam.

- **Im Dev-Modus:** Der Entwickler sieht sofort den exakten Fehler inklusive Stack-Trace zur schnellen Fehlerbehebung.
- **Im Produktiv-Betrieb:** Der Nutzer sieht eine freundliche Fehlerseite im Vereins-Design ("Ups! Etwas ist schiefgelaufen"), und der technische Fehler wird geräuschlos im Hintergrund in die `php_errors.log` geschrieben.

**Der riesige Vorteil:**Sicherheitslücken durch geleakte Serverpfade in Fehlermeldungen sind ausgeschlossen. Die User-Experience bleibt selbst bei fatalen Systemfehlern professionell.

## 12: Security by Design (Rate Limiting & Security Headers)

### Problem

Webanwendungen sind oft durch simple Angriffe gefährdet. Jemand könnte ein Skript schreiben, das 10.000 Mal pro Minute versucht, Admin-Passwörter zu erraten oder Login-Codes (Tokens) auszuprobieren.

### Die Clean-Code-Lösung

Sicherheit wurde nicht nachträglich angeflanscht, sondern als Middleware tief in die Applikation verwurzelt (**Rate Limiting**).

**Implementierung:**Wir haben den `RateLimiter` implementiert, der in die Middleware-Pipeline eingebunden ist. Er speichert Login- und Verifizierungsversuche in der `login_attempts`-Tabelle (oder JSON).

```php
// In RateLimitMiddleware.php
if ($this->rateLimiter->isBlocked($ip)) {
    return new RedirectResponse('...msg=Zu viele Versuche. IP für 15 Min gesperrt.');
}
```

Zusätzlich sichert die `SecurityHeadersMiddleware` das System automatisch ab, indem sie bei jedem Request XSS-Schutz, Content-Security-Policies (CSP) und HSTS-Vorgaben in den HTTP-Header injiziert.

**Der riesige Vorteil:**Brute-Force-Attacken auf das System (etwa beim Vorstands-Login oder bei der Eingabe der Bestätigungs-E-Mails) laufen ins Leere. Cross-Site-Scripting-Angriffe (XSS) werden durch die modernen Browser-Vorgaben der Middleware standardmäßig blockiert.

## 13: Rich Domain Models (Kapselung der Geschäftslogik)

### Problem

In vielen klassischen PHP-Projekten sind die Klassen, die Datenbankeinträge repräsentieren (Entities), sogenannte "Anemic Domain Models" (blutarme Modelle). Sie bestehen nur aus öffentlichen Eigenschaften oder simplen Gettern/Settern. Die gesamte Geschäftslogik landet dann in riesigen, unübersichtlichen "Service"-Klassen, die diese dummen Datencontainer manipulieren.

### Die Clean-Code-Lösung

Wir nutzen **Rich Domain Models**. Objekte wie `Permit` oder `Voucher` wissen selbst, wie sie funktionieren, und verwalten ihren eigenen Zustand.

**Implementierung:**Statt im Service zu prüfen, ob ein Gutschein abgelaufen ist, fragen wir das Objekt selbst. Das `Voucher`-Objekt hat Methoden wie `isValid()` oder `redeem()`. Das `Permit`-Objekt hat Methoden wie `isExpired()` oder `isValid()`.

```php
// In der Permit-Entity:
public function isExpired(\DateTimeImmutable $now): bool {
    return $this->validity->bis < $now;
}

// Im Aufrufer:
if ($permit->isExpired($now)) { ... }
```

**Der riesige Vorteil:**Die Logik lebt genau dort, wo auch die Daten sind (hohe Kohäsion). Wenn sich die Regeln dafür ändern, wann eine Genehmigung "gültig" ist, musst du nicht 20 verschiedene Controller durchsuchen, sondern änderst exakt eine Methode in der `Permit`-Klasse.

## 14: Concurrency Control & Thread-Safety (Vermeidung von Race Conditions)

### Problem

Wenn zwei Pächter exakt auf die Millisekunde genau auf "Jetzt bezahlen" klicken oder das System unter Last gerät, greifen mehrere parallele PHP-Prozesse auf dieselben Daten zu. Ohne Schutzmechanismen überschreiben sie sich gegenseitig die Daten (Race Condition). Das ist besonders bei dateibasierten Speichern (JSON) fatal und führt zu Dateikorruption.

### Die Clean-Code-Lösung

Implementierung von striktem **Locking (Concurrency Control)** für kritische Schreiboperationen.

**Implementierung:**Wir nutzen den `LockManagerInterface` (z. B. `FileLockManager`) und den `JsonTransactionTrait`. Für kritische Abläufe (wie den Checkout oder Cronjobs) wird mittels `flock()` ein exklusiver Schreibschutz über die Datei gelegt.

```php
public function finaliseRequest(string $token, string $status = 'offen'): Permit {
    return $this->lockManager->executeWithLock('checkout', function () use ($token, $status) {
        // ... kritische Logik ...
        return $permit;
    });
}
```

**Der riesige Vorteil:**Absolute Datenkonsistenz, selbst bei Lastspitzen. Der zweite Prozess wartet brav im Bruchteil einer Sekunde, bis der erste Prozess seine Transaktion sauber in die Datei (oder Datenbank) geschrieben hat. Defekte JSON-Dateien oder doppelt eingelöste Gutscheine sind damit technisch ausgeschlossen.
