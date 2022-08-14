/*!
 * JavaScript for Special:Preferences: live previewing of skins' themes.
 *
 * Code adapted from /resources/src/mediawiki.special.preferences.ooui/editfont.js
 * HT MatmaRex
 */
( function () {
	var cache = Object.create( null );

	/**
	 * @param {string} theme Theme name
	 * @return {jQuery.Promise}
	 */
	function loadTheme( theme ) {
		if ( !( theme in cache ) ) {
			var skin = mw.config.get( 'skin' ),
				// @todo FIXME: When core-ifying this code again,
				// remove this stupid special case hack
				prefix = skin !== 'monaco' ? 'themeloader.' : '',
				moduleName = prefix + 'skins.' + skin + '.' + theme,
				params = {
					// Use alphabetical order.
					debug: 1,
					modules: moduleName,
					only: 'styles',
					skin: skin
				};
			if ( mw.config.get( 'debug' ) !== 1 ) {
				// The delete keeps the alphabetical order.
				delete params.debug;
			}
			cache[ theme ] = $.ajax( {
				url: mw.util.wikiScript( 'load' ) + '?' + $.param( params )
			} );
		}
		return cache[ theme ];
	}

	// This first handler listens to change to the *skin*, and when the skin is changed,
	// it attempts to pull the list of themes for that skin (if any) via the API
	mw.hook( 'htmlform.enhance' ).add( function ( $root ) {
		var widget, lastValue,
			queryDone = false,
			$target = $root.find( '#mw-input-wpskin' );

		if (
			!$target.length ||
			$target.closest( '.mw-htmlform-autoinfuse-lazy' ).length
		) {
			return;
		}

		widget = OO.ui.infuse( $target );

		/*
		 * Get list of menu items from a server response.
		 * This is needed because the raw API response cannot be passed to OOUI as-is.
		 *
		 * @param {Object} data API query result
		 * @return {OO.ui.MenuOptionWidget[]} Menu items
		 */
		function convertForOO( data ) {
			var len, i, theme,
				items = [];

			for ( i = 0, len = data.length; i < len; i++ ) {
				theme = data[ i ] || {};
				items.push( new OO.ui.MenuOptionWidget( {
					label: theme.name,
					data: theme.code
				} ) );
			}

			return items;
		}

		/**
		 * @param {string} value Skin name, e.g. "monobook", "vector", etc.
		 */
		function updateLabel( value ) {
			var chosenValue = value,
				themeWidget,
				apiThemeQueryCache = {};

			// Only query the API once (per request) and cache the result
			if ( !queryDone && chosenValue !== lastValue ) {
				// @todo FIXME: Why is this query performed when the user chooses the "Appearance"
				// tab? I want it to run only when the user actually touches the skin preference
				// option...
				( new mw.Api() ).get( {
					formatversion: 2,
					/* Once https://gerrit.wikimedia.org/r/465451/ is merged and thus core exposes
					the list of skins and their themes, this will do:
					action: 'query',
					meta: 'siteinfo',
					siprop: 'themes'
					*/
					action: 'themelist'
				} ).done( function ( data ) {
					apiThemeQueryCache = data.query.themes;
				} );
				queryDone = true;
			}

			if ( queryDone && apiThemeQueryCache[ value ] ) {
				// This skin has themes -> now update the theme drop-down menu as appropriate
				try {
					themeWidget = OO.ui.infuse( $root.find( '#mw-input-wptheme' ) );
					themeWidget.dropdownWidget.menu.clearItems();
					themeWidget.dropdownWidget.menu.addItems(
						convertForOO( apiThemeQueryCache[ value ] )
					);
				} catch ( err ) {
					return;
				}
			// } else if ( queryDone && apiThemeQueryCache[ value ].length === 1 ) {
				// If the array length is 1, it means the array has only one item, which is
				// "default", i.e. the skin does not have any non-default themes.
				// @todo Remove #mw-input-wptheme from DOM or whatever is the OOUI equivalent
				// for that action, replace it with a note stating the skin doesn't have themes
			}

			lastValue = value;
		}

		widget.on( 'change', updateLabel );
		updateLabel( widget.getValue() );
	} );

	// Main theme live preview code starts here
	mw.hook( 'htmlform.enhance' ).add( function ( $root ) {
		/*
		 * We need to load the CSS without ResourceLoader because RL refuses
		 * to load the same module twice, but we essentially need that functionality
		 * because we .remove() the loaded CSS if and when the user previews a
		 * different theme. This way the user can preview e.g.
		 * pink -> dark -> stellarbook -> pink again; without this caching strategy
		 * the last step (previewing the pink theme again) would fail.
		 *
		 * See https://lists.wikimedia.org/pipermail/wikitech-l/2017-June/088363.html
		 * for more info.
		 */
		var widget, lastChosenValue,
			// Theme initially loaded by ResourceLoader
			// @todo FIXME: The overwrite by the URL parameter `usetheme` is not detected.
			themeLoaded = mw.user.options.get( 'theme' ),
			addedStyle = null,
			$target = $root.find( '#mw-input-wptheme' ),
			$previewNote = null;

		if (
			!$target.length ||
			$target.closest( '.mw-htmlform-autoinfuse-lazy' ).length
		) {
			return;
		}

		widget = OO.ui.infuse( $target );

		/**
		 * @param {string} chosenValue Theme name, e.g. "dark", "stellarbook", etc.
		 */
		function updateThemeLabel( chosenValue ) {
			var userTheme = mw.user.options.get( 'theme' );

			lastChosenValue = chosenValue;
			// Per Samantha, show a note indicating that the change hasn't been
			// saved yet and has to be explicitly saved by the user
			// Remove this element if it already exists
			if ( $previewNote ) {
				$previewNote.remove();
				$previewNote = null;
			}

			// If a user has chosen e.g. Pink MonoBook theme, do _not_ show the note when pink is
			// chosen (which it'll be by default if it's their theme of choice, d'oh!)
			// Instead only show this for other themes (like default, dark and stellarbook)
			if ( userTheme !== null && userTheme !== 'default' && chosenValue !== userTheme ) {
				// @todo FIXME: Should use OOUI's LabelWidget or somesuch for slightly better
				// styling?
				$previewNote = $( '<tr>' ).append(
					$( '<td>' ).addClass( 'htmlform-tip' ).attr( 'colspan', 2 ).text(
						mw.msg( 'theme-livepreview-note', chosenValue )
					)
				);
				$target.after( $previewNote );
			}

			// Remove already added theme <style> element from <head>
			if ( addedStyle ) {
				addedStyle.remove();
				addedStyle = null;
			}

			// User is currently using a non-default theme and wants to preview a theme?
			// We need to rebuild the ResourceLoader-generated <link> element in the page
			// <head> to prevent the CSS stacking issue (HT SamanthaNguyen)
			if ( themeLoaded !== null &&
				themeLoaded !== 'default' &&
				themeLoaded !== chosenValue
			) {
				// moduleName is the name of the module we want to *remove* from the <link>
				var skin = mw.config.get( 'skin' ),
					// @todo FIXME: When core-ifying this code again,
					// remove this stupid special case hack
					prefix = skin !== 'monaco' ? 'themeloader.' : '',
					moduleName = prefix + 'skins.' + skin + '.' + themeLoaded;

				// The module is already loaded via ResourceLoader together with other styles.
				// Only do this magic when chosenValue is not the theme you are already using.
				// @see T275903
				// @todo FIXME: potential perf issue
				if ( mw.loader.getState( moduleName ) === 'ready' ) {
					// Remove the module from the ResourceLoader URLs.
					$( 'head link[rel="stylesheet"]' ).attr( 'href', function ( i, href ) {
						return href.replace( encodeURIComponent( '|' + moduleName ), '' );
					} );
					themeLoaded = null;
				}
			}

			if ( chosenValue === 'default' ) {
				// No need to load anything if we chose 'default'
				return;
			}

			// Load the module via AJAX with a promise cache
			loadTheme( chosenValue ).done( function ( css ) {
				// Apply the style if not already another style is added and
				// the chosen theme is still the last chosen theme.
				if ( !addedStyle && chosenValue === lastChosenValue ) {
					addedStyle = mw.loader.addStyleTag( css );
				}
			} );
		}

		widget.on( 'change', updateThemeLabel );
	} );
}() );
