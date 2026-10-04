import fs from 'node:fs';
import fsPromises from 'node:fs/promises';
import path from 'node:path';
import readline from 'node:readline/promises';
import { fileURLToPath } from 'node:url';

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

// --- 1. Grundkonfiguration & Pfade ---
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const basePath = path.resolve(__dirname, '..');

let globalIncludeRootFiles = false;
let globalKeepDocBlocks = false; // Toggle für DocBlocks

// Version aus package.json lesen
let version = 'unknown';
try {
    const pkg = JSON.parse(fs.readFileSync(path.join(basePath, 'package.json'), 'utf-8'));
    version = pkg.version;
} catch (_e) {
    console.warn(`${c.yellow}⚠️ package.json nicht gefunden oder fehlerhaft.${c.reset}`);
}

const debugFolder = path.join(basePath, '.debug', version);

// =============================================================================
// KI SYSTEM-PROMPT (Wird automatisch oben in die Markdown-Datei eingefügt)
// =============================================================================
const AI_SYSTEM_PROMPT = `> **[SYSTEM-INFO FÜR LLM]**
> Dieser Code wurde für den Kontext-Upload automatisch minifiziert (Token-Optimierung).
> - Kommentare, Whitespace und Zeilenumbrüche wurden stark reduziert.
> - \`<?php echo ... ?>\` wurde für den Upload teilweise zu \`<?= ... ?>\` verkürzt.
>
> **WICHTIGE ANWEISUNG FÜR DEINE ANTWORTEN:**
> 1. Schreibe generierten Code für den Nutzer **immer sauber formatiert** (mit korrekten Einrückungen und Zeilenumbrüchen) zurück.
> 2. Nutze in PHP **immer die ausführliche Schreibweise** \`<?php echo ... ?>\` (keine Short-Echos), es sei denn, der Nutzer bittet explizit darum.

---

`;

// =============================================================================
// KONFIGURATION AUSLAGERN & LADEN
// =============================================================================
const configPath = path.join(__dirname, 'collect-config.json');

const defaultConfig = {
    ALWAYS_IGNORE_DIRS: [
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
    ],
    ALWAYS_IGNORE_PATHS: ['public/assets', 'public/dev'],
    ALWAYS_IGNORE_FILES: [
        '*.lock',
        '*-lock.json',
        '.DS_Store',
        '*.min.js',
        '*.min.css',
        '*.local.*',
        'routes_v2.php',
    ],
    IGNORE_BY_TYPE: {
        js: {
            dirs: ['public/assets'],
            files: ['svgo.config.*', 'purgecss.config.*', 'eslint.config.*', 'commitlint.config.*'],
        },
        php: { dirs: ['tests'], files: ['*php-cs-fixer.dist*', 'rector.php'] },
        phtml: { dirs: [], files: [] },
        scss: { dirs: [], files: [] },
        sql: { dirs: [], files: [] },
    },
    ENV_FILES: ['composer.json', 'package.json', 'deptrac.yaml', 'phpstan.neon.dist'],
    SQL_TARGET_DIR: 'database/migrations',
};

let userConfig;
try {
    if (!fs.existsSync(configPath)) {
        fs.writeFileSync(configPath, JSON.stringify(defaultConfig, null, 4), 'utf-8');
        userConfig = defaultConfig;
        console.log(`${c.dim}ℹ️ Config erstellt: ${configPath}${c.reset}`);
    } else {
        userConfig = JSON.parse(fs.readFileSync(configPath, 'utf-8'));
    }
} catch (e) {
    console.warn(
        `${c.yellow}⚠️ Fehler beim Laden der collect-config.json. Nutze Standardwerte.${c.reset}`
    );
    userConfig = defaultConfig;
}

const ALWAYS_IGNORE_DIRS = userConfig.ALWAYS_IGNORE_DIRS || [];
const ALWAYS_IGNORE_PATHS = userConfig.ALWAYS_IGNORE_PATHS || [];
const ALWAYS_IGNORE_FILES = userConfig.ALWAYS_IGNORE_FILES || [];
const IGNORE_BY_TYPE = userConfig.IGNORE_BY_TYPE || {};

