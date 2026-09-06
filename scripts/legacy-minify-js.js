import { existsSync } from 'node:fs';
import fs from 'node:fs/promises';
import path from 'node:path';
import { minify } from 'terser';

// PROJEKTUNABHÄNGIGE KONFIGURATION
// Keine Hardcoded-Unterordner mehr. Das Skript spiegelt die gesamte Struktur 1:1.
const config = {
    srcDir: 'src/assets/js',
    destDir: 'public/assets/js',
};

/**
 * Asynchrone Hilfsfunktion: Findet alle JS-Dateien rekursiv in einem Verzeichnis
 * @param {string} dir Das zu durchsuchende Verzeichnis
 * @param {string[]} fileList Array, in dem die Pfade gesammelt werden
 * @returns {Promise<string[]>}
 */
async function walkDir(dir, fileList = []) {
    if (!existsSync(dir)) return fileList;

    const files = await fs.readdir(dir);
    for (const file of files) {
        const filePath = path.join(dir, file);
        const stat = await fs.stat(filePath);

        if (stat.isDirectory()) {
            await walkDir(filePath, fileList);
        } else if (filePath.endsWith('.js') && !filePath.endsWith('.min.js')) {
            fileList.push(filePath);
        }
    }
    return fileList;
}

/**
 * Der eigentliche Minification-Worker
 */
async function processFile(file, srcDir, destDir) {
    const relativePath = path.relative(srcDir, file);
    const outputFilePath = path.join(destDir, relativePath);
    const outputDir = path.dirname(outputFilePath);

    // Ziel-Ordner asynchron anlegen
    await fs.mkdir(outputDir, { recursive: true });

    const mapName = `${path.basename(outputFilePath)}.map`;

    try {
        const code = await fs.readFile(file, 'utf8');

        // Wir können module: true global setzen, da alle Dateien (selbst app.js)
        // im modernen ES-Module Scope laufen. Das hilft Terser beim perfekten Mangle.
        const result = await minify(code, {
            module: true,
            compress: true,
            mangle: true,
            sourceMap: {
                filename: mapName,
                url: mapName,
            },
        });

        // Datei und Source-Map schreiben
        await fs.writeFile(outputFilePath, result.code);
        if (result.map) {
            await fs.writeFile(`${outputFilePath}.map`, result.map);
        }

        console.log(`  ✅ Minifiziert: ${relativePath}`);
    } catch (error) {
        console.error(`  ❌ Fehler bei ${file}:`, error);
    }
}

async function runBuilder() {
    console.log('🚀 Starte JS-Minifizierung (Nativ, Asynchron & Parallel)...');
    console.time('⏱️ Build-Dauer');

    // Den alten public-Ordner VOR dem Build restlos löschen
    if (existsSync(config.destDir)) {
        console.log(`🧹 Leere Zielverzeichnis: ${config.destDir} ...`);
        await fs.rm(config.destDir, { recursive: true, force: true });
    }

    if (!existsSync(config.srcDir)) {
        console.error(`⚠️ Abbruch: Quellverzeichnis ${config.srcDir} nicht gefunden.`);
        return;
    }

    const allFiles = await walkDir(config.srcDir);

    // Wir mappen das Array der Dateipfade direkt auf ein Array von Promises
    const tasks = allFiles.map((file) => processFile(file, config.srcDir, config.destDir));

    // Alle Tasks GLEICHZEITIG ausführen
    await Promise.all(tasks);

    console.log(`🎉 Erfolgreich ${tasks.length} Dateien verarbeitet.`);
    console.timeEnd('⏱️ Build-Dauer');
}

// Start!
runBuilder();
