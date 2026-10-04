import fs from 'node:fs';
import path from 'node:path';
import readline from 'node:readline';
import { fileURLToPath } from 'node:url';

// =============================================================================
// SCHNELLE KONFIGURATION (Hier einfach Ordner/Dateien ergänzen)
// =============================================================================
// HINWEIS ZU WILDCARDS (*):
// - Ohne '*' -> Exakter Treffer (z.B. 'backup' ignoriert NUR den Ordner "backup")
// - Mit '*'  -> Teilstring/Muster (z.B. '*backup*' ignoriert auch "mein_backup_2025")

// 1. GLOBALE IGNORES (Gelten für ALLE Dateiarten)
const ALWAYS_IGNORE_DIRS = [
    '.build',
    '.cache',
    '.debug',
    '.git',
    '.github',
    '.husky',
    '.vscode',
    'backups',
    'cache',
    'docs',
    'logs',
    'node_modules',
    'scripts',
    'tools',
    'vendor',
];

const ALWAYS_IGNORE_PATHS = ['public/assets', 'public/dev'];

const ALWAYS_IGNORE_FILES = [
    '*.lock',
    '*-lock.json',
    '.DS_Store',
    '*.min.js',
    '*.min.css',
    '*.local.*',
    'routes_v2.php',
];

// 2. IGNORES PRO DATEIART (Werden zusätzlich zu den globalen Ignores beachtet)
const IGNORE_BY_TYPE = {
    js: {
        dirs: ['public/assets'],
        files: ['svgo.config.*', 'purgecss.config.*', 'eslint.config.*', 'commitlint.config.*'],
    },
    php: {
        dirs: ['tests'],
        files: ['*php-cs-fixer.dist*', 'rector.php'],
    },
    phtml: {
        dirs: [],
        files: [],
    },
    scss: {
        dirs: [],
        files: [],
    },
    sql: {
        dirs: [],
        files: [],
    },
};

// =============================================================================

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

// --- 1. Grundkonfiguration ---
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const basePath = path.resolve(__dirname, '..');

let globalIncludeRootFiles = false;

// Version aus package.json lesen
let version = 'unknown';
try {
    const pkg = JSON.parse(fs.readFileSync(path.join(basePath, 'package.json'), 'utf-8'));
    version = pkg.version;
} catch (_e) {
    console.warn(`${c.yellow}⚠️ package.json nicht gefunden oder fehlerhaft.${c.reset}`);
}

// Dynamischer Zielordner basierend auf der Version
const debugFolder = path.join(basePath, '.debug', version);

// --- 2. Filter-Konfigurationen ---
const configs = {
    PHP: {
        name: 'PhpCode',
        filter: /\.php$/,
        ext: '.md',
        exclDirs: IGNORE_BY_TYPE.php.dirs,
        exclFiles: IGNORE_BY_TYPE.php.files,
    },
    PHTML: {
        name: 'PhtmlCode',
        filter: /\.phtml$/,
        ext: '.md',
        exclDirs: IGNORE_BY_TYPE.phtml.dirs,
        exclFiles: IGNORE_BY_TYPE.phtml.files,
    },
    JS: {
        name: 'JsCode',
        filter: /\.js$/,
        ext: '.md',
        exclDirs: IGNORE_BY_TYPE.js.dirs,
        exclFiles: IGNORE_BY_TYPE.js.files,
    },
    SCSS: {
        name: 'ScssCode',
        filter: /\.scss$/,
        ext: '.md',
        exclDirs: IGNORE_BY_TYPE.scss.dirs,
        exclFiles: IGNORE_BY_TYPE.scss.files,
    },
    SQL: {
        name: 'SqlMigrations',
        filter: /\.sql$/,
        ext: '.md',
        targetDir: 'database/migrations',
        exclDirs: IGNORE_BY_TYPE.sql.dirs,
        exclFiles: IGNORE_BY_TYPE.sql.files,
    },
    ENV: {
        name: 'Entwicklungsumgebung',
        explicitFiles: [
            'composer.json',
            'package.json',
            'deptrac.yaml',
            'phpstan.neon.dist',
            // '.github/workflows/deploy.yml',
        ],
        ext: '.md',
    },
};