// --- 2. Filter-Konfigurationen ---
const configs = {
    PHP: {
        name: 'PhpCode',
        filter: /\.php$/,
        ext: '.md',
        exclDirs: IGNORE_BY_TYPE.php?.dirs || [],
        exclFiles: IGNORE_BY_TYPE.php?.files || [],
    },
    PHTML: {
        name: 'PhtmlCode',
        filter: /\.phtml$/,
        ext: '.md',
        exclDirs: IGNORE_BY_TYPE.phtml?.dirs || [],
        exclFiles: IGNORE_BY_TYPE.phtml?.files || [],
    },
    JS: {
        name: 'JsCode',
        filter: /\.js$/,
        ext: '.md',
        exclDirs: IGNORE_BY_TYPE.js?.dirs || [],
        exclFiles: IGNORE_BY_TYPE.js?.files || [],
    },
    SCSS: {
        name: 'ScssCode',
        filter: /\.scss$/,
        ext: '.md',
        exclDirs: IGNORE_BY_TYPE.scss?.dirs || [],
        exclFiles: IGNORE_BY_TYPE.scss?.files || [],
    },
    SQL: {
        name: 'SqlMigrations',
        filter: /\.sql$/,
        ext: '.md',
        targetDir: userConfig.SQL_TARGET_DIR || 'database/migrations',
        exclDirs: IGNORE_BY_TYPE.sql?.dirs || [],
        exclFiles: IGNORE_BY_TYPE.sql?.files || [],
    },
    ENV: {
        name: 'Entwicklungsumgebung',
        explicitFiles: userConfig.ENV_FILES || [],
        ext: '.md',
    },
};

// --- 3. Daten-Bereinigung, Formatierung & Token-Schätzung ---

function estimateTokens(text) {
    return Math.ceil(text.length / 4);
}

function sanitizeJsonContent(filePath, rawContent) {
    const baseName = path.basename(filePath).toLowerCase();
    if (baseName !== 'composer.json' && baseName !== 'package.json') return rawContent;

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
        return rawContent;
    }
}

/**
 * Token-Optimierungs-Logik (Kombiniert Kommentarentfernung, Spaces & Zeilenumbruch-Reduktion)
 */
