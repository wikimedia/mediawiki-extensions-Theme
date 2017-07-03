<?php

class ThemeHooks {

	public static function onBeforePageDisplay( &$out, &$sk ) {
		global $wgRequest, $wgDefaultTheme, $wgValidSkinNames;

		$userTheme = false;
		// User's personal theme override, if any
		$user = $out->getUser();
		if ( $user->getOption( 'theme' ) ) {
			$userTheme = $user->getOption( 'theme' );
		}

		$theme = $wgRequest->getVal( 'usetheme', $userTheme );
		$useskin = $wgRequest->getVal( 'useskin', false );
		$skin = $useskin ? $useskin : $sk->getSkinName();

		if ( !array_key_exists( strtolower( $skin ), $wgValidSkinNames ) ) {
			// so we don't load themes for skins when we can't actually load the skin
			$skin = $sk->getSkinName();
		}
		$skin = strtolower( $skin );

		if ( $theme ) {
			$themeName = $theme;
		} elseif ( isset( $wgDefaultTheme ) && $wgDefaultTheme != 'default' ) {
			$themeName = $wgDefaultTheme;
		} else {
			$themeName = false;
		}

		// Check that we have something to include later on; if not, bail out
		if ( !$themeName || !Theme::skinHasTheme( $skin, $themeName ) ) {
			return true;
		}

		$prefix = ( $skin !== 'monaco' ? 'themeloader.' : '' );
		$moduleName = $prefix . 'skins.' . $skin . '.' .
			strtolower( $themeName );

		// Add the CSS file via ResourceLoader.
		$out->addModuleStyles( $moduleName );

		return true;
	}

	/**
	 * Add the JS needed to preview themes in real time onto the output
	 * on Special:Preferences and its global version, Special:GlobalPreferences.
	 *
	 * @param OutputPage $out
	 * @param Skin $sk
	 * @return bool
	 */
	public static function addJSonPreferences( &$out, &$sk ) {
		if (
			$out->getTitle()->isSpecial( 'Preferences' ) ||
			$out->getTitle()->isSpecial( 'GlobalPreferences' )
		) {
			// Only load this JS on Special:Preferences/Special:GlobalPreferences
			$out->addModules( 'ext.theme.livepreview' );
		}

		return true;
	}

	/**
	 * Add the theme selector to user preferences.
	 *
	 * @param User $user
	 * @param array $defaultPreferences
	 * @return bool
	 */
	public static function onGetPreferences( $user, &$defaultPreferences ) {
		global $wgDefaultTheme;

		$ctx = RequestContext::getMain();
		$useskin = $ctx->getRequest()->getVal( 'useskin', false );
		$skin = $useskin ? $useskin : $user->getOption( 'skin' );

		$themes = Theme::getAvailableThemes( $skin );
		// Braindead code needed to make the theme *names* show up
		// Without this they show up as "0", "1", etc. in the UI
		$themeArray = [];
		foreach ( $themes as $theme ) {
			$themeArray[$theme] = $theme;
		}

		if ( count( $themes ) > 1 ) {
			$defaultPreferences['theme'] = [
				'type' => 'select',
				'options' => $themeArray,
				'default' => $user->getOption( 'theme', $wgDefaultTheme ),
				'label-message' => 'theme-prefs-label',
				'section' => 'rendering/skin',
			];
		} else {
			// If a skin has no themes (besides "default"),
			// show only an informative message instead
			$defaultPreferences['theme'] = [
				'type' => 'info',
				'raw' => true,
				'label-message' => 'theme-prefs-label',
				'default' => $ctx->msg( 'theme-unsupported-skin' )->text(),
				'section' => 'rendering/skin',
			];
		}

		return true;
	}

}