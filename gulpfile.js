/* eslint-disable */
/**
 * Gulp File
 *
 * 1) Make sure you have node and npm installed locally
 *
 * 2) Install all the modules from package.json:
 * $ npm install
 *
 * 3) Run `gulp package` to build a packaged version of the plugin.`
 */

var checktextdomain = require( 'gulp-checktextdomain' );
var del             = require( 'del' );
var exec            = require( 'child_process' ).exec;
var gulp            = require( 'gulp' );
var sort            = require( 'gulp-sort' );
var wpPot           = require( 'gulp-wp-pot' );
var zip             = require( 'gulp-zip' );
var process         = require( 'process' );
var env             = process.env;

var paths = {
	packageContents: [
		'src/**/*',
		'vendor/**/*',
		'lang/**/*',
		'sensei-enrollment-comparison-tool.php',
	],
	packageDir: 'build/sensei-enrollment-comparison-tool',
	packageZip: 'build/sensei-enrollment-comparison-tool.zip'
};

gulp.task( 'clean', gulp.series( function( cb ) {
	return del( [
		'build'
	], cb );
} ) );


gulp.task( 'pot', gulp.series( function() {
	return gulp.src( [ '**/**.php', '!node_modules/**', '!vendor/**', '!build/**' ] )
		.pipe( sort() )
		.pipe( wpPot( {
			domain: 'sensei-enrollment-comparison-tool',
		} ) )
		.pipe( gulp.dest( 'lang/sensei-enrollment-comparison-tool.pot' ) );
} ) );

gulp.task( 'textdomain', gulp.series( function() {
	return gulp.src( [ '**/*.php', '!node_modules/**', '!build/**' , '!vendor/**' ] )
		.pipe( checktextdomain( {
			text_domain: 'sensei-enrollment-comparison-tool',
			keywords: [
				'__:1,2d',
				'_e:1,2d',
				'_x:1,2c,3d',
				'esc_html__:1,2d',
				'esc_html_e:1,2d',
				'esc_html_x:1,2c,3d',
				'esc_attr__:1,2d',
				'esc_attr_e:1,2d',
				'esc_attr_x:1,2c,3d',
				'_ex:1,2c,3d',
				'_n:1,2,4d',
				'_nx:1,2,4c,5d',
				'_n_noop:1,2,3d',
				'_nx_noop:1,2,3c,4d'
			]
		} ) );
} ) );

gulp.task( 'prep-composer', gulp.series( function prepComposer( cb ) {
	var process = exec( 'composer install --no-dev', cb );
} ) );

gulp.task( 'post-composer', gulp.series( function prepComposer( cb ) {
	var process = exec( 'composer install', cb );
} ) );

gulp.task( 'build', gulp.series( 'clean', 'prep-composer' ) );

gulp.task( 'copy-package', function() {
	return gulp.src( paths.packageContents, { base: '.' } )
		.pipe( gulp.dest( paths.packageDir ) );
} );

gulp.task( 'zip-package', function() {
	return gulp.src( paths.packageDir + '/**/*', { base: paths.packageDir + '/..' } )
		.pipe( zip( paths.packageZip ) )
		.pipe( gulp.dest( '.' ) );
} );

gulp.task( 'package', gulp.series( 'build', 'copy-package', 'zip-package', 'post-composer' ) );
gulp.task( 'default', gulp.series( 'package' ) );