function optimizeTokens(content, fileExtension) {
    const ext = fileExtension.toLowerCase();
    const isPhpOrPhtml = ext === '.php' || ext === '.phtml';
    const isJsOrScss = ext === '.js' || ext === '.scss';

    // Fallback für Dateien wie SQL, JSON oder YAML (nur rudimentäres Trimming)
    if (!isPhpOrPhtml && !isJsOrScss) {
        return content
            .split(/\r?\n/)
            .map((line) => line.trim())
            .filter((line) => line.length > 0)
            .join('\n');
    }

    // =========================================================================
    // 1. SCHUTZMECHANISMEN (Strings & sensible Blöcke in den Tresor)
    // =========================================================================
    const stringMap = new Map();
    let stringId = 0;

    // Zieht alle Strings (", ', `) ab und ersetzt sie durch einen Platzhalter.
    let optimizedContent = content.replace(/(["'`])(?:\\.|(?!\1)[^\\])*\1/g, (match) => {
        const id = `___STR_PLACEHOLDER_${stringId++}___`;
        stringMap.set(id, match);
        return id;
    });

    const blockMap = new Map();
    let blockId = 0;

    // Schützt <script>, <style>, <pre> und <textarea> vor der Zeilenzerstörung
    if (isPhpOrPhtml) {
        optimizedContent = optimizedContent.replace(
            /<(script|style|pre|textarea)[\s\S]*?>[\s\S]*?<\/\1>/gi,
            (match) => {
                const id = `___BLOCK_PLACEHOLDER_${blockId++}___`;
                blockMap.set(id, match);
                return id;
            }
        );
    }

    // =========================================================================
    // 2. KOMMENTARE ENTFERNEN & OPERATOR-PADDING
    // =========================================================================
    const operatorRegex = /(?<!<\?)\s*(===|!==|<=|>=|=>|==|!=|\+=|-=|=)\s*/g;

    // Verhindert global, dass URLs (http://) in SCSS/JS als Kommentar gewertet werden
    const singleLineCommentRegex = /(?<!https?:|ftp:|file:|\\)\/\/.*$/gim;

    if (isJsOrScss) {
        // SCSS / JS Logic (läuft global über die Datei)
        // Wenn aktiviert, behalte DocBlocks (/** ... */) die ein '@' enthalten
        if (globalKeepDocBlocks) {
            optimizedContent = optimizedContent.replace(/\/\*\*[\s\S]*?\*\//g, (match) => {
                // Nur wenn ein Annotation-Tag existiert ab in den schützenden Tresor
                if (match.includes('@')) {
                    const id = `___BLOCK_PLACEHOLDER_${blockId++}___`;
                    // Zeilenumbrüche (\n) hinzufügen, damit es gut lesbar bleibt
                    blockMap.set(id, `\n${match}\n`);
                    return id;
                }
                // Ansonsten lassen wir es stehen, damit der nächste Schritt es löscht
                return match;
            });
        }

        // Multi-line Kommentare /* ... */ (löscht alles was nicht im Tresor ist)
        optimizedContent = optimizedContent.replace(/\/\*[\s\S]*?\*\//g, '');
        optimizedContent = optimizedContent.replace(singleLineCommentRegex, '');
        optimizedContent = optimizedContent.replace(operatorRegex, ' $1 ');
    } else if (isPhpOrPhtml) {
        // HTML & SQL Kommentare (laufen sicher global über die Datei)
        optimizedContent = optimizedContent.replace(/<!--[\s\S]*?-->/g, '');
        optimizedContent = optimizedContent.replace(/(?<!!)--\s.*$/gm, '');

        // FIX: PHP Kommentare STRIKT auf <?php ... ?> Blöcke begrenzen!
        // HTML bleibt von // und /* unangetastet.
        optimizedContent = optimizedContent.replace(
            /(<\?[pP][hH][pP]|<\?=)([\s\S]*?)(\?>|$)/g,
            (_match, openTag, phpCode, closeTag) => {
                // DocBlocks
                if (globalKeepDocBlocks) {
                    phpCode = phpCode.replace(/\/\*\*[\s\S]*?\*\//g, (match) => {
                        if (match.includes('@')) {
                            const id = `___BLOCK_PLACEHOLDER_${blockId++}___`;
                            blockMap.set(id, `\n${match}\n`);
                            return id;
                        }
                        return match;
                    });
                }

                // PHP /* */
                phpCode = phpCode.replace(/\/\*[\s\S]*?\*\//g, '');

                // PHP //
                phpCode = phpCode.replace(singleLineCommentRegex, '');

                // SICHERHEITS-CHECK: Verhindert das Löschen von PHP 8 Attributes (#[...])
                if (ext === '.php') {
                    phpCode = phpCode.replace(/(^|[^"'])#(?!\[).*$/gm, (_m, prefix) => prefix);
                }

                // PHP Operatoren
                phpCode = phpCode.replace(operatorRegex, ' $1 ');

                return `${openTag}${phpCode}${closeTag}`;
            }
        );
    }

    // =========================================================================
    // 3. ZEILEN & WHITESPACE MINIMIEREN (INKL. HTML & PHP SHORT-ECHOS)
    // =========================================================================
    let joinedResult = optimizedContent;

    if (isPhpOrPhtml) {
        // Leere PHP Tags komplett entfernen (z.B. <?php ?>)
        joinedResult = joinedResult.replace(/<\?php\s*\?>/gi, '');

        // PHP Short-Echos erzwingen: <?php echo $var; ?> wird zu <?=$var?>
        joinedResult = joinedResult.replace(/<\?php\s+echo\s+(.+?);\s*\?>/g, '<?=$1?>');
        // Auch für Fälle ohne Semikolon: <?php echo $var ?>
        joinedResult = joinedResult.replace(/<\?php\s+echo\s+(.+?)\s*\?>/g, '<?=$1?>');
    }

    const lines = joinedResult.split(/\r?\n/);
    const optimizedLines = [];

    if (ext === '.phtml') {
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i].trim();
            if (line.length === 0) continue;

            if (optimizedLines.length > 0) {
                const lastLine = optimizedLines[optimizedLines.length - 1];
                if (
                    !line.startsWith('<') ||
                    (!lastLine.endsWith('>') && !lastLine.endsWith('?>'))
                ) {
                    optimizedLines[optimizedLines.length - 1] += ` ${line}`;
                    continue;
                }
            }
            optimizedLines.push(line);
        }
        joinedResult = optimizedLines.join('');

        // HTML: Entferne Leerzeichen zwischen Tags </div> <div> -> </div><div>
        joinedResult = joinedResult.replace(/>\s+</g, '><');

        // ROBUSTE HTML-ATTRIBUT KOMPRESSION
        // Löscht alle mehrfachen Whitespaces (inkl. Zeilenumbrüche) innerhalb von Tag-Deklarationen
        // z.B. aus `<script \n src="...">` wird `<script src="...">`
        joinedResult = joinedResult.replace(/<([a-zA-Z0-9-]+)([^>]*?)>/g, (match, tag, attrs) => {
            const cleanAttrs = attrs.replace(/\s+/g, ' ').trim();
            return cleanAttrs ? `<${tag} ${cleanAttrs}>` : `<${tag}>`;
        });
    } else {
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i].trim();
            if (line.length === 0) continue;

            if (ext === '.php' && (/^<\?php/i.test(line) || /^declare\s*\(/i.test(line))) {
                optimizedLines.push(line);
                continue;
            }
            if (
                optimizedLines.length > 0 &&
                !(ext === '.php' && /^<\?php/i.test(optimizedLines[optimizedLines.length - 1]))
            ) {
                const lastLine = optimizedLines[optimizedLines.length - 1];
                if (/[a-zA-Z0-9_\])%]$/.test(lastLine) && /^[a-zA-Z0-9_$-]/.test(line)) {
                    optimizedLines[optimizedLines.length - 1] += ` ${line}`;
                } else {
                    optimizedLines[optimizedLines.length - 1] += line;
                }
            } else {
                optimizedLines.push(line);
            }
        }
        joinedResult = optimizedLines.join('\n');
    }

    // =========================================================================
    // 4. TRESOR WIEDERHERSTELLEN & STRINGS KOMPRIMIEREN
    // =========================================================================

    // Blöcke (wie <style> oder <script>) komprimieren
    blockMap.forEach((originalBlock, placeholderKey) => {
        let restoredBlock = originalBlock;

        // CSS in <style> Tags extrem komprimieren
        if (restoredBlock.toLowerCase().startsWith('<style>')) {
            restoredBlock = restoredBlock
                .replace(/\r?\n/g, '') // Neue Zeilen weg
                .replace(/\s+/g, ' ') // Mehrfache Leerzeichen zu einem
                .replace(/\s*{\s*/g, '{') // Leerzeichen um Klammern weg
                .replace(/\s*}\s*/g, '}')
                .replace(/\s*:\s*/g, ':') // Leerzeichen um Doppelpunkte weg
                .replace(/\s*;\s*/g, ';');
        }
        joinedResult = joinedResult.split(placeholderKey).join(restoredBlock);
    });

    // Strings (SQL, HTML-Heredoc, etc.) komprimieren
    stringMap.forEach((originalString, placeholderKey) => {
        let restoredString = originalString;

        // Wenn der String mehrzeilig ist (typisch für SQL-Queries oder Heredoc-HTML)
        if (restoredString.includes('\n')) {
            restoredString = restoredString
                .split('\n')
                .map((line) => line.trim()) // Führende/abschließende Leerzeichen pro Zeile weg
                .filter((line) => line.length > 0) // Leere Zeilen im String löschen
                .join(' ') // Alles in eine Zeile packen
                .replace(/\s{2,}/g, ' '); // Übrig gebliebene doppelte Leerzeichen entfernen
        }

        joinedResult = joinedResult.split(placeholderKey).join(restoredString);
    });

    return joinedResult;
}

