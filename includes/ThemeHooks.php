<?php

class ThemeHooks {

	/**
	 * @param OutputPage &$out
	 * @param Skin &$sk
	 * @return bool
	 */
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
		global $wgDefaultTheme;

		$ctx = RequestContext::getMain();
		$useskin = $ctx->getRequest()->getVal( 'useskin', false );
		$skin = $useskin ? $useskin : $user->getOption( 'skin' );
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
				'label-message' => 'theme-prefs-label',
				'default' => $ctx->msg( 'theme-unsupported-skin' )->escaped(),
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
		global $wgDefaultTheme;

		// Check the following things in this order:
		// 1) value of $wgDefaultTheme (set in site configuration)
		// 2) user's personal preference/override
		// 3) per-page usetheme URL parameter
		$userTheme = $wgDefaultTheme;
		// User's personal theme override, if any
		$user = $out->getUser();
		if ( $user->getOption( 'theme' ) ) {
			$userTheme = $user->getOption( 'theme' );
		}
		$theme = $out->getRequest()->getVal( 'usetheme', $userTheme );
		$theme = strtolower( htmlspecialchars( $theme ) ); // paranoia

		if ( !Theme::skinHasTheme( $sk->getSkinName(), $theme ) ) {
			return true;
		}

		if ( isset( $theme ) && $theme !== 'default' ) {
			$bodyAttrs['class'] .= ' theme-' . $theme;
		}
	}

	/**
	 * Expose the value of $wgDefaultTheme as a JavaScript globals so that site/user
	 * JS can use mw.config.get( 'wgDefaultTheme' ) to read its value.
	 *
	 * @param array &$vars Pre-existing JavaScript global variables
	 */
	public static function onResourceLoaderGetConfigVars( &$vars ) {
		global $wgDefaultTheme;
		$vars['wgDefaultTheme'] = $wgDefaultTheme;
	}

}
