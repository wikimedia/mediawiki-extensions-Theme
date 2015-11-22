<?php
/**
 * Theme "extension" (allows using themes of skins)
 *
 * @file
 * @ingroup Extensions
 * @version 1.7.1
 * @author Ryan Schmidt <skizzerz at gmail dot com>
 * @author Jack Phoenix <jack@countervandalism.net>
 * @license https://en.wikipedia.org/wiki/Public_domain Public domain
 * @link https://www.mediawiki.org/wiki/Extension:Theme Documentation
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'Theme' );
	$wgMessagesDirs['Theme'] =  __DIR__ . '/i18n';
	/* wfWarn(
		'Deprecated PHP entry point used for Theme extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the Theme extension requires MediaWiki 1.25+' );
}
