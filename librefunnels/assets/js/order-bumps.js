(function ( $ ) {
	'use strict';

	$( document.body ).on( 'change', '.librefunnels-order-bump__input', function () {
		$( document.body ).trigger( 'update_checkout' );
	} );
}( jQuery ));
