# Coding Guidelines & Klassenstruktur

Um die hohe Code-Qualität und Lesbarkeit dauerhaft zu gewährleisten, gelten für die Entwicklung am KGA-Einfahrts-Manager strenge, semantische Vorgaben.

## 1. Strict Typing & Immutability

* Jede PHP-Datei **muss** mit `declare(strict_types=1);` beginnen.
* Klassen, die nicht zur Vererbung gedacht sind (z.B. Actions, Services), **müssen** `final readonly class` sein (PHP 8.2+). Das zwingt zur Immutability.

## 2. Reihenfolge in Methoden & Klassen

Der Aufbau einer Klasse folgt strikt dem "Bedeutungs-Flow" von oben nach unten:

1. **Properties:** Deklaration der Eigenschaften (bei `readonly` Klassen meist in der Konstruktor-Signatur per Constructor Property Promotion).
2. **Magic Methods:** `__construct` für die Dependency Injection.
3. **Public Methoden:** Die öffentliche API, die von außen angesprochen wird (z. B. `handleRequest` oder `execute`).
4. **Private/Protected Helper:** Die gekapselte interne Logik.

## 3. Anordnung in Interfaces

Interfaces definieren Verträge. Die Reihenfolge richtet sich nach Zugriffshäufigkeit und Mutationsgrad:

1. **Lese-Methoden / Getter:** (z. B. `loadAll()`, `findByHash()`, `isValid()`).
2. **Schreib-Methoden / Mutationen:** (z. B. `save()`, `delete()`, `enqueue()`).
3. **Utility-Methoden:** Low-Level Helfer (z. B. `migrateTo()`).

## 4. Repository & Storage Struktur (App\Infrastructure)

Für alle Infrastruktur-Klassen (Datenbank, JSON-Speicher, Mail-Versand) gilt die Regel:
`Konstruktor ➔ Öffentliche API ➔ Private Logik ➔ Private Low-Level-Helfer (Dateipfade, Queries)`.

Für reine Daten-Repositories (CRUD) ist die Reihenfolge bindend:
**Laden ➔ Speichern ➔ Suchen ➔ Löschen ➔ Hilfsmethoden**
