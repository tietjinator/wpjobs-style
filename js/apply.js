/**
 * Email Apply button: after it is clicked, show the address and subject under
 * it, for anyone whose browser did not open a mail app. The button still does
 * its normal mailto: action. Without JavaScript the message is always shown
 * (see the <noscript> rule in templates/job-application.php).
 */
( function () {
	var buttons = document.querySelectorAll( '.job_application .wpjm-apply[href^="mailto:"]' );

	Array.prototype.forEach.call( buttons, function ( button ) {
		var note = button.parentNode.querySelector( '.wpjm-apply-fallback' );
		if ( ! note ) {
			return;
		}

		button.addEventListener( 'click', function () {
			note.hidden = false;
		} );
	} );
}() );
