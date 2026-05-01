# 🛡️ Dokumentation: Pächter-Verlauf & Self-Service Portal (v0.13.x)

Das History-System ermöglicht es Pächtern, alle ihre beantragten Genehmigungen ohne klassisches Passwort-Konto einzusehen und Dokumente erneut zu drucken.

## 1. Login-Verfahren (Magic Link)

Da Pächter das System nur selten nutzen, wurde auf Passwörter verzichtet. Stattdessen kommt das **Magic Link** Verfahren zum Einsatz:

1. Der Pächter gibt auf `history.php` seine E-Mail-Adresse ein.
2. Das System prüft, ob für diese E-Mail bereits Genehmigungen in `daten.json` existieren.
3. Falls ja, wird ein kryptographisch sicheres Einmal-Token generiert und per E-Mail versandt.
4. Der Link ist standardmäßig **15 Minuten** gültig (`magic_link_duration` in der Config).
5. Nach dem Klick wird eine Session gesetzt und das Token sofort ungültig gemacht.

## 2. Funktionsumfang

Eingeloggte Pächter sehen eine tabellarische Übersicht mit:

- **SmartCode:** Eindeutige Identifikation.
- **Zeitraum:** Von-Bis Datum der Gültigkeit.
- **Status:** Anzeige ob `WARTEND` (Überweisung offen) oder `BEZAHLT`.
- **Self-Service Druck:** Über den Button "Dokument laden" kann das offizielle A4-PDF/Dokument jederzeit neu generiert werden.

## 3. Sicherheitsaspekte

- **Besitzprüfung:** Beim Aufruf der Druckfunktion wird serverseitig erneut geprüft, ob die Genehmigung tatsächlich zur E-Mail der aktuellen Sitzung gehört.
- **Session-basiert:** Der Zugriff erlischt beim Schließen des Browsers oder nach Klick auf "Abmelden".
- **Token-Schutz:** Token werden in `storage/magic_links.json` mit Ablaufdatum gespeichert und automatisch bereinigt.

## 4. Konfiguration

In der `config.php` können folgende Parameter angepasst werden:

- `magic_link_duration`: Lebensdauer des Login-Links in Minuten.
