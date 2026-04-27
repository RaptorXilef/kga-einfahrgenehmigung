# 🗺️ Der Schlachtplan: Vom Skript zur Architektur

## Phase 1: Die Infrastruktur (Das Fundament)

Zuerst schaffen wir den Raum, in dem der neue Code leben kann. Wir orientieren uns an PSR-4.

- **Struktur aufbauen:** Erstellen der Ordner `src/`, `bootstrap/`, `templates/` und `resources/scss/`.
- **Service Container:** Finalisierung des `Container.php`.
- **Config-Refactoring:** Umwandlung der flachen `config.php` in ein strukturiertes Array oder ein `Config`-Objekt, das vom Container verwaltet wird.

## Phase 2: Der Data-Layer (Die Sicherheit & Persistenz)

Hier lösen wir die "JSON vs. MySQL"-Frage und sichern die Daten ab.

- **DTO (Data Transfer Object):** Wir erstellen eine `Permit`-Klasse. Das verhindert, dass wir mit unsicheren assoziativen Arrays arbeiten (Typsicherheit!).
- **Storage-System:** \* `StorageInterface` (Die Regel).
  - `JsonStorage` (Die aktuelle Lösung).
  - `MySqlStorage` (Die zukünftige Lösung).

- **Migration-Tool:** Ein kleiner Service, der Datensätze von A nach B schiebt.

## Phase 3: Core-Logik & Payment (Das Gehirn)

Hier eliminieren wir die Sicherheitslücken beim Bezahlvorgang.

- **PaymentManager:** Ein Dienst, der verschiedene Anbieter (PayPal, Bank, etc.) koordiniert.
- **Secure Capture:** Wir bauen die Logik so um, dass JavaScript nur den Bezahlvorgang startet, aber **PHP** am Ende direkt bei PayPal prüft: _"Ist das Geld wirklich da?"_ (Server-Side Verification).
- **MailService:** Ein moderner Mailer, der HTML-Templates aus dem `templates/`-Ordner lädt, damit du das Design der Mails ohne PHP-Kenntnisse ändern kannst.

## Phase 4: Modernes Styling (Das Gesicht)

Wir übernehmen dein SCSS-Konzept aus der Referenz.

- **ITCSS & 7-1 Pattern:** Aufbau der `_tokens.scss`, `_mixins.scss` etc.
- **BEM-Komponenten:** Umwandlung der alten Styles in saubere Blöcke (z.B. `.c-permit-card`, `.c-status-badge`).
- **Runtime-Brücke:** CSS-Variablen so setzen, dass wir die "Jahresfarbe" aus der PHP-Config direkt ins CSS injizieren.

## Phase 5: Routing & Views (Die Schnittstelle)

Die alten Dateien (`index.php`, `check.php`, `admin.php`) werden zu schlanken "Entry Points".

- **Logic vs. View:** Die Dateien enthalten keine Logik mehr, sondern rufen nur noch den Container auf und laden ein Template.
- **Admin-Bereich:** Integration der Import/Export-Funktion für den Datenbank-Wechsel.

---

## 🛠️ Benötigte Dateiliste (Vorschau)

| **Datei**                          | **Zweck**                       | **Prinzip**        |
| ---------------------------------- | ------------------------------- | ------------------ |
| `src/DTO/Permit.php`               | Datenmodell einer Genehmigung   | **SSOT**           |
| `src/Storage/StorageInterface.php` | Vertrag für Datenspeicher       | **ISP (SOLID)**    |
| `src/Payment/PayPalProvider.php`   | Server-seitige PayPal Prüfung   | **Security First** |
| `src/Service/MailService.php`      | Versand von Template-Mails      | **SoC**            |
| `resources/scss/main.scss`         | Haupt-Einstiegspunkt für Styles | **ITCSS**          |
