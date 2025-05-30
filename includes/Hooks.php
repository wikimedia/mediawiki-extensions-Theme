<?php

namespace MediaWiki\Extension\Theme;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Output\Hook\OutputPageBodyAttributesHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use Skin;
use SkinFactory;

class Hooks implements
	BeforePageDisplayHook,
	GetPreferencesHook,
	OutputPageBodyAttributesHook,
	ResourceLoaderGetConfigVarsHook
{
	private SkinFactory $skinFactory;
	private UserOptionsLookup $userOptionsLookup;

	/**
	 * @param SkinFactory $skinFactory
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct(
		SkinFactory $skinFactory,
		UserOptionsLookup $userOptionsLookup
	) {
		$this->skinFactory = $skinFactory;
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

		$theme = $this->getTheme( $out );

		if ( $theme === 'default' ) {
			return;
		}

		$skin = strtolower( $sk->getSkinName() );
		$prefix = $skin !== 'monaco' ? 'themeloader.' : '';
		$moduleName = $prefix . "skins.$skin.$theme";
		$script = $out->getResourceLoader()->getLoadScript( 'local' );

		if (
			$out->getTitle()->isSpecial( 'Preferences' ) ||
			$out->getTitle()->isSpecial( 'GlobalPreferences' )
		) {
			// Add the CSS file with a <link> element.
			// This allows to remove the theme style on live preview.
			$out->addLink( [
				'id' => 'mw-themeloader-module',
				'data-theme' => $theme,
				'rel' => 'stylesheet',
				'href' => wfAppendQuery( $script, [
					'modules' => $moduleName,
					'only' => 'styles',
					'skin' => $skin
				] )
			] );
		} else {
			// Add the CSS file via ResourceLoader.
			$out->addModuleStyles( $moduleName );
		}
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
		$skin = $ctx->getSkin()->getSkinName();

		$themes = Theme::getAvailableThemes( $skin );
		// Braindead code needed to make the theme *names* show up
		// Without this they show up as "0", "1", etc. in the UI
		$themeArray = [];
		foreach ( $themes as $theme ) {
			$themeDisplayNameMsg = $ctx->msg( "theme-name-$skin-$theme" );
			if ( $themeDisplayNameMsg->isDisabled() ) {
				// No i18n available for this -> use the key as-is
				$themeDisplayName = $theme;
			} else {
				// Use i18n; it's much nicer to display formatted theme names if and when
				// a theme name contains spaces, uppercase characters, etc.
				$themeDisplayName = $themeDisplayNameMsg->text();
			}
			$themeArray[$themeDisplayName] = $theme;
		}

		$skinsWithThemes = Theme::getSkinsWithThemes(
			method_exists( $this->skinFactory, 'getInstalledSkins' ) ?
				$this->skinFactory->getInstalledSkins() : $this->skinFactory->getSkinNames()
		);
		$showIf = [ 'OR' ];
		foreach ( $skinsWithThemes as $skin ) {
			$showIf[] = [ '===', 'skin', $skin ];
		}

		$defaultTheme = $ctx->getConfig()->get( 'DefaultTheme' );
		$defaultTheme = $this->userOptionsLookup->getOption( $user, 'theme', $defaultTheme );
		$defaultPreferences['theme'] = [
			'type' => 'select',
			'options' => $themeArray,
			'default' => $defaultTheme,
			'label-message' => 'theme-prefs-label',
			'section' => 'rendering/skin',
			'hide-if' => [ 'NOT', $showIf ],
		];

		// If a skin has no themes (besides "default"),
		// show only an informative message instead
		$defaultPreferences['notheme'] = [
			'type' => 'info',
			'label-message' => 'theme-prefs-label',
			'default' => $ctx->msg( 'theme-unsupported-skin' )->text(),
			'section' => 'rendering/skin',
			'hide-if' => $showIf,
		];
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
		$theme = $this->getTheme( $out );

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

	/**
	 * Detect the theme
	 *
	 * @param IContextSource $context
	 * @return string Name of the theme
	 */
	private function getTheme( IContextSource $context ): string {
		// Check the following things in this order:
		// 1) per-page usetheme URL parameter
		// 2) user's personal preference/override
		// 3) value of $wgDefaultTheme (set in site configuration)
		$theme = $context->getRequest()->getRawVal( 'usetheme' )
			?? $this->userOptionsLookup->getOption( $context->getUser(), 'theme' )
			?? $context->getConfig()->get( 'DefaultTheme' );

		// Support any case of the theme name.
		$theme = strtolower( $theme );

		// Fall back to 'default' if the skin has no theme with this theme name.
		$skinName = $context->getSkin()->getSkinName();
		$resourceLoader = $context->getOutput()->getResourceLoader();
		if ( !Theme::skinHasTheme( $skinName, $theme, $resourceLoader ) ) {
			$theme = 'default';
		}

		return $theme;
	}

}
