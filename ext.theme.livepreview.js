( function ( mw, $ ) {
	/**
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
	var cache = {};
	$( '#mw-input-wptheme' ).on( 'change', function () {
		var chosenValue = $( this ).val(),
			skin = mw.config.get( 'skin' ),
			userSkin = mw.user.options.get( 'skin' ),
			userTheme = mw.user.options.get( 'theme' ),
			useSkin = mw.util.getParamValue( 'skin' ),
			match, moduleName, originalStyleHref, prefix, re;

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
		$( this ).parent().parent().after(
			'<tr id="theme-preview-note"><td class="htmlform-tip" colspan="2">' +
			mw.msg( 'theme-livepreview-note', chosenValue ) +
			'</td></tr>'
		);

		// Clear out everything by removing the last appended <style> from <head>
		$( 'head style' ).last().remove();

		// User is currently using a non-default theme and wants to preview a theme?
		// We need to rebuild the ResourceLoader-generated <link> element in the page
		// <head> to prevent the CSS stacking issue (HT SamanthaNguyen)
		if ( userTheme !== null && userTheme !== 'default' ) {
			moduleName = prefix + 'skins.' + skin + '.' + userTheme; // name of the module we want to *remove* from the <link>
			originalStyleHref = $( 'head link[rel="stylesheet"]' ).attr( 'href' );
			re = new RegExp( moduleName, 'g' );
			match = originalStyleHref.match( re );

			if ( match !== null ) { // match *is* null when choosing "default" _as well as_ when choosing the non-default theme you were already using!
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
	} );
}( mediaWiki, jQuery ) );