// --- 3. Daten-Bereinigung & Formatierungs-Logik ---

/**
 * Leert sensible Daten aus package.json und composer.json
 */
function sanitizeJsonContent(filePath, rawContent) {
    const baseName = path.basename(filePath).toLowerCase();

    if (baseName !== 'composer.json' && baseName !== 'package.json') {
        return rawContent;
    }

    try {
        const parsed = JSON.parse(rawContent);
        let indent = 4;

        if (baseName === 'composer.json') {
            if ('name' in parsed) parsed.name = '';
            if ('description' in parsed) parsed.description = '';
            if ('license' in parsed) parsed.license = '';

            if (Array.isArray(parsed.authors)) {
                parsed.authors.forEach((author) => {
                    if (typeof author === 'object') {
                        if ('name' in author) author.name = '';
                        if ('email' in author) author.email = '';
                    }
                });
            }
        }

        if (baseName === 'package.json') {
            indent = 2;
            if ('name' in parsed) parsed.name = '';
            if ('description' in parsed) parsed.description = '';
            if ('author' in parsed) parsed.author = '';
            if ('license' in parsed) parsed.license = '';
        }

        return JSON.stringify(parsed, null, indent);
    } catch (_e) {
        // Falls das JSON defekt ist, geben wir sicherheitshalber den Roh-Inhalt zurück
        return rawContent;
    }
}

function formatContent(content) {
    // Teilt den Inhalt in einzelne Zeilen auf
    const lines = content.split(/\r?\n/);
    const formattedLines = [];

    for (let i = 0; i < lines.length; i++) {
        // trim() entfernt führende UND abschließende Leerzeichen (wie z.B. unsichtbare Spaces am Zeilenende).
        // Der Code selbst (Kommentare, Operatoren, etc.) bleibt völlig unangetastet.
        const line = lines[i].trim();

        // Wir behalten leere Zeilen bei, filtern sie aber auf absolut "leer" (0 Zeichen)
        formattedLines.push(line);
    }

    return formattedLines.join('\n');
}

// =============================================================================
// FILE SYSTEM & CLI LOGIC
// =============================================================================

/**
 * Prüft, ob ein Ziel-String (Datei/Ordner) auf ein Pattern passt.
 * - Mit '*'   : Wildcard-Suche (z.B. '*backup*' -> enthält "backup", 'backup*' -> beginnt mit "backup")
 * - Ohne '*'  : Exakte Übereinstimmung (z.B. 'backup' -> trifft NUR auf "backup" zu, nicht auf "my_backup")
 */
function matchPattern(target, pattern) {
    if (!pattern) return false;

    const cleanTarget = target.toLowerCase();
    const cleanPattern = pattern.toLowerCase();

    if (cleanPattern.includes('*')) {
        // RegEx-Sonderzeichen escapen, außer das Sternchen (*)
        const escapeRegex = (s) => s.replace(/[.+?^${}()|[\]\\]/g, '\\$&');
        const regexStr = `^${cleanPattern.split('*').map(escapeRegex).join('.*')}$`;
        return new RegExp(regexStr, 'i').test(cleanTarget);
    }

    // Exakter Treffer, wenn kein Sternchen angegeben wurde
    return cleanTarget === cleanPattern;
}

/**
 * Prüft, ob ein relativer Ordnerpfad durch eine Pattern-Liste ausgeschlossen ist.
 * Unterstützt sowohl reine Ordnernamen ('backup', '*backup*') als auch Pfade ('public/assets').
 */
