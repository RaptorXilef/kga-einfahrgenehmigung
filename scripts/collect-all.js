import fs from 'node:fs';
import fsPromises from 'node:fs/promises';
import path from 'node:path';
import readline from 'node:readline/promises'; // Nutzt nun die asynchrone Promise-Version!
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

// Version aus package.json lesen (Dies bleibt synchron, da es nur 1x beim Start passiert)
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

// --- 3. Daten-Bereinigung, Formatierung & Token-Schätzung ---

/**
 * Schätzt die Token-Anzahl für LLMs (1 Token entspricht ca. 4 Zeichen)
 */
function estimateTokens(text) {
    return Math.ceil(text.length / 4);
}

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

/**
 * Generiert eine visuelle Baumstruktur aus dem Array der gesammelten Dateien.
 */
function generateTreeString(files) {
    const tree = {};

    // 1. Objekt-Struktur aufbauen
    for (const file of files) {
        // Pfade normalisieren (Slashes für einheitliche Verarbeitung)
        const parts = file.relPath.replace(/\\/g, '/').split('/');
        let current = tree;
        for (let i = 0; i < parts.length; i++) {
            const part = parts[i];
            if (!current[part]) {
                // Letztes Element (Datei) wird null, Ordner werden als {} angelegt
                current[part] = i === parts.length - 1 ? null : {};
            }
            current = current[part];
        }
    }

    // 2. Rekursives Rendern der Baumstruktur
    function renderNode(node, prefix = '') {
        let result = '';

        // Sortierung: Ordner zuerst, dann alphabetisch
        const keys = Object.keys(node).sort((a, b) => {
            const isDirA = node[a] !== null;
            const isDirB = node[b] !== null;
            if (isDirA && !isDirB) return -1;
            if (!isDirA && isDirB) return 1;
            return a.localeCompare(b);
        });

        for (let i = 0; i < keys.length; i++) {
            const key = keys[i];
            const isLast = i === keys.length - 1;
            const pointer = isLast ? '└── ' : '├── ';

            result += `${prefix}${pointer}${key}\n`;

            if (node[key] !== null) {
                // Wenn es ein Ordner ist -> rekursiv absteigen
                const nextPrefix = prefix + (isLast ? '    ' : '│   ');
                result += renderNode(node[key], nextPrefix);
            }
        }
        return result;
    }

    return renderNode(tree);
}

// =============================================================================
// ASYNCHRONE FILE SYSTEM LOGIC
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

/**
 * Asynchrone rekursive Dateisuche
 */
