jQuery( $ => {
    // console.log( 'Role Visibility WP List Table JS Loaded...' );

    /**
     * SHOW/HIDE ROLES
     */

    function updateRoleVisibility( selectElement ) {
        var style = selectElement.val() === 'logged-in' ? 'block' : 'none';
        selectElement.closest( 'fieldset' ).find( '.quick-edit-role_visibility.roles' ).css( 'display', style );
    }

    // Initial check for each select
    $( '.role-visibility-select' ).each( function() {
        updateRoleVisibility( $( this ) );
    } );

    // Bind change event for each select individually
    $( document ).on( 'change', '.role-visibility-select', function() {
        updateRoleVisibility( $( this ) );
    } );


    /**
     * BULK EDIT
     */

    // Create a copy of the WP inline edit post function
    const wp_inline_edit = inlineEditPost.edit;

    // Overwrite the function with our own code
    inlineEditPost.edit = function( post_id ) {
        // Call the original WP edit function
        wp_inline_edit.apply( this, arguments );

        // Get the post ID
        if ( typeof ( post_id ) == 'object' ) {
            post_id = parseInt( this.getId( post_id ) );
        }
        if ( post_id > 0 ) {
            // Define the edit row
            const edit_row = $( '#edit-' + post_id );
            const post_row = $( '#post-' + post_id );

            // Get current values for role visibility
            const roleVisibilityElements = $( '.column-role_visibility span', post_row );
            const selectedRoles = [];

            roleVisibilityElements.each( function() {
                const option = $( this ).data( 'option' );
                const role = $( this ).data( 'role' );

                if ( option ) {
                    selectedRoles.push( option );
                }
                if ( role ) {
                    selectedRoles.push( role );
                }
            } );

            // Populate the select and checkboxes in quick edit
            if ( selectedRoles.length > 0 ) {
                // Set selected options for the select
                $( 'select[name="role_visibility[]"]', edit_row ).val( selectedRoles );

                // Set checkboxes based on selected roles
                selectedRoles.forEach( role => {
                    $( `input[name="role_visibility[]"][value="${ role }"]`, edit_row ).prop( 'checked', true );
                } );
            }

            // Display the roles if logged-in is chosen
            if ( selectedRoles.includes( 'logged-in' ) ) {
                $( '.quick-edit-role_visibility.roles', edit_row ).css( 'display', 'block' );
            }
        }
    };

    // Save the bulk edit fields
    $( '#bulk_edit' ).on( 'click', function ( event ) {
        const bulk_row = $( '#bulk-edit' );

        // Get the selected post ids that are being edited.
        var post_ids = [];
        var roleVisibilityOptions = [];

        // Get post IDs from the bulk_edit ID. .ntdelbutton is the class that holds the post ID.
        bulk_row.find( '#bulk-titles-list .ntdelbutton' ).each( function () {
            post_ids.push( $( this ).attr( 'id' ).replace( /^(_)/i, '' ) );
        } );       

        // Collect selected roles from the select
        const selectedRoles = $( 'select[name="role_visibility[]"]', bulk_row ).val();
        if ( Array.isArray( selectedRoles ) ) {
            roleVisibilityOptions.push( ...selectedRoles );
        } else if ( selectedRoles ) {
            roleVisibilityOptions.push( selectedRoles );
        }

        // Collect checked roles from checkboxes
        $( 'input[name="role_visibility[]"]:checked', bulk_row ).each( function() {
            roleVisibilityOptions.push( $( this ).val() );
        } );

        // Convert all post_ids to integer
        post_ids.map( function ( value, index, array ) {
            array[ index ] = parseInt( value );
        } );

        // Save the data
        $.ajax( {
            url: ajaxurl,
            type: 'POST',
            async: false,
            cache: false,
            data: {
                action: 'role_visibility_save_bulk_edit',
                post_ids: post_ids,
                role_visibility: roleVisibilityOptions.join( ', ' ),
                role_visibility_bulk_edit_nonce: role_visibility.nonce
            }
        } );
    } );

} )
