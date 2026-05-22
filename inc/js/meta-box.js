jQuery( $ => {
    // console.log( 'Role Visibility Meta Box JS Loaded...' );

    function updateRoleVisibility() {
        var style = $( '#role-visibility-select' ).val() === 'logged-in' ? 'inline' : 'none';
        $( '.role_visibility-selection' ).css( 'display', style );
    }

    // Initial check
    updateRoleVisibility();

    // Bind change event
    $( '#role-visibility-select' ).on( 'change', updateRoleVisibility );
} )