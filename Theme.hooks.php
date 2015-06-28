<?php

class ThemeHooks {

	public static function onBeforePageDisplay( &$out, &$sk ) {
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
}