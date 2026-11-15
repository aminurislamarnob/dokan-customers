const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );
const entryPoints = require( './webpack-entries' );
const {
	requestToExternal,
	requestToHandle,
} = require( './webpack-dependency-mapping' );

const isProduction = process.env.NODE_ENV === 'production';

const updatedConfig = {
	...defaultConfig,
	entry: {
		...entryPoints,
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, './assets/js' ),
		clean: isProduction,
	},
	plugins: [
		/**
		 * Multiple instances of the plugin dependency extractor are not supported.
		 *
		 * @see https://developer.wordpress.org/block-editor/reference-guides/packages/packages-dependency-extraction-webpack-plugin/#webpack
		 */
		...defaultConfig.plugins.filter(
			( plugin ) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new DependencyExtractionWebpackPlugin( {
			requestToExternal,
			requestToHandle,
		} ),
	],
};

module.exports = updatedConfig;
