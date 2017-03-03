<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


// Append new links to the Plugin admin side

add_filter( 'plugin_action_links_' . NSUR::get_instance( )->plugin_file , 'nsur_plugin_action_links' );

function nsur_plugin_action_links( $links ) {

	$network_subsite_user_registration = NSUR::get_instance( );

	$settings_link = '<a href="options-general.php?page=' . $network_subsite_user_registration->menu . '">' . __( 'Settings' ) . "</a>";
	array_push( $links, $settings_link );
	return $links;
}


// add action after the settings save hook.
add_action( 'tabbed_settings_after_update', 'nsur_after_settings_update' );

function nsur_after_settings_update( ) {

	//RBHN_Capabilities::nsur_add_role_caps( );		// Add the selected role capabilities for use with the role help notes
	//RBHN_Capabilities::nsur_clean_inactive_capabilties( );	// remove the inactive role capabilities
	
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

		$network_subsite_user_registration = NSUR::get_instance( );
		
		$config = array(
				'default_tab_key' => 'nsur_general',					// Default settings tab, opened on first settings page open.
				'menu_parent' => 'users.php',    					
				'menu_access_capability' => 'promote_users',    			// menu capability make this the lowest of all 'access_capability' defined in the settings array.
				'menu' => $network_subsite_user_registration->menu,    					
				'menu_title' => $network_subsite_user_registration->menu_title,    		
				'page_title' => $network_subsite_user_registration->page_title,    		
				);
				
           
		$settings = 	apply_filters( 'nsur_settings', 
									array(								
										'nsur_general' => array(
											'access_capability' => 'promote_users',
											'title' 		=> __( 'General', 'network-subsite-user-registration' ),
											'description' 	=> __( 'Settings to allow the public to register with this site.', 'network-subsite-user-registration' ),
											'settings' 		=> array(		
                                                                                                            array(
                                                                                                                    'name' 		=> 'nsur_join_site_enabled',
                                                                                                                    'std' 		=> false,
                                                                                                                    'label' 	=> __( 'Allow Users to Register', 'network-subsite-user-registration' ),
                                                                                                                    'desc'		=> __( "Enabling this option allows the public to register with this local site. The 'subscriber' role will be granted (note the Network settings should have user registration allowed).", 'network-subsite-user-registration' ),
                                                                                                                    'type'          => 'field_checkbox_option'
                                                                                                                    ),
                                                                                                        ),
										),
									) );


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