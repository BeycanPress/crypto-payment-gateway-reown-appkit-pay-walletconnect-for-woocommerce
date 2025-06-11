import gulp from 'gulp'
import ts from 'gulp-typescript'
import uglify from 'gulp-uglify'
import rename from 'gulp-rename'

const tsProject = ts.createProject('tsconfig.json')

gulp.task('typescript', function () {
    return gulp
        .src('./src/ts/**/*.ts')
        .pipe(tsProject())
        .pipe(uglify())
        .pipe(
            rename((path) => {
                path.basename += '.min'
            })
        )
        .pipe(gulp.dest('./assets/js'))
})

gulp.task('watch', function () {
    gulp.watch('./src/ts/**/*.ts', gulp.series('typescript'))
})

gulp.task('default', gulp.series('typescript', 'watch'))
