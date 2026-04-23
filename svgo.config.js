/**
 * @file svgo.config.js
 * @description Optimiert SVGs.
 */
export default {
    multipass: true,
    plugins: [
        {
            name: 'preset-default',
            params: {
                overrides: {
                    removeViewBox: false, // Wichtig für Skalierbarkeit
                    cleanupIds: false,
                },
            },
        },
        'removeDimensions',
        'sortAttrs',
    ],
};