function generateTreeString(files) {
    const tree = {};
    for (const file of files) {
        const parts = file.relPath.replace(/\\/g, '/').split('/');
        let current = tree;
        for (let i = 0; i < parts.length; i++) {
            const part = parts[i];
            if (!current[part]) current[part] = i === parts.length - 1 ? null : {};
            current = current[part];
        }
    }

    function renderNode(node, prefix = '') {
        let result = '';
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

function matchPattern(target, pattern) {
    if (!pattern) return false;
    const cleanTarget = target.toLowerCase();
    const cleanPattern = pattern.toLowerCase();
    if (cleanPattern.includes('*')) {
        const escapeRegex = (s) => s.replace(/[.+?^${}()|[\]\\]/g, '\\$&');
        const regexStr = `^${cleanPattern.split('*').map(escapeRegex).join('.*')}$`;
        return new RegExp(regexStr, 'i').test(cleanTarget);
    }
    return cleanTarget === cleanPattern;
}

function isDirExcludedByList(relPath, patterns = []) {
    if (!patterns || patterns.length === 0 || !relPath || relPath === '.') return false;
    const normalizedRelPath = relPath.replace(/\\/g, '/');
    const segments = normalizedRelPath.split('/');
    const subPaths = segments.map((_, idx) => segments.slice(0, idx + 1).join('/'));
    return patterns.some((pattern) => {
        const normalizedPattern = pattern.replace(/\\/g, '/');
        if (normalizedPattern.includes('/'))
            return subPaths.some((sub) => matchPattern(sub, normalizedPattern));
        return segments.some((segment) => matchPattern(segment, normalizedPattern));
    });
}

function isFileExcludedByList(fileName, relFilePath, patterns = []) {
    if (!patterns || patterns.length === 0) return false;
    const normalizedRelFile = relFilePath.replace(/\\/g, '/');
    return patterns.some((pattern) => {
        const normalizedPattern = pattern.replace(/\\/g, '/');
        if (normalizedPattern.includes('/'))
            return matchPattern(normalizedRelFile, normalizedPattern);
        return matchPattern(fileName, normalizedPattern);
    });
}

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
            const isExcludedDir =
                file.startsWith('.') ||
                isDirExcludedByList(relPath, ALWAYS_IGNORE_DIRS) ||
                isDirExcludedByList(relPath, ALWAYS_IGNORE_PATHS) ||
                isDirExcludedByList(relPath, exclDirs);

            if (!isExcludedDir)
                await getFiles(fullPath, filter, exclDirs, exclFiles, includeRoot, currentFiles);
        } else {
            const isRootFile = path.dirname(fullPath) === basePath;
            if (!includeRoot && isRootFile) continue;
            if (!filter.test(file)) continue;

            const extKey = path.extname(file).toLowerCase().replace('.', '');
            const typeIgnores = IGNORE_BY_TYPE[extKey] || {
                dirs: [],
                files: [],
            };
            const relDir = path.dirname(relPath);

            const isExcludedByTypeDir =
                relDir !== '.' && isDirExcludedByList(relDir, typeIgnores.dirs);
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

// Generiert einen sauberen Zeitstempel-Ordnernamen (YYYY-MM-DD_hh-mm-ss)
function getTimestampString() {
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}_${pad(d.getHours())}-${pad(d.getMinutes())}-${pad(d.getSeconds())}`;
}

async function getFilesForConfig(conf, silent = false) {
    let foundFiles = [];
    if (conf.explicitFiles) {
        for (const filePath of conf.explicitFiles) {
            const normalizedPath = path.normalize(filePath);
            const fullPath = path.join(basePath, normalizedPath);
            if (fs.existsSync(fullPath)) {
                foundFiles.push({
                    fullPath,
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

async function processFilesToMarkdown(foundFiles, silent = false) {
    let combinedContent = '';
    const chunkSize = 50;

    for (let i = 0; i < foundFiles.length; i += chunkSize) {
        const chunk = foundFiles.slice(i, i + chunkSize);

        const chunkResults = await Promise.all(
            chunk.map(async (file) => {
                try {
                    let rawContent = await fsPromises.readFile(file.fullPath, 'utf-8');
                    rawContent = sanitizeJsonContent(file.relPath, rawContent);

                    // Nutzt nun die integrierte Token-Optimierung anstatt einfachem Format!
                    const optimizedContent = optimizeTokens(rawContent, file.ext);

                    const extName = file.ext.toLowerCase().replace('.', '');
                    const langMap = {
                        js: 'javascript',
                        php: 'php',
                        phtml: 'html',
                        scss: 'scss',
                        json: 'json',
                        yml: 'yaml',
                        yaml: 'yaml',
                        sql: 'sql',
                    };
                    const lang = langMap[extName] || extName;
                    const mdPath = file.relPath.replace(/\\/g, '/');

                    if (!silent) console.log(`${c.gray} + [Optimiert] ${file.relPath}${c.reset}`);

                    return `### /${mdPath}\n\`\`\`${lang}\n${optimizedContent}\n\`\`\`\n\n`;
                } catch (_e) {
                    if (!silent)
                        console.log(
                            `${c.gray} ! Überspringe (Binär/Fehler?): ${file.relPath}${c.reset}`
                        );
                    return '';
                }
            })
        );

        combinedContent += chunkResults.join('');
    }
    return combinedContent;
}

