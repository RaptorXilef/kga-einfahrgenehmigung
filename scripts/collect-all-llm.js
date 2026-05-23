import fs from 'node:fs';
import path from 'node:path';
import readline from 'node:readline';
import { fileURLToPath } from 'node:url';

// =============================================================================
// SCHNELLE KONFIGURATION (Hier einfach Ordner/Dateien ergänzen)
// =============================================================================

const ALWAYS_IGNORE_DIRS = [
    'backup',
    'alt',
    'notizen',
    'notes',
    'vendor',
    'node_modules',
    '_Commits',
    'debug',
    'scripts',
    '.git',
    '.cache',
    '.build',
];

const ALWAYS_IGNORE_FILES = [
    '.lock',
    '-lock.json',
    '.DS_Store',
    'min.js',
    'min.css',
    'config.local.php',
    '.local.*',
];

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
    JS: {
        name: 'JsCode',
        filter: /\.js$/,
        ext: '.js',
        exclDirs: ['public/assets'],
        exclFiles: ['svgo.config', 'purgecss.config', 'eslint.config', 'commitlint.config'],
    },
    PHP: {
        name: 'PhpCode',
        filter: /\.php$/,
        ext: '.php',
        exclDirs: ['tests'],
        exclFiles: ['php-cs-fixer.dist', 'rector.php'],
    },
    PHTML: {
        name: 'PhtmlCode',
        filter: /\.phtml$/,
        ext: '.phtml',
        exclDirs: [],
        exclFiles: [],
    },
    SCSS: {
        name: 'ScssCode',
        filter: /\.scss$/,
        ext: '.scss',
        exclDirs: [],
        exclFiles: [],
    },
    PROJECT: {
        name: 'ProjektZusammenfassung',
        filter: /\.(js|php|phtml|scss)$/,
        ext: '.txt',
        exclDirs: [],
        exclFiles: [],
    },
};

// --- 3. Token-Optimierungs-Logik ---

/**
 * Optimiert den Code-Inhalt basierend auf dem Dateityp
 */
function optimizeTokens(content, fileExtension) {
    const ext = fileExtension.toLowerCase();
    const isPhpOrPhtml = ext === '.php' || ext === '.phtml';
    const isJsOrScss = ext === '.js' || ext === '.scss';

    // 1. Schritt: Kommentare entfernen (außer deine geschützten Pfad-Kommentare)
    if (isJsOrScss || isPhpOrPhtml) {
        // Multi-line Kommentare /* ... */ entfernen (außer sie enthalten geschützte Wörter)
        content = content.replace(/\/\*[\s\S]*?\*\//g, (match) => {
            // Bei JS/SCSS behalten wir geschützte Kommentare, bei PHP/PHTML löschen wir sie gnadenlos
            if (isJsOrScss && /path:|pfad:|file:/i.test(match)) return match;
            return '';
        });

        // Single-line Kommentare // ... entfernen
        // Verhindert das matchen von URLs (http://) durch [^:]
        content = content.replace(/(^|[^:])\/\/.*$/gm, (match, prefix) => {
            if (isJsOrScss && /path:|pfad:|file:/i.test(match)) return match;
            return prefix; // Behalte das Zeichen vor dem //
        });

        // Speziell für PHP/PHTML: # Kommentare entfernen
        if (isPhpOrPhtml) {
            content = content.replace(/(^|[^"'])#.*$/gm, (match, prefix) => {
                // if (/path:|pfad:|file:/i.test(match)) return match;
                return prefix;
            });
        }
    }

    // Extra-Schritt für PHP/PHTML/JS/SCSS: Mehrfache Leerzeichen vor und nach '=>', '+=', '=', '=>' etc. kollabieren
    // Reduziert tabellarische Ausrichtungen wie 'key      => value' zu 'key => value'
    content = content.replace(/\s*(=>|==|=|<=|>=|\+=|-=)\s*/g, ' $1 ');

    // 2. Schritt: Whitespace & Zeilenumbrüche minimieren
    if (ext === '.phtml') {
        // PHTML-Schonwaschgang: Kollabiert mehrfache Leerzeilen, schützt aber HTML-Einrückungen
        // um HTML-Strukturen und Inline-PHP nicht zu beschädigen.
        content = content.replace(/\n\s*\n/g, '\n');
        return content.trim();
    }

    // Für JS, PHP und SCSS gehen wir zeilenweise vor, um die Struktur präzise zu stauchen
    const lines = content.split(/\r?\n/);
    const optimizedLines = [];

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i].trim();
        if (line.length === 0) continue;

        // JS/SCSS: Wenn die Zeile ein geschützter Kommentar ist, MUSS sie eine eigene Zeile bleiben
        if (isJsOrScss && /^\/\/.*(path:|pfad:|file:)/i.test(line)) {
            optimizedLines.push(line);
            continue;
        }

        // Sicherheitsmaßnahme für PHP-Tags und Deklarationen
        if (ext === '.php' && (/^<\?php/i.test(line) || /^declare\s*\(/i.test(line))) {
            optimizedLines.push(line);
            continue;
        }

        // Zeilen zusammenhängen, wenn die vorherige Zeile kein kritischer Stopper war
        if (
            optimizedLines.length > 0 &&
            !(
                isJsOrScss &&
                /^\/\/.*(path:|pfad:|file:)/i.test(optimizedLines[optimizedLines.length - 1])
            ) &&
            !(ext === '.php' && /^<\?php/i.test(optimizedLines[optimizedLines.length - 1]))
        ) {
            const lastLine = optimizedLines[optimizedLines.length - 1];

            // Ein kleines Leerzeichen spendieren, falls Wortgrenzen aufeinandertreffen (z.B. zwischen Keywords)
            if (/[a-zA-Z0-9_]$/.test(lastLine) && /^[a-zA-Z0-9_]/.test(line)) {
                optimizedLines[optimizedLines.length - 1] += ' ' + line;
            } else {
                optimizedLines[optimizedLines.length - 1] += line;
            }
        } else {
            optimizedLines.push(line);
        }
    }

    return optimizedLines.join('\n');
}

