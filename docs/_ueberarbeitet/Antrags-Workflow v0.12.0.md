# 📄 Dokumentation: Antrags-Workflow v0.12.0

Dieser Workflow stellt sicher, dass nur verifizierte und abgeschlossene Anträge in der Hauptdatenbank landen und den Vorstand benachrichtigen.

## 1. Phase: Antragstellung (Warteraum 1)

- **Aktion:** Nutzer füllt das Hauptformular aus.
- **Speicherung:** Daten landen in `pending_verification.json`.
- **E-Mail:** Nutzer erhält "E-Mail bestätigen".
- **Gültigkeit:** 24 Stunden. Danach löscht die Auto-Bereinigung den Eintrag.

## 2. Phase: E-Mail Verifizierung (Warteraum 2)

- **Aktion:** Nutzer klickt auf den Link in der Bestätigungs-Mail.
- **Verschiebung:** Daten wandern von `pending_verification.json` nach `verified_pending.json`.
- **Status:** Die E-Mail ist nun verifiziert. Der Antrag ist noch NICHT in der Hauptliste.
- **Gültigkeit:** 48 Stunden. Der Link führt nun zur Bezahlseite (`check.php?token=...`). In dieser Zeit kann der Link aus der Mail immer wieder aufgerufen werden, um zur Bezahlseite zu gelangen.
- **Vorstand:** Wird noch NICHT benachrichtigt.

## 3. Phase: Finaler Abschluss (Datenbank)

Der Umzug in die Hauptdatenbank (`daten.json`) und der Versand der Mails an den Vorstand erfolgt erst durch:

1. **Gutschein:** Ein gültiger Code wird erkannt (entweder sofort beim Verifizieren oder durch Eingabe auf der Bezahlseite).
2. **PayPal:** Die Zahlung wurde über die API erfolgreich bestätigt.
3. **Überweisung:** Der Nutzer klickt explizit auf "Zahlung per Überweisung verbindlich abschließen".

**Nach Auslösung:**

- **Speicherung:** Daten wandern in die finale `daten.json`.
- **Bereinigung:** Eintrag in `verified_pending.json` wird gelöscht.
- **3-Mail-System:**
  - Mail A: Vorstand erhält Infos über neuen (bezahlten/ausstehenden) Antrag.
  - Mail B: Nutzer erhält die offizielle Genehmigung (A4-Dokument).
  - Mail C: Nutzer erhält (nur bei Überweisung) die Zahlungsaufforderung.

**Ergebnis:** Der Vorstand erhält erst dann eine Nachricht, wenn der Nutzer sich für einen Bezahlweg entschieden hat oder der Gutschein gültig war.
