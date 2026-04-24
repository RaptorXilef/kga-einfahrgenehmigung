// changelog-config.js
'use strict';

module.exports = {
    parserOpts: {
        headerPattern: /^(\w*)(?:\((.*)\))?: (.*)$/,
        headerCorrespondence: ['type', 'scope', 'subject'],
    },
    writerOpts: {
        // Hier definieren wir die Reihenfolge der Sektionen im Changelog
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
                '⚡ Performance',
            ];
            return order.indexOf(a.title) - order.indexOf(b.title);
        },
        commitsSort: ['scope', 'subject'],
        transform: (commit, context) => {
            let newCommit = { ...commit };

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
                perf: '⚡ Performance',
            };

            if (!newCommit.type) return;

            const typeKey = newCommit.type.toLowerCase();

            if (typeMap[typeKey]) {
                newCommit.type = typeMap[typeKey];
            } else {
                // Verhindert, dass unbekannte Typen das Changelog überladen.
                // Prevents unknown types from cluttering the changelog.
                return;
            }

            // Hash kürzen für die Anzeige
            if (typeof newCommit.hash === 'string') {
                newCommit.shortHash = newCommit.hash.substring(0, 7);
            }

            // Scope-Formatierung (falls vorhanden)
            if (newCommit.scope === '*') {
                newCommit.scope = '';
            }

            return newCommit;
        },
    },
};
