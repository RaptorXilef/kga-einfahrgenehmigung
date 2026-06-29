# Pächter-Verlauf (Self-Service Portal)

Das Verlaufsportal (`history.php`) reduziert den administrativen Aufwand des Vorstands erheblich. Es ermöglicht Pächtern, eigenständig auf aktive und historische Genehmigungen zuzugreifen.

## 1. Authentifizierung ("Magic Links")

Um Passwort-Verwaltung für Pächter obsolet zu machen, arbeitet das System passwortlos.

1. Eingabe der E-Mail-Adresse.
2. Der `RateLimiter` verhindert Brute-Force-Scraping.
3. Der `MagicLinkService` generiert ein Token und schickt dieses an den Nutzer (`SendMagicLinkMailListener`).
4. Nach Klick oder Code-Eingabe wird eine sichere, zeitlich begrenzte Browser-Session etabliert.
*Konfiguration:* Die Gültigkeit des Tokens wird in der Config unter `magic_link_duration` (Standard: 15 Min) gesteuert.

## 2. Portal-Features

* **Status-Live-Check:** Pächter sehen in Echtzeit, ob eine Zahlung verbucht wurde oder überfällig ist (`getOverdueLevel()`).
* **Dokumenten-Nachdruck:** Mit einem Klick auf "Dokument" wird das PDF-äquivalente A4-Dokument mit aktuellen Daten (wie Feiertagen) neu gerendert (`HistoryPrintAction`).
* **Auto-Archiv:** Abgelaufene Genehmigungen des Vorjahres können bei Bedarf nachgeladen werden.

## 3. Stornierungs-System (User Cancellation)

* **Logik:** Liegt der Gültigkeitsbeginn einer Genehmigung in der Zukunft und ist sie noch unbezahlt, kann der Pächter sie selbst stornieren (`cancelPermit()`).
* **Datenschutz:** Der Eintrag wird sofort anonymisiert und vom aktiven Speicher (`permits`) in den Storno-Speicher (`permits_cancelled`) verschoben.
* *Konfiguration:* Lässt sich global via `allow_user_cancellation = true/false` steuern.

## 4. Sicherheit

* **Strict Ownership:** Jede Aktion (z.B. Druck, Storno) verifiziert zwingend, ob die Session-E-Mail identisch zur hinterlegten E-Mail im `Permit`-Objekt ist.
* **Inactivity Watchdog:** Ein JS-Watcher meldet den User bei Nicht-Aktivität im Browser automatisch vom Server ab.
