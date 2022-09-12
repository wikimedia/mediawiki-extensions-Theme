<?php

namespace MediaWiki\Extension\Theme;

use ApiBase;
use ApiMain;
use ApiResult;
use SkinFactory;

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

	/** @var SkinFactory */
	private $skinFactory;

	/**
	 * @param ApiMain $main
	 * @param string $action
	 * @param SkinFactory $skinFactory
	 */
	public function __construct(
		ApiMain $main,
		$action,
		SkinFactory $skinFactory
	) {
		parent::__construct( $main, $action );
		$this->skinFactory = $skinFactory;
	}

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
		$skinNames = $this->skinFactory->getSkinNames();
		foreach ( $skinNames as $skin => $displayName ) {
			$themes = Theme::getAvailableThemes( $skin );
			// When this stuff is in core, use:
			// $themes = Skin::getAvailableThemes( $skin );
			foreach ( $themes as $idx => $themeKey ) {
				$msg = $this->msg( "theme-name-{$skin}-{$themeKey}" );
				$msg->inLanguage( $this->getLanguage() );
				$themeDisplayName = $msg->exists() ? $msg->text() : $themeKey;
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
