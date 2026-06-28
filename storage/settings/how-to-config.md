# Handbuch: Systemeinstellungen (JSON) richtig bearbeiten

Willkommen im Einstellungs-Ordner des KGA-Einfahrts-Managers!
In den `.json`-Dateien in diesem Ordner stecken alle Texte, Preise, Fahrzeugtypen und Regeln Ihres Systems. Sie können diese Dateien jederzeit bearbeiten, um das System an Ihre Bedürfnisse anzupassen.

⚠️ **WICHTIGER CRASHKURS VOR DEM BEARBEITEN:**
JSON-Dateien sind sehr pingelig. Wenn Sie einen Fehler machen, kann es sein, dass die Webseite vorübergehend nicht lädt. Beachten Sie immer diese 3 goldenen Regeln:

1. **Texte** stehen IMMER in doppelten Anführungszeichen: `"Beispiel"`
2. **Zahlen** und **Wahrheitswerte** (`true` für Ja / `false` für Nein) stehen OHNE Anführungszeichen: `465` oder `true`
3. **Kommas:** Nach jedem Eintrag (außer dem allerletzten in einer Liste) MUSS ein Komma stehen! Setzen Sie niemals ein Komma hinter den allerletzten Eintrag vor einer schließenden Klammer `}` oder `]`.

---

## 🚗 Fahrzeuge & Genehmigungen

### `vehicles.json` (Fahrzeugtypen)

Hier legen Sie fest, welche Fahrzeuge im Antragsformular ausgewählt werden können.

* **`label`**: Der Name, der dem Nutzer im Menü angezeigt wird (z. B. `"Privat PKW"`).
* **`icon`**: Das kleine Bildchen. Lassen Sie diesen Pfad am besten, wie er ist.
* **`show_company`**: Steht hier `true`, muss der Nutzer zwingend einen Firmennamen angeben. Bei `false` wird das Feld versteckt.
* **`active`**: Steht hier `false`, wird das Fahrzeug im Formular nicht mehr angeboten. *Tipp: Löschen Sie alte Fahrzeuge niemals komplett aus der Datei, sondern setzen Sie sie nur auf `false`. Sonst gibt es Fehler bei alten Genehmigungen!*

### `templates.json` (Genehmigungs-Vorlagen & Preise)

Hier definieren Sie die Dauer und die Kosten für eine Genehmigung.

* **`type`**: Entweder `"standard"` (normale Einfahrt) oder `"permanent"` (Dauerkarte).
* **`days`**: Die Gültigkeit in Tagen (z.B. `7` oder `30`). Wenn der Nutzer den Zeitraum selbst auf dem Kalender wählen soll, schreiben Sie hier `"custom"` (in Anführungszeichen!).
* **`prices`**: Hier weisen Sie jedem Fahrzeugkürzel aus der `vehicles.json` einen Preis zu. Beispiel: `"pkw": 5.0` bedeutet 5,00 Euro für PKWs. Dezimalstellen immer mit Punkt, nicht mit Komma!
* **`public`**: Steht hier `true`, kann jeder Pächter diese Vorlage auf der Webseite frei auswählen. Bei `false` kann nur der Vorstand diese Genehmigung im Admin-Bereich (oder über einen Gutscheincode) ausstellen.

---

## 🕒 Öffnungszeiten & Feiertage

### `times.json` (Wann darf gefahren werden?)

Diese Datei steuert die Schrankenzeiten ganz exakt.

* **`default_opening_hours`**: Die normalen Wochenzeiten. Jeder Wochentag (z.B. `"mon"` für Montag) hat eine Liste von Zeitblöcken.
  * *Beispiel für vormittags und nachmittags:* `[["07:00", "13:00"], ["15:00", "20:00"]]`
  * *Beispiel für durchgehend:* `[["07:00", "20:00"]]`
  * *Beispiel für komplett gesperrt (z. B. Sonntag):* `[]` (Einfach eine leere Klammer).
* **`seasons`**: Hier können Sie Jahreszeiten definieren, in denen andere Zeiten gelten (z.B. Winterruhe). `"start": "10-01"` bedeutet 1. Oktober.
* **`holiday_check`**: Tragen Sie hier Ihr Bundesland ein (z. B. `"Berlin"`). Das System berechnet Ostern, Pfingsten etc. dann vollautomatisch und sperrt die Anlage an diesen Tagen!
* **`custom_holidays`**: Eigene Tage, an denen die Anlage gesperrt ist (z. B. Sommerfest).
  * *Wie füge ich einen Tag hinzu?* Schreiben Sie das Datum im Format Jahr-Monat-Tag in die Liste: `["2026-12-24", "2026-12-31"]`.

---

## ⚖️ Formular-Auswahl & Rechtliches

### `purposes.json` (Grund der Einfahrt)

Das Auswahlmenü, warum jemand in die Anlage fahren möchte. Vor dem Doppelpunkt steht ein kurzes internes Kürzel, danach der Text für den Nutzer.
*Beispiel für einen neuen Eintrag:* `"umzug": "Umzug / Möbeltransport"`

### `reasons.json` (Interne Notizen)

Wenn der Vorstand manuell eine Genehmigung ausstellt, kann er hier schnell einen Grund anklicken (z. B. "Bargeld erhalten"). Fügen Sie einfach neue Texte in Anführungszeichen zur Liste hinzu.

### `agreements.json` (Checkboxen / Zustimmungen)

Dinge, die der Pächter vor dem Absenden abhaken muss (AGB, Datenschutz).

