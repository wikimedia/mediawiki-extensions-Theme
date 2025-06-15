/* eslint-env node, es6 */
module.exports = function ( grunt ) {
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-eslint' );
	grunt.loadNpmTasks( 'grunt-stylelint' );

	grunt.initConfig( {
		banana: {
			all: 'i18n/'
		},
		eslint: {
			options: {
				cache: true
			},
			all: [
				'**/*.{js,json}',
				'!{vendor,node_modules}/**'
			]
		},
		stylelint: {
			options: {
				cache: true
			},
			all: [
				'**/*.{css,less}',
				'!{vendor,node_modules}/**'
			]
		}
	} );

	grunt.registerTask( 'test', [ 'eslint', 'stylelint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
