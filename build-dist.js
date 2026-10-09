#!/usr/bin/env node

/**
 * Build Distribution Script for BloatBuster plugin
 *
 * This script creates a clean distribution package in the `build/` directory
 * by copying only production files and excluding development files.
 * Uses fast-glob for reliable pattern matching.
 *
 * Usage: npm run build-dist
 */

const fs = require( 'fs' );
const path = require( 'path' );
const fg = require( 'fast-glob' );

// Configuration
const PLUGIN_NAME = 'bloatbuster-performance-asset-cleaner';
const SOURCE_DIR = __dirname;
const BUILD_DIR = path.join( SOURCE_DIR, 'build', PLUGIN_NAME );

// Files and directories to include (production files only)
const INCLUDE_PATTERNS = [
	'**/*.php',
	'**/*.css',
	'**/*.txt',
	'languages/**',
];

// Files and directories to exclude (development files)
const EXCLUDE_PATTERNS = [
	// Build directory itself (must be first to prevent recursion)
	'build/**',

	// Ignore source maps
	'**/*.map',

	// Git files
	'.git/**',
	'.github/**',
	'.gitignore',

	// Source files
	'assets/source/**',

	// Webpack JS stub generated for SCSS-only entry
	'assets/admin.js',

	// Node dependencies
	'node_modules/**',

	// Development configuration
	'package.json',
	'package-lock.json',
	'webpack.config.js',
	'build-dist.js',
	'phpcs.xml',

	// GitHub readme, wp.org uses README.txt
	'README.md',

	// Editor and IDE files
	'.vscode/**',
	'*.log',
	'.htaccess',
	'*.esproj',
	'*.tmproj',
	'*.tmproject',
	'tmtags',
	'.*.sw[a-z]',
	'*.un~',
	'Session.vim',
	'*.swp',

	// OS files
	'.DS_Store',
	'._*',
	'.Spotlight-V100',
	'.Trashes',
	'Thumbs.db',
	'Desktop.ini',
];

/**
 * Build the distribution package.
 */
const buildDistribution = async () => {
	console.log( `🚀 Building distribution package for ${ PLUGIN_NAME }...\n` );

	try {
		// Clean build directory
		console.log( 'Cleaning build directory...' );
		await fs.promises.rm( BUILD_DIR, { recursive: true, force: true } );

		// Create fresh build directory
		await fs.promises.mkdir( BUILD_DIR, { recursive: true } );
		console.log( `Created build directory: ${ BUILD_DIR }\n` );

		// Get all files matching include patterns, excluding exclude patterns
		console.log( 'Scanning for production files...' );
		const files = await fg( INCLUDE_PATTERNS, {
			cwd: SOURCE_DIR,
			ignore: EXCLUDE_PATTERNS,
			dot: false,
			onlyFiles: true,
		} );

		console.log( `Found ${ files.length } files to copy\n` );

		for ( const file of files ) {
			const srcPath = path.join( SOURCE_DIR, file );
			const destPath = path.join( BUILD_DIR, file );

			await fs.promises.mkdir( path.dirname( destPath ), { recursive: true } );
			await fs.promises.copyFile( srcPath, destPath );
			console.log( `✓ Copied: ${ file }` );
		}

		// Summary
		console.log( '\n' + '='.repeat( 60 ) );
		console.log( '✅ Distribution package created successfully!' );
		console.log( '='.repeat( 60 ) );
		console.log( `📁 Location: ${ BUILD_DIR }` );
		console.log( `📊 Files copied: ${ files.length }` );

		console.log( '\n📋 Files included in distribution:' );
		console.log( '- Plugin PHP files (main file, includes/, uninstall.php)' );
		console.log( '- Compiled admin CSS from assets/ folder' );
		console.log( '- Translation template (languages/)' );
		console.log( '- Documentation (README.txt, LICENSE.txt)' );

		console.log( '\n🚫 Files excluded from distribution:' );
		console.log( '- Git files (.git/, .gitignore)' );
		console.log( '- Source files (assets/source/)' );
		console.log( '- Node.js dependencies (node_modules/)' );
		console.log( '- Development configuration files' );
		console.log( '- Build script itself (build-dist.js)' );

		console.log( '\n📦 Package is ready for WordPress.org submission!' );
	} catch ( error ) {
		console.error( '\n❌ Error building distribution package:' );
		console.error( error.message );
		console.error( error.stack );
		process.exit( 1 );
	}
};

// Run the script
buildDistribution();
