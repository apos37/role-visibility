/**
 * ROLE VISIBILITY: List table
 *
 * Fills the Quick Edit controls from the post's stored rule, read from the column's data attributes.
 */

( function ( $ ) {
    'use strict';

    if ( typeof inlineEditPost === 'undefined' || ! window.roleVisibilityFieldsApi ) {
        return;
    }

    var api = window.roleVisibilityFieldsApi;
    var originalEdit = inlineEditPost.edit;

    inlineEditPost.edit = function ( id ) {
        originalEdit.apply( this, arguments );

        var postId = 'object' === typeof id ? parseInt( this.getId( id ), 10 ) : parseInt( id, 10 );

        if ( ! postId ) {
            return;
        }

        var column = $( '#post-' + postId + ' .rolevisibility-column' );
        var editRow = $( '#edit-' + postId );
        var rule = parseRule( String( column.data( 'access' ) || '' ) );
        var users = column.data( 'users' ) || [];
        var select = editRow.find( 'select[name="rolevisibility[access]"]' );

        select.val( rule.access );
        editRow.find( 'input[name="rolevisibility[roles][]"]' ).each( function () {
            $( this ).prop( 'checked', rule.roles.indexOf( $( this ).val() ) !== -1 );
        } );

        var picker = editRow.find( '.rolevisibility-picker' );
        var restrictTo = editRow.find( '.rolevisibility-restrict-to' );

        picker.find( '.rolevisibility-picker-selected' ).empty();
        restrictTo.children( 'input.rolevisibility-kept-user' ).remove();

        users.forEach( function ( user ) {
            if ( picker.length ) {
                api.addItem( picker, user.id, user.text );
            } else {
                restrictTo.append( $( '<input type="hidden" class="rolevisibility-kept-user" name="rolevisibility[users][]">' ).val( user.id ) );
            }
        } );

        api.toggleRestrictions( select );
    };


    /**
     * Parse a stored rule string, matching Access::parse() in PHP.
     *
     * @param {string} raw
     * @return {Object}
     */
    function parseRule( raw ) {
        var tokens = raw.split( ',' ).map( function ( token ) {
            return token.trim();
        } ).filter( Boolean );
        var roles = tokens.filter( function ( token ) {
            return [ 'default', 'everyone', 'logged-in', 'logged-out' ].indexOf( token ) === -1 && token.indexOf( 'user:' ) !== 0;
        } );
        var hasUsers = tokens.some( function ( token ) {
            return token.indexOf( 'user:' ) === 0;
        } );
        var access = 'default';

        if ( tokens.indexOf( 'logged-out' ) !== -1 ) {
            access = 'logged-out';
            roles = [];
        } else if ( tokens.indexOf( 'logged-in' ) !== -1 || roles.length || hasUsers ) {
            access = 'logged-in';
        } else if ( tokens.indexOf( 'everyone' ) !== -1 ) {
            access = 'everyone';
        }

        return { access: access, roles: roles };
    } // End parseRule()

} )( jQuery );
