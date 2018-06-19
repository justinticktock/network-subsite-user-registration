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
																								'access_capability'     => 'promote_users',
																								'std'           => false,                                  
																								'label'         => __( 'Allow Users to Register', 'network-subsite-user-registration' ),
																								'desc'          => __( "Enabling this option allows the public to register with this local site.", 'network-subsite-user-registration' ),
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
																								//'cb_label'      => _x( 'Found', 'enable the setting option.', 'network-subsite-user-registration' ), 
																								),
																						array(
																								'name'          => 'default_role',
																								'access_capability'     => 'promote_users',
																								'label'         => __( 'Default Role', 'network-subsite-user-registration' ),
																								'desc'          => __( "Select the role that new users will start with after registration. </Br>". 
																														"( Note: For Safety, roles with 'edit_users' capability are not selectable ).", 'network-subsite-user-registration' ),
																								'type'          => 'field_wp_dropdown_roles',
																								//'cb_label'      => _x( 'Enable', 'enable the setting option.', 'network-subsite-user-registration' ), 
																								),	
																				),
													),								
											) );

			// remove the 'nsur_site_theme_tempate_available' option if none found in the theme
			if ( ! $custom_template ) {
					unset( $settings['nsur_general']['settings'][1] );
			}

			// remove the 'default_role' option if 'nsur_join_site_enabled is false
            if ( get_blog_option( get_current_blog_id( ), 'nsur_join_site_enabled', false ) == false ) {
					unset( $settings['nsur_general']['settings'][2] );
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

		/**
		 * field_role_select_list_option 
		 *
		 * @param array of arguments to pass the option name to render the form field.
		 * @access public
		 * @return void
		 */
		public function field_wp_dropdown_roles( array $args  ) {

				$option	= $args['option'];

				?><select
						id="setting-<?php echo esc_html( $option['name'] );?>" 
						name="<?php echo esc_html( $option['name'] );?>"
				>
				<?php
				$local_site_default_role = get_option( 'default_role' );
				wp_dropdown_roles( $local_site_default_role ); 
				?>
				</select>
	
				<?php 
				if ( ! empty( $option['desc'] )) {
					echo ' <p class="description">' . $option['desc'] . '</p>';		
				}
		}
		
		
		
		public function do_on_settings_page( ) {
			
				add_filter( 'editable_roles', array( $this, 'exclude_roles_from_user' ) );
				
		}

		/**
		 * Used to remove roles from the wp_dropdown_roles() pulldown option on the settings page
		 *
		 * @param   Array of editable_roles
		 * @return  Array of editable_roles
		 */
		public function exclude_roles_from_user( $editable_roles ) {

				global $wp_roles;
				
				$caps_not_allowed_in_selecting_roles = array('edit_users', 'promote_users');
				
				if ( ! isset( $wp_roles ) ) {
						$wp_roles = new WP_Roles( );
				}

				$roles_with_restricted_cap = array( );
			 
				/* Loop through each role object because we need to check the caps for $caps_not_allowed_in_selecting_roles. */
				foreach ( $wp_roles->role_objects as $key => $role ) {
					
						/* Roles without capabilities will cause an error, so we need to check if $role->capabilities is an array. */
						if ( is_array( $role->capabilities ) ) {

								/* Loop through the next role's capabilities to find if the $caps_not_allowed_in_selecting_roles is set. */
								foreach ( $role->capabilities as $cap => $grant ) {
										if ( in_array($cap, $caps_not_allowed_in_selecting_roles ) && $grant ) {
												 $roles_with_restricted_cap[] = $key;
												 break;
										}
								}
						}
				}        

				// now we have gathered all roles that are still allowed so now we will find the 
				// inverse to get an array of roles to be excluded
				$roles_all = array_keys( $wp_roles->get_names( ) );

				if ( $roles_all != $roles_with_restricted_cap ) {

						// find roles not allowed for the current user
						$excluded_roles = array_diff( $roles_all, $roles_with_restricted_cap );

						// exclude roles from $editable_roles
						foreach ( $roles_with_restricted_cap as $role_key_exclude ) {
							
							
								unset ( $editable_roles[$role_key_exclude] );
						}
					//	die(var_dump($role_key_exclude));
				}
				
				return $editable_roles;
		}
}

// Include the Tabbed_Settings class.
require_once( dirname( __FILE__ ) . '/class-tabbed-settings.php' );

// Create new tabbed settings object for this plugin..
// and Include additional functions that are required.
NSUR_Settings::get_instance( )->registerHandler( new NSUR_Settings_Additional_Methods( ) );