function isDirExcludedByList(relPath, patterns = []) {
    if (!patterns || patterns.length === 0 || !relPath || relPath === '.') return false;

    const normalizedRelPath = relPath.replace(/\\/g, '/');
    const segments = normalizedRelPath.split('/');
    // Bildet alle Teilpfade ab Root (z.B. ['public', 'public/assets', 'public/assets/js'])
    const subPaths = segments.map((_, idx) => segments.slice(0, idx + 1).join('/'));

    return patterns.some((pattern) => {
        const normalizedPattern = pattern.replace(/\\/g, '/');
        if (normalizedPattern.includes('/')) {
            return subPaths.some((sub) => matchPattern(sub, normalizedPattern));
        }
        return segments.some((segment) => matchPattern(segment, normalizedPattern));
    });
}

/**
 * Prüft, ob eine Datei durch eine Pattern-Liste ausgeschlossen ist.
 * Unterstützt Dateinamen ('*.local.*', 'rector.php') sowie relative Dateipfade ('config/routes.php').
 */
function isFileExcludedByList(fileName, relFilePath, patterns = []) {
    if (!patterns || patterns.length === 0) return false;

    const normalizedRelFile = relFilePath.replace(/\\/g, '/');

    return patterns.some((pattern) => {
        const normalizedPattern = pattern.replace(/\\/g, '/');
        if (normalizedPattern.includes('/')) {
            return matchPattern(normalizedRelFile, normalizedPattern);
        }
        return matchPattern(fileName, normalizedPattern);
    });
}

function getFiles(
    dir,
    filter,
    exclDirs = [],
    exclFiles = [],
    includeRoot = false,
    currentFiles = []
) {
    const files = fs.readdirSync(dir);

    for (const file of files) {
        const fullPath = path.join(dir, file);
        const relPath = path.relative(basePath, fullPath);
        const stat = fs.statSync(fullPath);

        if (stat.isDirectory()) {
            // 1. Globale & aktive Config-Ordner-Ignores prüfen
            const isExcludedDir =
                file.startsWith('.') ||
                isDirExcludedByList(relPath, ALWAYS_IGNORE_DIRS) ||
                isDirExcludedByList(relPath, ALWAYS_IGNORE_PATHS) ||
                isDirExcludedByList(relPath, exclDirs);

            if (!isExcludedDir) {
                getFiles(fullPath, filter, exclDirs, exclFiles, includeRoot, currentFiles);
            }
        } else {
            const isRootFile = path.dirname(fullPath) === basePath;
            if (!includeRoot && isRootFile) continue;

            if (!filter.test(file)) continue;

            // Dateiart bestimmen (z.B. 'js', 'php', 'phtml', 'scss', 'sql')
            const extKey = path.extname(file).toLowerCase().replace('.', '');
            const typeIgnores = IGNORE_BY_TYPE[extKey] || { dirs: [], files: [] };
            const relDir = path.dirname(relPath);

            // 2. Prüfen, ob der Ordner speziell für DIESE Dateiart ignoriert werden soll
            // (Besonders wichtig bei PROJECT und --mirror, wo mehrere Dateiarten gleichzeitig gesammelt werden)
            const isExcludedByTypeDir =
                relDir !== '.' && isDirExcludedByList(relDir, typeIgnores.dirs);

            // 3. Prüfen, ob die Datei global, in der Config oder speziell für diese Dateiart ignoriert wird
            const isExcludedFile =
                isFileExcludedByList(file, relPath, ALWAYS_IGNORE_FILES) ||
                isFileExcludedByList(file, relPath, exclFiles) ||
                isFileExcludedByList(file, relPath, typeIgnores.files);

            if (!isExcludedByTypeDir && !isExcludedFile) {
                currentFiles.push({
                    fullPath,
                    relPath,
                    ext: path.extname(file),
                });
            }
        }
    }
    return currentFiles;
}

