# Automatische Prozesse & Cronjobs

Der KGA-Einfahrts-Manager führt im Hintergrund wichtige Wartungsarbeiten aus, wie das DSGVO-konforme Archivieren von abgelaufenen Genehmigungen, das Erstellen von Backups und den stufenweisen Versand von E-Mails (Mail-Queue).

## 1. Der Pseudo-Cronjob (Standard)

Standardmäßig ist das System so konfiguriert, dass es Wartungsarbeiten automatisch ausführt, sobald sich ein Administrator im Backend einloggt oder im Dashboard navigiert (`SystemMaintenanceMiddleware`).

* **Vorteil:** Funktioniert auf jedem noch so günstigen Webspace ohne Konfiguration.
* **Nachteil:** Wenn sich drei Tage lang niemand einloggt, werden auch drei Tage lang keine Backups erstellt und keine archivierten Daten bereinigt.

## 2. Der "Echte" Cronjob (Empfohlen)

Für einen professionellen, zeitgenauen Betrieb (z. B. exakt um 0:05 Uhr) empfehlen wir die Nutzung echter Cronjobs. Bei Webhostern ohne eigene Cronjob-Funktion können kostenlose externe Dienste wie [cron-job.org](https://cron-job.org) genutzt werden. Diese rufen die URLs deiner Applikation zu festgelegten Zeiten als "unsichtbarer Besucher" auf.

Um unbefugte Aufrufe zu verhindern, müssen die URLs mit dem `cron_secret` aus deiner `config/secrets.php` autorisiert werden.

### Job A: System-Wartung (Backups & DSGVO-Bereinigung)

Dieser Job kümmert sich um die schweren Datenbank-Operationen. Er verschiebt abgelaufene Genehmigungen ins Archiv, anonymisiert alte Datensätze und erstellt das automatische Backup.

* **Ausführungs-Intervall:** 1x täglich (Empfehlung: `00:05 Uhr`).
* **Aufruf-URL:** `https://deine-kga.de/cron.php?token=DEIN_CRON_SECRET`

### Job B: Mail-Warteschlange (Mail-Queue)

Um zu verhindern, dass der Server bei vielen gleichzeitigen Anträgen (z. B. 50 auf einmal) durch Spam-Filter blockiert wird oder in ein Time-Out läuft, nutzt das System eine Mail-Queue. E-Mails werden in die Datenbank geschrieben und tröpfchenweise versendet.

* **Ausführungs-Intervall:** Alle `5 Minuten`.
* **Aufruf-URL:** `https://deine-kga.de/api/process_mail_queue.php?token=DEIN_CRON_SECRET`
