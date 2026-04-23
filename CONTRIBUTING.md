# Contributing to KGA-Einfahrgenehmigung 🦖

Vielen Dank, dass du helfen möchtest, KGA-Einfahrgenehmigung noch besser zu machen!
Um die "Perfection" des Codes zu erhalten, folge bitte diesem Workflow:

## 🛠️ Development Setup

1. Clone das Repo.
2. Führe `composer setup` aus (installiert PHP-Tools & Hooks).
3. Führe `npm install` und `npm run setup` aus.

## 📏 Coding Standards

Wir nutzen strikte Standards. Dein Code wird nur akzeptiert, wenn alle Checks grün sind:

- **PHP:** PER-CS (via `composer analyze:cs`)
- **Static Analysis:** PHPStan Level 6+ (`composer analyze:phpstan`)
- **JS/SCSS:** ESLint & Stylelint (`npm run analyze`)

## 🧪 Testing

Jeder neue Code benötigt Tests:

- Unit-Tests: `vendor/bin/phpunit --testsuite Unit`
- Mutation Testing: `vendor/bin/infection` (Ziel: Hohe MSI-Rate)

## 📝 Commits

Wir nutzen **Conventional Commits**.
Beispiel: `feat(core): add new panel rendering engine`

## Mitwirken am Projekt

Vielen Dank, dass du helfen möchtest! Um sicherzustellen, dass das Projekt rechtlich sauber bleibt und wir den Code langfristig verwalten können, bitten wir alle Mitwirkenden, unser Contributor License Agreement (CLA) zu akzeptieren.

### Contributor License Agreement (CLA)

Mitwirkenden-Lizenzvereinbarung (Individual CLA)
Projekt: KGA-Einfahrgenehmigung
Lizenzgeber: Felix Maywald (alias) RaptorXilef

1. Gewährung von Rechten: Mit der Einreichung von Code oder anderen Materialien (der "Beitrag") an dieses Projekt gewähren Sie dem Lizenzgeber eine unbefristete, weltweite, nicht exklusive, gebührenfreie und unwiderrufliche Urheberrechtslizenz, den Beitrag zu reproduzieren, vorzubereiten, öffentlich anzuzeigen, unterzulizenzieren und zu verbreiten.

2. Kommerzielle Nutzung: Sie erkennen ausdrücklich an, dass der Lizenzgeber den Beitrag in kommerziellen Produkten oder Dienstleistungen verwenden darf, auch wenn das Projekt unter einer nicht-kommerziellen Lizenz für die Allgemeinheit veröffentlicht wird.

3. Urheberschaft: Sie versichern, dass Sie der rechtmäßige Urheber des Beitrags sind oder über die notwendigen Rechte verfügen, diesen Beitrag unter den oben genannten Bedingungen einzureichen.

4. Keine Gewährleistung: Der Beitrag wird "wie besehen" geliefert, ohne jegliche ausdrückliche oder implizite Gewährleistung.

5. Annahme der Bedingungen
   Durch das Erstellen eines Pull Requests und das Hinzufügen des Satzes "I accept the CLA" in der Beschreibung des Pull Requests erklären Sie sich mit diesen Bedingungen einverstanden.

---

Copyright (c) 2026 Felix Maywald | RaptorXilef