/**
 * Durchsucht rekursiv Verzeichnisse
 */
function getFiles(dir, filter, exclDirs, exclFiles, includeRoot, currentFiles = []) {
    const files = fs.readdirSync(dir);

    for (const file of files) {
        const fullPath = path.join(dir, file);
        const relPath = path.relative(basePath, fullPath);
        const stat = fs.statSync(fullPath);

        if (stat.isDirectory()) {
            const isExcluded =
                ALWAYS_IGNORE_DIRS.some(
                    (d) => file.toLowerCase().includes(d.toLowerCase()) || file.startsWith('.')
                ) || exclDirs.some((d) => relPath.toLowerCase().includes(d.toLowerCase()));

            if (!isExcluded) {
                getFiles(fullPath, filter, exclDirs, exclFiles, includeRoot, currentFiles);
            }
        } else {
            const isRootFile = path.dirname(fullPath) === basePath;
            if (!includeRoot && isRootFile) continue;

            const matchesFilter = filter.test(file);
            const isExcludedFile =
                ALWAYS_IGNORE_FILES.some((f) => file.toLowerCase().includes(f.toLowerCase())) ||
                exclFiles.some((f) => file.toLowerCase().includes(f.toLowerCase()));

            if (matchesFilter && !isExcludedFile) {
                currentFiles.push({ fullPath, relPath, ext: path.extname(file) });
            }
        }
    }
    return currentFiles;
}

function startFileCollection(configKey, silent = false) {
    const conf = configs[configKey];
    const timestamp = new Date().toISOString().replace(/[:T]/g, '-').split('.')[0];
    const outputName = `${conf.name}_${timestamp}${conf.ext}`;
    const outputPath = path.join(debugFolder, outputName);

    if (!fs.existsSync(debugFolder)) fs.mkdirSync(debugFolder, { recursive: true });

    if (!silent)
        console.log(
            `\n${c.cyan}🚀 Starte token-optimierte Sammlung: ${c.bright}${conf.name}${c.reset}...`
        );

    const foundFiles = getFiles(
        basePath,
        conf.filter,
        conf.exclDirs,
        conf.exclFiles,
        globalIncludeRootFiles
    );

    if (foundFiles.length === 0) {
        if (!silent) console.log(`${c.red}❌ Keine Dateien gefunden.${c.reset}`);
        return;
    }

    let combinedContent = '';
    for (const file of foundFiles) {
        try {
            const rawContent = fs.readFileSync(file.fullPath, 'utf-8');

            // Hier passiert die Magie der Token-Optimierung
            const optimizedContent = optimizeTokens(rawContent, file.ext);

            combinedContent += `// ========== START FILE: [${file.relPath}] ==========\n`;
            combinedContent += `${optimizedContent}\n`;
            combinedContent += `// ========== END FILE: [${file.relPath}] ==========\n\n`;
            if (!silent) console.log(`${c.gray} + [Optimiert] ${file.relPath}${c.reset}`);
        } catch (_e) {
            if (!silent) console.log(`${c.gray} ! Überspringe (Binär?): ${file.relPath}${c.reset}`);
        }
    }

    fs.writeFileSync(outputPath, combinedContent, 'utf-8');
    const displayPath = path.relative(basePath, outputPath);
    console.log(
        `${c.green}✅ Erfolg: ${c.bright}${displayPath}${c.reset} (${foundFiles.length} Dateien optimiert).`
    );
}

