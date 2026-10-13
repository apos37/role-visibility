/**
 * ROLE VISIBILITY: Settings
 *
 * AJAX save with Ctrl+S support, plus download, upload and reset of settings,
 * creating a No Access Page, and importing Nav Menu Roles rules.
 */

( function ( $ ) {
    'use strict';

    var config = window.roleVisibilitySettings || {};
    var form;
    var saveButton;
    var originalText;
    var savingInterval;
    var isDirty = false;

    $( document ).ready( function () {
        form = $( '#rolevisibility-settings-form' );
        saveButton = $( '#rolevisibility-save' );

        if ( ! form.length || ! saveButton.length ) {
            return;
        }

        originalText = saveButton.text();

        form.on( 'input change', '[name]', markDirty );
        form.on( 'submit', function ( event ) {
            event.preventDefault();
            saveSettings();
        } );

        saveButton.on( 'click', function ( event ) {
            event.preventDefault();
            saveSettings();
        } );

        $( document ).on( 'keydown', function ( event ) {
            if ( ( event.ctrlKey || event.metaKey ) && 's' === event.key.toLowerCase() ) {
                event.preventDefault();
                saveSettings();
            }
        } );

        $( '#rolevisibility-download-settings' ).on( 'click', downloadSettings );
        $( '#rolevisibility-upload-settings' ).on( 'change', importSettings );
        $( '#rolevisibility-reset-settings' ).on( 'click', resetSettings );
        $( '#rolevisibility-create-page' ).on( 'click', createPage );
        $( '#rolevisibility_no_access_page' ).on( 'change', toggleCreatePage );
        $( '#rolevisibility-import-menus' ).on( 'click', importMenus );
        $( '#rolevisibility-run-migration' ).on( 'click', runMigration );

        $( 'label[for="rolevisibility-upload-settings"]' ).on( 'keydown', function ( event ) {
            if ( 'Enter' === event.key || ' ' === event.key ) {
                event.preventDefault();
                $( '#rolevisibility-upload-settings' ).trigger( 'click' );
            }
        } );
    } );


    /**
     * Send a request to admin-ajax with the settings nonce.
     *
     * @param {string} action
     * @param {Object|string} data
     * @return {jqXHR}
     */
    function request( action, data ) {
        var payload = 'string' === typeof data ? data + '&' + $.param( { action: action, nonce: config.nonce } ) : $.extend( { action: action, nonce: config.nonce }, data || {} );

        return $.ajax( { url: config.ajaxUrl, method: 'POST', dataType: 'json', data: payload } );
    } // End request()


    /**
     * Show the save reminder.
     */
    function markDirty() {
        if ( isDirty ) {
            return;
        }

        isDirty = true;
        $( '#rolevisibility-save-reminder' ).fadeIn( 200 );
    } // End markDirty()


    /**
     * Save all settings via AJAX.
     */
    function saveSettings() {
        if ( saveButton.prop( 'disabled' ) ) {
            return;
        }

        var originalTitle = startSavingTitle();

        $( '#rolevisibility-save-reminder' ).hide();
        isDirty = false;
        showSaving();

        request( 'rolevisibility_save_settings', form.serialize() ).done( function ( response ) {
            showResult( response.success ? config.savedText : ( response.data || config.errorText ), response.success );
        } ).fail( function () {
            showResult( config.errorText, false );
        } ).always( function () {
            stopSavingTitle( originalTitle );
        } );
    } // End saveSettings()


    /**
     * Download the settings as a JSON file.
     */
    function downloadSettings() {
        request( 'rolevisibility_export' ).done( function ( response ) {
            if ( ! response.success ) {
                showResult( response.data || config.errorText, false );
                return;
            }

            var blob = new Blob( [ JSON.stringify( response.data, null, 2 ) ], { type: 'application/json' } );
            var link = document.createElement( 'a' );

            link.href = URL.createObjectURL( blob );
            link.download = config.exportName;
            document.body.appendChild( link );
            link.click();
            link.remove();
            URL.revokeObjectURL( link.href );
        } ).fail( function () {
            showResult( config.errorText, false );
        } );
    } // End downloadSettings()


    /**
     * Read an exported file and send it to the server.
     *
     * @param {Event} event
     */
    function importSettings( event ) {
        var file = event.target.files[ 0 ];

        if ( ! file ) {
            return;
        }

        if ( ! window.confirm( config.confirmImport ) ) {
            $( event.target ).val( '' );
            return;
        }

        var reader = new FileReader();

        reader.onload = function ( loaded ) {
            $( '#rolevisibility-upload-filename' ).text( file.name ).show();

            request( 'rolevisibility_import', { payload: loaded.target.result } ).done( function ( response ) {
                if ( response.success ) {
                    reloadWith( config.importOk );
                } else {
                    showResult( response.data || config.importError, false );
                }
            } ).fail( function () {
                showResult( config.importError, false );
            } );
        };

        reader.readAsText( file );
    } // End importSettings()


    /**
     * Reset every option.
     */
    function resetSettings() {
        if ( ! window.confirm( config.confirmReset ) ) {
            return;
        }

        request( 'rolevisibility_reset' ).done( function ( response ) {
            if ( response.success ) {
                reloadWith( config.resetOk );
            } else {
                showResult( response.data || config.errorText, false );
            }
        } ).fail( function () {
            showResult( config.errorText, false );
        } );
    } // End resetSettings()


    /**
     * Create a No Access Page and select it.
     */
    function createPage() {
        var button = $( this );

        button.prop( 'disabled', true );

        request( 'rolevisibility_create_page' ).done( function ( response ) {
            if ( ! response.success ) {
                showResult( response.data || config.errorText, false );
                return;
            }

            var select = $( '#rolevisibility_no_access_page' );

            select.append( $( '<option></option>' ).val( response.data.id ).text( response.data.title ) ).val( String( response.data.id ) ).trigger( 'change' );
            showResult( config.pageCreated, true );
        } ).fail( function () {
            showResult( config.errorText, false );
        } ).always( function () {
            button.prop( 'disabled', false );
        } );
    } // End createPage()


    /**
     * Show the Create Page button only while no page is selected.
     */
    function toggleCreatePage() {
        $( '#rolevisibility-create-page' ).prop( 'hidden', '0' !== String( $( '#rolevisibility_no_access_page' ).val() ) );
    } // End toggleCreatePage()


    /**
     * Run the version 1.x migration again.
     */
    function runMigration() {
        var button = $( this );

        button.prop( 'disabled', true );

        request( 'rolevisibility_migrate' ).done( function ( response ) {
            if ( response.success ) {
                reloadWith( response.data );
            } else {
                showResult( response.data || config.errorText, false );
                button.prop( 'disabled', false );
            }
        } ).fail( function () {
            showResult( config.errorText, false );
            button.prop( 'disabled', false );
        } );
    } // End runMigration()


    /**
     * Import menu rules from Nav Menu Roles.
     */
    function importMenus() {
        if ( ! window.confirm( config.confirmMenus ) ) {
            return;
        }

        request( 'rolevisibility_import_menus' ).done( function ( response ) {
            if ( response.success ) {
                reloadWith( response.data );
            } else {
                showResult( response.data || config.errorText, false );
            }
        } ).fail( function () {
            showResult( config.errorText, false );
        } );
    } // End importMenus()


    /**
     * Show a message, then reload the page.
     *
     * @param {string} message
     */
    function reloadWith( message ) {
        showResult( message, true );

        setTimeout( function () {
            window.location.reload();
        }, 900 );
    } // End reloadWith()


    /**
     * Animate the browser tab title while saving.
     *
     * @return {string}
     */
    function startSavingTitle() {
        var originalTitle = document.title;
        var dots = 0;

        document.title = config.savingText;

        savingInterval = setInterval( function () {
            dots = ( dots + 1 ) % 4;
            document.title = config.savingText + '.'.repeat( dots );
        }, 500 );

        return originalTitle;
    } // End startSavingTitle()


    /**
     * Restore the browser tab title.
     *
     * @param {string} originalTitle
     */
    function stopSavingTitle( originalTitle ) {
        clearInterval( savingInterval );
        document.title = originalTitle;
    } // End stopSavingTitle()


    /**
     * Show the spinning save state.
     */
    function showSaving() {
        saveButton.addClass( 'rolevisibility-button-saving' ).prop( 'disabled', true ).empty()
            .append( '<span class="dashicons dashicons-update rolevisibility-spin"></span>' )
            .append( document.createTextNode( ' ' + config.savingText + '…' ) );
        $( '#rolevisibility-save-note' ).remove();
    } // End showSaving()


    /**
     * Show a result message next to the save button.
     *
     * @param {string} message
     * @param {boolean} success
     */
    function showResult( message, success ) {
        saveButton.removeClass( 'rolevisibility-button-saving' ).prop( 'disabled', false ).text( originalText );
        $( '#rolevisibility-save-note' ).remove();

        var note = $( '<span id="rolevisibility-save-note"></span>' ).text( message ).addClass( success ? 'is-success' : 'is-error' );

        saveButton.after( note );

        setTimeout( function () {
            note.fadeOut( 400, function () {
                $( this ).remove();
            } );
        }, 4000 );
    } // End showResult()

} )( jQuery );
