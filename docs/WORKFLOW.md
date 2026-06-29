# Lebenszyklus eines Antrags (Workflow)

Dieser Workflow garantiert, dass das System vor Spam geschützt bleibt und nur final abgeschlossene (verifizierte und bezahlte/mit Gutschein versehene) Anträge in der primären Persistenzschicht (MySQL/JSON) landen.

## Phase 1: Antragstellung (Warteraum 1)

- **Aktion:** Der Nutzer füllt das Formular (`index.php`) aus.
- **Verarbeitung:** Die Daten passieren das `PermitSubmitRequest` DTO.
- **Speicherung:** Über den `PermitService` und das `VerificationRepository` landen die Daten im `pending_verification`-Speicher.
- **Benachrichtigung:** Ein kryptografischer Token wird erzeugt; der Nutzer erhält eine "E-Mail bestätigen"-Nachricht.
- **Gültigkeit:** 24 Stunden. Nach Ablauf räumt der System-Cronjob den Speicher auf.

## Phase 2: E-Mail-Verifizierung (Warteraum 2)

- **Aktion:** Nutzer klickt den Bestätigungslink oder gibt den SmartCode ein.
- **Verarbeitung:** `VerificationSubmitAction` validiert den Code.
- **Speicherung:** Die Daten migrieren vom `pending_verification`- in den `verified_pending`-Speicher.
- **Status:** Die Identität ist bestätigt, die Genehmigung ist aber noch *nicht* aktiv.
- **Sicherheit:** Der Nutzer gelangt in den Checkout. Dieser Link bleibt für 48 Stunden gültig.

## Phase 3: Finaler Abschluss (Primary Storage)

Die Migration in den Hauptspeicher (`permits`) erfolgt erst durch einen validen Abschluss:

1. **Gutschein-Deckung:** Ein Gutschein deckt 100% der Kosten (Gratis).
2. **Überweisung:** Nutzer wählt aktiv den Weg "Zahlung per Überweisung".
3. **PayPal API:** Rückmeldung über erfolgreiche Capture-Transaktion.

**Nach Auslösung (`finaliseRequest`):**

- Lock-Manager greift (verhindert Race-Conditions).
- Eintrag wandert als instanziiertes `Permit`-Objekt in die Hauptdatenbank.
- Der Warteraum-Eintrag wird restlos gelöscht.
- **Event Dispatcher (`PermitCreatedEvent`)** wird gefeuert und versendet:
  1. *Board Notification:* Information an den Vorstand. (falls aktiv)
  2. *A4-Dokument:* Vorab an den Pächter.
  3. *Payment Request:* Zahlungsaufforderung an den Pächter (falls zutreffend).
