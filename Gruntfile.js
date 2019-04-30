module.exports = function(grunt) {
	require('jit-grunt')(grunt);
	
	grunt.initConfig({
		less: {
			development: {
				options: {
					compress: true,
					yuicompress: true,
					optimization: 2
				},
				files: {
					'assets/css/publish.entry_relationship_field.css': 'assets/css/lib/*.less'
				}
			}
		},
		concat: {
			options: {
				separator: '\n'
			},
			dist: {
				src: 'assets/js/lib/*.js',
				dest: 'assets/js/publish.entry_relationship_field.js'
			}
		},
		watch: {
			styles: {
				files: ['**/*.less'],
				tasks: ['less'],
				options: {
					nospawn: true
				}
			},
			scripts: {
				files: ['**/*.js'],
				tasks: ['concat'],
				options: {
					nospawn: true
				}
			}
		}
	});
	
	grunt.registerTask('default', ['less', 'concat', 'watch']);
};
