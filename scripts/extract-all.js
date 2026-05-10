import fs from 'node:fs';
import path from 'node:path';
import readline from 'node:readline';
import { fileURLToPath } from 'node:url';

// --- Farben & Styling ---
const c = {
    reset: '\x1b[0m',
    bright: '\x1b[1m',
    dim: '\x1b[2m',
    red: '\x1b[31m',
    green: '\x1b[32m',
    yellow: '\x1b[33m',
    blue: '\x1b[34m',
    magenta: '\x1b[35m',
    cyan: '\x1b[36m',
    gray: '\x1b[90m',
};

const ROOT_LEVEL_LABEL = '(Dateien direkt im Hauptverzeichnis)';

// --- 1. Grundkonfiguration ---
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const basePath = path.resolve(__dirname, '..');
const debugRoot = path.join(basePath, '.debug');

// Version aus package.json lesen für den Zielordner
let version = 'unknown';
try {
    const pkg = JSON.parse(fs.readFileSync(path.join(basePath, 'package.json'), 'utf-8'));
    version = pkg.version;
} catch (_e) {
    /* ignore */
}

const extractBaseFolder = path.join(debugRoot, 'extract', version);

// --- 2. Hilfsfunktionen ---

/**
 * Findet alle Verzeichnisse direkt unter .debug (außer 'extract')
 */
function getDebugSubfolders() {
    if (!fs.existsSync(debugRoot)) return [];
    const subfolders = fs
        .readdirSync(debugRoot, { withFileTypes: true })
        .filter((dirent) => dirent.isDirectory() && dirent.name !== 'extract')
        .map((dirent) => dirent.name);

    // Wir fügen einen virtuellen Eintrag für das Hauptverzeichnis hinzu
    return [ROOT_LEVEL_LABEL, ...subfolders];
}

/**
 * Listet alle relevanten Bundle-Dateien in einem spezifischen Verzeichnis
 */
function getFilesFromFolder(folderName) {
    // Wenn Hauptverzeichnis gewählt, Pfad nicht vertiefen
    const targetDir =
        folderName === ROOT_LEVEL_LABEL ? debugRoot : path.join(debugRoot, folderName);

    if (!fs.existsSync(targetDir)) return [];

    // WICHTIG: Nur Dateien in DIESER Ebene, keine Unterordner scannen
    return fs
        .readdirSync(targetDir)
        .filter((file) => {
            const fullPath = path.join(targetDir, file);
            return fs.statSync(fullPath).isFile() && file.match(/\.(js|php|phtml|scss|txt)$/);
        })
        .map((file) => ({
            name: file,
            fullPath: path.join(targetDir, file),
        }));
}

/**
 * Extrahiert den Inhalt basierend auf den Markern
 */
function unbundleFile(bundlePath) {
    console.log(`\n${c.cyan}📂 Extrahiere: ${c.bright}${path.basename(bundlePath)}${c.reset}...`);

    const content = fs.readFileSync(bundlePath, 'utf-8');
    const lines = content.split(/\r?\n/);

    let currentFile = null;
    let currentContent = [];
    let fileCount = 0;

    for (const line of lines) {
        const startMatch = line.match(/\/\/ ========== START FILE: \[(.*?)\] ==========/);
        if (startMatch) {
            currentFile = startMatch[1];
            currentContent = [];
            continue;
        }

        const endMatch = line.match(/\/\/ ========== END FILE: \[(.*?)\] ==========/);
        if (endMatch && currentFile) {
            const targetPath = path.join(extractBaseFolder, currentFile);
            const targetDir = path.dirname(targetPath);

            if (!fs.existsSync(targetDir)) fs.mkdirSync(targetDir, { recursive: true });

            fs.writeFileSync(targetPath, currentContent.join('\n').trimEnd(), 'utf-8');
            console.log(`${c.green}  + ${currentFile}${c.reset}`);

            fileCount++;
            currentFile = null;
            continue;
        }

        if (currentFile) currentContent.push(line);
    }
    return fileCount;
}

// --- 3. Menü-Logik ---
const rl = readline.createInterface({ input: process.stdin, output: process.stdout });

function showFolderMenu() {
    console.clear();
    console.log(`${c.magenta}===============================================`);
    console.log(`${c.magenta}    ${c.bright}EXTRACTOR: Schritt 1 - Verzeichnis wählen${c.reset}`);
    console.log(`${c.magenta}===============================================${c.reset}`);

    const folders = getDebugSubfolders();

    if (folders.length === 0) {
        console.log(`${c.red}Keine Daten in .debug gefunden!${c.reset}`);
        process.exit(0);
    }

    folders.forEach((f, i) => {
        const color = f === ROOT_LEVEL_LABEL ? c.yellow : c.cyan;
        console.log(`${color}${i + 1})${c.reset} ${f}`);
    });
    console.log(`${c.gray}-----------------------------------------------${c.reset}`);
    console.log(`${c.bright}Q)${c.reset} Beenden`);

    rl.question(`\n${c.bright}Wahl: ${c.reset}`, (answer) => {
        if (answer.toUpperCase() === 'Q') process.exit();
        const idx = parseInt(answer, 10) - 1;
        if (folders[idx]) {
            showFileMenu(folders[idx]);
        } else {
            console.log(`${c.red}Ungültige Wahl!${c.reset}`);
            setTimeout(showFolderMenu, 800);
        }
    });
}

function showFileMenu(folderName) {
    console.clear();
    console.log(`${c.magenta}===============================================`);
    console.log(`${c.magenta}    ${c.bright}EXTRACTOR: Schritt 2 - Datei wählen${c.reset}`);
    console.log(`${c.magenta}    Quelle: ${c.yellow}${folderName}${c.reset}`);
    console.log(`${c.magenta}===============================================${c.reset}`);

    const files = getFilesFromFolder(folderName);

    if (files.length === 0) {
        console.log(`${c.red}Keine passenden Dateien in dieser Ebene gefunden!${c.reset}`);
        rl.question(`\n${c.gray}Zurück mit Enter...${c.reset}`, showFolderMenu);
        return;
    }

    files.forEach((f, i) => {
        console.log(`${c.cyan}${i + 1})${c.reset} ${f.name}`);
    });
    console.log(`${c.gray}-----------------------------------------------${c.reset}`);
    console.log(`${c.bright}B)${c.reset} Zurück zur Auswahl | ${c.bright}Q)${c.reset} Exit`);

    rl.question(`\n${c.bright}Datei wählen: ${c.reset}`, (answer) => {
        const choice = answer.toUpperCase();
        if (choice === 'Q') process.exit();
        if (choice === 'B') {
            showFolderMenu();
            return;
        }

        const idx = parseInt(answer, 10) - 1;
        if (files[idx]) {
            const count = unbundleFile(files[idx].fullPath);
            console.log(
                `\n${c.green}${c.bright}ERFOLG!${c.reset} ${count} Dateien in ${c.yellow}.debug/extract/${version}/${c.reset} erstellt.`
            );
            rl.question(`\n${c.gray}Drücke Enter...${c.reset}`, showFolderMenu);
        } else {
            console.log(`${c.red}Ungültige Wahl!${c.reset}`);
            setTimeout(() => showFileMenu(folderName), 800);
        }
    });
}

showFolderMenu();