* **Der Link-Trick:** Wenn Sie ein Wort in eckige Klammern setzen (z. B. `gemäß [Datenschutzerklärung] ein`), wird dieses Wort automatisch blau hinterlegt und klickbar gemacht!
* **`link`**: Die Web-Adresse oder PDF-Datei, die sich beim Klick öffnen soll.
* **`required`**: Bei `true` kann das Formular ohne Häkchen nicht abgesendet werden.

### `datenschutz.json` & `impressum.json` (Ihre Rechtstexte)

Hier stehen die rechtlichen Angaben Ihres Vereins.

* Ändern Sie einfach die Texte bei "verein", "adresse", "telefon" etc. in Ihre echten Daten.
* Bei `datenschutz.json` unter `"sections"` können Sie in Zukunft per Array weitere Datenschutz-Absätze hinzufügen.

### `consent.json` (Cookie Banner)

Das Hinweisfenster am unteren Bildschirmrand. Wenn Sie keine Cookies von Google Analytics nutzen, können Sie `"enabled": false` setzen, dann verschwindet das Banner komplett.

---

## 🎨 Optik & Organisation

### `organization.json` (Basis-Vereinsdaten)

* **`base_url`**: Die Web-Adresse, unter der das System läuft. WICHTIG: Sie muss zwingend mit einem Schrägstrich `/` enden! (z.B. `"https://meine-kga.de/manager/"`)
* **`vereins_name`**: Wie Ihr Verein heißt. Dies steht oben auf den Ausdrucken.
* **`prefix`**: Kurze Buchstabenkombination vor jeder Genehmigung, z.B. `"ZM"` für Zufahrts-Manager.

### `colors.json` (Farben auf den PDF-Ausdrucken)

Sie können die Farben der Dokumente als HEX-Code ändern. (Suchen Sie im Internet nach "HEX Color Picker", um Codes wie `#2ecc71` für ein schönes Grün zu finden).

---

## ✉️ Kommunikation & Finanzen

### `email.json` (Postausgang)

Hier stehen Ihre SMTP-Zugangsdaten (E-Mail, Passwort, Server-Adresse), damit das System Mails verschicken kann.

* **`send_board_notification`**: Bei `true` bekommt der Vorstand bei jedem neuen Antrag eine E-Mail-Zusammenfassung.
* **`magic_link_duration`**: Wie viele Minuten ein 6-stelliger Login-Code gültig ist (Standard: `15`).

### `payment.json` (Bank & PayPal)

Die Bankverbindung für die Rechnungen.

* **`iban`**, **`bic`**, **`kontoinhaber`**: Ändern Sie dies zwingend in die Daten Ihres Vereins! Diese Daten werden in der E-Mail und als Giro-QR-Code angezeigt.
* **`payment_due_days`**: Wie viele Tage der Pächter Zeit hat, das Geld zu überweisen (Standard: `14`).
* **`payment_due_days_before_validity`**: Wie viele Tage VOR Beginn der Genehmigung die Zahlung allerspätestens fällig ist (Standard: `2`). Das System wählt immer die strengere Frist aus.

### `settings.json` (System-Grenzen)

Aktuell wird hier nur geregelt, wie viele Einträge im Dashboard pro Seite angezeigt werden (z. B. `25`).

---

## 💾 System, Speicher & Datenbank

### `storage.json` (Ordner, Backups & Speichermethoden)

Diese Datei steuert das Herzstück der Datenverwaltung.

* **Wartung & Automatisierung**:
  * **`use_pseudo_cron`**: Bei `true` erledigt das System im Hintergrund Aufräumarbeiten (wie das Anonymisieren alter Genehmigungen oder Erstellen von Backups), sobald der Vorstand im Admin-Bereich klickt.
  * **`archive_grace_days`**: Zusatztage, die abgewartet werden, bevor eine abgelaufene Genehmigung endgültig in das Archiv verschoben wird (Standard: `0`).
* **Backups (`backup_settings`)**:
  * **`interval_hours`**: Zeitfenster in Stunden zwischen den automatischen Voll-Backups (Standard: `24`).
  * **`max_backups`**: Wie viele Sicherungen maximal auf dem Server behalten werden, bevor die jeweils älteste gelöscht wird (Standard: `15`).
* **Speichermethoden / Engine-Mapping (`storage_config`)**:
  * Das System kann jeden Datenbereich einzeln entweder als Datei (`"type": "json"`) oder in einer echten Datenbank (`"type": "mysql"`) speichern.
  * *Tipp:* Wenn Sie das System auf MySQL umstellen möchten, ändern Sie einfach das Wort `"json"` in `"mysql"`. Sie können die bestehenden Daten danach bequem über den Tab "Migration" im Dashboard synchronisieren!

### `config/storage.php` (Datenbank-Zugang)

⚠️ **WICHTIG:** Dies ist die einzige Einstellungsdatei, die absichtlich eine `.php` Datei geblieben ist und *nicht* im Settings-Ordner liegt!
*Warum?* Hier stehen Ihre sensiblen **MySQL-Datenbank-Passwörter**. PHP-Dateien werden vom Server niemals als Klartext im Browser angezeigt - das schützt Ihre Passwörter vor unbefugtem Zugriff.

* **`enabled`**: Setzen Sie dies auf `true`, wenn Sie eine MySQL-Datenbank nutzen möchten.
* **`host`, `dbname`, `user`, `pass`**: Tragen Sie hier die Zugangsdaten Ihres Webhosters ein.
