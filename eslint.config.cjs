const wpScriptsConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...wpScriptsConfig,
	{
		files: [ 'blocks/**/*.js' ],
		rules: {
			'jsdoc/require-returns-description': 'off',
			'jsdoc/require-param-type': 'off',
			'jsdoc/empty-tags': 'off',
			'no-console': 'off',
			'no-alert': 'off',
			'no-nested-ternary': 'off',
		},
	},
];
