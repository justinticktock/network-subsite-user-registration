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
				
                
                
                $main_site_disable_options = is_main_site();
                
                
                //$url = die( network_admin_url('/settings.php'));
                $network_settings_link = '<a href="' . network_admin_url('/settings.php') . '">' . __( 'Network settings', 'network-subsite-user-registration' ) . "</a>";

                // XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
                
                $main_site_id = $network_subsite_user_registration->get_network_main_site_id();

                // collect the Network registration setting
                $network_user_registration_configured = null;
                if ( is_main_site() ) {
                        /* collect the main site configutaion for allowed user registration so that our settings here
                         * follows and mirrors this in the disabled setting.  To change what users see on the settings 
                         * page for the main_site they will need to change the settings of the Main Site.
                         */

                        $network_user_registration_configured = in_array( get_site_option( 'registration' ), array( 'user', 'all' ) ) ? True : false ;

                        //echo sprintf( __( 'Roles for %1$s :', 'role-includer' ), $user_profile_link );
                        $cb_label = __( '( this setting cannot be changed on this page for the main site )', 'network-subsite-user-registration' );
                        $desc = sprintf( __( '</Br></Br>( To change this setting goto the %1$s ).', 'network-subsite-user-registration' ), $network_settings_link );
                } else { 
                        /* otherwise allow the plugin settings page to define user registration.
                         */                    
                        $cb_label = _x( 'Enable', 'enable the setting option.', 'network-subsite-user-registration' ); 
                        $desc = sprintf( __( "Enabling this option allows the public to register with this local site. The 'subscriber' role will be granted "
                                . '</Br></Br>'
                                . '( note %1$s should have user registration allowed ).', 'network-subsite-user-registration' ), $network_settings_link );
                }

                
                // XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
                
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
                                                                                                                
                                                                                                                    // XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
                                                                                                                    'value'             => $network_user_registration_configured,
                                                                                                                    'label'         => __( 'Allow Users to Register', 'network-subsite-user-registration' ),
                                                                                                                    'desc'          => $desc,
                                                                                                                    'type'          => 'field_checkbox_option',                                                                                                                
                                                                                                                    'cb_label'      => $cb_label, 
                                                                                                                    'disabled'      => $main_site_disable_options,
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