function getTimestampString() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}_${pad(d.getHours())}-${pad(d.getMinutes())}-${pad(d.getSeconds())}`;
}

/**
 * Sammelt die Dateiliste für eine spezifische Konfiguration.
 * Wurde ausgelagert, um sie für Einzel- und Projektzusammenfassungen nutzen zu können.
 */
function getFilesForConfig(conf, silent = false) {
    let foundFiles = [];
    if (conf.explicitFiles) {
        for (const filePath of conf.explicitFiles) {
            const normalizedPath = path.normalize(filePath);
            const fullPath = path.join(basePath, normalizedPath);
            if (fs.existsSync(fullPath)) {
                foundFiles.push({
                    fullPath: fullPath,
                    relPath: normalizedPath,
                    ext: path.extname(fullPath),
                });
            } else {
                if (!silent)
                    console.log(
                        `${c.yellow} ! Überspringe (nicht gefunden): ${normalizedPath}${c.reset}`
                    );
            }
        }
    } else {
        const searchDir = conf.targetDir ? path.join(basePath, conf.targetDir) : basePath;
        if (fs.existsSync(searchDir)) {
            foundFiles = getFiles(
                searchDir,
                conf.filter,
                conf.exclDirs || [],
                conf.exclFiles || [],
                globalIncludeRootFiles
            );
        } else {
            if (!silent)
                console.log(`${c.yellow} ! Ordner nicht gefunden: ${conf.targetDir}${c.reset}`);
        }
    }
    return foundFiles;
}

/**
 * Wandelt Dateiinhalte in formatierten Markdown-Code um
 */
function processFilesToMarkdown(foundFiles, silent = false) {
    let combinedContent = '';
    for (const file of foundFiles) {
        try {
            let rawContent = fs.readFileSync(file.fullPath, 'utf-8');
            rawContent = sanitizeJsonContent(file.relPath, rawContent);
            const formattedContent = formatContent(rawContent);

            const extName = file.ext.toLowerCase().replace('.', '');
            const langMap = {
                js: 'javascript',
                php: 'php',
                phtml: 'phtml',
                scss: 'scss',
                json: 'json',
                yml: 'yaml',
                yaml: 'yaml',
                sql: 'sql',
            };
            const lang = langMap[extName] || extName;
            const mdPath = file.relPath.replace(/\\/g, '/');

            combinedContent += `### /${mdPath}\n`;
            combinedContent += `\`\`\`${lang}\n`;
            combinedContent += `${formattedContent}\n`;
            combinedContent += `\`\`\`\n\n`;

            if (!silent) console.log(`${c.gray} + [Gesammelt] ${file.relPath}${c.reset}`);
        } catch (_e) {
            if (!silent) console.log(`${c.gray} ! Überspringe (Binär?): ${file.relPath}${c.reset}`);
        }
    }
    return combinedContent;
}

function startStructureMirror() {
    const timestampDirName = getTimestampString();
    const targetDirName = `${timestampDirName}_collected`;
    const targetDir = path.join(debugFolder, targetDirName);

    console.log(`\n${c.cyan}🚀 Starte Erstellung der gespiegelten RAW-Struktur...`);
    console.log(`${c.yellow}Target: .debug/${version}/${targetDirName}/${c.reset}`);

    // Für den Mirror holen wir pauschal alles, was unterstützt wird
    const foundFiles = getFiles(
        basePath,
        /\.(js|php|phtml|scss|sql)$/,
        [],
        [],
        globalIncludeRootFiles
    );

    if (foundFiles.length === 0) {
        console.log(`${c.red}❌ Keine Dateien gefunden.${c.reset}`);
        return;
    }

    let count = 0;
    for (const file of foundFiles) {
        try {
            let rawContent = fs.readFileSync(file.fullPath, 'utf-8');

            // Sensible Daten aus package.json/composer.json entfernen
            rawContent = sanitizeJsonContent(file.relPath, rawContent);

            const formattedContent = formatContent(rawContent);

            const fileOutputDir = path.join(targetDir, path.dirname(file.relPath));
            const fileOutputPath = path.join(targetDir, file.relPath);

            if (!fs.existsSync(fileOutputDir)) fs.mkdirSync(fileOutputDir, { recursive: true });

            fs.writeFileSync(fileOutputPath, formattedContent, 'utf-8');
            count++;
            console.log(`${c.gray} + [Spiegeln] ${file.relPath}${c.reset}`);
        } catch (e) {
            console.log(`${c.red} ! Fehler bei Datei: ${file.relPath} (${e.message})${c.reset}`);
        }
    }

    console.log(
        `${c.green}✅ Erfolg: Struktur gespiegelt! ${c.bright}${count} Dateien${c.reset} exportiert nach ${c.yellow}.debug/${version}/${targetDirName}/${c.reset}`
    );
}

