<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Append new links to the Plugin admin side
add_filter( 'plugin_action_links_' . NSUR::get_instance( )->plugin_file , 'nsur_plugin_action_links' );

function nsur_plugin_action_links( $links ) {

        $settings_link = '<a href="' . NSUR_Settings::get_instance( )->menu_parent . '?page=' . NSUR_Settings::get_instance( )->menu . '">' . __( 'Settings' ) . "</a>";
        array_push( $links, $settings_link );
        return $links;
}

                
// add action after the settings save hook.
add_action( 'tabbed_settings_after_update', 'nsur_after_settings_update' );

function nsur_after_settings_update( ) {

}

/**
 * NSUR_Settings class.
 *
 * Main Class which inits the CPTs and plugin
 */
class NSUR_Settings {
	
	// Refers to a single instance of this class.
    private static $instance = null;
	
    /**
     * __construct function.
     *
     * @access public
     * @return void
     */
    private function __construct( ) {          

    }
        
    /**
     * Creates or returns an instance of this class.
     *
     * @return   A single instance of this class.
     */
    public static function get_instance( ) {

		$config = array(
				'default_tab_key'           => 'nsur_general',			// Default settings tab, opened on first settings page open.
				'menu_parent'               => 'users.php',    					
				'menu_access_capability'    => 'promote_users',    		// menu capability make this the lowest of all 'access_capability' 
                                                                                                //defined in the settings array.
				'menu'                      => 'registration-settings',    					
				'menu_title'                => 'Registration',    		
				'page_title'                => 'User Registration',    		
				);	
                
                $custom_template = str_replace( WP_CONTENT_DIR, '', NSUR::get_instance()->find_custom_template('page-signup.php') );                

		$settings = 	apply_filters( 'nsur_settings', 
                                        array(								
                                                'nsur_general' => array(
                                                        'access_capability'     => 'promote_users',
                                                        'title' 		=> __( 'General', 'network-subsite-user-registration' ),
                                                        'description'           => __( 'Settings to allow the public to register with this site.', 'network-subsite-user-registration' ),
                                                        'settings' 		=> array(	
                                                                                        array(
                                                                                                'name'          => 'nsur_join_site_enabled',
                                                                                                'std'           => false,                                  
                                                                                                'label'         => __( 'Allow Users to Register', 'network-subsite-user-registration' ),
                                                                                                'desc'          => __( "Enabling this option allows the public to register with this local site. "
                                                                                                                        . "The 'subscriber' role will be granted.", 'network-subsite-user-registration' ),
                                                                                                'type'          => 'field_checkbox_option',                                                                                                                
                                                                                                'cb_label'      => _x( 'Enable', 'enable the setting option.', 'network-subsite-user-registration' ), 
                                                                                                ),		
                                                                                        array(
                                                                                                'name'          => 'nsur_site_theme_tempate_available',
                                                                                                'std'           => false,                                  
                                                                                                'value'         => $custom_template,      
                                                                                                'disabled'      => true,
                                                                                                'label'         => __( 'Template Found:', 'network-subsite-user-registration' ),
                                                                                                'desc'          => __( "shows if the theme has overriden by defining a 'page-signup.php' file template.", 'network-subsite-user-registration' ),
                                                                                                'type'          => 'field_default_option',                                                                                                                
                                                                                              //  'cb_label'      => _x( 'Found', 'enable the setting option.', 'network-subsite-user-registration' ), 
                                                                                                ),
                                                                            ),
                                                ),
                                        ) );

        if ( ! $custom_template ) {
                // remove the 'nsur_site_theme_tempate_available' option if none found in the theme
                unset( $settings['nsur_general']['settings'][1] );
        }
                                        
        if ( null == self::$instance ) {
            self::$instance = new Tabbed_Settings( $settings, $config );
        }
 
        return self::$instance;
 
    }
}


/**
 * NSUR_Settings_Additional_Methods class.
 */
class NSUR_Settings_Additional_Methods {

	
}

            
// Include the Tabbed_Settings class.
require_once( dirname( __FILE__ ) . '/class-tabbed-settings.php' );

// Create new tabbed settings object for this plugin..
// and Include additional functions that are required.
NSUR_Settings::get_instance( )->registerHandler( new NSUR_Settings_Additional_Methods( ) );