/**
 * Funktion für den neuen Menüpunkt 6 (Gleiche Ordnerstruktur spiegeln)
 */
async function startStructureMirror() {
    const timestampDirName = getTimestampString();

    // Namenszusatz hinzufügen, wenn DocBlocks aktiviert sind
    const docSuffix = globalKeepDocBlocks ? '_docblock' : '';
    const targetDirName = `${timestampDirName}_minimized${docSuffix}`;
    const targetDir = path.join(debugFolder, targetDirName);

    console.log(`\n${c.cyan}🚀 Starte Erstellung der gespiegelten Token-Struktur...`);
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
    const chunkSize = 50;

    for (let i = 0; i < foundFiles.length; i += chunkSize) {
        const chunk = foundFiles.slice(i, i + chunkSize);

        await Promise.all(
            chunk.map(async (file) => {
                try {
                    let rawContent = await fsPromises.readFile(file.fullPath, 'utf-8');
                    rawContent = sanitizeJsonContent(file.relPath, rawContent);
                    let optimizedContent = optimizeTokens(rawContent, file.ext);

                    let commentPrefix = `// Path: ${file.relPath}\n`;
                    if (file.ext.toLowerCase() === '.phtml') {
                        commentPrefix = `<!-- Path: ${file.relPath} -->\n`;
                    } else if (file.ext.toLowerCase() === '.sql') {
                        commentPrefix = `-- Path: ${file.relPath}\n`;
                    }

                    if (file.ext.toLowerCase() === '.php' && /^\s*<\?php/i.test(optimizedContent)) {
                        optimizedContent = optimizedContent.replace(
                            /^\s*<\?php/i,
                            (match) => `${match}\n${commentPrefix.trim()}`
                        );
                    } else {
                        optimizedContent = `${commentPrefix}${optimizedContent}`;
                    }

                    const fileOutputDir = path.join(targetDir, path.dirname(file.relPath));
                    const fileOutputPath = path.join(targetDir, file.relPath);

                    await fsPromises.mkdir(fileOutputDir, { recursive: true });
                    await fsPromises.writeFile(fileOutputPath, optimizedContent, 'utf-8');
                    count++;
                    console.log(`${c.gray} + [Spiegeln] ${file.relPath}${c.reset}`);
                } catch (e) {
                    console.log(
                        `${c.red} ! Fehler bei Datei: ${file.relPath} (${e.message})${c.reset}`
                    );
                }
            })
        );
    }

    console.log(
        `${c.green}✅ Erfolg: Struktur gespiegelt! ${c.bright}${count} Dateien${c.reset} exportiert nach ${c.yellow}.debug/${version}/${targetDirName}/${c.reset}`
    );
}

