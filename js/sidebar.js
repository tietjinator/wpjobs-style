/*
 * 1. The "Sort by" dropdown is rendered outside the filter form (top right of the
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
			reset.closest( '.job_listings' ).querySelectorAll( '.wpjm-drawer--categories input[type="checkbox"]' ).forEach( function ( box ) {
				box.checked = false;
			} );
		},
		true
	);

	/*
	 * 2. Hint on the Job type drawer header, so a closed drawer still shows what
	 * is filtered: "All types", "2 of 5", plus "Remote" when that box is ticked.
	 */
	function updateHints() {
		document.querySelectorAll( '.wpjm-drawer' ).forEach( function ( drawer ) {
			var hint = drawer.querySelector( '.wpjm-drawer__hint' );
			if ( ! hint ) {
				return;
			}

			// Category drawer: nothing ticked is "All categories".
			if ( drawer.classList.contains( 'wpjm-drawer--categories' ) ) {
				var ticked = drawer.querySelectorAll( 'input[type="checkbox"]:checked' ).length;
				hint.textContent = ticked ? ticked + ' selected' : 'All categories';
				hint.classList.toggle( 'is-active', ticked > 0 );
				return;
			}

			var boxes = drawer.querySelectorAll( 'ul.job_types input[type="checkbox"]' );
			var checked = Array.prototype.filter.call( boxes, function ( box ) {
				return box.checked;
			} ).length;
			var remote = drawer.querySelector( '#remote_position' );
			var parts = [];

			if ( boxes.length ) {
				parts.push( checked === boxes.length ? 'All types' : checked === 0 ? 'None' : checked + ' of ' + boxes.length );
			}
			if ( remote && remote.checked ) {
				parts.push( 'Remote' );
			}

			hint.textContent = parts.join( ' \u00b7 ' );
			// Highlight when the drawer is narrowing the results.
			hint.classList.toggle( 'is-active', checked !== boxes.length || !! ( remote && remote.checked ) );
		} );
	}

	/*
	 * 3. Category checklist <-> the plugin's own (hidden) category dropdown. The
	 * plugin's JavaScript reads that dropdown, so the checklist drives it.
	 */
	function categorySelect( drawer ) {
		var wrap = drawer.closest( 'div.job_listings' );
		return wrap ? wrap.querySelector( 'select[name^="search_categories"]' ) : null;
	}

	// Tick the boxes to match the dropdown (page load, restored filters).
	function syncChecklistFromSelect() {
		document.querySelectorAll( '.wpjm-drawer--categories' ).forEach( function ( drawer ) {
			var select = categorySelect( drawer );
			if ( ! select ) {
				return;
			}
			var chosen = Array.prototype.map.call( select.selectedOptions, function ( o ) {
				return o.value;
			} );
			drawer.querySelectorAll( 'input[type="checkbox"]' ).forEach( function ( box ) {
				box.checked = chosen.indexOf( box.value ) !== -1;
			} );
		} );
	}

	document.addEventListener( 'change', function ( event ) {
		var box = event.target.closest && event.target.closest( '.wpjm-drawer--categories input[type="checkbox"]' );
		if ( box ) {
			var drawer = box.closest( '.wpjm-drawer--categories' );
			var select = categorySelect( drawer );
			if ( select ) {
				var ticked = Array.prototype.map.call( drawer.querySelectorAll( 'input[type="checkbox"]:checked' ), function ( b ) {
					return b.value;
				} );
				Array.prototype.forEach.call( select.options, function ( o ) {
					o.selected = ticked.indexOf( o.value ) !== -1;
				} );
				// The plugin runs the search when this dropdown changes.
				select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
		}

		if ( event.target.closest && event.target.closest( '.wpjm-drawer' ) ) {
			updateHints();
		}
	} );

	// Reset changes the checkboxes with script and no change event. Wait for the plugin's handler.
	document.addEventListener( 'click', function ( event ) {
		if ( event.target.closest && event.target.closest( 'form.job_filters .reset' ) ) {
			setTimeout( function () {
				updateHints();
				updateFilterBar();
			}, 0 );
		}
	} );

	/*
	 * 4. The "Filter" bar on small screens: open and close the filters, and show
	 * how many are applied ("2 applied") so a closed bar still says something.
	 */
	function countApplied( wrap ) {
		var form = wrap.querySelector( 'form.job_filters' );
		if ( ! form ) {
			return 0;
		}

		var n = 0;
		var keywords = form.querySelector( '#search_keywords' );
		var state = form.querySelector( '#search_state' );
		var types = form.querySelectorAll( 'ul.job_types input[type="checkbox"]' );
		var typesAllOn = Array.prototype.every.call( types, function ( box ) {
			return box.checked;
		} );

		if ( keywords && keywords.value.trim() ) {
			n++;
		}
		if ( state && state.value ) {
			n++;
		}
		if ( types.length && ! typesAllOn ) {
			n++;
		}
		if ( form.querySelector( '.wpjm-drawer--categories input[type="checkbox"]:checked' ) ) {
			n++;
		}
		if ( form.querySelector( '#remote_position:checked' ) ) {
			n++;
		}

		return n;
	}

	function updateFilterBar() {
		document.querySelectorAll( 'div.job_listings' ).forEach( function ( wrap ) {
			var hint = wrap.querySelector( ':scope > .wpjm-filter-toggle .wpjm-filter-toggle__hint' );
			if ( hint ) {
				var n = countApplied( wrap );
				hint.textContent = n ? n + ' applied' : '';
			}
		} );
	}

	document.addEventListener( 'click', function ( event ) {
		var bar = event.target.closest && event.target.closest( '.wpjm-filter-toggle' );
		if ( ! bar ) {
			return;
		}
		var wrap = bar.closest( 'div.job_listings' );
		var open = wrap.classList.toggle( 'wpjm-filters-open' );
		bar.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	} );

	// Pressing Search Jobs folds the filters away so the results are in view.
	document.addEventListener(
		'submit',
		function ( event ) {
			var form = event.target;
			if ( form.matches && form.matches( 'form.job_filters' ) ) {
				var wrap = form.closest( 'div.job_listings' );
				var bar = wrap && wrap.querySelector( ':scope > .wpjm-filter-toggle' );
				if ( wrap && bar && wrap.classList.contains( 'wpjm-filters-open' ) ) {
					wrap.classList.remove( 'wpjm-filters-open' );
					bar.setAttribute( 'aria-expanded', 'false' );
				}
			}
		},
		true
	);

	[ 'input', 'change' ].forEach( function ( type ) {
		document.addEventListener( type, function ( event ) {
			if ( event.target.closest && event.target.closest( 'form.job_filters' ) ) {
				updateFilterBar();
			}
		} );
	} );

	function refresh() {
		syncChecklistFromSelect();
		updateHints();
		updateFilterBar();
	}

	refresh();
	document.addEventListener( 'DOMContentLoaded', refresh );
	window.addEventListener( 'load', refresh );

	// The plugin restores saved filter values after load, then fires this after each search.
	if ( window.jQuery ) {
		window.jQuery( function ( $ ) {
			$( 'div.job_listings' ).on( 'updated_results', refresh );
		} );
	}
} )();
