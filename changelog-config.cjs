/**
 * @file changelog-config.cjs
 */
module.exports = {
    // 1. Parser-Optionen: Wie werden Commits gelesen?
    parserOpts: {
        headerPattern: /^(\w*)(?:\((.*)\))?: (.*)$/,
        headerCorrespondence: ['type', 'scope', 'subject'],
    },

    // 2. Writer-Optionen: Wie wird das Markdown gebaut?
    writerOpts: {
        // Diese Funktion entscheidet, welche Commits ins Changelog kommen und wie sie aussehen
        transform: (commit) => {
            const typeMap = {
                feat: '🚀 Features',
                fix: '🐛 Bug Fixes',
                perf: '⚡ Performance',
                refactor: '⚙️ Refactoring',
                build: '🏗️ Build System',
                ci: '👷 CI/CD Configuration',
                style: '💎 Styling',
                test: '🧪 Tests',
                docs: '📚 Dokumentation',
                chore: '🧹 Chore / Maintenance',
            };

            const clonedCommit = { ...commit };

            // Mapping des Typs auf deine Emojis
            if (clonedCommit.type && typeMap[clonedCommit.type.toLowerCase()]) {
                clonedCommit.type = typeMap[clonedCommit.type.toLowerCase()];
            } else {
                // Unbekannte Typen oder Typen ohne Mapping werden ignoriert
                return null;
            }

            if (typeof clonedCommit.hash === 'string') {
                clonedCommit.shortHash = clonedCommit.hash.substring(0, 7);
            }

            return clonedCommit;
        },

        // Gruppierung und Sortierung
        commitGroupsSort: (a, b) => {
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
        },

        // WICHTIG: Standard-Sortierung innerhalb der Gruppen (nach Subject)
        commitsSort: ['scope', 'subject'],
    },
};
