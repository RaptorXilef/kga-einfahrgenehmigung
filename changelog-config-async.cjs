/**
 * @file changelog-config.cjs
 */
module.exports = (async () => {
    const conventionalCommits =
        await import('conventional-changelog-conventionalcommits');
    const createPreset = conventionalCommits.default || conventionalCommits;

    const config = await createPreset({
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

    // WICHTIG: Bei conventionalcommits liegen die Optionen oft in config.conventionalChangelog
    const target = config.conventionalChangelog || config;

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

    return config;
})();
