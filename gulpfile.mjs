import gulp from 'gulp'
import esbuild from 'gulp-esbuild'
import plumber from 'gulp-plumber'

gulp.task('typescript', function () {
    return gulp
        .src('./src/ts/**/*.ts')
        .pipe(plumber())
        .pipe(
            esbuild({
                bundle: true,
                minify: true,
                target: 'es2020',
                platform: 'browser',
                outfile: 'main.min.js'
            })
        )
        .pipe(gulp.dest('./assets/js'))
})

gulp.task('watch', function () {
    gulp.watch('./src/ts/**/*.ts', gulp.series('typescript'))
})

gulp.task('default', gulp.series('typescript', 'watch'))
