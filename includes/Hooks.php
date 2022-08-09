<?php

namespace MediaWiki\Extension\Theme;

use Config;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\OutputPageBodyAttributesHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;
use MediaWiki\User\UserOptionsLookup;
use OutputPage;
use RequestContext;
use Sanitizer;
use Skin;
use User;

class Hooks implements
	BeforePageDisplayHook,
	GetPreferencesHook,
	OutputPageBodyAttributesHook,
	ResourceLoaderGetConfigVarsHook
{

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/**
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct(
		UserOptionsLookup $userOptionsLookup
	) {
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/**
	 * Add the JS needed to preview themes in real time onto the output
	 * on Special:Preferences and its global version, Special:GlobalPreferences.
	 *
	 * @param OutputPage $out
	 * @param Skin $sk
	 * @return void This hook must not abort, it must return no value
	 */
	public function onBeforePageDisplay( $out, $sk ): void {
		$this->addJSonPreferences( $out, $sk );

		$request = $out->getRequest();
		$config = $out->getConfig();

		// User's personal theme override, if any
		$theme = $this->userOptionsLookup->getOption( $out->getUser(), 'theme' );
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
			return;
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
	 * @param OutputPage $out
	 * @param Skin $sk
	 * @return void This hook must not abort, it must return no value
	 */
	private function addJSonPreferences( $out, $sk ): void {
		if (
			$out->getTitle()->isSpecial( 'Preferences' ) ||
			$out->getTitle()->isSpecial( 'GlobalPreferences' )
		) {
			// Only load this JS on Special:Preferences/Special:GlobalPreferences
			$out->addModules( 'ext.theme.livepreview' );
		}
	}

	/**
	 * Add the theme selector to user preferences.
	 *
	 * @param User $user
	 * @param array &$defaultPreferences
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onGetPreferences( $user, &$defaultPreferences ) {
		$ctx = RequestContext::getMain();
		$skin = $ctx->getRequest()->getRawVal( 'useskin' ) ??
			$this->userOptionsLookup->getOption( $user, 'skin' );
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
		$defaultTheme = $this->userOptionsLookup->getOption( $user, 'theme', $defaultTheme );
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
	 * @return void This hook must not abort, it must return no value
	 */
	public function onOutputPageBodyAttributes( $out, $sk, &$bodyAttrs ): void {
		// Check the following things in this order:
		// 1) value of $wgDefaultTheme (set in site configuration)
		// 2) user's personal preference/override
		// 3) per-page usetheme URL parameter
		$theme = $out->getConfig()->get( 'DefaultTheme' );
		$theme = $this->userOptionsLookup->getOption( $out->getUser(), 'theme', $theme );
		$theme = $out->getRequest()->getRawVal( 'usetheme', $theme );

		// Paranoia
		$theme = strtolower( $theme );

		$resourceLoader = $out->getResourceLoader();
		if ( !Theme::skinHasTheme( $sk->getSkinName(), $theme, $resourceLoader ) ) {
			return;
		}

		if ( $theme !== 'default' ) {
			$bodyAttrs['class'] .= ' theme-' . Sanitizer::escapeClass( $theme );
		}
	}

	/**
	 * Expose the value of $wgDefaultTheme as a JavaScript globals so that site/user
	 * JS can use mw.config.get( 'wgDefaultTheme' ) to read its value.
	 *
	 * @param array &$vars Pre-existing JavaScript global variables
	 * @param string $skin
	 * @param Config $config
	 * @return void This hook must not abort, it must return no value
	 */
	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		$vars['wgDefaultTheme'] = $config->get( 'DefaultTheme' );
	}

}
