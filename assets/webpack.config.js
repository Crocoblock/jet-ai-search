var path = require('path');
var webpack = require('webpack');

module.exports = {
	name: 'admin',
	context: path.resolve( __dirname, 'src' ),
	entry: {
		'js/admin.js': 'admin.js',
	},
	output: {
		path: path.resolve( __dirname ),
		filename: '[name]'
	},
	resolve: {
		modules: [
			path.resolve( __dirname, 'src' ),
			'node_modules'
		],
		extensions: [ '.js' ],
		alias: {
			'@': path.resolve( __dirname, 'src' ),
		}
	},
	module: {
		rules: [
			{
				test: /\.js$/,
				loader: 'babel-loader',
				exclude: /node_modules/
			}
		]
	}
}
