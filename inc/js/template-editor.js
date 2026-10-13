/**
 * ROLE VISIBILITY: Template editor
 *
 * Switches the scope panels and inserts or copies merge tags.
 */

( function ( $ ) {
    'use strict';

    var config = window.roleVisibilityTemplate || {};

    $( document ).on( 'change', '#rolevisibility-template-scope', function () {
        var scope = $( this ).val();

        $( '.rolevisibility-scope-panel' ).each( function () {
            $( this ).prop( 'hidden', $( this ).data( 'scope' ) !== scope );
        } );
    } );

    $( document ).on( 'click', '.rolevisibility-merge-tag', function () {
        var button = $( this );
        var tag = button.data( 'tag' );

        if ( insertIntoClassicEditor( tag ) ) {
            return;
        }

        copy( tag ).then( function () {
            button.addClass( 'is-copied' ).attr( 'title', config.copied );

            setTimeout( function () {
                button.removeClass( 'is-copied' ).removeAttr( 'title' );
            }, 1200 );
        } );
    } );

    $( document ).on( 'change', '.rolevisibility-merge-tag-select', function () {
        var tag = $( this ).val();

        if ( tag ) {
            insertIntoClassicEditor( tag );
            $( this ).val( '' );
        }
    } );


    /**
     * Insert text into the Classic Editor, in whichever mode is active.
     *
     * @param {string} text
     * @return {boolean} Whether the text was inserted.
     */
    function insertIntoClassicEditor( text ) {
        var textarea = document.getElementById( 'content' );

        if ( ! textarea || $( 'body' ).hasClass( 'block-editor-page' ) ) {
            return false;
        }

        var editor = window.tinymce ? window.tinymce.get( 'content' ) : null;

        if ( editor && ! editor.isHidden() ) {
            editor.execCommand( 'mceInsertContent', false, text );
            return true;
        }

        var start = textarea.selectionStart || 0;
        var end = textarea.selectionEnd || 0;

        textarea.value = textarea.value.substring( 0, start ) + text + textarea.value.substring( end );
        textarea.selectionStart = textarea.selectionEnd = start + text.length;
        textarea.focus();

        return true;
    } // End insertIntoClassicEditor()


    /**
     * Copy text to the clipboard.
     *
     * @param {string} text
     * @return {Promise}
     */
    function copy( text ) {
        if ( navigator.clipboard && window.isSecureContext ) {
            return navigator.clipboard.writeText( text );
        }

        var temp = $( '<textarea readonly></textarea>' ).val( text ).css( { position: 'fixed', top: '-1000px' } ).appendTo( 'body' );

        temp.trigger( 'select' );
        document.execCommand( 'copy' );
        temp.remove();

        return Promise.resolve();
    } // End copy()

} )( jQuery );
