<?php

namespace MediaWiki\Extension\Theme;

use ExtensionRegistry;
use ResourceLoader;

/**
 * Utility/helper class.
 *
 * @file
 * @date 16 June 2017
 */
class Theme {

	/**
	 * Get all the themes the given skin has.
	 *
	 * @param string $ourSkin Skin name, e.g. aurora, monobook, vector, etc.
	 * @return array Array containing theme names for the skin or 'default' if
	 *               the skin has no custom themes available
	 */
	public static function getAvailableThemes( $ourSkin ) {
		// Paranoia
		$ourSkin = strtolower( $ourSkin );

		$themes = ExtensionRegistry::getInstance()->getAttribute( 'ThemeModules' );

		if ( !isset( $themes[$ourSkin] ) ) {
			$themes[$ourSkin] = [];
		}

		// Ensure that 'default' is always the 1st array item
		array_unshift( $themes[$ourSkin], 'default' );

		return !empty( $themes[$ourSkin] ) ? $themes[$ourSkin] : [ 'default' => 'default' ];
	}

	/**
	 * Check if the given skin has a theme named $theme.
	 *
	 * @param string $skin Skin name, e.g. aurora, monobook, vector, etc.
	 * @param string $theme Theme name, e.g. dark, pink, etc.
	 * @param ResourceLoader &$resourceLoader Reference to ResourceLoader
	 * @return bool
	 */
	public static function skinHasTheme( $skin, $theme, &$resourceLoader ) {
		$skin = strtolower( $skin );
		$theme = strtolower( $theme );

		// Special case, all skins have a default theme since default means
		// "the skin without any custom styling"
		if ( $theme === 'default' ) {
			return true;
		}

		$moduleName = 'themeloader.skins.' . $skin . '.' . $theme;
		if ( $skin === 'monaco' ) {
			$moduleName = 'skins.' . $skin . '.' . $theme;
		}

		if ( $resourceLoader->isModuleRegistered( $moduleName ) && $resourceLoader->getModule( $moduleName ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get skins with themes.
	 *
	 * @param array $skins Array with all skin names
	 * @return array Array containing skin names which supports themes
	 */
	public static function getSkinsWithThemes( $skins ) {
		$skinsWithThemes = [];
		$themes = ExtensionRegistry::getInstance()->getAttribute( 'ThemeModules' );

		foreach ( $skins as $skin ) {
			if ( isset( $themes[$skin] ) ) {
				$skinsWithThemes[] = $skin;
			}
		}

		return $skinsWithThemes;
	}

}
