<?php

use MediaWiki\MediaWikiServices;

class ThemeHooks {

	/**
	 * @param OutputPage &$out
	 * @param Skin &$sk
	 * @return bool
	 */
	public static function onBeforePageDisplay( &$out, &$sk ) {
		$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();
		$request = $out->getRequest();
		$config = $out->getConfig();

		// User's personal theme override, if any
		$theme = $userOptionsLookup->getOption( $out->getUser(), 'theme' );
		$theme = $request->getRawVal( 'usetheme', $theme );
		$skin = $request->getRawVal( 'useskin' );

		if ( $skin === null ||
			!array_key_exists( strtolower( $skin ), $config->get( 'ValidSkinNames' ) )
		) {
			// so we don't load themes for skins when we can't actually load the skin
			$skin = $sk->getSkinName();
		}
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullableInternal
		$skin = strtolower( $skin );

		if ( $theme ) {
			$themeName = $theme;
		} elseif ( $config->has( 'DefaultTheme' ) &&
			$config->get( 'DefaultTheme' ) !== 'default'
		) {
			$themeName = $config->get( 'DefaultTheme' );
		} else {
			$themeName = false;
		}

		// Check that we have something to include later on; if not, bail out
		$resourceLoader = $out->getResourceLoader();
		if ( !$themeName || !Theme::skinHasTheme( $skin, $themeName, $resourceLoader ) ) {
			return true;
		}

		$prefix = ( $skin !== 'monaco' ? 'themeloader.' : '' );
		$moduleName = $prefix . 'skins.' . $skin . '.' .
			strtolower( $themeName );

		// Add the CSS file via ResourceLoader.
		$out->addModuleStyles( $moduleName );
	}

	/**
	 * Add the JS needed to preview themes in real time onto the output
	 * on Special:Preferences and its global version, Special:GlobalPreferences.
	 *
	 * @param OutputPage &$out
	 * @param Skin &$sk
	 */
	public static function addJSonPreferences( &$out, &$sk ) {
		if (
			$out->getTitle()->isSpecial( 'Preferences' ) ||
			$out->getTitle()->isSpecial( 'GlobalPreferences' )
		) {
			// Only load this JS on Special:Preferences/Special:GlobalPreferences
			$out->addModules( 'ext.theme.livepreview' );
			// Stupid CSS hack
			$out->addModuleStyles( 'ext.theme.preferences' );
		}
	}

	/**
	 * Add the theme selector to user preferences.
	 *
	 * @param User $user
	 * @param array &$defaultPreferences
	 */
	public static function onGetPreferences( $user, &$defaultPreferences ) {
		$ctx = RequestContext::getMain();
		$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();
		$skin = $ctx->getRequest()->getRawVal( 'useskin' ) ??
			$userOptionsLookup->getOption( $user, 'skin' );
		// Normalize the key; this'll return the default skin in case if the user
		// requested a skin that is *not* installed but for which Theme has themes
		$skin = Skin::normalizeKey( $skin );

		$themes = Theme::getAvailableThemes( $skin );
		// Braindead code needed to make the theme *names* show up
		// Without this they show up as "0", "1", etc. in the UI
		$themeArray = [];
		foreach ( $themes as $theme ) {
			$themeDisplayNameMsg = $ctx->msg( 'theme-name-' . $skin . '-' . $theme );
			if ( $themeDisplayNameMsg->isDisabled() ) {
				// No i18n available for this -> use the key as-is
				$themeDisplayName = $theme;
			} else {
				// Use i18n; it's much nicer to display formatted theme names if and when
				// a theme name contains spaces, uppercase characters, etc.
				$themeDisplayName = $themeDisplayNameMsg->escaped();
			}
			$themeArray[$themeDisplayName] = $theme;
		}

		$defaultTheme = $ctx->getConfig()->get( 'DefaultTheme' );
		$defaultTheme = $userOptionsLookup->getOption( $user, 'theme', $defaultTheme );
		if ( count( $themes ) > 1 ) {
			$defaultPreferences['theme'] = [
				'type' => 'select',
				'options' => $themeArray,
				'default' => $defaultTheme,
				'label-message' => 'theme-prefs-label',
				'section' => 'rendering/skin',
			];
		} else {
			// If a skin has no themes (besides "default"),
			// show only an informative message instead
			$defaultPreferences['theme'] = [
				'type' => 'info',
				'label-message' => 'theme-prefs-label',
				'default' => $ctx->msg( 'theme-unsupported-skin' )->text(),
				'section' => 'rendering/skin',
			];
		}
	}

	/**
	 * Add theme-<theme name> class to the <body> element to allow per-theme
	 * styling on on-wiki CSS pages, such as MediaWiki:Vector.css.
	 * The class is added only for non-default themes.
	 *
	 * @param OutputPage $out
	 * @param Skin $sk
	 * @param array &$bodyAttrs Existing attributes of the <body> tag as an array
	 * @return void|bool Void normally, bool true if $sk(in) has no requested theme
	 */
	public static function onOutputPageBodyAttributes( $out, $sk, &$bodyAttrs ) {
		$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();

		// Check the following things in this order:
		// 1) value of $wgDefaultTheme (set in site configuration)
		// 2) user's personal preference/override
		// 3) per-page usetheme URL parameter
		$theme = $out->getConfig()->get( 'DefaultTheme' );
		$theme = $userOptionsLookup->getOption( $out->getUser(), 'theme', $theme );
		$theme = $out->getRequest()->getRawVal( 'usetheme', $theme );

		$theme = strtolower( htmlspecialchars( $theme ) ); // paranoia

		$resourceLoader = $out->getResourceLoader();
		if ( !Theme::skinHasTheme( $sk->getSkinName(), $theme, $resourceLoader ) ) {
			return true;
		}

		if ( $theme !== 'default' ) {
			$bodyAttrs['class'] .= ' theme-' . $theme;
		}
	}

	/**
	 * Expose the value of $wgDefaultTheme as a JavaScript globals so that site/user
	 * JS can use mw.config.get( 'wgDefaultTheme' ) to read its value.
	 *
	 * @param array &$vars Pre-existing JavaScript global variables
	 * @param string $skin
	 * @param Config $config
	 */
	public static function onResourceLoaderGetConfigVars( &$vars, $skin, Config $config ) {
		$vars['wgDefaultTheme'] = $config->get( 'DefaultTheme' );
	}

}