async function startFileCollection(configKey, silent = false) {
    const conf = configs[configKey];
    const timestamp = getTimestampString();

    // Namenszusatz hinzufügen, wenn DocBlocks aktiviert sind
    const docSuffix = globalKeepDocBlocks ? '_docblock' : '';
    const outputName = `${conf.name}_${timestamp}_minimized${docSuffix}${conf.ext}`;
    const outputPath = path.join(debugFolder, outputName);

    if (!fs.existsSync(debugFolder)) await fsPromises.mkdir(debugFolder, { recursive: true });

    if (!silent)
        console.log(
            `\n${c.cyan}🚀 Starte token-optimierte Sammlung: ${c.bright}${conf.name}${c.reset}...`
        );

    const foundFiles = await getFilesForConfig(conf, silent);

    if (foundFiles.length === 0) {
        if (!silent) console.log(`${c.red}❌ Keine Dateien gefunden.${c.reset}`);
        return;
    }

    const treeString = generateTreeString(foundFiles);
    const codeContent = await processFilesToMarkdown(foundFiles, silent);

    // Fügt den KI-System-Prompt ganz oben ein!
    const finalContent = `${AI_SYSTEM_PROMPT}## 📁 Datei-Struktur\n\n\`\`\`text\n${treeString}\`\`\`\n\n---\n\n${codeContent}`;

    const tokens = estimateTokens(finalContent);
    await fsPromises.writeFile(outputPath, finalContent, 'utf-8');

    const displayPath = path.relative(basePath, outputPath);
    console.log(
        `${c.green}✅ Erfolg: ${c.bright}${displayPath}${c.reset} (${foundFiles.length} Dateien, ca. ${tokens.toLocaleString('de-DE')} Tokens).`
    );
}

