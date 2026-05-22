jQuery( window ).on( 'load', function( $ ) {
    // console.log( 'Remove QS JS Loaded...' );
    if ( typeof role_visibility_remove_qs !== 'undefined' && role_visibility_remove_qs ) {
        if ( history.pushState ) {
            var obj = { Title: role_visibility_remove_qs.title, Url: role_visibility_remove_qs.url };
            window.history.pushState( obj, obj.Title, obj.Url );
        }
    }
} );