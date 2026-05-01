# 📁 Dokumentation: Pächter-Verlauf (Self-Service Portal)

Das Verlaufsportal ermöglicht Pächtern den Zugriff auf alle ihre bisherigen Genehmigungen und bietet eine bequeme Nachdruck-Funktion.

## 1. Authentifizierung (Magic Link)

Um die Hürden niedrig zu halten, nutzt das System keine Passwörter, sondern **Magic Links**:

1. Der Nutzer gibt seine E-Mail-Adresse auf der Login-Seite an.
2. Das System validiert die Existenz von Datensätzen zu dieser E-Mail.
3. Ein temporärer, kryptographischer Token wird generiert und per E-Mail versandt.
4. Nach Klick auf den Link wird eine Session für den Browser des Nutzers erstellt.

**Sicherheitsparameter:**

- **Gültigkeit:** Standardmäßig 15 Minuten (einstellbar via `magic_link_duration`).
- **Einmal-Nutzung:** Der Token wird sofort nach der ersten erfolgreichen Verifizierung gelöscht.

## 2. Funktionsübersicht

Nach dem Login erhält der Pächter eine tabellarische Übersicht seiner Genehmigungen:

- **Status-Check:** Anzeige, ob die Genehmigung bereits bezahlt wurde oder noch auf Zahlung wartet.
- **SmartCode:** Anzeige der eindeutigen Kennung für die Windschutzscheibe.
- **Zeitraum:** Historische und zukünftige Gültigkeitsdaten.

## 3. Self-Service Druckfunktion

Pächter können über den Button **"Dokument laden"** das offizielle Genehmigungs-Dokument (A4-Format) jederzeit neu generieren.

- Dies verhindert Rückfragen beim Vorstand, falls die ursprüngliche E-Mail verloren ging.
- Die Druckansicht entspricht exakt dem offiziellen Dokument, das auch beim Erstantrag versandt wird.

## 4. Datenschutz & Sicherheit

- **Strict Ownership:** Beim Abruf eines Dokuments prüft der Server, ob die E-Mail-Adresse der aktiven Session exakt mit der E-Mail-Adresse in der Genehmigung übereinstimmt.
- **Session-Timeout:** Die Anmeldung ist an die PHP-Session gebunden und erlischt beim Schließen des Browsers oder manuellem Logout.

## 4. Konfiguration

In der `config.php` können folgende Parameter angepasst werden:

- `magic_link_duration`: Lebensdauer des Login-Links in Minuten.