function startFileCollection(configKey, silent = false) {
    const conf = configs[configKey];
    const timestamp = getTimestampString();

    const outputName = `${conf.name}_${timestamp}_collected${conf.ext}`;
    const outputPath = path.join(debugFolder, outputName);

    if (!fs.existsSync(debugFolder)) fs.mkdirSync(debugFolder, { recursive: true });

    if (!silent)
        console.log(`\n${c.cyan}🚀 Starte RAW-Sammlung: ${c.bright}${conf.name}${c.reset}...`);

    const foundFiles = getFilesForConfig(conf, silent);

    if (foundFiles.length === 0) {
        if (!silent) console.log(`${c.red}❌ Keine Dateien gefunden.${c.reset}`);
        return;
    }

    const combinedContent = processFilesToMarkdown(foundFiles, silent);

    fs.writeFileSync(outputPath, combinedContent, 'utf-8');
    const displayPath = path.relative(basePath, outputPath);
    console.log(
        `${c.green}✅ Erfolg: ${c.bright}${displayPath}${c.reset} (${foundFiles.length} Dateien gesammelt).`
    );
}

/**
 * Erstellt eine massive Datei aus mehreren gewählten Kategorien
 */
function startProjectSummary(selectedKeys, silent = false) {
    const timestamp = getTimestampString();
    const outputName = `ProjektZusammenfassung_${timestamp}_collected.md`;
    const outputPath = path.join(debugFolder, outputName);

    if (!fs.existsSync(debugFolder)) fs.mkdirSync(debugFolder, { recursive: true });

    if (!silent)
        console.log(
            `\n${c.cyan}🚀 Starte Projekt-Zusammenfassung (Kategorien: ${selectedKeys.join(', ')})...${c.reset}`
        );

    let totalContent = '';
    let totalFiles = 0;

    for (const key of selectedKeys) {
        const conf = configs[key];
        const foundFiles = getFilesForConfig(conf, true); // intern leise
        if (foundFiles.length > 0) {
            totalFiles += foundFiles.length;
            totalContent += `\n# === BEREICH: ${conf.name} ===\n\n`;
            totalContent += processFilesToMarkdown(foundFiles, silent);
        }
    }

    if (totalFiles === 0) {
        console.log(`${c.red}❌ Keine Dateien für die Zusammenfassung gefunden.${c.reset}`);
        return;
    }

    fs.writeFileSync(outputPath, totalContent, 'utf-8');
    const displayPath = path.relative(basePath, outputPath);
    console.log(
        `${c.green}✅ Erfolg: Zusammenfassung in ${c.bright}${displayPath}${c.reset} gespeichert (${totalFiles} Dateien gesammelt).`
    );
}