function showHelp() {
    console.log(`\n${c.bright}HILFE & CLI ARGUMENTE (TOKEN OPTIMIERT)${c.reset}`);
    console.log(`${c.gray}------------------------------------------------------------${c.reset}`);
    console.table([
        { Argument: '--js', Beschreibung: 'Sammelt & optimiert nur JavaScript Dateien' },
        { Argument: '--php', Beschreibung: 'Sammelt & optimiert nur PHP Dateien' },
        {
            Argument: '--phtml',
            Beschreibung: 'Sammelt & optimiert nur PHTML Dateien (Sicherheitsmodus)',
        },
        { Argument: '--scss', Beschreibung: 'Sammelt & optimiert nur SCSS Dateien' },
        {
            Argument: '--project',
            Beschreibung: 'Projektweite Zusammenfassung (*.txt) - komplett optimiert',
        },
        { Argument: '--all', Beschreibung: 'Führt Punkt 1-4 automatisch aus' },
        { Argument: '--root', Beschreibung: 'Bezieht Dateien im Root-Verzeichnis mit ein' },
        { Argument: '--help', Beschreibung: 'Zeigt diese Hilfe an' },
    ]);
    console.log(`${c.gray}Info: Im CI-Modus (mit Argumenten) läuft das Skript stumm.${c.reset}\n`);
}

// --- 4. CLI & Menü Handling ---
const args = process.argv.slice(2);

if (args.length > 0) {
    if (args.includes('--help') || args.includes('-h')) {
        showHelp();
        process.exit(0);
    }
    if (args.includes('--root')) globalIncludeRootFiles = true;

    if (args.includes('--all')) {
        ['JS', 'PHP', 'PHTML', 'SCSS'].forEach((k) => {
            startFileCollection(k, true);
        });
    } else {
        if (args.includes('--js')) startFileCollection('JS', true);
        if (args.includes('--php')) startFileCollection('PHP', true);
        if (args.includes('--phtml')) startFileCollection('PHTML', true);
        if (args.includes('--scss')) startFileCollection('SCSS', true);
        if (args.includes('--project')) startFileCollection('PROJECT', true);
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
        console.log(
            `${c.cyan}    ${c.bright}DATEI-ZUSAMMENFASSUNG (TOKEN OPTIMIERT)${c.reset} ${c.dim}(NodeJS CLI)${c.reset}`
        );
        console.log(`${c.cyan}    Root: ${c.gray}${basePath}${c.reset}`);
        console.log(`${c.cyan}    Ziel: ${c.yellow}.debug/${version}/${c.reset}`);
        console.log(`${c.cyan}===============================================${c.reset}`);
        console.log(`${c.bright} 1)${c.reset} JavaScript (*.js)`);
        console.log(`${c.bright} 2)${c.reset} PHP (*.php)`);
        console.log(`${c.bright} 3)${c.reset} PHTML (*.phtml) ${c.dim}(Sicherer Modus)${c.reset}`);
        console.log(`${c.bright} 4)${c.reset} SCSS (*.scss)`);
        console.log(
            `${c.bright} 5)${c.reset} ${c.magenta}PROJEKT-ZUSAMMENFASSUNG${c.reset} (*.txt)`
        );
        console.log(`${c.gray}-----------------------------------------------${c.reset}`);
        console.log(`${c.bright} T)${c.reset} Toggle Root-Files: [${rootStatus}]`);
        console.log(`${c.bright} A)${c.reset} ${c.yellow}ALLE nacheinander (1-4)${c.reset}`);
        console.log(`${c.bright} H)${c.reset} Hilfe / CI Info`);
        console.log(`${c.bright} Q)${c.reset} Beenden`);
        console.log(`${c.gray}-----------------------------------------------${c.reset}`);

        rl.question(`${c.bright}Wähle eine Option: ${c.reset}`, (answer) => {
            const choice = answer.toUpperCase();

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
            if (choice === 'A') {
                ['JS', 'PHP', 'PHTML', 'SCSS'].forEach((k) => {
                    startFileCollection(k);
                });
                rl.question(`\n${c.gray}Fertig. Drücke Enter...${c.reset}`, showMenu);
                return;
            }

            const map = { 1: 'JS', 2: 'PHP', 3: 'PHTML', 4: 'SCSS', 5: 'PROJECT' };
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
