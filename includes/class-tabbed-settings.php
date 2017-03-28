<?php
/**
 * Plugin tabbed settings option class for WordPress themes.
 *
 * @package   class-tabbed-settings.php
 * @version   1.2.2
 * @author    Justin Fletcher <justin@justinandco.com>
 * @copyright Copyright ( c ) 2014, Justin Fletcher
 * @license   http://opensource.org/licenses/gpl-2.0.php GPL v2 or later
 *
 * The text domain must be manually replaced with the required plugin text domain.
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Everything is pulled into this Class to allow for extendibility with including
 * new callouts that are required on a plugin by plugin basis.
 */
if ( ! class_exists( 'Extendible_Tabbed_Settings' ) ) { 
	Class Extendible_Tabbed_Settings  {

		private $handlers = array( );
		
		public function registerHandler( $handler ) {
			$this->handlers[] = $handler;
		}

		public function __call( $method, $arguments ) {
			foreach ( $this->handlers as $handler ) {
				if ( method_exists( $handler, $method ) ) {
					return call_user_func_array(
						array( $handler, $method ),
						$arguments
					);
				}
			}
		}
		
	}
}

if ( ! class_exists( 'Tabbed_Settings' ) ) {

	/**
	 * Tabbed_Settings class.
	 */
	class Tabbed_Settings extends Extendible_Tabbed_Settings {

		// the following are configurable externally
        public $menu_access_capability = '';
        public $menu_parent = '';
        public $menu = '';
        public $menu_title = '';
        public $page_title = '';
		public $default_tab_key = '';

		/**
		 * __construct function.
		 *
		 * @return void
		 */	 
		function __construct( $settings, $config ) {

			$this->register_config( $config );
			$this->register_tabbed_settings( $settings );

			// hook priority = 9 to load settings before the class-tgm-plugin-activation.php runs with the same init hook at priority level 10
			add_action( 'init', array( $this, 'init' ), 9 );
			
			add_action( 'admin_init', array( $this, 'render_setting_page' ) );

			add_action( 'admin_menu', array( $this, 'add_admin_menus' ) );

		}

		/**
		 * init function.
		 *
		 * @return void
		 */
		public function init( ) {
		
			do_action( 'tabbed_settings_register' );
			// After this point, the settings should be registered and the configuration set.
			
		}

		/**
		 * Called during admin_menu, adds rendered using the plugin_options_page method.
		 *
		 * @return void
		 */	 
		public function add_admin_menus( ) {

			add_submenu_page( $this->menu_parent, $this->page_title, $this->menu_title, $this->menu_access_capability, $this->menu, array( &$this, 'plugin_options_page' ) );
			
		}

		/**
         * perform an AND function on for the current user to have all capabilities listed within the Array
		 *		 
		 * @param array() of capabilities to perform an AND function on for the current user.
		 * @return true is the current user can do all capabilities held within the array passed.
		 */	 
		public function current_user_can_do_all( $capabilities ) {
			
			$user_has_all_caps = true;
			
			foreach ( ( array ) $capabilities as $capability ) {
				if ( ! current_user_can( $capability ) ) {
					$user_has_all_caps = false;
					break;
				}
			}
			return $user_has_all_caps;
			
		}		
		
		
        /**
		 *		 
         * Go through the tabbed_settings and limit based on current user capability.
         *
         * @param void
         */
        private function register_tabbed_settings( $settings ) {
		
			$this->settings = $settings;
			
			foreach ( $this->settings as $tab_name => $registered_setting_page ) {
			
				// remove form elements based on user capability
				if ( ( array_key_exists( 'access_capability', $registered_setting_page ) ) && _
					 ( ! $this->current_user_can_do_all( $registered_setting_page['access_capability'] ) ) ) {
					 
					// remove settings pages/tabs if user is lacking the 'access_capability'
					unset( $this->settings[$tab_name] );
					
				} else {
				
					// now remove individual settings if user is lacking the 'access_capability'
					foreach ( $this->settings[$tab_name]['settings'] as $settings_field_key => $settings_field_options ) {

						if ( ( array_key_exists( 'access_capability', $settings_field_options ) ) && ( ! $this->current_user_can_do_all( $settings_field_options['access_capability'] ) ) ) {
							unset( $this->settings[$tab_name]['settings'][$settings_field_key] );
						}
					}			
				}
			}
				
			// If the 'default_tab_key' no longer exists due to the access_capability removal of settings
			if ( ! array_key_exists( 'default_tab_key', ( array ) $this->settings ) ) {

				$current_user_settings = array_filter( $this->settings );
				
				if ( ! empty( $current_user_settings ) ) {
				
					$first_key = key( $current_user_settings );
				
					$this->default_tab_key = $first_key;
				}
			}
        }

        /**
         * Amend default configuration.  This function strips out the config array elements and stores them 
         * as a variable within the current Class object $this->"config-element" will be the means to return the 
         * current configuration stored.
         *
         * @param array $config Array of config options to pass as class properties.
		 * @return - sets up the CLASS object values $this->config.
         */
        public function register_config( $config ) {

            $keys = array( 
						'default_tab_key',
						'menu_parent',
						'menu_access_capability',
						'menu',
						'menu_title',
						'page_title',
					);

            foreach ( $keys as $key ) {
                if ( isset( $config[$key] ) ) {
                    if ( is_array( $config[$key] ) ) {
                        foreach ( $config[$key] as $subkey => $value ) {
                           $this->{$key}[$subkey] = $value;
                        }
                    } else {
                        $this->$key = $config[$key];
                    }
                }
            }
        }
		
		/**
		 * render_setting_page function.
		 *
		 * @return void
		 */
		public function render_setting_page( ){

			foreach ( $this->settings as $options_group => $section  ) {

				if ( isset( $section['settings'] ) ) {
                                        
					foreach ( $section['settings'] as $option ) {
                                            
                                            if ( isset( $option['name'] ) ) {
                                                
                                                $defaults = array(
                                                                'std' => "",
                                                                'sanitize_callback' => "",
                                                                'title' => "",
                                                                'type' => "field_default_option",
                                                                'label' => "",
                                                                );
                                                
                                                $option = wp_parse_args( $option, $defaults );

						register_setting( $options_group, $option['name'], $option['sanitize_callback'] );
						add_settings_section( $options_group, $section['title'], array( $this, 'hooks_section_callback' ), $options_group );
						add_settings_field( $option['name'].'_setting-id', $option['label'], array( $this, $option['type'] ), $options_group, $options_group, array( 'option' => $option ) );	

                                            }              
					}
				}
			}
		}
		
		/**
		 * Settings page rendering it checks for active tab and replaces key with the related
		 * settings key. Uses the plugin_options_tabs method to render the tabs.
		 *
		 * @return void
		 */
		public function plugin_options_page( ) {

			$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : $this->default_tab_key;
			if ( isset( $_GET['settings-updated'] ) ) {		
				do_action( 'tabbed_settings_after_update' );
			}
			
			?>
			<div class="wrap">
				<?php $this->plugin_options_tabs( ); ?>
				<?php $this->get_form_action( $tab ); ?>		
					<?php do_settings_sections( $tab ); ?>
					<?php submit_button( ); ?>
				</form>
			</div>
			<?php
		}

		/**
		 * Wordpress by default doesn't include a section callback to send any section descriptive text 
		 * to the form.  This function uses section description based on the current section id being processed.
		 *
		 * @param section_passed.
		 * @return void
		 */	
		public function hooks_section_callback( $section_passed ){
			foreach ( $this->settings as $options_group => $section  ) {
				if ( ( $section_passed['id'] == $options_group ) && ( ! empty( $section['description'] ) ) ) {	
					echo esc_html( $this->settings[$options_group]['description'] );	
				}
			}
		 }
		 
		/**
		 * field_checkbox_option 
		 *
		 * @param array of arguments to pass the option name to render the form field.
                 *  'name'      =   option name
                 *  'std'       =   default value to initialise the option to.                                                                           
                 *  'value'     =   value to overwrite/force the setting to.
                 *  'label'     =   left of the setting
                 *  'desc'      =   for text under the setting.
                 *  'type'      =   'field_checkbox_option'
                 *  'cb_label'  =   right of the setting
                 *  'disabled'  =   if true setting is disabled
                 * 
		 * @return void
		 */
		public function field_checkbox_option( array $args  ) {
                    
                        $defaults = array(
                                        'value' => null,
                                        'disabled' => false,
                                      );

                        $option = wp_parse_args( $args['option'], $defaults );

                        // Take value if not null
                        if( is_null( $value = $option['value'] ) ) {
                            $value = get_option( $option['name'] );
                        }
                        ?><label><input id="setting-<?php echo esc_html( $option['name'] ); ?>" name="<?php echo esc_html( $option['name'] ); ?>" type="checkbox" <?php if( $option['disabled'] ) esc_html_e('disabled="disabled"' ); ?> value="1" <?php checked( '1', $value ); ?> /> <?php echo $option['cb_label']; ?></label><?php
			if ( ! empty( $option['desc'] ) ) {
                            echo ' <p class="description">' .  $option['desc'] . '</p>';
                        }
		}

		/**
		 * field_page_select_list_option 
		 *
		 * @param array of arguments to pass the option name to render the form field.
		 * @return void
		 */
		public function field_page_select_list_option( array $args  ) {
		
			$option	= $args['option'];
                        
                        if ( array_key_exists( 'post_status', $option ) ) {
                            $post_status = $option['post_status'];
                        } else {
                            $post_status = 'publish';
                        }
			
			?><label for="<?php echo $option['name']; ?>"><?php 
			wp_dropdown_pages( array( 
									'name' => $option['name'],
									'id'         	=> 'setting-' . $option['name'],
									'echo' => 1, 
									'hierarchical'  => 0,
									'sort_order'   	=> 'ASC',
									'sort_column'  	=> 'post_title',
									'show_option_none' => _x( "- None -", 'text for no page selected', 'network-subsite-user-registration' ), 
									'option_none_value' => '0', 
									'selected' => get_option( $option['name'] ),
                                                                        'post_status'  => $post_status,
									) 
								); ?>
			</label>

			
			<?php
			if ( ! empty( $option['desc'] ) ) {
				echo ' <p class="description">' . $option['desc'] . '</p>';
                        }
		}
	
		/**
		 * field_plugin_checkbox_option 
		 *
		 * @param array of arguments to pass the option name to render the form field.
		 * @return void
		 */
		public function field_plugin_checkbox_option( array $args  ) {
		

			$option   = $args['option'];
			$filename = ( isset( $option['filename'] ) ? $option['filename'] : $option['slug'] );
			$plugin_main_file =  trailingslashit( $option['plugin_dir']. $option['slug'] ) .  $filename . '.php' ;
			$value = get_option( $option['name'] );

			if ( is_plugin_active_for_network( $option['slug'] . '/' . $filename . '.php' ) ) {
				?><label><input id="setting-<?php echo esc_html( $option['name'] ); ?>" name="<?php echo esc_html( $option['name'] ); ?>" type="checkbox" disabled="disabled" checked="checked"/> <?php
				update_option( $option['name'], false );
			} else {
				?><label><input id="setting-<?php echo esc_html( $option['name'] ); ?>" name="<?php echo esc_html( $option['name'] ); ?>" type="checkbox" value="1" <?php checked( '1', $value ); ?> /> <?php 
			}

			if ( ! file_exists( $plugin_main_file ) ) {
				echo esc_html__( 'Enable to prompt installation and force active.', 'network-subsite-user-registration' ) . ' ( ';
				if ( $value ) {
                                    echo '  <a href="' . add_query_arg( 'page', TGM_Plugin_Activation::$instance->menu, admin_url( 'themes.php' ) ) . '">' .  _x( 'Install', 'Install the Plugin', 'network-subsite-user-registration' ) . " </a> | " ;
                                }
				
			} elseif ( is_plugin_active( $option['slug'] . '/' . $option['slug'] . '.php' ) &&  ! is_plugin_active_for_network( $option['slug'] . '/' . $option['slug'] . '.php' ) ) {
				echo esc_html__( 'Force Active', 'network-subsite-user-registration' ) . ' ( ';
				if ( ! $value ) { 
                                    echo '<a href="plugins.php?s=' . esc_html( $option['label'] )	 . '">' .  _x( 'Deactivate', 'deactivate the plugin', 'network-subsite-user-registration' ) . "</a> | " ;	
                                }
			} else {
				echo esc_html__( 'Force Active', 'network-subsite-user-registration' ) . ' ( ';
			}
			echo ' <a href="http://wordpress.org/plugins/' . esc_html( $option['slug'] ) . '">' .  esc_html__( "wordpress.org", 'network-subsite-user-registration' ) . " </a> )" ;		
			?></label><?php
			if ( ! empty( $option['desc'] ) ) {
				echo ' <p class="description">' .  $option['desc']  . '</p>';
                        }
		}	

		/**
		 * field_textarea_option 
		 *
		 * @param array of arguments to pass the option name to render the form field.
		 * @return void
		 */
		public function field_textarea_option( array $args  ) {
	
			$defaults = array( 	
						'columns' => "40",
						'rows' => "3",
						);
				
			$option = wp_parse_args( $args['option'], $defaults );
			$value = get_option( $option['name'] );
			?><textarea id="setting-<?php echo esc_html( $option['name'] ); ?>" cols=<?php echo $option['columns']; ?> rows=<?php echo $option['rows']; ?> name="<?php echo esc_html( $option['name'] ); ?>" ><?php echo esc_textarea( $value ); ?></textarea><?php

			if ( ! empty( $option['desc'] ) ) {
				echo ' <p class="description">' . $option['desc'] . '</p>';
                        }
		}

		/**
		 * field_select_option 
		 *
		 * @param array of arguments to pass the option name to render the form field.
		 * @return void
		 */
		public function field_select_option( array $args  ) {
			$option   = $args['option'];
			$value = get_option( $option['name'] );
			?>
                        <select id="setting-<?php echo esc_html( $option['name'] ); ?>" class="regular-text" name="<?php echo esc_html( $option['name'] ); ?>"><?php
				foreach( $option['options'] as $key => $name ) {
					echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $name ) . '</option>';
                                }
			?>
                        </select>
                        <?php
			if ( ! empty( $option['desc'] ) ) {
				echo ' <p class="description">' . esc_html( $option['desc'] ) . '</p>';
                        }
		}
		
		/**
		 * field_roles_checkbox 
		 *
		 * @param array of arguments to pass the option name to render the form field.
		 * @return void
		 */
		public function field_roles_checkbox( array $args  ) {

			$option   = $args['option'];

			//  loop through the site roles and create a custom post for each
			global $wp_roles;
			
			if ( ! isset( $wp_roles ) ) {
				$wp_roles = new WP_Roles( );
			}

			$roles = $wp_roles->get_names( );
			unset( $wp_roles );
			
			?><ul><?php 
			asort( $roles );

			
			foreach( $roles as $role_key=>$role_name )
			{
				$id = sanitize_key( $role_key );
				$value = ( array ) get_option( $option['name'] );

				// Render the output  
				?> 
				<li><label>
				<input type='checkbox'  
					id="<?php echo esc_html( "exclude_enabled_{$id}" ) ; ?>" 
					name="<?php echo esc_html( $option['name'] ); ?>[]"
					value="<?php echo esc_attr( $role_key )	; ?>"<?php checked( in_array( $role_key, $value ) ); ?>
				>
				<?php echo esc_html( $role_name ) . " <br/>"; ?>	
				</label></li>
				<?php 
			}?></ul><?php 
			if ( ! empty( $option['desc'] ) ) {
				echo ' <p class="description">' . $option['desc'] . '</p>';
                        }
		}
		

                
                
                
                        
                
                /**
		 * field_checkbox_option 
		 *
		 * @param array of arguments to pass the option name to render the form field.
                 *  'name'      =   option name
                 *  'std'       =   default value to initialise the option to.                                                                           
                 *  'value'     =   value to overwrite/force the setting to.
                 *  'label'     =   left of the setting
                 *  'desc'      =   for text under the setting.
                 *  'type'      =   'field_checkbox_option'
                 *  'cb_label'  =   right of the setting
                 *  'disabled'  =   if true setting is disabled
                 * 
		 * @return void
		 */
		public function field_default_option( array $args  ) {
                    $defaults = array(
                                        'value' => null,
                                        'disabled' => false,
                    );

                    $option = wp_parse_args( $args['option'], $defaults );	
                    $option = $args['option'];
                    // Take value if not null
                    if( is_null( $value = $option['value'] ) ) {
                        $value = get_option( $option['name'] );
                    }                    
                    ?><input id="setting-<?php echo esc_html( $option['name'] ); ?>" class="regular-text" type="text" name="<?php echo esc_html( $option['name'] ); ?>" value="<?php esc_attr_e( $value ); ?>"  <?php if( $option['disabled'] ) esc_html_e('disabled="disabled"' ); ?> /> <?php echo $option['cb_label']; ?><?php

                    if ( ! empty( $option['desc'] ) ) {
                            echo ' <p class="description">' . $option['desc'] . '</p>';
                    }
		}
		
		/**
		 * Renders our tabs in the plugin options page,
		 * walks through the object's tabs array and prints
		 * them one by one.
		 *
		 * @return void
		 */
		public function plugin_options_tabs( ) {
		
			$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : $this->default_tab_key;

			screen_icon( );
			echo '<h2 class="nav-tab-wrapper">';
			foreach ( $this->settings as $tab_key => $tab_options_array ) {
				$active = $current_tab == $tab_key ? 'nav-tab-active' : '';
				echo '<a class="nav-tab ' . $active . '" href="?page=' . $this->menu . '&tab=' . $tab_key . '">' . $tab_options_array['title'] . '</a>';	
			}
			echo '</h2>';
		}

		/**
		 * Echos the correct form action ( <form action="XXXXX" )
		 * if the standard options are to be save in the database the following will be return:
		 *"options.php"
		 *
		 * if the admin_post_ hook is used to run code and do something special then the settings option ['form_action']
		 * provided will be used."options.php"
		 *
		 * @return void
		 */
		public function get_form_action( $tab ) {

			$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : $this->default_tab_key;
			$form_action = "options.php"; 
			screen_icon( );
			echo '<h2 class="nav-tab-wrapper">';
			foreach ( $this->settings as $tab_key => $tab_options_array ) {
				if ( $current_tab == $tab_key ) {
					if ( isset( $tab_options_array['form_action'] ) ) {
						$form_action = $tab_options_array['form_action'];
						break;
					}
				} 
			}		
				
			echo '<form method="post" action="' . $form_action . '">';
			
			if ( $form_action == "options.php" ) {
				// handle the standard settings API nonce
					settings_fields( $tab );
			} else {
				// otherwise use an explicit nonce for using with wp_verify_nonce() authentication
				wp_nonce_field( $tab, $tab . '_nonce' );
			}
		}

		/**
		 * selected_plugins function.
		 *
		 * @return array of plugins selected within the settings page for installation via the TGM_Plugin_Activation class
		 */
		public function selected_plugins( $plugin_extension_tab_name ) {

			$plugins = array( );

			if ( isset( $this->settings ) && array_key_exists( $plugin_extension_tab_name, $this->settings ) ) {

				$plugin_array = array_filter( $this->settings[$plugin_extension_tab_name]['settings'] );
                                
				foreach ( $plugin_array as $plugin ) {

					if ( get_option( $plugin['name'] ) ) {
						// change the array element key name from 'label' to 'name' for use by TGM Activation
						$plugin['option-name'] = $plugin['name'];
						$plugin['name'] = $plugin['label'];
						unset( $plugin['label'] );
						$plugins[] = $plugin;
					}
				}

			}
			
			return $plugins;
		}
	}
}