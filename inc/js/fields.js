/**
 * ROLE VISIBILITY: Fields
 *
 * Shows role and user limits when "Logged-In Only" is chosen, and powers the searchable
 * user and post pickers. Events are delegated so cloned Quick Edit rows work too.
 */

( function ( $ ) {
    'use strict';

    var config = window.roleVisibilityFields || {};
    var searchTimer;
    var lastRequest;

    $( document ).on( 'change', '.rolevisibility-access-select', function () {
        toggleRestrictions( $( this ) );
    } );

    $( document ).on( 'input', '.rolevisibility-picker-search', function () {
        var input = $( this );

        clearTimeout( searchTimer );
        searchTimer = setTimeout( function () {
            search( input.closest( '.rolevisibility-picker' ), input.val().trim() );
        }, 300 );
    } );

    $( document ).on( 'keydown', '.rolevisibility-picker-search', function ( event ) {
        var results = $( this ).siblings( '.rolevisibility-picker-results' );
        var items = results.children( 'li[data-id]' );
        var active = items.filter( '.is-active' );
        var index = items.index( active );

        if ( 'Enter' === event.key ) {
            event.preventDefault();

            if ( active.length ) {
                active.trigger( 'click' );
            }
        } else if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
            event.preventDefault();
            index = 'ArrowDown' === event.key ? Math.min( index + 1, items.length - 1 ) : Math.max( index - 1, 0 );
            items.removeClass( 'is-active' ).eq( index ).addClass( 'is-active' );
        } else if ( 'Escape' === event.key ) {
            results.prop( 'hidden', true );
        }
    } );

    $( document ).on( 'click', '.rolevisibility-picker-results li[data-id]', function () {
        var item = $( this );
        var picker = item.closest( '.rolevisibility-picker' );

        addItem( picker, item.data( 'id' ), item.text() );
        picker.find( '.rolevisibility-picker-search' ).val( '' ).trigger( 'focus' );
        picker.find( '.rolevisibility-picker-results' ).prop( 'hidden', true ).empty();
    } );

    $( document ).on( 'click', '.rolevisibility-picker-remove', function () {
        $( this ).closest( 'li' ).remove();
    } );

    $( document ).on( 'click', function ( event ) {
        if ( ! $( event.target ).closest( '.rolevisibility-picker' ).length ) {
            $( '.rolevisibility-picker-results' ).prop( 'hidden', true );
        }
    } );


    /**
     * Show or hide the role and user limits for an access dropdown.
     *
     * @param {jQuery} select
     */
    function toggleRestrictions( select ) {
        select.closest( '.rolevisibility-access-fields' ).find( '.rolevisibility-restrict-to' ).prop( 'hidden', 'logged-in' !== select.val() );
    } // End toggleRestrictions()


    /**
     * Search users or posts and list the matches.
     *
     * @param {jQuery} picker
     * @param {string} term
     */
    function search( picker, term ) {
        var results = picker.find( '.rolevisibility-picker-results' );
        var source = picker.data( 'source' );

        if ( term.length < 2 && ! ( 'posts' === source && /^\d+$/.test( term ) ) ) {
            results.prop( 'hidden', true ).empty();
            return;
        }

        if ( lastRequest ) {
            lastRequest.abort();
        }

        results.prop( 'hidden', false ).html( $( '<li class="is-empty"></li>' ).text( config.searching ) );

        lastRequest = $.ajax( {
            url: config.ajaxUrl,
            method: 'GET',
            dataType: 'json',
            data: { action: 'rolevisibility_search_' + source, nonce: config.nonce, term: term }
        } ).done( function ( response ) {
            var selected = picker.find( '.rolevisibility-picker-selected input' ).map( function () {
                return String( $( this ).val() );
            } ).get();
            var matches = response.success ? response.data.filter( function ( match ) {
                return selected.indexOf( String( match.id ) ) === -1;
            } ) : [];

            results.empty();

            if ( ! matches.length ) {
                results.append( $( '<li class="is-empty"></li>' ).text( config.noResults ) );
                return;
            }

            matches.forEach( function ( match, i ) {
                results.append( $( '<li></li>' ).attr( 'data-id', match.id ).toggleClass( 'is-active', 0 === i ).text( match.text ) );
            } );
        } );
    } // End search()


    /**
     * Add a selected item to a picker.
     *
     * @param {jQuery} picker
     * @param {number} id
     * @param {string} text
     */
    function addItem( picker, id, text ) {
        var list = picker.find( '.rolevisibility-picker-selected' );

        if ( list.find( 'li[data-id="' + id + '"]' ).length ) {
            return;
        }

        var item = $( '<li></li>' ).attr( 'data-id', id );

        item.append( $( '<span></span>' ).text( text ) );
        item.append( $( '<button type="button" class="rolevisibility-picker-remove">&times;</button>' ).attr( 'aria-label', config.remove ) );
        item.append( $( '<input type="hidden">' ).attr( 'name', picker.data( 'name' ) ).val( id ) );

        list.append( item );
    } // End addItem()


    window.roleVisibilityFieldsApi = {
        addItem: addItem,
        toggleRestrictions: toggleRestrictions
    };

} )( jQuery );
