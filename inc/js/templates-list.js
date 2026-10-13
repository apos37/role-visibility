/**
 * ROLE VISIBILITY: Templates list
 *
 * Saves the per-page choice and reloads the list.
 */

( function ( $ ) {
    'use strict';

    $( document ).on( 'change', '.rolevisibility-per-page', function () {
        var url = new URL( window.location.href );

        url.searchParams.set( roleVisibilityList.key, $( this ).val() );
        url.searchParams.set( '_wpnonce', roleVisibilityList.nonce );
        url.searchParams.delete( 'paged' );

        window.location.href = url.toString();
    } );

} )( jQuery );
