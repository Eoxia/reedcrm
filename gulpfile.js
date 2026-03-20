'use strict';

const gulp       = require('gulp');
const sass       = require('gulp-sass')(require('sass'));
const rename     = require('gulp-rename');
const uglify     = require('gulp-uglify');
const concat     = require('gulp-concat');
const cleanCSS   = require('gulp-clean-css');
const sourcemaps = require('gulp-sourcemaps');

const paths = {
	scss_core:  ['css/scss/**/*.scss', 'css/'],
	js_backend: ['js/reedcrm.js', 'js/modules/*.js']
};

/** SCSS — dev : sourcemaps inline, pas de minification */
gulp.task('scss_core', function() {
	return gulp.src(paths.scss_core[0])
		.pipe(sourcemaps.init())
		.pipe(sass().on('error', sass.logError))
		.pipe(rename('./reedcrm.min.css'))
		.pipe(sourcemaps.write('.'))
		.pipe(gulp.dest(paths.scss_core[1]));
});

/** SCSS — prod : clean-css, pas de sourcemaps */
gulp.task('scss_core:prod', function() {
	return gulp.src(paths.scss_core[0])
		.pipe(sass().on('error', sass.logError))
		.pipe(cleanCSS())
		.pipe(rename('./reedcrm.min.css'))
		.pipe(gulp.dest(paths.scss_core[1]));
});

/** JS — concat + uglify (dev et prod) */
gulp.task('js_backend', function() {
	return gulp.src(paths.js_backend)
		.pipe(concat('reedcrm.min.js'))
		.pipe(uglify())
		.pipe(gulp.dest('./js/'));
});

/** Build prod — one-shot, minifié, sans sourcemaps */
gulp.task('build', gulp.parallel('scss_core:prod', 'js_backend'));

/** Watch dev — compile initial puis surveille reedcrm.js + modules */
gulp.task('watch', function() {
	gulp.watch(paths.scss_core[0], gulp.series('scss_core'));
	gulp.watch(paths.js_backend,   gulp.series('js_backend'));
});

/** Default = build dev initial + watch */
gulp.task('default', gulp.series(
	gulp.parallel('scss_core', 'js_backend'),
	'watch'
));
