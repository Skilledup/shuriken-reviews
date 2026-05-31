/**
 * Custom webpack configuration for the Shuriken Reviews block editor scripts.
 *
 * Extends the default @wordpress/scripts config with one entry point per
 * block plus the two shared modules. All @wordpress/* imports are externalised
 * to the wp.* runtime globals by DependencyExtractionWebpackPlugin, and an
 * index.asset.php file is emitted alongside each bundle.
 */
const path = require('path');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
    ...defaultConfig,
    entry: {
        'shuriken-rating/index': path.resolve(__dirname, 'blocks/shuriken-rating/index.js'),
        'shuriken-grouped-rating/index': path.resolve(__dirname, 'blocks/shuriken-grouped-rating/index.js'),
        'shuriken-query-sort/index': path.resolve(__dirname, 'blocks/shuriken-query-sort/index.js'),
        'shuriken-post-sidebar/index': path.resolve(__dirname, 'blocks/shuriken-post-sidebar/index.js'),
        'shared/ratings-store': path.resolve(__dirname, 'blocks/shared/ratings-store.js'),
        'shared/block-helpers': path.resolve(__dirname, 'blocks/shared/block-helpers.js'),
    },
    output: {
        ...defaultConfig.output,
        path: path.resolve(__dirname, 'build'),
        filename: '[name].js',
    },
};
