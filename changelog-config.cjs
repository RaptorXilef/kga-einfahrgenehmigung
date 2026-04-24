const presetFactory = require('conventional-changelog-conventionalcommits');

const getPreset = (p) => {
    if (typeof p === 'function') return p;
    if (p && typeof p.default === 'function') return p.default;
    return p;
};

// Wir rufen die Factory auf. Diese liefert bei dir offenbar
// direkt das Config-Objekt zurück, keine Promise.
const config = getPreset(presetFactory)({
    types: [
        { type: 'feat', section: '🚀 Features' },
        { type: 'fix', section: '🐛 Bug Fixes' },
        { type: 'perf', section: '⚡ Performance' },
        { type: 'refactor', section: '⚙️ Refactoring' },
        { type: 'build', section: '🏗️ Build System' },
        { type: 'ci', section: '👷 CI/CD Configuration' },
        { type: 'style', section: '💎 Styling' },
        { type: 'test', section: '🧪 Tests' },
        { type: 'docs', section: '📚 Dokumentation' },
        { type: 'chore', section: '🧹 Chore / Maintenance' },
    ],
});

/**
 * Da manche Versionen das Objekt in einem Promise verstecken und andere nicht,
 * stellen wir hier sicher, dass wir direkt auf die Properties zugreifen.
 */
const finalizeConfig = (obj) => {
    const target = obj.conventionalChangelog || obj;

    if (target.parserOpts) {
        target.parserOpts.headerPattern = /^(\w*)(?:\((.*)\))?: (.*)$/;
        target.parserOpts.headerCorrespondence = ['type', 'scope', 'subject'];
    }

    if (target.writerOpts) {
        target.writerOpts.commitGroupsSort = (a, b) => {
            const order = [
                '🚀 Features',
                '🐛 Bug Fixes',
                '⚡ Performance',
                '⚙️ Refactoring',
                '🏗️ Build System',
                '👷 CI/CD Configuration',
                '💎 Styling',
                '🧪 Tests',
                '📚 Dokumentation',
                '🧹 Chore / Maintenance',
            ];
            const idxA = order.indexOf(a.title);
            const idxB = order.indexOf(b.title);
            return (idxA > -1 ? idxA : 99) - (idxB > -1 ? idxB : 99);
        };
    }
    return obj;
};

// Falls config eine Promise ist, behandeln wir sie,
// falls nicht (dein aktueller Fehler), exportieren wir direkt.
if (config && typeof config.then === 'function') {
    module.exports = config.then((c) => finalizeConfig(c));
} else {
    module.exports = finalizeConfig(config);
}
