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

if ( !defined( 'MEDIAWIKI' ) ) {
	die();
}

// Extension credits that will show up on Special:Version
$wgExtensionCredits['other'][] = array(
	'name' => 'Theme',
	'namemsg' => 'extensionname-theme',
	'version' => '1.7.1',
	'author' => array( 'Ryan Schmidt', 'Jack Phoenix' ),
	'descriptionmsg' => 'theme-desc',
	'url' => 'https://www.mediawiki.org/wiki/Extension:Theme'
);

$wgMessagesDirs['Theme'] =  __DIR__ . '/i18n';

// For ShoutWiki, $wgDefaultTheme is set in GlobalSettings.php to 'default'
// For a non-ShoutWiki site where you want to use this extension, you should
// set $wgDefaultTheme to the name of one of the available themes for your
// $wgDefaultSkin

// Register themes for core skins here; for custom skins, do the registration
// in the custom skin's Skinname.php file
//
// Monobook
/**
 * XXX FILTHY HACK ALERT!
 * The 'themeloader.' prefix is needed to work around a dumb MediaWiki core bug
 * introduced in MW 1.23. Without a prefix of any kind (i.e. if we have
 * 'skins.monobook.foobar'), the modules are loaded *before* the skin's core
 * CSS, and hence shit hits the fan.
 * Same goes if the prefix sorts *above* 'skins' in the alphabet (hence why we
 * can't use 'shoutwiki.' as the prefix, for example).
 * Apparently™ this "never should have worked" and it only worked "due to dumb luck",
 * despite working consistently from MW 1.16 to 1.22.
 * I honestly have no idea anymore.
 *
 * @see https://bugzilla.wikimedia.org/show_bug.cgi?id=45229
 * @see https://bugzilla.wikimedia.org/show_bug.cgi?id=66508
 */
$wgResourceModules['themeloader.skins.monobook.dark'] = array(
	'styles' => array(
		'extensions/Theme/monobook/dark.css' => array( 'media' => 'screen' )
	)
);

$wgResourceModules['themeloader.skins.monobook.pink'] = array(
	'styles' => array(
		'extensions/Theme/monobook/pink.css' => array( 'media' => 'screen' )
	)
);

$wgResourceModules['themeloader.skins.monobook.stellarbook'] = array(
	'styles' => array(
		'extensions/Theme/monobook/stellarbook.css' => array( 'media' => 'screen' )
	)
);

// Vector
$wgResourceModules['themeloader.skins.vector.dark'] = array(
	'styles' => array(
		'extensions/Theme/vector/dark.css' => array( 'media' => 'screen' )
	)
);

// Actual extension logic begins here
$wgHooks['BeforePageDisplay'][] = 'wfDisplayTheme';

function wfDisplayTheme( &$out, &$sk ) {
	global $wgRequest, $wgDefaultTheme, $wgValidSkinNames;
	global $wgResourceModules;

	$theme = $wgRequest->getVal( 'usetheme', false );
	$useskin = $wgRequest->getVal( 'useskin', false );
	$skin = $useskin ? $useskin : $sk->getSkinName();

	if ( !array_key_exists( strtolower( $skin ), $wgValidSkinNames ) ) {
		// so we don't load themes for skins when we can't actually load the skin
		$skin = $sk->getSkinName();
	}

	// Monaco is a special case, since it handles its themes in its main PHP
	// file instead of leaving theme handling to us (ShoutWiki bug #173)
	if ( strtolower( $skin ) == 'monaco' ) {
		return true;
	}

	if ( $theme ) {
		$themeName = $theme;
	} elseif ( isset( $wgDefaultTheme ) && $wgDefaultTheme != 'default' ) {
		$themeName = $wgDefaultTheme;
	} else {
		$themeName = false;
	}

	$moduleName = 'themeloader.skins.' . strtolower( $skin ) . '.' .
		strtolower( $themeName );

	// Check that we have something to include later on; if not, bail out
	if ( !$themeName || !isset( $wgResourceModules[$moduleName] ) ) {
		return true;
	}

	// Add the CSS file via ResourceLoader.
	$out->addModuleStyles( $moduleName );

	return true;
}