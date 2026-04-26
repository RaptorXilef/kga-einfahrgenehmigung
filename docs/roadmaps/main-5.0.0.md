# 🗺️ Projekt-Roadmap: Ausnahmegenehmigungs-System

## Phase 1: Die Infrastruktur (Der "Move to Root")

Bevor wir neue Funktionen bauen, ziehen wir die Dateien um.

- **Struktur:** Verschieben von `src`, `templates`, `vendor` etc. aus `kga-core` zurück in den Projekt-Root.
- **Anker-System:** Update der `index.php`, `admin.php` und `check.php` auf die neue, flexible Pfad-Erkennung.
- **Config v2:** Erweiterung der `config.php` um die neuen Parameter (Fahrzeugtypen, Zwecke, Berlin-Sperrzeiten, Bank-Zahlungsziel).

## Phase 2: Die "Berlin-Logik" & Kern-Entitäten

Wir bauen die Intelligenz des Systems um.

- **Permit-Entität:** Anpassung an die vierstellige Parzellennummer (`0762`) und Speicherung des Preises zum Zeitpunkt der Buchung (für die Statistik).
- **Smart-Code Generator v2:** Neue Logik: `[Präfix]-[YY]-[Parzelle]-[ID]`.
- **Berlin-Kalender Service:** Eine Logik, die Sonntage und Berliner Feiertage erkennt und die Einfahrt gemäß Config-Zeiten (XX:XX bis XX:XX Uhr) einschränkt.
- **Kennzeichen-Formatter:** Ein kleiner Service, der aus `BHD7398` automatisch `B-HD 7398` macht.

## Phase 3: Das neue Frontend (User Experience)

Das Formular in der `index.php` wird moderner und interaktiver.

- **Validierung:** E-Mail-Prüfung und Echtzeit-Formatierung des Kennzeichens während der Eingabe.
- **Zeitraum-Rechner:** Ein JS-Tool im Formular, das dem Nutzer sofort anzeigt: "Du darfst in dieser Woche an X Tagen jeweils von A bis B Uhr einfahren" (unter Berücksichtigung von Sonntagen/Feiertagen).
- **Dynamische Felder:** Einblendung von Firma/Lieferant nur bei Auswahl des entsprechenden Typs.

## Phase 4: Das 3-Mail-System

Wir stellen die Kommunikation auf das neue Modell um.

1. **Mail 1 (Vorstand):** Betreff-Formatierung nach Vorgabe, Link zum Admin-Login.
2. **Mail 2 (Zahlung):** Reine Zahlungsaufforderung mit berechnetem Fälligkeitsdatum (`Antrag + X Tage`).
3. **Mail 3 (Genehmigung):** Die formale Ausnahmegenehmigung im A4-Druckformat (CSS `@media print`).

## Phase 5: Admin-Bereich & Berechtigungen

Die Schaltzentrale für den Vorstand.

- **Authentifizierung:** Einfaches Login-System mit zwei Rollen (Admin/Vollzugriff, Viewer/Einsicht).
- **Dashboards:** Aufteilung in Aktive, Zukünftige und Abgelaufene Genehmigungen.
- **Statistik-Modul:** Auswertung von Einnahmen und Parzellen-Frequenz über wählbare Zeiträume.
- **Export-Service:** CSV- und JSON-Export für Buchhaltung und Finanzdaten.

* * *

## 🛠️ Technische Highlights & Herausforderungen

- **Parzellen-Formatierung:** Wir nutzen `str_pad($parzelle, 4, '0', STR_PAD_LEFT)`, um sicherzustellen, dass aus der 20 die 0020 wird.
- **A4-Druck-Design:** Wir werden ein spezielles PHTML-Template entwerfen, das nur Standard-HTML/CSS nutzt, damit es in Outlook, Gmail und beim Drucken identisch aussieht.
- **Feiertage:** Wir implementieren eine kleine Logik für die beweglichen Berliner Feiertage (Ostern, Christi Himmelfahrt etc.), damit das System wartungsfrei bleibt.