async function startProjectSummary(selectedKeys, silent = false) {
    const timestamp = getTimestampString();
    const docSuffix = globalKeepDocBlocks ? '_docblock' : '';
    const outputName = `ProjektZusammenfassung_${timestamp}_minimized${docSuffix}.md`;
    const outputPath = path.join(debugFolder, outputName);

    if (!fs.existsSync(debugFolder)) await fsPromises.mkdir(debugFolder, { recursive: true });
    if (!silent)
        console.log(
            `\n${c.cyan}🚀 Starte Projekt-Zusammenfassung (Kategorien: ${selectedKeys.join(', ')})...${c.reset}`
        );

    let totalContent = '';
    const allFoundFiles = [];

    for (const key of selectedKeys) {
        const conf = configs[key];
        const foundFiles = await getFilesForConfig(conf, true);
        if (foundFiles.length > 0) {
            allFoundFiles.push(...foundFiles);
            totalContent += `\n# === BEREICH: ${conf.name} ===\n\n`;
            totalContent += await processFilesToMarkdown(foundFiles, silent);
        }
    }

    if (allFoundFiles.length === 0) {
        console.log(`${c.red}❌ Keine Dateien für die Zusammenfassung gefunden.${c.reset}`);
        return;
    }

    const globalTreeString = generateTreeString(allFoundFiles);

    // Fügt den KI-System-Prompt ganz oben ein!
    const finalContent = `${AI_SYSTEM_PROMPT}# 📦 Projekt-Zusammenfassung\n\n## 📁 Globale Datei-Struktur\n\n\`\`\`text\n${globalTreeString}\`\`\`\n\n---\n${totalContent}`;

    const tokens = estimateTokens(finalContent);
    await fsPromises.writeFile(outputPath, finalContent, 'utf-8');

    const displayPath = path.relative(basePath, outputPath);
    console.log(
        `${c.green}✅ Erfolg: Zusammenfassung in ${c.bright}${displayPath}${c.reset} gespeichert (${allFoundFiles.length} Dateien, ca. ${tokens.toLocaleString('de-DE')} Tokens).`
    );
}

