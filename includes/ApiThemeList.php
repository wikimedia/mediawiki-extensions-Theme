<?php

use MediaWiki\MediaWikiServices;

/**
 * API module for listing skins' themes.
 * Lazy shim until https://gerrit.wikimedia.org/r/465451/ is merged into core,
 * at which point this file can go away and the JS can go back to using
 * api.php?action=query&meta=siteinfo&siprop=themes
 *
 * @file
 * @ingroup API
 */
class ApiThemeList extends ApiBase {

	/**
	 * Main entry point.
	 */
	public function execute() {
		$this->appendThemes();
	}

	/**
	 * @param string $property
	 * @return bool
	 */
	public function appendThemes( $property = 'themes' ) {
		$data = [];
		$config = $this->getConfig();
		$defaultTheme = $config->get( 'DefaultTheme' );
		$defaultSkin = $config->get( 'DefaultSkin' );
		$skinNames = MediaWikiServices::getInstance()->getSkinFactory()->getSkinNames();
		foreach ( $skinNames as $skin => $displayName ) {
			$themes = Theme::getAvailableThemes( $skin );
			// When this stuff is in core, use:
			// $themes = Skin::getAvailableThemes( $skin );
			foreach ( $themes as $idx => $themeKey ) {
				$msg = $this->msg( "theme-name-{$skin}-{$themeKey}" );
				// added @ because I was getting
				// Notice: Undefined index: inlanguagecode in ../includes/api/ApiBase.php on line 879
				// on my 1.34alpha devbox; that is not a problem with the code in
				// https://gerrit.wikimedia.org/r/465451/
				$code = @$this->getParameter( 'inlanguagecode' );
				if ( $code && Language::isValidCode( $code ) ) {
					$msg->inLanguage( $code );
				} else {
					$msg->inContentLanguage();
				}
				$themeDisplayName = $themeKey;
				if ( $msg->exists() ) {
					$themeDisplayName = $msg->text();
				}
				$theme = [ 'code' => $themeKey ];
				ApiResult::setContentValue( $theme, 'name', $themeDisplayName );
				if ( $themeKey === $defaultTheme && $skin === $defaultSkin ) {
					$theme['default'] = true;
				}
				$data[$skin][] = $theme;
			}
		}
		ApiResult::setIndexedTagName( $data, 'theme' );

		return $this->getResult()->addValue( 'query', $property, $data );
	}

	/**
	 * @see ApiBase#getAllowedParams()
	 * @return array
	 */
	public function getAllowedParams() {
		return [];
	}

	/**
	 * @see ApiBase#getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=themelist' => 'apihelp-themelist-example-1'
		];
	}
}
