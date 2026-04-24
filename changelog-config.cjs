module.exports = {
    parserOpts: {
        headerPattern: /^(\w*)(?:\((.*)\))?: (.*)$/,
        headerCorrespondence: ['type', 'scope', 'subject'],
    },
    writerOpts: {
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
            return order.indexOf(a.title) - order.indexOf(b.title);
        },
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
            if (!commit.type || !typeMap[commit.type.toLowerCase()])
                return null;
            commit.type = typeMap[commit.type.toLowerCase()];
            if (typeof commit.hash === 'string')
                commit.shortHash = commit.hash.substring(0, 7);
            return commit;
        },
    },
};