function showHelp() {
    console.log(`\n${c.bright}HILFE & CLI ARGUMENTE (RAW/COLLECTED)${c.reset}`);
    console.log(`${c.gray}------------------------------------------------------------${c.reset}`);
    console.table([
        { Argument: '--php', Beschreibung: 'Sammelt nur PHP Dateien' },
        { Argument: '--phtml', Beschreibung: 'Sammelt nur PHTML Dateien' },
        { Argument: '--js', Beschreibung: 'Sammelt nur JavaScript Dateien' },
        { Argument: '--scss', Beschreibung: 'Sammelt nur SCSS Dateien' },
        { Argument: '--sql', Beschreibung: 'Sammelt SQL Dateien (database/migrations)' },
        {
            Argument: '--env',
            Beschreibung: 'Sammelt Entwicklungsumgebungs-Dateien (composer.json etc.)',
        },
        {
            Argument: '--project',
            Beschreibung: 'Projektweite Zusammenfassung (Alle Code-Dateien in eine Datei)',
        },
        { Argument: '--mirror', Beschreibung: 'Spiegelt die gesamte Ordnerstruktur' },
        { Argument: '--all', Beschreibung: 'Führt Code-Sammlungen einzeln automatisch aus' },
        { Argument: '--root', Beschreibung: 'Bezieht Dateien im Root-Verzeichnis mit ein' },
        { Argument: '--help', Beschreibung: 'Zeigt diese Hilfe an' },
    ]);
    console.log(`${c.gray}Info: Im CI-Modus (mit Argumenten) läuft das Skript stumm.${c.reset}\n`);
}

// --- Hilfsfunktion fürs Menü ---
function parseUserSelection(input) {
    const allKeys = ['PHP', 'PHTML', 'JS', 'SCSS', 'SQL', 'ENV'];
    if (!input || input.trim() === '') return allKeys; // Default: Alle

    const map = {
        1: 'PHP',
        2: 'PHTML',
        3: 'JS',
        4: 'SCSS',
        5: 'SQL',
        6: 'ENV',
    };

    const parts = input.split(',').map((s) => s.trim());
    const selected = [];
    for (const p of parts) {
        if (map[p] && !selected.includes(map[p])) {
            selected.push(map[p]);
        }
    }
    return selected.length > 0 ? selected : allKeys;
}

// --- 4. CLI & Menü Handling ---
const args = process.argv.slice(2);

