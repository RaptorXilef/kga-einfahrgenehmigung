// scripts/legacy-minify-css_v2.js

import browserslist from 'browserslist';
import { browserslistToTargets, transform } from 'lightningcss';
import fs from 'node:fs';
import path from 'node:path';

const config = [{ src: 'public/assets/css', dest: 'public/assets/css' }];

console.log('🚀 Starte CSS-Optimierung (Minify + Autoprefix)...');

// Holt die Ziel-Browser aus der package.json oder nutzt sinnvolle Defaults
const targets = browserslistToTargets(browserslist('>= 0.5%, last 2 versions, not dead'));

for (const entry of config) {
    // Wandle die Pfade in absolute Pfade um, damit wir problemlos das
    // Arbeitsverzeichnis (cwd) für den execSync Aufruf ändern können.
    const absoluteSrc = path.resolve(entry.src);
    const absoluteDest = path.resolve(entry.dest);

    if (!fs.existsSync(absoluteDest)) fs.mkdirSync(absoluteDest, { recursive: true });
    if (!fs.existsSync(absoluteSrc)) {
        console.warn(`⚠️ Warnung: Quellverzeichnis ${entry.src} nicht gefunden.`);
        continue;
    }

    // Finde alle .css Dateien, aber ignoriere bereits minifizierte .min.css
    const files = fs
        .readdirSync(absoluteSrc)
        .filter((f) => f.endsWith('.css') && !f.endsWith('.min.css'));

    for (const file of files) {
        // file ist jetzt NUR noch der Dateiname (z.B. "main.css")
        const baseName = path.parse(file).name;
        const outputFile = `${baseName}.min.css`;
        const absoluteInput = path.join(absoluteSrc, file);

        // Der absolute Pfad, wo die minifizierte Datei landen soll
        const absoluteOutput = path.join(absoluteDest, outputFile);

        console.log(`  - Verarbeite: ${file}`);

        try {
            // CSS einlesen
            const cssContent = fs.readFileSync(absoluteInput);

            // Lightning CSS transformiert, fügt Prefixes hinzu und minifiziert
            const { code, map } = transform({
                filename: file,
                code: cssContent,
                minify: true,
                sourceMap: true,
                targets: targets,
            });

            // Minifizierte Datei und Source-Map schreiben
            fs.writeFileSync(absoluteOutput, code);
            if (map) fs.writeFileSync(`${absoluteOutput}.map`, map);
        } catch (error) {
            console.error(`  ❌ Fehler bei ${file}:`, error.message);
        }
    }
}

console.log('✅ CSS-Optimierung abgeschlossen.');
