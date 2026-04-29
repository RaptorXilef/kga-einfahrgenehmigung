# 🗺 Erweiterte Roadmap: v0.10.1 bis v1.1.0

| **Version** | **Fokus** | **Features** |
| --- | --- | --- |
| **v0.10.3** | **Daten & Zeit** | Speicherung des Erstellungs-Zeitpunkts & Dynamisches Ruhezeiten-Array (Wochentag-spezifisch). |
| **v0.11.0** | **Admin-Power** | Manuelle Genehmigungserstellung (Bargeld-Workaround) & Gutschein-System (Einmal-Codes). |
| **v0.12.0** | **Self-Service** | "Meine Genehmigungen": E-Mail-basierter Login-Link (15 Min. gültig) zur Verlaufseinsicht. |
| **v1.0.0** | **Dokumente** | PDF-Integration (Dompdf), Browser-Vorschau & E-Mail-Anhang. |
| **v1.1.0** | **Finanzen** | PayPal Live-Schaltung & Finaler Audit. |

## v0.10.3 - Zeitmanagement & Datenpräzision

- [ ] Feld `erstelltAm` in Permit-Entität und Storage (JSON/MySQL) fixieren.
- [ ] Implementierung der dynamischen Ruhezeiten-Matrix (Mo-So, individuelle Slots).
- [ ] Anzeige überfälliger Zahlungen im Admin-Dashboard (basiert auf Antragsdatum).

## v0.11.0 - Manuelle Buchungen & Gutscheine

- [ ] Admin-UI: Formular zur manuellen Erstellung kostenloser Genehmigungen (Lvl 0-3).
- [ ] Gutschein-System: Generierung von Einmal-Codes für kostenlose Buchungen durch Pächter.
- [ ] Validierung: Integration der Gutschein-Logik in den Bezahlprozess.

## v0.12.0 - Pächter-Verlauf (Self-Service)

- [ ] Neuer Controller für den Antragsverlauf.
- [ ] "Magic Link" System: 15 Minuten gültiger Token per E-Mail zur Identifikation.
- [ ] Übersicht aller aktiven und vergangenen Genehmigungen für den Pächter.

## v1.0.0 - PDF & Dokumenten-Automatisierung

- [ ] Installation und Setup von Dompdf via Composer.
- [ ] PDF-Renderer für das fälschungssichere A4-Zertifikat.
- [ ] Automatischer Versand der PDF als E-Mail-Anhang nach Zahlung.

## v1.1.0 - Finalisierung

- [ ] PayPal Live-API Integration.
- [ ] Projekt-Abschluss und Übergabe.
