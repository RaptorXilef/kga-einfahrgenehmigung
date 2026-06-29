# DSGVO & Datenlebenszyklus-Management

Das System ist so aufgebaut, dass es den Vorgaben der Datenschutz-Grundverordnung (DSGVO) sowie den Aufbewahrungspflichten nach Abgabenordnung (§ 147 AO) automatisch und kompromisslos nachkommt.

## 1. Klassifizierung der Datenfelder

Das System erhebt und klassifiziert Daten bei der Beantragung streng nach Notwendigkeit:

* **Personenbezogene Daten:** Name (z.B. Max Mustermann), E-Mail-Adresse (z.B. <max.mustermann@kga-berlin.de>), Kennzeichen (z.B. B-KG 1234), Parzelle (z.B. Garten 42).
* **Finanzdaten:** Betrag (z.B. 154.50 €), Zweck (z.B. Pacht & Umlagen).
* **Systemdaten:** Belegdatum/Zeitstempel (z.B. 2026).

## 2. Der Lebenszyklus (Lifecycle)

Die Zeitachse der Datenverarbeitung unterteilt sich in drei Phasen:

### Phase A: Aktiv (In Nutzung)

* **Dauer:** Von Erstellung (Jahr 0) bis kurz nach Ablauf der Genehmigung.
* **Status:** Die Rechtsgrundlage ist Art. 6 DSGVO zur Vertragserfüllung/Genehmigung. Die Daten liegen in der primären Speichertabelle (`permits`).

### Phase B: Archivierung nach § 147 AO

* **Dauer:** Von Ablauf bis Jahr 10.
* **Mechanismus:** Der `CronScheduler` ruft täglich `PermitService::autoArchiveExpiredPermits()` auf. Genehmigungen werden aus dem operativen Speicher in das Archiv (`permits_archive`) verschoben.
* **Zweck:** Nachweis für finanzielle Transaktionen gegenüber den Finanzbehörden (10-jährige Aufbewahrungsfrist). Die Daten bleiben hier unverändert, sind aber von der regulären Dashboard-Ansicht getrennt.

### Phase C: Anonymisierung

* **Dauer:** Ab Jahr > 10.
* **Mechanismus:** Der Cronjob führt `anonymizeOldRecords(10)` auf dem Archiv aus.
* **Prozess:**
  * Name, E-Mail und Kennzeichen werden irreversibel mit `[ANONYMISIERT]` überschrieben.
  * Die Parzelle wird auf `0000` gesetzt.
  * Systemdaten und Finanzbeträge bleiben für statistische Zwecke (z.B. für langfristige `ReportingService`-Umsatzberichte) erhalten, weisen aber keinerlei Personenbezug mehr auf.

## 3. Vorzeitiger Lebenszyklus-Abbruch (Stornierung)

Zieht ein Pächter seinen Antrag eigenständig im Self-Service-Portal zurück (`cancelPermit()`), wird dieser Datensatz *sofort* anonymisiert und in die Tabelle `permits_cancelled` verschoben. Personenbezogene Daten werden nicht mehr benötigt und daher sofort zerstört, während der Buchungs-Header als Beleg für die Lücke in den fortlaufenden Genehmigungsnummern ("SmartCodes") verbleibt.
