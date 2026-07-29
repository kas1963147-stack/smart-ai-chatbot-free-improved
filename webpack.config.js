const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
	...defaultConfig,
	resolve: {
		...defaultConfig.resolve,
		extensions: ['.js', '.jsx', '.ts', '.tsx', ...(defaultConfig.resolve ? (defaultConfig.resolve.extensions || []) : [])],
	},
};
