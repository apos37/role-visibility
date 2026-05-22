jQuery( $ => {    
    // Reset settings page
    $( '#reset-settings-page' ).on( 'click', function( e ) {
        if ( !confirm( role_visibility.confirmReset ) ) {
            e.preventDefault();
        }
    } );
    
    // Function to update the name attributes of all rows
    function updateIndexes( fieldsContainer ) {
        fieldsContainer.find( '.text-plus-row' ).each( function( i ) {
            $( this ).attr( 'data-row', i );

            $( this ).find( 'input, select' ).each( function() {
                var name = $( this ).attr( 'name' );
                var newName = name.replace( /\[\d+\]/, '[' + i + ']' );
                $( this ).attr( 'name', newName );
            } );
        } );
    }

    // Add new field
    $( '.add-new-field' ).on( 'click', function() {
        var fieldsContainer = $( this ).siblings( '.fields_container' );
        var lastRow = fieldsContainer.find( '.text-plus-row' ).last();

        if ( lastRow.data( 'row' ) == 0 && lastRow.is( ':hidden' ) ) {
            lastRow.show();
        } else {
            var newRow = lastRow.clone();
            newRow.find( 'input' ).val( '' ).prop( 'disabled', false );
            newRow.find( 'select' ).prop( 'selectedIndex', 0 );
            fieldsContainer.append( newRow );
            updateIndexes( fieldsContainer );
        }
        checkMaxRows( fieldsContainer );
        checkForDuplicates( fieldsContainer );
    } );

    // Remove field
    $( '.fields_container' ).on( 'click', '.remove-row', function() {
        var row = $( this ).closest( '.text-plus-row' );
        var fieldsContainer = row.closest( '.fields_container' );

        row.remove();
        updateIndexes( fieldsContainer );
        checkMaxRows( fieldsContainer );
        checkForDuplicates( fieldsContainer );
    } );

    // Prevent duplicates
    $( '.fields_container' ).on( 'change', 'select[data-type="post_type"], select[data-type="role"]', function() {
        var fieldsContainer = $( this ).closest( '.fields_container' );
        checkForDuplicates( fieldsContainer );
    } );

    // Prevent adding more rows
    function checkMaxRows( fieldsContainer ) {
        var name = fieldsContainer.data( 'name' );
        let maxCount = ( name === 'role_visibility_post_types' ) ? role_visibility.postTypeCount : role_visibility.roleCount;
        var addNewButton = fieldsContainer.siblings( '.add-new-field' );

        addNewButton.prop( 'disabled', fieldsContainer.find( '.text-plus-row' ).length >= maxCount );
    }

    // Check for duplicates
    function checkForDuplicates( fieldsContainer ) {
        const seen = {};
        let hasDuplicates = false;

        fieldsContainer.find( '.text-plus-row' ).each( function() {
            const type = fieldsContainer.data( 'name' ) === 'role_visibility_post_types' ? 'post_type' : 'role';
            const input = $( this ).find( `select[data-type="${type}"]` );
            const value = input.val().trim();
            const warningMessage = $( this ).find( '.warning-message' );

            // Hide warning message initially
            warningMessage.hide();

            if ( value ) {
                if ( seen[value] ) {
                    hasDuplicates = true;
                    input.addClass( 'duplicate' );
                    warningMessage.show().text( type === 'post_type' ? role_visibility.duplicatePostType : role_visibility.duplicateRole );
                } else {
                    seen[value] = true;
                    input.removeClass( 'duplicate' );
                }
            } else {
                input.removeClass( 'duplicate' );
            }
        } );

        toggleButtons( hasDuplicates, fieldsContainer );
    }

    // Toggle buttons
    function toggleButtons( hasWarnings, fieldsContainer ) {
        fieldsContainer.closest( 'form' ).find( '#submit' ).prop( 'disabled', hasWarnings );
        if ( hasWarnings ) {
            var addNewButton = fieldsContainer.siblings( '.add-new-field' );
            addNewButton.prop( 'disabled', true );
        } else {
            checkMaxRows( fieldsContainer );
        }
    }
} );
