# Datenbank-Sicherheit & MySQL-Berechtigungen

## Das Prinzip der minimalen Rechte (Principle of Least Privilege)

Um die Sicherheit des KGA-Einfahrts-Managers zu maximieren, darf der Datenbank-Benutzer der Webapplikation exakt nur die Rechte besitzen, die für den reibungslosen Betrieb und das Einspielen von Updates notwendig sind. Alle administrativen oder serverweiten Rechte müssen entzogen werden.

### 1. Zwingend benötigte Rechte (Must-Have)

Da die Applikation nicht nur Daten liest und schreibt, sondern auch eine **Auto-Install-Funktion** (`PdoFactory`) und **automatische Datenbank-Updates** (`UpdateMigrationService`) über das Backend bietet, benötigt der User neben den Daten-Rechten auch Struktur-Rechte (DDL) **für diese spezifische Datenbank**.

Folgende Rechte müssen vergeben werden:

* **`SELECT`, `INSERT`, `UPDATE`, `DELETE`**
  * *Warum?* Für den normalen Anwendungsbetrieb (CRUD). Erlaubt das Auslesen, Erstellen, Bearbeiten und Löschen von Genehmigungen, Benutzern und Logs. (Deckt auch das im Code genutzte `REPLACE INTO` und `INSERT ... ON DUPLICATE KEY UPDATE` ab).
* **`CREATE`**
  * *Warum?* Wird benötigt, damit die `PdoFactory` fehlende Tabellen (z. B. nach einem Update) automatisch anlegen kann (`CREATE TABLE IF NOT EXISTS`).
* **`ALTER`**
  * *Warum?* Wird vom `UpdateMigrationService` benötigt, um bei Updates neue Spalten zu bestehenden Tabellen hinzuzufügen (`ALTER TABLE ... ADD COLUMN`).
* **`DROP`**
  * *Warum?* Wird für zwei spezifische Admin-Werkzeuge benötigt:
        1. Für Tabellen-Umbenennungen in Migrationen (MySQL wertet ein `RENAME TABLE` als `DROP` und `CREATE`).
        2. Für die "Datenbestand löschen"-Funktion im Dashboard (`TRUNCATE TABLE` erfordert das `DROP`-Recht).
* **`INDEX`**
  * *Warum?* Wird benötigt, um bei neuen Tabellen oder in Migrationen die Performance-Indizes (z. B. `INDEX idx_kennzeichen`) anzulegen.

### 2. Strikt zu verbietende Rechte (Gefahrenzone)

Alle anderen Rechte aus der MySQL-Liste werden von dieser Software **nicht** genutzt und stellen ein unnötiges Sicherheitsrisiko dar. Folgende Rechte dürfen **nicht** vergeben werden:

* ❌ **`GRANT OPTION`** (Lebensgefährlich: Erlaubt dem User, seine eigenen Rechte an andere zu vergeben oder neue User zu erstellen).
* ❌ **`SUPER` / `FILE` / `SHUTDOWN`** (Serverweite Admin-Rechte).
* ❌ **`References`** (Wird nur für Foreign Keys (Fremdschlüssel) benötigt. Die Applikation nutzt zur besseren Portierbarkeit keine harten Foreign Keys auf DB-Ebene).
* ❌ **`Lock_tables`** (Die Applikation nutzt moderne, implizite InnoDB-Transaktionen auf Zeilenebene (`$pdo->beginTransaction()`), kein explizites "Locking" der ganzen Tabelle).
* ❌ **`Create_view`, `Show_view`** (Die Software nutzt keine SQL-Views).
* ❌ **`Create_tmp_table`** (Wird nicht verwendet).
* ❌ **`Event`, `Trigger`** (Die Logik liegt im PHP-Code (Domain Events), es werden keine MySQL-internen Trigger oder Events genutzt).
* ❌ **`Create_routine`, `Alter_routine`, `Execute`** (Die Software nutzt keine Stored Procedures (gespeicherte Prozeduren) in der Datenbank).

---

## 3. Best Practice: Der sichere Setup-Prozess

Um das Setup auf einem Live-Server optimal und sicher abzuwickeln, solltest du folgenden Prozess nutzen:

1 **Datenbank manuell anlegen (als Root-Admin):**
   Damit der Web-User keine serverweiten `CREATE`-Rechte benötigt, lege die leere Datenbank einmalig als Server-Admin an.

  ```sql
  CREATE DATABASE `kga_einfahrts_manager` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  ```

2 **Eingeschränkten App-User anlegen:**
   Erstelle einen eigenen Benutzer nur für diese Applikation.

  ```sql
  CREATE USER 'kga_app_user'@'localhost' IDENTIFIED BY 'ein_sehr_sicheres_passwort';
  ```

3 **Rechte exakt und nur auf diese Datenbank beschränken:**
   Der wichtigste Schritt: Vergib die Rechte mit `ON kga_einfahrts_manager.*` anstatt `ON *.*`.

  ```sql
  GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, ALTER, INDEX
  ON `kga_einfahrts_manager`.* TO 'kga_app_user'@'localhost';

  FLUSH PRIVILEGES;
  ```

4 **Konfiguration eintragen:**
   Trage diesen neuen User (`kga_app_user`) nun in der Datei `config/storage.php` ein.

**Ergebnis:** Sollte die Applikation jemals gehackt werden, kann der Angreifer im allerschlimmsten Fall nur die Daten dieser einen KGA-Datenbank manipulieren, jedoch niemals den Datenbank-Server selbst übernehmen, keine anderen Webseiten auf dem Server stören und sich keine eigenen Admin-Zugänge generieren.
