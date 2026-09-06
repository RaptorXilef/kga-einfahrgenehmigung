import { existsSync } from 'node:fs';
import fs from 'node:fs/promises';
import path from 'node:path';
import { minify } from 'terser';

// PROJEKTUNABHÄNGIGE KONFIGURATION
// Spiegelt den gesamten src Ordner, egal wie viele Unterordner (core, ui, modules) du anlegst!
const config = {
    srcDir: 'src/assets/js',
    destDir: 'public/assets/js',
};

// Globaler Timestamp für diesen spezifischen Build-Vorgang
const BUILD_VERSION = Date.now();

/**
 * Asynchrone Hilfsfunktion: Findet alle JS-Dateien rekursiv
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

    await fs.mkdir(outputDir, { recursive: true });
    const mapName = `${path.basename(outputFilePath)}.map`;

    try {
        let code = await fs.readFile(file, 'utf8');

        // 🟢 MAGIE: ESM CACHE-BUSTING
        // Wir suchen alle relativen Import-Pfade (die mit './' oder '../' beginnen und mit '.js' enden)
        // und hängen hart unseren Build-Timestamp an. So wird der Browser gezwungen,
        // nach jedem Build die neuen verschachtelten Module zu laden!

        // 1. Statische Imports: import { x } from './core/Api.js'; -> import { x } from './core/Api.js?v=17123456';
        code = code.replace(/(from\s+['"])(\.[^'"]+\.js)(['"])/g, `$1$2?v=${BUILD_VERSION}$3`);

        // 2. Dynamische Imports: import('./core/Api.js') -> import('./core/Api.js?v=17123456')
        code = code.replace(
            /(import\(\s*['"])(\.[^'"]+\.js)(['"]\s*\))/g,
            `$1$2?v=${BUILD_VERSION}$3`
        );

        // Terser Minifizierung mit globalem ESM-Modus
        const result = await minify(code, {
            module: true,
            compress: true,
            mangle: true,
            sourceMap: {
                filename: mapName,
                url: mapName,
            },
        });

        await fs.writeFile(outputFilePath, result.code);
        if (result.map) {
            await fs.writeFile(`${outputFilePath}.map`, result.map);
        }

        console.log(`  ✅ Minifiziert & Cache-Busted: ${relativePath}`);
    } catch (error) {
        console.error(`  ❌ Fehler bei ${file}:`, error);
    }
}

async function runBuilder() {
    console.log('🚀 Starte JS-Minifizierung (Nativ, Asynchron & Parallel)...');
    console.time('⏱️ Build-Dauer');

    if (existsSync(config.destDir)) {
        console.log(`🧹 Leere Zielverzeichnis: ${config.destDir} ...`);
        await fs.rm(config.destDir, { recursive: true, force: true });
    }

    if (!existsSync(config.srcDir)) {
        console.error(`⚠️ Abbruch: Quellverzeichnis ${config.srcDir} nicht gefunden.`);
        return;
    }

    const allFiles = await walkDir(config.srcDir);
    const tasks = allFiles.map((file) => processFile(file, config.srcDir, config.destDir));

    await Promise.all(tasks);

    console.log(`🎉 Erfolgreich ${tasks.length} Dateien verarbeitet.`);
    console.log(`🏷️ Build-Version (Cache-Tag): ?v=${BUILD_VERSION}`);
    console.timeEnd('⏱️ Build-Dauer');
}

runBuilder();
