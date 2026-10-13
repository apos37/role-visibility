/**
 * ROLE VISIBILITY: Navigation block
 *
 * Adds a Role Visibility panel to Navigation link and submenu blocks. The values are stored
 * as block attributes and applied on the front end by NavMenus::filter_block().
 */

( function ( wp ) {
    'use strict';

    var config = window.roleVisibilityNavBlock || {};

    if ( ! wp || ! wp.hooks || ! wp.blockEditor || ! wp.components || ! config.blocks ) {
        return;
    }

    var el = wp.element.createElement;
    var Fragment = wp.element.Fragment;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var PanelBody = wp.components.PanelBody;
    var SelectControl = wp.components.SelectControl;
    var CheckboxControl = wp.components.CheckboxControl;

    wp.hooks.addFilter( 'blocks.registerBlockType', 'role-visibility/nav-attributes', function ( settings, name ) {
        if ( config.blocks.indexOf( name ) === -1 ) {
            return settings;
        }

        settings.attributes = Object.assign( {}, settings.attributes, {
            rolevisibilityAccess: { type: 'string', default: '' },
            rolevisibilityRoles: { type: 'array', default: [] }
        } );

        return settings;
    } );

    var withVisibilityPanel = wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
        return function ( props ) {
            if ( config.blocks.indexOf( props.name ) === -1 ) {
                return el( BlockEdit, props );
            }

            return el( Fragment, null,
                el( BlockEdit, props ),
                el( InspectorControls, null, renderPanel( props ) )
            );
        };
    }, 'withRoleVisibilityPanel' );

    wp.hooks.addFilter( 'editor.BlockEdit', 'role-visibility/nav-panel', withVisibilityPanel );


    /**
     * Render the visibility panel for a block.
     *
     * @param {Object} props
     * @return {Object}
     */
    function renderPanel( props ) {
        var attributes = props.attributes;
        var linked = 'post-type' === attributes.kind && attributes.id;
        var current = attributes.rolevisibilityAccess || ( linked ? 'match' : 'everyone' );
        var roles = attributes.rolevisibilityRoles || [];
        var choices = config.choices.filter( function ( choice ) {
            return linked || 'match' !== choice.value;
        } );

        var children = [
            el( SelectControl, {
                key: 'access',
                label: config.accessLabel,
                value: current,
                options: choices,
                help: 'match' === current ? config.matchHelp : undefined,
                onChange: function ( value ) {
                    props.setAttributes( { rolevisibilityAccess: value === ( linked ? 'match' : 'everyone' ) ? '' : value } );
                }
            } )
        ];

        if ( 'logged-in' === current && config.roles.length ) {
            children.push( el( 'p', { key: 'roles-label' }, el( 'strong', null, config.rolesLabel ) ) );

            config.roles.forEach( function ( role ) {
                children.push( el( CheckboxControl, {
                    key: 'role-' + role.value,
                    label: role.label,
                    checked: roles.indexOf( role.value ) !== -1,
                    onChange: function ( checked ) {
                        var next = roles.filter( function ( value ) {
                            return value !== role.value;
                        } );

                        if ( checked ) {
                            next.push( role.value );
                        }

                        props.setAttributes( { rolevisibilityRoles: next } );
                    }
                } ) );
            } );
        }

        return el( PanelBody, { title: config.panelTitle, initialOpen: !! attributes.rolevisibilityAccess }, children );
    } // End renderPanel()

} )( window.wp );
