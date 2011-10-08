<?php

//Theme "extension" (allows using themes of skins)

$wgExtensionCredits['other'][] = array(
	'name' => 'Theme',
	'description' => 'Theme loader extension for skins',
	'version' => '1.0',
	'author' => 'Ryan Schmidt',
);

$wgHooks['BeforePageDisplay'][] = 'efDisplayTheme';

function efDisplayTheme( &$out, &$sk ) {
	global $wgRequest, $wgStylePath, $wgStyleDirectory, $wgDefaultTheme, $wgValidSkinNames;
	$theme = $wgRequest->getVal( 'usetheme', false );
	$useskin = $wgRequest->getVal( 'useskin', false );
	$skin = $useskin ? $useskin : $sk->getSkinName();
	if( !array_key_exists( strtolower( $skin ), $wgValidSkinNames ) ) {
		$skin = $sk->getSkinName(); //so we don't load themes for skins when we can't actually load the skin
	}
	if( $theme ) {
		$url = $skin . '/themes/' . $theme . '.css';
	} elseif( isset( $wgDefaultTheme ) && $wgDefaultTheme != 'default' ) {
		$url = $skin . '/themes/' . $wgDefaultTheme . '.css';
	} else {
		$url = false;
	}
	if( !$url || !file_exists( $wgStyleDirectory . '/' . $url ) ) {
		return true;
	}
	$out->addExtensionStyle( $wgStylePath . '/' . $url );
	return true;
}