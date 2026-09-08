/**
 * Path: stylelint.template.js
 * @description Spezialisierte SCSS-Qualitätssicherung. (Maximal-Optimierung 2026)
 */

export default {
    extends: [
        'stylelint-config-standard-scss',
        'stylelint-config-recess-order', // Erzwingt logische Reihenfolge (recess)
    ],
    plugins: [
        'stylelint-declaration-strict-value',
        'stylelint-use-logical-spec', // NEU: Erzwingt logische Eigenschaften
    ],
    rules: {
        'alpha-value-notation': 'number',
        'no-empty-source': null,
        'scss/at-rule-no-unknown': true,

        // NEU: Verbanne HEX-Farben komplett, um den OKLCH-Standard zu erzwingen
        'color-no-hex': [
            true,
            { message: 'Nutze oklch() oder CSS-Variablen statt HEX-Farben für moderne Themes.' },
        ],

        // NEU: Erzwinge CSS Logical Properties (z. B. margin-inline statt margin-left)
        'liberty/use-logical-spec': [
            true,
            {
                direction: 'ltr',
                except: ['top', 'bottom', 'left', 'right'], // Erlaubt für absolute Positionierung
            },
        ],

        // Begrenzt die Verschachtlung auf 3 Ebenen
        'max-nesting-depth': [
            3,
            {
                ignorePseudoClasses: ['hover', 'focus', 'active', 'focus-visible'],
            },
        ],

        // Warnt vor der Nutzung von Variablen für Design-Tokens
        'scale-unlimited/declaration-strict-value': [
            ['/color/', 'font-family', 'font-size', 'font-weight', 'spacing'],
            {
                ignoreValues: [
                    '0',
                    'inherit',
                    'transparent',
                    'initial',
                    'none',
                    'currentColor',
                    'sans-serif',
                    'arial',
                    '/^\\d+(%|vw|vh|rem|em)$/',
                ],
                disableFix: true,
                severity: 'warning',
                message:
                    // biome-ignore lint/suspicious/noTemplateCurlyInString: Stylelint uses this as an internal placeholder
                    "Hinweis: Idealerweise nutzt du eine Variable für '${property}'. Hart-codierte Werte sind unerwünscht.",
            },
        ],

        // NEU & OPTIMIERT: BEM mit striktem Namespace-Zwang (c-, o-, u-, is-, has-)
        'selector-class-pattern': [
            '^(c|o|u|is|has)-([a-z][a-z0-9]*)(-[a-z0-9]+)*(__[a-z0-9]+(-[a-z0-9]+)*)?(--[a-z0-9]+(-[a-z0-9]+)*)?$',
            {
                message:
                    'Klassennamen müssen das Namespace-BEM-Muster nutzen (z.B. .c-card__title oder .u-hidden)',
            },
        ],
    },
    ignoreFiles: ['**/_palette.scss', 'vendor/**/*.scss', 'public/assets/**/*.css'],
};