if (args.length > 0) {
    if (args.includes('--help') || args.includes('-h')) {
        showHelp();
        process.exit(0);
    }
    if (args.includes('--root')) globalIncludeRootFiles = true;

    const allKeys = ['PHP', 'PHTML', 'JS', 'SCSS', 'SQL', 'ENV'];

    if (args.includes('--all')) {
        for (const k of allKeys) startFileCollection(k, true);
    } else {
        if (args.includes('--php')) startFileCollection('PHP', true);
        if (args.includes('--phtml')) startFileCollection('PHTML', true);
        if (args.includes('--js')) startFileCollection('JS', true);
        if (args.includes('--scss')) startFileCollection('SCSS', true);
        if (args.includes('--sql')) startFileCollection('SQL', true);
        if (args.includes('--env')) startFileCollection('ENV', true);
        if (args.includes('--project')) startProjectSummary(allKeys, true);
        if (args.includes('--mirror')) startStructureMirror();
    }
    process.exit(0);
} else {
    const rl = readline.createInterface({
        input: process.stdin,
        output: process.stdout,
    });

    const showMenu = () => {
        const rootStatus = globalIncludeRootFiles
            ? `${c.green}${c.bright}AN${c.reset}`
            : `${c.red}${c.bright}AUS${c.reset}`;

        console.clear();
        console.log(`${c.cyan}===============================================`);
        console.log(`${c.cyan}    ${c.bright}DATEI-ZUSAMMENFASSUNG (RAW/COLLECTED)${c.reset}`);
        console.log(`${c.cyan}    Root: ${c.gray}${basePath}${c.reset}`);
        console.log(`${c.cyan}    Ziel: ${c.yellow}.debug/${version}/${c.reset}`);
        console.log(`${c.cyan}===============================================${c.reset}`);
        // GEÄNDERTE REIHENFOLGE WIE GEWÜNSCHT
        console.log(`${c.bright} 1)${c.reset} PHP (*.md)`);
        console.log(`${c.bright} 2)${c.reset} PHTML (*.md)`);
        console.log(`${c.bright} 3)${c.reset} JavaScript (*.md)`);
        console.log(`${c.bright} 4)${c.reset} SCSS (*.md)`);
        console.log(
            `${c.bright} 5)${c.reset} ${c.cyan}SQL MIGRATIONS${c.reset} (database/migrations/*.sql)`
        );
        console.log(
            `${c.bright} 6)${c.reset} ${c.blue}ENTWICKLUNGSUMGEBUNG${c.reset} (composer, yaml, etc.)`
        );
        console.log(
            `${c.bright} 7)${c.reset} ${c.magenta}PROJEKT-ZUSAMMENFASSUNG${c.reset} (*.md)`
        );
        console.log(
            `${c.bright} 8)${c.reset} ${c.green}PROJEKT-STRUKTUR SPIEGELN${c.reset} (Einzeldateien in Verzeichnissen)`
        );
        console.log(`${c.gray}-----------------------------------------------${c.reset}`);
        console.log(`${c.bright} T)${c.reset} Toggle Root-Files: [${rootStatus}]`);
        console.log(
            `${c.bright} A)${c.reset} ${c.yellow}ALLE nacheinander (wählbar aus 1-6)${c.reset}`
        );
        console.log(`${c.bright} H)${c.reset} Hilfe / CI Info`);
        console.log(`${c.bright} Q)${c.reset} Beenden`);
        console.log(`${c.gray}-----------------------------------------------${c.reset}`);

        rl.question(`${c.bright}Wähle eine Option: ${c.reset}`, (answer) => {
            const choice = answer.toUpperCase().trim();

            if (choice === 'Q') process.exit();
            if (choice === 'H') {
                showHelp();
                rl.question('Drücke Enter für Menü...', showMenu);
                return;
            }
            if (choice === 'T') {
                globalIncludeRootFiles = !globalIncludeRootFiles;
                showMenu();
                return;
            }

            // ALLE NACHEINANDER (Mit dynamischer Auswahl)
            if (choice === 'A') {
                rl.question(
                    `\n${c.yellow}Welche Bereiche nacheinander ausführen? (z.B. 1,3,6 | Enter für alle): ${c.reset}`,
                    (sel) => {
                        const keys = parseUserSelection(sel);
                        for (const k of keys) {
                            startFileCollection(k);
                        }
                        rl.question(`\n${c.gray}Fertig. Drücke Enter...${c.reset}`, showMenu);
                    }
                );
                return;
            }

            // PROJEKT ZUSAMMENFASSUNG (Mit dynamischer Auswahl)
            if (choice === '7') {
                rl.question(
                    `\n${c.magenta}Welche Bereiche in EINE Zusammenfassung packen? (z.B. 1,2,6 | Enter für alle): ${c.reset}`,
                    (sel) => {
                        const keys = parseUserSelection(sel);
                        startProjectSummary(keys);
                        rl.question(`\n${c.gray}Fertig. Drücke Enter...${c.reset}`, showMenu);
                    }
                );
                return;
            }

            // STRUKTUR SPIEGELN
            if (choice === '8') {
                startStructureMirror();
                rl.question(`\n${c.gray}Fertig. Drücke Enter...${c.reset}`, showMenu);
                return;
            }

            // EINZELNE AUSWAHL (1-6)
            const map = {
                1: 'PHP',
                2: 'PHTML',
                3: 'JS',
                4: 'SCSS',
                5: 'SQL',
                6: 'ENV',
            };

            if (map[choice]) {
                startFileCollection(map[choice]);
                rl.question(`\n${c.gray}Fertig. Drücke Enter...${c.reset}`, showMenu);
            } else {
                console.log(`${c.red}Ungültige Auswahl!${c.reset}`);
                setTimeout(showMenu, 1000);
            }
        });
    };
    showMenu();
}
