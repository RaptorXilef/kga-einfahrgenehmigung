# Software-Architektur & Clean-Code-Standards

## Vorwort & Architektur-Vision

Willkommen in der Codebasis des KGA-Einfahrts-Managers. Dieses Projekt wurde mit einem klaren architektonischen Leitgedanken entwickelt: **Maximale Zuverlässigkeit, Typsicherheit und Wartbarkeit.**

Anstatt auf veraltete, lose gekoppelte Skripte zu setzen, nutzt diese Software moderne Paradigmen aus dem **Domain-Driven Design (DDD)** und den **SOLID-Prinzipien** (speziell ab PHP 8.3). Durch den Einsatz eines eigenen Dependency Injection Containers, einer strikten Middleware-Pipeline, des Action-Domain-Responder (ADR) Patterns und stark typisierten Value Objects ist das System nicht nur extrem robust gegenüber Fehlern, sondern auch flexibel erweiterbar. Infrastruktur (wie Datenbanken oder E-Mail-Provider) und Kernlogik sind vollständig entkoppelt.

Dieses Dokument dient als Leitfaden für die wichtigsten Architektur-Entscheidungen und Muster, die in diesem Projekt Anwendung finden.

---

## 1. Typsichere Status-Verwaltung mittels PHP Enums

**Problem:** "Magic Strings" (`'bezahlt'`) führen zu unentdeckten Tippfehlern.
**Lösung:** Nutzung von PHP 8.1+ Enums (z. B. `PermitStatus`). Die IDE erkennt ungültige Werte sofort, was zu absoluter Typsicherheit und Refactoring-Sicherheit führt.

## 2. Flash-Messages (Post-Redirect-Get Pattern)

**Problem:** `?msg=...` in URLs sieht unsauber aus und führt bei F5-Refreshes zu doppelten Meldungen.
**Lösung:** Nutzung des `SessionManager` für Flash-Messages nach dem Post-Redirect-Get-Pattern. Meldungen werden einmalig gerendert und direkt gelöscht.

## 3. Automatisches Action-Routing via PHP-Attribute

**Problem:** Manuelles Pflegen von Factory-Switches (`match ($actionKey)`) ist fehleranfällig.
**Lösung:** Actions definieren sich selbst über PHP-Attribute (z. B. `#[Route('export')]`). Die Applikation registriert diese vollautomatisch ("Zero-Configuration").

## 4. Data Transfer Objects (DTOs) & Request Validation

**Problem:** Unvalidierte `$_POST`-Zugriffe direkt in Controllern blähen den Code auf und bergen Sicherheitsrisiken.
**Lösung:** Jede Anfrage passiert ein DTO (z.B. `PermitSubmitRequest::fromArray()`). Dort wird der Input bereinigt und validiert. Die Kernlogik arbeitet danach ausschließlich mit garantierten, typisierten Objekten.

## 5. Die Middleware-Pipeline (Sicherheits-Schichten)

**Problem:** Wiederholende Logik-Checks (Login, Rechte, Wartungsmodus, CSRF) in jeder Datei.
**Lösung:** Implementierung einer Middleware-Pipeline (z.B. `AuthGuardMiddleware`, `CsrfMiddleware`). Jede Anfrage durchläuft diese "Türsteher". Die eigentliche Action ist völlig befreit von Infrastruktur-Checks (Single Responsibility Principle).

## 6. EventDispatcher & Domain Events

**Problem:** Das Speichern eines Antrags löst Mails, Benachrichtigungen und PDFs aus - alles eng in einer Klasse verwoben.
**Lösung:** Das Observer-Pattern. Der `PermitService` speichert nur den Datensatz und feuert ein `PermitCreatedEvent`. Unabhängige Listener (z.B. `SendPermitMailListener`) reagieren darauf im Hintergrund.

## 7. Dependency Injection (DI) & Autowiring

**Problem:** Harte Abhängigkeiten (`new SmtpMailService()`) machen das System starr und schwer testbar.
**Lösung:** Ein DI-Container mit Reflection-basiertem Autowiring. Klassen deklarieren ihre Abhängigkeiten im Konstruktor, das System instanziiert und injiziert sie vollautomatisch.

## 8. Action-Domain-Responder (ADR) & Strict Typing

**Problem:** "Fat Controllers" und Nebeneffekte durch Mutation.
**Lösung:** Konsequente Nutzung von `declare(strict_types=1);` und `final readonly class`. Controller sind in atomare Actions aufgeteilt, die den Service aufrufen und eine `Response` (z.B. `JsonResponse`) zurückgeben.

## 9. Value Objects (Vermeidung von "Primitive Obsession")

**Problem:** E-Mails oder Kennzeichen als rohe Strings auszuwerten führt zu dezentraler Validierung.
**Lösung:** Eigene Klassen für Werte (`EmailAddress`, `LicensePlate`). Sobald ein solches Objekt existiert, ist mathematisch garantiert, dass der Wert formell korrekt ist.

## 10. Repository Pattern & Dependency Inversion

**Problem:** Direkte SQL-Queries im Code verunmöglichen den Wechsel des Speichermediums.
**Lösung:** Services fordern nur Interfaces (z. B. `StorageInterface`). Über den DI-Container wird dynamisch entschieden, ob z.B. MySQL (`MySqlStorage`) oder JSON (`JsonStorage`) zum Einsatz kommt.

## 11. Zentrales Exception Handling

**Problem:** Abstürze geben sensible Stack-Traces an den User weiter.
**Lösung:** Ein `GlobalExceptionHandler` fängt alle Fehler ab. Im Produktivbetrieb sieht der Nutzer eine schicke Vereins-Fehlerseite, während der Admin präzise Logs im Hintergrund erhält.

## 12. Security by Design

**Problem:** Brute-Force- und XSS-Angriffe.
**Lösung:** Ein `RateLimiter` blockiert IPs nach zu vielen Fehlversuchen. Die `SecurityHeadersMiddleware` injiziert bei jedem Request harte Browser-Vorgaben (CSP, HSTS).

## 13. Rich Domain Models

**Problem:** "Blutarme" Datenklassen, deren Logik in riesigen Service-Klassen liegt.
**Lösung:** Entitäten verwalten ihren Zustand selbst. Ein `Voucher` weiß per `$voucher->isValid()`, ob er abgelaufen ist. Die Geschäftslogik liegt bei den Daten.

## 14. Concurrency Control (Locking)

**Problem:** Gleichzeitige Anfragen ("Doppelklicks") korrumpieren JSON-Dateien oder lösen Gutscheine doppelt ein (Race Conditions).
**Lösung:** Der `LockManager` nutzt Dateisystem-Sperren (`flock`). Kritische Transaktionen (Checkout) werden linear nacheinander abgearbeitet - Datenkorruption ist ausgeschlossen.
