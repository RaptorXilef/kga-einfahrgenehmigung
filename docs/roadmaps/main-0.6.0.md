# Phase 5

1. **Erweiterter Admin-Controller:**

    - Handling von Datumsfiltern (Startdatum bis Enddatum).
    - Rollenbasierte Ansicht (Stufe 2 sieht keine Finanzdaten, wenn gewünscht - oder nur Leserechte).

2. **Statistik-Logik:**

    - Berechnung der Gesamteinnahmen über den gewählten Zeitraum (nutzt `preisSnapshot`).
    - Zählung der Genehmigungen nach Typ (PKW vs. LKW).
    - **Parzellen-Ranking:** Welche Parzelle hat wie oft gebucht?

3. **Export-Service:**

    - **CSV-Export:** Perfekt für Excel/Buchhaltung.
    - **JSON-Export:** Für technisches Backup oder Migration.
    - Direkter Download-Trigger im Browser.
