/*
 * The "Sort by" dropdown is rendered outside the filter form (top right of the
 * results), but the plugin's JS only sends fields that belong to the form.
 * This gives the form an id and points the dropdown at it with the `form`
 * attribute, so it is serialised with the other filters. It runs while the
 * page is parsed, before the plugin runs its first search.
 */
( function () {
	var FORM_ID = 'wpjm-job-filters';

	document.querySelectorAll( 'form.job_filters' ).forEach( function ( form ) {
		var wrap = form.parentNode;
		var sort = wrap && wrap.querySelector( '.wpjm-sort-bar select' );

		if ( sort ) {
			form.id = form.id || FORM_ID;
			sort.setAttribute( 'form', form.id );
		}
	} );

	// "Reset Filters" only clears fields inside the form, so clear the sort too.
	// Capture phase: the plugin's own handler stops the click from bubbling.
	document.addEventListener(
		'click',
		function ( event ) {
			var reset = event.target.closest && event.target.closest( 'form.job_filters .reset' );
			if ( ! reset ) {
				return;
			}
			var sort = reset.closest( '.job_listings' ).querySelector( '.wpjm-sort-bar select' );
			if ( sort ) {
				sort.value = '';
			}
		},
		true
	);
} )();
