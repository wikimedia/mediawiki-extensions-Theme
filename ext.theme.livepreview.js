/*!
 * JavaScript for Special:Preferences: live previewing of skins' themes.
 *
 * Code adapted from /resources/src/mediawiki.special.preferences.ooui/editfont.js
 * HT MatmaRex
 */
( function () {
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
					label: theme[ '*' ],
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
				// @todo FIXME: Why is this query performed when the user chooses the "Appearance" tab?
				// I want it to run only when the user actually touches the skin preference option...
				( new mw.Api() ).get( {
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
					themeWidget.dropdownWidget.menu.addItems( convertForOO( apiThemeQueryCache[ value ] ) );
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
		 * We need to cache the CSS returned by ResourceLoader because RL refuses
		 * to load the same module twice, but we essentially need that functionality
		 * because we .remove() the loaded CSS if and when the user previews a
		 * different theme. This way the user can preview e.g.
		 * pink -> dark -> stellarbook -> pink again; without this caching strategy
		 * the last step (previewing the pink theme again) would fail.
		 *
		 * See https://lists.wikimedia.org/pipermail/wikitech-l/2017-June/088363.html
		 * for more info.
		 */
		var widget,
			cache = {},
			$target = $root.find( '#mw-input-wptheme' );

		if (
			!$target.length ||
			$target.closest( '.mw-htmlform-autoinfuse-lazy' ).length
		) {
			return;
		}

		widget = OO.ui.infuse( $target );

		/**
		 * @param {string} value Theme name, e.g. "dark", "stellarbook", etc.
		 */
		function updateThemeLabel( value ) {
			var chosenValue = value,
				skin = mw.config.get( 'skin' ),
				userSkin = mw.user.options.get( 'skin' ),
				userTheme = mw.user.options.get( 'theme' ),
				useSkin = mw.util.getParamValue( 'skin' ),
				match, moduleName, originalStyleHref, prefix, re;

			// @todo FIXME: When core-ifying this code again, remove this stupid special case hack
			prefix = ( skin !== 'monaco' ? 'themeloader.' : '' );

			if ( useSkin !== null && useSkin !== userSkin ) {
				// prefer the URL param over prefs option
				skin = useSkin;
			}

			// Per Samantha, show a note indicating that the change hasn't been
			// saved yet and has to be explicitly saved by the user
			if ( $( '#theme-preview-note' ).length > 0 ) {
				// Remove this element if it already exists
				$( '#theme-preview-note' ).remove();
			}
			// If a user has chosen e.g. Pink MonoBook theme, do _not_ show the note when pink is chosen
			// (which it'll be by default if it's their theme of choice, d'oh!)
			// Instead only show this for other themes (like default, dark and stellarbook)
			if ( userTheme !== null && userTheme !== 'default' && chosenValue !== userTheme ) {
				// @todo FIXME: Should use OOUI's LabelWidget or somesuch for slightly better styling?
				$target.after(
					'<tr id="theme-preview-note"><td class="htmlform-tip" colspan="2">' +
					mw.message( 'theme-livepreview-note', chosenValue ).escaped() +
					'</td></tr>'
				);
			}

			// Clear out everything by removing the last appended <style> from <head>
			$( 'head style' ).last().remove();

			// User is currently using a non-default theme and wants to preview a theme?
			// We need to rebuild the ResourceLoader-generated <link> element in the page
			// <head> to prevent the CSS stacking issue (HT SamanthaNguyen)
			if ( userTheme !== null && userTheme !== 'default' ) {
				// moduleName is the name of the module we want to *remove* from the <link>
				moduleName = prefix + 'skins.' + skin + '.' + userTheme;
				originalStyleHref = $( 'head link[rel="stylesheet"]' ).attr( 'href' );
				re = new RegExp( moduleName, 'g' );
				match = originalStyleHref.match( re );

				// match *is* null when choosing "default" _as well as_ when choosing the
				// non-default theme you were already using!
				// Only do this magic when chosenValue is not the theme you are already using.
				// @see T275903
				// @todo FIXME: potential perf issue -- the AJAX query below gets executed unnecessarily
				if ( match !== null && chosenValue !== userTheme ) {
					$( 'head link[rel="stylesheet"]' ).attr( 'href', originalStyleHref.replace( '%7C' + moduleName, '' ) );
				} else if ( chosenValue === userTheme ) {
					// Try cache anyway
					if ( cache[ skin + '-' + chosenValue ] !== undefined ) {
						mw.util.addCSS( cache[ skin + '-' + chosenValue ] );
						return;
					}

					// If we didn't get a cache hit, load the requested module from
					// the server.
					// This has to be so damn complicated because if you're viewing
					// Special:Preferences with dark as your personal theme (and
					// Vector as your skin), then the themeloader.skins.vector.dark
					// module is already loaded, *but* we remove it above (the if
					// match is not null loop), so we can't load it again -- as RL
					// will think it's already been loaded, which is sorta true.
					// This, however, works as you'd think.
					$.ajax( {
						url: mw.config.get( 'wgScriptPath' ) + '/load.php?debug=false&modules=' +
							prefix + 'skins.' + skin + '.' + chosenValue + '&only=styles&skin=' + skin
					} ).done( function ( css ) {
						if ( cache[ skin + '-' + chosenValue ] === undefined ) {
							cache[ skin + '-' + chosenValue ] = css;
						}
						mw.util.addCSS( css );
					} );
				}
			}

			if ( chosenValue === 'default' ) {
				// No need to load anything if we chose 'default'
				return;
			}

			// Try cache first
			if ( cache[ skin + '-' + chosenValue ] !== undefined ) {
				// Yes, we got a cache hit! Inject the cached CSS, then.
				mw.util.addCSS( cache[ skin + '-' + chosenValue ] );
				// Return because there's nothing to be done here, we already have
				// the proper CSS (and calling ResourceLoader again below
				// would just load some Tipsy CSS or w/e and we don't want to pollute
				// our cache with such junk)
				return;
			}

			// No cache hit -> call RL to load the module for the 1st time and
			// store it in cache
			mw.loader.using(
				prefix + 'skins.' + skin + '.' + chosenValue
			).then( function () {
				if ( cache[ skin + '-' + chosenValue ] === undefined ) {
					cache[ skin + '-' + chosenValue ] = $( 'head style' ).last().text();
				}
			} );
		}

		widget.on( 'change', updateThemeLabel );
		updateThemeLabel( widget.getValue() );
	} );
}() );