function showHelp() {
    console.log(`\n${c.bright}HILFE & CLI ARGUMENTE (TOKEN OPTIMIERT)${c.reset}`);
    console.log(`${c.gray}------------------------------------------------------------${c.reset}`);
    console.table([
        {
            Argument: '--php',
            Beschreibung: 'Sammelt & optimiert nur PHP Dateien',
        },
        {
            Argument: '--phtml',
            Beschreibung: 'Sammelt & optimiert nur PHTML Dateien',
        },
        {
            Argument: '--js',
            Beschreibung: 'Sammelt & optimiert nur JavaScript Dateien',
        },
        {
            Argument: '--scss',
            Beschreibung: 'Sammelt & optimiert nur SCSS Dateien',
        },
        {
            Argument: '--sql',
            Beschreibung: 'Sammelt SQL Dateien (database/migrations)',
        },
        {
            Argument: '--env',
            Beschreibung: 'Sammelt Entwicklungsumgebungs-Dateien',
        },
        {
            Argument: '--project',
            Beschreibung: 'Projektweite Zusammenfassung (*.md)',
        },
        {
            Argument: '--mirror',
            Beschreibung: 'Spiegelt die gesamte optimierte Ordnerstruktur',
        },
        {
            Argument: '--all',
            Beschreibung: 'Führt Code-Sammlungen einzeln automatisch aus',
        },
        {
            Argument: '--root',
            Beschreibung: 'Bezieht Dateien im Root-Verzeichnis mit ein',
        },
        {
            Argument: '--docblocks',
            Beschreibung: 'Behält DocBlocks mit @-Tags bei',
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
        if (map[p] && !selected.includes(map[p])) selected.push(map[p]);
    }
    return selected.length > 0 ? selected : allKeys;
}

const args = process.argv.slice(2);

if (args.length > 0) {
    if (args.includes('--help') || args.includes('-h')) {
        showHelp();
        process.exit(0);
    }
    if (args.includes('--root')) globalIncludeRootFiles = true;
    if (args.includes('--docblocks')) globalKeepDocBlocks = true;

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

    async function runMenu() {
        while (true) {
            const rootStatus = globalIncludeRootFiles
                ? `${c.green}${c.bright}AN${c.reset}`
                : `${c.red}${c.bright}AUS${c.reset}`;
            const docBlockStatus = globalKeepDocBlocks
                ? `${c.green}${c.bright}AN${c.reset}`
                : `${c.red}${c.bright}AUS${c.reset}`;

            console.clear();
            console.log(`${c.cyan}===============================================`);
            console.log(
                `${c.cyan}    ${c.bright}DATEI-ZUSAMMENFASSUNG (TOKEN OPTIMIERT)${c.reset}`
            );
            console.log(`${c.cyan}    Root: ${c.gray}${basePath}${c.reset}`);
            console.log(`${c.cyan}    Config: ${c.gray}${configPath}${c.reset}`);
            console.log(`${c.cyan}    Ziel: ${c.yellow}.debug/${version}/${c.reset}`);
            console.log(`${c.cyan}===============================================${c.reset}`);
            console.log(`${c.bright} 1)${c.reset} PHP (*.md)`);
            console.log(`${c.bright} 2)${c.reset} PHTML (*.md)`);
            console.log(`${c.bright} 3)${c.reset} JavaScript (*.md)`);
            console.log(`${c.bright} 4)${c.reset} SCSS (*.md)`);
            console.log(
                `${c.bright} 5)${c.reset} ${c.cyan}SQL MIGRATIONS${c.reset} (${userConfig.SQL_TARGET_DIR || 'database/migrations'}/*.sql)`
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
                `${c.bright} D)${c.reset} Toggle DocBlocks (@param, etc.): [${docBlockStatus}]`
            );
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
            if (choice === 'D') {
                globalKeepDocBlocks = !globalKeepDocBlocks;
                continue;
            }
            if (choice === 'A') {
                const sel = await rl.question(
                    `\n${c.yellow}Welche Bereiche nacheinander ausführen? (z.B. 1,3,6 | Enter für alle): ${c.reset}`
                );
                for (const k of parseUserSelection(sel)) await startFileCollection(k);
                await rl.question(`\n${c.gray}Fertig. Drücke Enter...${c.reset}`);
                continue;
            }
            if (choice === '7') {
                const sel = await rl.question(
                    `\n${c.magenta}Welche Bereiche in EINE Zusammenfassung packen? (z.B. 1,2,6 | Enter für alle): ${c.reset}`
                );
                await startProjectSummary(parseUserSelection(sel));
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
