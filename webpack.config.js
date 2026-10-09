const webpack = require( 'webpack' );
const path = require( 'path' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );

const config = {
	entry: {
		admin: [
			'./assets/source/sass/admin/bloatbuster-performance-asset-cleaner-admin.scss'
		]
	},
	output: {
		path: path.resolve(
			__dirname,
			'assets'
		),
		filename: '[name].js'
	},
	module: {
		rules: [
			{
				test: /\.js$/,
				use: 'babel-loader',
				exclude: /node_modules/
			},
			{
				test: /\.scss$/,
				use: [
					MiniCssExtractPlugin.loader,
					'css-loader',
					'sass-loader'
				]
			}
		]
	},
	plugins: [
		new MiniCssExtractPlugin()
	]
};

module.exports = config;