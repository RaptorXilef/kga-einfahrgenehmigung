# Hier ist die Reihenfolge

## Methoden allgemein

1. **Properties** (Attribute der Klasse)
2. **Magic Methods** (`__construct`)
3. **Public Methoden** (Die öffentliche API, also die Methoden, die von außen aufgerufen werden, z. B. `handleRequest`)
4. **Private/Protected Hilfsmethoden** (Die interne Logik, wie `handleAuthActions`, `render`, `action...` etc.)

## Interfaces nach der **semantischen Gewichtung** und der **logischen Verwendung**

1. **Getter / Lese-Methoden** (z. B. `load...`, `get...`, `is...`) - Das ist meist der häufigste Zugriff.
2. **Mutations-Methoden** (z. B. `save...`, `enqueue...`, `upload...`) - Diese verändern den Zustand.
3. **Utility / Spezial-Methoden** (z. B. `migrateTo`, `mapToEntity`).