async function getFiles(
    dir,
    filter,
    exclDirs = [],
    exclFiles = [],
    includeRoot = false,
    currentFiles = []
) {
    const files = await fsPromises.readdir(dir);

    for (const file of files) {
        const fullPath = path.join(dir, file);
        const relPath = path.relative(basePath, fullPath);
        const stat = await fsPromises.stat(fullPath);

        if (stat.isDirectory()) {
            // 1. Globale & aktive Config-Ordner-Ignores prüfen
            const isExcludedDir =
                file.startsWith('.') ||
                isDirExcludedByList(relPath, ALWAYS_IGNORE_DIRS) ||
                isDirExcludedByList(relPath, ALWAYS_IGNORE_PATHS) ||
                isDirExcludedByList(relPath, exclDirs);

            if (!isExcludedDir) {
                await getFiles(fullPath, filter, exclDirs, exclFiles, includeRoot, currentFiles);
            }
        } else {
            const isRootFile = path.dirname(fullPath) === basePath;
            if (!includeRoot && isRootFile) continue;

            if (!filter.test(file)) continue;

            // Dateiart bestimmen (z.B. 'js', 'php', 'phtml', 'scss', 'sql')
            const extKey = path.extname(file).toLowerCase().replace('.', '');
            const typeIgnores = IGNORE_BY_TYPE[extKey] || {
                dirs: [],
                files: [],
            };
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
 * Asynchron: Sammelt die Dateiliste für eine spezifische Konfiguration.
 */
async function getFilesForConfig(conf, silent = false) {
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
            foundFiles = await getFiles(
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
 * Asynchron: Wandelt Dateiinhalte in formatierten Markdown-Code um
 */
async function processFilesToMarkdown(foundFiles, silent = false) {
    let combinedContent = '';
    for (const file of foundFiles) {
        try {
            // Asynchrones Lesen der Datei
            let rawContent = await fsPromises.readFile(file.fullPath, 'utf-8');
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
            if (!silent)
                console.log(`${c.gray} ! Überspringe (Binär/Fehler?): ${file.relPath}${c.reset}`);
        }
    }
    return combinedContent;
}

async function startStructureMirror() {
    const timestampDirName = getTimestampString();
    const targetDirName = `${timestampDirName}_collected`;
    const targetDir = path.join(debugFolder, targetDirName);

    console.log(`\n${c.cyan}🚀 Starte Erstellung der gespiegelten RAW-Struktur...`);
    console.log(`${c.yellow}Target: .debug/${version}/${targetDirName}/${c.reset}`);

    const foundFiles = await getFiles(
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
            let rawContent = await fsPromises.readFile(file.fullPath, 'utf-8');
            rawContent = sanitizeJsonContent(file.relPath, rawContent);
            const formattedContent = formatContent(rawContent);

            const fileOutputDir = path.join(targetDir, path.dirname(file.relPath));
            const fileOutputPath = path.join(targetDir, file.relPath);

            if (!fs.existsSync(fileOutputDir)) {
                await fsPromises.mkdir(fileOutputDir, { recursive: true });
            }

            await fsPromises.writeFile(fileOutputPath, formattedContent, 'utf-8');
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

async function startFileCollection(configKey, silent = false) {
    const conf = configs[configKey];
    const timestamp = getTimestampString();
    const outputName = `${conf.name}_${timestamp}_collected${conf.ext}`;
    const outputPath = path.join(debugFolder, outputName);

    if (!fs.existsSync(debugFolder)) {
        await fsPromises.mkdir(debugFolder, { recursive: true });
    }

    if (!silent)
        console.log(`\n${c.cyan}🚀 Starte RAW-Sammlung: ${c.bright}${conf.name}${c.reset}...`);

    const foundFiles = await getFilesForConfig(conf, silent);

    if (foundFiles.length === 0) {
        if (!silent) console.log(`${c.red}❌ Keine Dateien gefunden.${c.reset}`);
        return;
    }

    // Baumstruktur und Code generieren
    const treeString = generateTreeString(foundFiles);
    const codeContent = await processFilesToMarkdown(foundFiles, silent);

    const finalContent = `## 📁 Datei-Struktur\n\n\`\`\`text\n${treeString}\`\`\`\n\n---\n\n${codeContent}`;

    // Tokens berechnen & Speichern
    const tokens = estimateTokens(finalContent);
    await fsPromises.writeFile(outputPath, finalContent, 'utf-8');

    const displayPath = path.relative(basePath, outputPath);
    console.log(
        `${c.green}✅ Erfolg: ${c.bright}${displayPath}${c.reset} (${foundFiles.length} Dateien, ca. ${tokens.toLocaleString('de-DE')} Tokens).`
    );
}

/**
 * Erstellt eine massive Datei aus mehreren gewählten Kategorien
 */
async function startProjectSummary(selectedKeys, silent = false) {
    const timestamp = getTimestampString();
    const outputName = `ProjektZusammenfassung_${timestamp}_collected.md`;
    const outputPath = path.join(debugFolder, outputName);

    if (!fs.existsSync(debugFolder)) {
        await fsPromises.mkdir(debugFolder, { recursive: true });
    }

    if (!silent)
        console.log(
            `\n${c.cyan}🚀 Starte Projekt-Zusammenfassung (Kategorien: ${selectedKeys.join(', ')})...${c.reset}`
        );

    let totalContent = '';
    let allFoundFiles = [];

    // Alle Dateien erst sammeln, um einen globalen Projekt-Baum zu bauen
    for (const key of selectedKeys) {
        const conf = configs[key];
        const foundFiles = await getFilesForConfig(conf, true);
        if (foundFiles.length > 0) {
            allFoundFiles.push(...foundFiles); // Für den globalen Baum zusammenfügen
            totalContent += `\n# === BEREICH: ${conf.name} ===\n\n`;
            totalContent += await processFilesToMarkdown(foundFiles, silent);
        }
    }

    if (allFoundFiles.length === 0) {
        console.log(`${c.red}❌ Keine Dateien für die Zusammenfassung gefunden.${c.reset}`);
        return;
    }

    // Globalen Baum erstellen
    const globalTreeString = generateTreeString(allFoundFiles);
    const finalContent = `# 📦 Projekt-Zusammenfassung\n\n## 📁 Globale Datei-Struktur\n\n\`\`\`text\n${globalTreeString}\`\`\`\n\n---\n${totalContent}`;

    // Tokens berechnen & Speichern
    const tokens = estimateTokens(finalContent);
    await fsPromises.writeFile(outputPath, finalContent, 'utf-8');

    const displayPath = path.relative(basePath, outputPath);
    console.log(
        `${c.green}✅ Erfolg: Zusammenfassung in ${c.bright}${displayPath}${c.reset} gespeichert (${allFoundFiles.length} Dateien, ca. ${tokens.toLocaleString('de-DE')} Tokens).`
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
        {
            Argument: '--sql',
            Beschreibung: 'Sammelt SQL Dateien (database/migrations)',
        },
        {
            Argument: '--env',
            Beschreibung: 'Sammelt Entwicklungsumgebungs-Dateien (composer.json etc.)',
        },
        {
            Argument: '--project',
            Beschreibung: 'Projektweite Zusammenfassung (Alle Code-Dateien in eine Datei)',
        },
        {
            Argument: '--mirror',
            Beschreibung: 'Spiegelt die gesamte Ordnerstruktur',
        },
        {
            Argument: '--all',
            Beschreibung: 'Führt Code-Sammlungen einzeln automatisch aus',
        },
        {
            Argument: '--root',
            Beschreibung: 'Bezieht Dateien im Root-Verzeichnis mit ein',
        },
        { Argument: '--help', Beschreibung: 'Zeigt diese Hilfe an' },
    ]);
    console.log(`${c.gray}Info: Im CI-Modus (mit Argumenten) läuft das Skript stumm.${c.reset}\n`);
}

function parseUserSelection(input) {
    const allKeys = ['PHP', 'PHTML', 'JS', 'SCSS', 'SQL', 'ENV'];
    if (!input || input.trim() === '') return allKeys;

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

// --- 4. CLI & ASYNCHRONES Menü Handling ---
const args = process.argv.slice(2);

// Haupt-Ausführungsblock (Unterstützt Top-Level-Await dank ESM)
if (args.length > 0) {
    if (args.includes('--help') || args.includes('-h')) {
        showHelp();
        process.exit(0);
    }
    if (args.includes('--root')) globalIncludeRootFiles = true;

    const allKeys = ['PHP', 'PHTML', 'JS', 'SCSS', 'SQL', 'ENV'];

    if (args.includes('--all')) {
        for (const k of allKeys) await startFileCollection(k, true);
    } else {
        if (args.includes('--php')) await startFileCollection('PHP', true);
        if (args.includes('--phtml')) await startFileCollection('PHTML', true);
        if (args.includes('--js')) await startFileCollection('JS', true);
        if (args.includes('--scss')) await startFileCollection('SCSS', true);
        if (args.includes('--sql')) await startFileCollection('SQL', true);
        if (args.includes('--env')) await startFileCollection('ENV', true);
        if (args.includes('--project')) await startProjectSummary(allKeys, true);
        if (args.includes('--mirror')) await startStructureMirror();
    }
    process.exit(0);
} else {
    const rl = readline.createInterface({
        input: process.stdin,
        output: process.stdout,
    });

    // Endlos-Schleife für das asynchrone Menü
    async function runMenu() {
        while (true) {
            const rootStatus = globalIncludeRootFiles
                ? `${c.green}${c.bright}AN${c.reset}`
                : `${c.red}${c.bright}AUS${c.reset}`;

            console.clear();
            console.log(`${c.cyan}===============================================`);
            console.log(`${c.cyan}    ${c.bright}DATEI-ZUSAMMENFASSUNG (RAW/COLLECTED)${c.reset}`);
            console.log(`${c.cyan}    Root: ${c.gray}${basePath}${c.reset}`);
            console.log(`${c.cyan}    Ziel: ${c.yellow}.debug/${version}/${c.reset}`);
            console.log(`${c.cyan}===============================================${c.reset}`);
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

            const answer = await rl.question(`${c.bright}Wähle eine Option: ${c.reset}`);
            const choice = answer.toUpperCase().trim();

            if (choice === 'Q') {
                rl.close();
                process.exit();
            }

            if (choice === 'H') {
                showHelp();
                await rl.question('Drücke Enter für Menü...');
                continue;
            }

            if (choice === 'T') {
                globalIncludeRootFiles = !globalIncludeRootFiles;
                continue;
            }

            if (choice === 'A') {
                const sel = await rl.question(
                    `\n${c.yellow}Welche Bereiche nacheinander ausführen? (z.B. 1,3,6 | Enter für alle): ${c.reset}`
                );
                const keys = parseUserSelection(sel);
                for (const k of keys) {
                    await startFileCollection(k);
                }
                await rl.question(`\n${c.gray}Fertig. Drücke Enter...${c.reset}`);
                continue;
            }

            if (choice === '7') {
                const sel = await rl.question(
                    `\n${c.magenta}Welche Bereiche in EINE Zusammenfassung packen? (z.B. 1,2,6 | Enter für alle): ${c.reset}`
                );
                const keys = parseUserSelection(sel);
                await startProjectSummary(keys);
                await rl.question(`\n${c.gray}Fertig. Drücke Enter...${c.reset}`);
                continue;
            }

            if (choice === '8') {
                await startStructureMirror();
                await rl.question(`\n${c.gray}Fertig. Drücke Enter...${c.reset}`);
                continue;
            }

            const map = {
                1: 'PHP',
                2: 'PHTML',
                3: 'JS',
                4: 'SCSS',
                5: 'SQL',
                6: 'ENV',
            };

            if (map[choice]) {
                await startFileCollection(map[choice]);
                await rl.question(`\n${c.gray}Fertig. Drücke Enter...${c.reset}`);
            } else {
                console.log(`${c.red}Ungültige Auswahl!${c.reset}`);
                await new Promise((resolve) => setTimeout(resolve, 1000));
            }
        }
    }

    runMenu();
}
