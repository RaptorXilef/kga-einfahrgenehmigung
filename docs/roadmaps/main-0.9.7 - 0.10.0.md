# Projekt Roadmap - KGA Einfahrgenehmigung

## v0.9.7 - Benutzerverwaltung (Identity Management)

- [ ] Implementierung Superadmin-Logik (Level 0 fest in Config).
- [ ] UI-Seite für Benutzer-CRUD (Create, Read, Update, Delete).
- [ ] Rollensystem: Level 0 (Vollzugriff), Level 1 (Vorstand), Level 2 (Aufsicht).
- [ ] Bezeichnungssystem (Labels) mit Dropdown-Historie für Level 0.
- [ ] Passwort-Reset-Funktion durch Superadmin.

## v0.9.8 - Erweiterte Validierung (Business Intelligence)

- [ ] `PermitService` Update: Overlap-Check für Parzellen.
- [ ] UI-Feedback im Antragsformular bei Zeit-Kollisionen.

## v0.9.9 - Mail-Logging (Audit Trail)

- [ ] Persistenz-Layer für versendete E-Mails.
- [ ] Admin-Dashboard Ansicht für gesendete Mails (Wer, Wann, Was?).

## v1.0.0 - PDF-Generierung & Finalisierung

- [ ] Integration einer PDF-Library (z.B. Dompdf).
- [ ] Generierung des fälschungssicheren A4-Dokuments im Browser.
- [ ] Automatischer PDF-Anhang in der Bestätigungsmail.
- [ ] PayPal Live-Schaltung & Projekt-Abschluss.
