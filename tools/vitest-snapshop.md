# Was passiert beim Ausführen?

Wenn du jetzt `npm run test` ausführst:

1. Vitest merkt: "Hey, ich habe noch keinen Snapshot für diesen Test."
2. Es erstellt eine Datei `tests/frontend/__snapshots__/hero.test.js.snap`.
3. Dort steht dein HTML drin.

**Wenn du nun das HTML in der `createHero`-Funktion änderst** (z. B. die Klasse von `hero__title` zu `hero__headline`), wird der Test fehlschlagen und dir genau zeigen, was sich geändert hat.

> **Profi-Tipp:** Wenn die Änderung Absicht war, drückst du im Vitest-Watcher einfach **`u`** (für Update), um den neuen Snapshot als "Wahrheit" zu akzeptieren.
