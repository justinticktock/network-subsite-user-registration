<?php
/*
Plugin Name: Network Subsite User Registration
Plugin URI: http://justinandco.com/plugins/network-subsite-user-registration/
Description: Allows subsite user registration for a Network (multisite) installation
Version: 1.0
Author: Justin Fletcher
Author URI: http://justinandco.com
Text Domain: network-subsite-user-registration
Domain Path: /languages/
License: GPLv2 or later
Network: true
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Network Subsite User Reqistration class.
 *
 * Main Class which inits the plugin
 */
class NSUR {

    // Refers to a single instance of this class.
    private static $instance = null;
	
    public  $plugin_full_path;
    public  $plugin_file = 'network-subsite-user-registration/network-subsite-user-registration.php';
	
    // Settings page slug	
    public  $menu = 'registration-settings';
	
    // Settings Admin Menu Title
    public  $menu_title = 'Registration';

    // menu item
    public  $menu_page = 'registration.php';
    
    // Settings Page Title
    public  $page_title = 'User Registration';
    
	/**
	 * __construct function.
	 *
	 * @access public
	 * @return void
	 */
	private function __construct() {            

                $this->plugin_full_path = plugin_dir_path(__FILE__) . 'network-subsite-user-registration.php' ;

                // Load the textdomain.
                add_action( 'plugins_loaded', array( $this, 'i18n' ), 1 );

                // Set the constants needed by the plugin.
                add_action( 'plugins_loaded', array( $this, 'constants' ), 1 );

                /* Load the functions files. */
                add_action( 'plugins_loaded', array( $this, 'includes' ), 2 );

                // admin prompt and drop out with warning if not a Network
                if ( ! is_multisite() ) {
                        add_action( 'admin_notices', array( $this, 'admin_not_a_network_notice' ));
                        return;
                }	

                // admin prompt and drop out with warning if not at the supported WordPress versions
                $wp_version = (float)get_bloginfo( 'version' );
                // drop out with warning if the WP version is not supported (eg. we have no tested page template yet)
                if ( $wp_version <  4.7 ) {
                        add_action( 'admin_notices', array( $this, 'admin_not_supported_wp_version' ));
                        return;
                }	

                // Load admin error message for Newtwork not allowing user registration
                add_action( 'current_screen', array( $this, 'call_admin_user_registration_not_enabled' ) );

                // register admin side - Loads the textdomain, upgrade routine and menu item.
                add_action( 'admin_init', array( $this, 'admin_init' ));
             //   add_action( 'admin_menu', array( $this, 'admin_menu' ) );

                // register the selected-active custom post types
                add_action( 'init', array( $this, 'init' ));

                // remove the ability for users to register if not selected in this plugins settings.
                //add_action( 'current_screen', array( $this, 'remove_users_can_register' ) );            
                add_action( 'plugins_loaded', array( $this, 'remove_users_can_register' ) );            


                // Load admin error messages
                add_action( 'admin_notices', array( $this, 'action_admin_notices' ) );

                // allow the plugin to use templates held by the parent theme, child theme rather than the plugin
                add_filter( 'template_include', array( $this, 'template_include' ) );            

                // redirect to the sign-up page
                add_filter( 'wp_signup_location', array( $this, 'nsur_signup_page' ) );

                // check and add users if already on the network 
                add_filter( 'wpmu_validate_user_signup', array( $this, 'nsur_add_exting_user' ) );

               
	}
	
	/**
	 * Defines constants used by the plugin.
	 *
	 * @return void
	 */
	function constants() {

		// Define constants
		define( 'NSUR_MYPLUGINNAME_PATH', plugin_dir_path(__FILE__) );
		define( 'NSUR_PLUGIN_DIR', trailingslashit( plugin_dir_path( NSUR_MYPLUGINNAME_PATH )));
		define( 'NSUR_PLUGIN_URI', plugins_url('', __FILE__) );
		
		// admin prompt constants
		define( 'NSUR_PROMPT_DELAY_IN_DAYS', 30);
		define( 'NSUR_PROMPT_ARGUMENT', 'nsur_hide_notice');
    
	}

	/**
	 * Loads the initial files needed by the plugin.
	 *
	 * @return void
	 */
	function includes() {	

		// settings 
		require_once( NSUR_MYPLUGINNAME_PATH . 'includes/settings.php' );  

	}

	/**
	 * menu_page:
	 *
	 * @return void
	 */
	public function menu_page() {
		
	}	
        
	/**
	 * remote the users can register if defined in settings 
         * and not on the main site.  If on the main site the network
         * settings will continue to define the function.
	 *
	 * @return void
	 */
	public function remove_users_can_register( ) {

            add_filter( 'pre_site_option_registration', array( $this, 'pre_site_option_registration' ), 10, 1 ); 

        }        

	/**
	 * remote the users can register if not selected in settings. 
	 *
	 * @return $network_registration setting updated with account of this plugins settings.
	 */
	public function pre_site_option_registration( $option ) {


            // remove the filter so that it executes only once
            remove_filter( 'pre_site_option_registration', array( $this, 'pre_site_option_registration' ), 10 );
            $network_registration = in_array( get_site_option( 'registration' ), array( 'none', 'user', 'blog', 'all' ) ) ? get_site_option( 'registration' ) : 'none' ;

            /*
             *  the network main site 'registration' option can be any of the following,
             * 
             * 'none' - neither user or new blogs can be registerd
             * 'all' - both user and new blogs can be registerd, 
             * 'blog' - Logged in users may register new sites, 
             * 'user' - new accounts may be registered
             */  
            
            $local_join_site_enabled = get_option( 'nsur_join_site_enabled', false );

            if ( ! $local_join_site_enabled ) {

                // allow new blog registration option through, block user registrations.
                switch ( $network_registration ) {
                        case 'none':
                        case 'user':
                                $network_registration = 'none';
                                break;                    
                        case 'all':                            
                        case 'blog':
                                // we are allowing uesrs to register sites if the network settings are configured for it.
                                $network_registration = 'blog';
                                break;       
                }                                          
            }
            
            return $network_registration;
	}   


	/**
	 * If users are already registered with the Network then simply add them
         * to this new site. 
	 *
	 * @return $result or drops out
	 */
        public function nsur_add_exting_user( $result ) {
            
            if ( is_user_logged_in() ) {
                return $result;
            }
            
            $submitted_user_email = $result['user_email'];
            $original_error = $result['errors'];  

              foreach( $original_error->get_error_codes() as $code ){
                 foreach(  $original_error->get_error_messages( $code ) as $message ){  
                       if( $code != 'user_email' && $message == __( 'Sorry, that username already exists!') ){                    
                            $user = get_user_by( 'email', $submitted_user_email );
                            $user_id = $user->ID;
                            $blog_id = get_current_blog_id();
                            add_user_to_blog( $blog_id, $user_id, get_site_option( 'default_user_role', 'subscriber' ) );
                            $user_blogs = get_blogs_of_user( $user_id );

                            $user_blogs_sorted = array();
                            foreach ( $user_blogs AS $user_blog ) {
                                    $user_blogs_sorted[ $user_blog->blogname ] = $user_blog->siteurl;
                            }

                            // A real quick way to do a case-insensitive sort of an array keyed by strings: 
                            uksort($user_blogs_sorted , "strnatcasecmp");

                            $html = "<h1>Hi " . $submitted_user_email . " you have been added to this site, your current sites on the Network are: </h1></Br>";
                            $html .= "<ul>";
                            foreach ( $user_blogs_sorted AS $sitename => $siteurl ) {
                                if ( ! is_main_site( $user_blog->userblog_id ) ) {
                                            $html .=  '<li><h2><strong><a href="' . wp_login_url($siteurl )   . '" target="_blank" >' . $sitename  . '</a></strong></h2></li>';
                                    }
                            }
                            $html .= "</ul>";    

                            wp_die($html);

                       }
                 }
            }   

            return $result;  

        }
        
	/**
	 * Initialise the plugin by handling upgrades
	 *
	 * @return void
	 */
	public function admin_init() {
            
            $plugin_current_version = get_option( 'nsur_plugin_version' );
            $plugin_new_version =  $this->plugin_get_version();


            // Admin notice hide prompt notice catch
            $this->catch_hide_notice();

            if ( empty($plugin_current_version) || $plugin_current_version < $plugin_new_version ) {

                $plugin_current_version = isset( $plugin_current_version ) ? $plugin_current_version : 0;

                $this->upgrade( $plugin_current_version );

                // set default options if not already set..
                $this->plugin_do_on_activation();

                // Update the option again after upgrade() changes and set the current plugin revision	
                update_option('nsur_plugin_version', $plugin_new_version ); 
            }

	}

	/**
	 * Loads the text domain.
	 *
	 * @return void
	 */
	public function i18n( ) {
		$ok = load_plugin_textdomain( 'network-subsite-user-registration', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/');                
	}
        

	/**
	 * Provides an upgrade path for older versions of the plugin
	 *
	 * @param float $current_plugin_version the local plugin version prior to an update 
	 * @return void
	 */
	public function upgrade( $current_plugin_version ) {
		
		// move current database stored values into the next structure
		if ( $current_plugin_version < '0.00001' ) {

		}
	}
	
         /**
	 * Redirect To New Signup Page
	 *
	 * @access public	 
	 * @return void
	 */
	public function nsur_redirect_signup_redirect() {
                wp_redirect( get_site_url() );
                exit();
        }
        

         /**
	 * redirect to the sign-up page
	 *
	 * @access public	 
	 * @return void
	 */
	public function nsur_signup_page( $url ) {

            return site_url('/local-signup/') ;
        }
        

	/**
	 * Registers all required code
	 *
	 * @access public	 
	 * @return void
	 */
	public function init() {

	}
        
        
	/**
	 * Add code for plugin activation.
	 *
	 * @access public
	 * @return $settings
	 */	
	public function plugin_do_on_activation() {

		// Record plugin activation date.
		add_option('nsur_install_date',  time() ); 
		
		// create the plugin_version store option if not already present.
		$plugin_version = $this->plugin_get_version();
		update_option('nsur_plugin_version', $plugin_version ); 

		//flush_rewrite_rules();
	}

	/**
	 * Returns current plugin version.
	 *
	 * @access public
	 * @return $plugin_version
	 */	
	public function plugin_get_version() {
            
		$plugin_data = get_plugin_data( $this->plugin_full_path );	
                
		$plugin_version = $plugin_data['Version'];
                
		return filter_var($plugin_version, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
	}
	
	/**
	 * Returns current plugin filename.
	 *
	 * @access public
	 * @return $plugin_file
	 */	
	public function get_plugin_file() {
		
		$plugin_data = get_plugin_data( $this->plugin_full_path );	
		$plugin_name = $plugin_data['Name'];
		
		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		$plugins = get_plugins();
		foreach( $plugins as $plugin_file => $plugin_info ) {
			if ( $plugin_info['Name'] == $plugin_name ) return $plugin_file;
		}
		return null;
	}
		

	/**
	 * Display the admin warnings.
	 *
	 * @access public
	 * @return null
	 */
	public function action_admin_notices() {

		// Prompt for rating
                
                if ( current_user_can( 'install_plugins' ) ) {
                    $this->action_admin_rating_prompt_notices();
                }
	}
	
	/**
	 * Store the current users start date with Help Notes.
	 *
	 * @access public
	 * @return null
	 */
	public function action_init_store_user_meta() {
		
			// start meta for a user
			add_user_meta( get_current_user_id(), 'nsur_start_date', time(), true );
			add_user_meta( get_current_user_id(), 'nsur_prompt_timeout', time() + 60*60*24*  NSUR_PROMPT_DELAY_IN_DAYS, true );
	}
	
	/**
	 * Display the admin message for plugin rating prompt.
	 *
	 * @access public
	 * @return null
	 */
	public function action_admin_rating_prompt_notices( ) {

		$user_responses =  array_filter( (array)get_user_meta( get_current_user_id(), NSUR_PROMPT_ARGUMENT, true ));
		if ( in_array(  "done_now", $user_responses ) )
			return;

		if ( current_user_can( 'install_plugins' ) ) {

			$next_prompt_time = get_user_meta( get_current_user_id(), 'nsur_prompt_timeout', true );
			if ( ( time() > $next_prompt_time )) {
				$plugin_user_start_date = get_user_meta( get_current_user_id(), 'nsur_start_date', true );
				?>
				<div class="update-nag">

					<p><?php esc_html(printf( __("You've been using <b>Network Subsite User Registration</b> for more than %s.  How about giving it a review by logging in at wordpress.org ?", 'network-subsite-user-registration'), human_time_diff( $plugin_user_start_date ) ) ); ?>

					</p>
					<p>

						<?php echo '<a href="' .  esc_url(add_query_arg( array( NSUR_PROMPT_ARGUMENT => 'doing_now' )))  . '">' .  esc_html__( 'Yes, please take me there.', 'network-subsite-user-registration' ) . '</a> '; ?>

						| <?php echo ' <a href="' .  esc_url(add_query_arg( array( NSUR_PROMPT_ARGUMENT => 'not_now' )))  . '">' .  esc_html__( 'Not right now thanks.', 'network-subsite-user-registration' ) . '</a> ';?>

						<?php
						if ( in_array(  "not_now", $user_responses ) || in_array(  "doing_now", $user_responses )) {
							echo '| <a href="' .  esc_url(add_query_arg( array( NSUR_PROMPT_ARGUMENT => 'done_now' )))  . '">' .  esc_html__( "I've already done this !", 'network-subsite-user-registration' ) . '</a> ';
						}?>

					</p>
				</div>
				<?php
			}
		}
	}

        
	/**
	 * Send Error message to Amdin when a non-multisite installation.
	 *
	 * @access public
	 * @return null
	 */
	public function admin_not_a_network_notice( ) {
                // Prompt for multisite error
                ?>
                <div class="notice notice-error">
                                <p>
                                <?php esc_html_e( __("The Network Subsite User Registration plugin only functions on WordPress Multisite.", 'network-subsite-user-registration' ) ); ?>
                                </p>
                        </p>
                </div>
                <?php 
	}
        

        
	/**
	 * Send Error message to Amdin when a non-multisite installation.
	 *
	 * @access public
	 * @return null
	 */
	public function admin_not_supported_wp_version( ) {
                // Prompt for multisite error
                ?>
                <div class="notice notice-error">
                                <p>
                                <?php esc_html_e( __("The Network Subsite User Registration plugin currently only supports WordPress version 4.7 upwards.", 'network-subsite-user-registration' ) ); ?>
                                </p>
                        </p>
                </div>
                <?php 
	}        
	
	/**
	 * Message for Network to allow user registration requirement
	 *
	 * @access public
	 * @return null
	 */
	public function admin_user_registration_not_enabled( ) {
            
                $network_settings_link = '<a href="' . network_admin_url('/settings.php') . '">' . __( 'Network settings', 'network-subsite-user-registration' ) . "</a>";

                // Prompt for multisite error
                ?>
                <div class="update-nag">
                                <p>
                                <?php echo sprintf(  __('You currently have not setup your %1$s to allow user registration, please allow this before continuing.', 'network-subsite-user-registration' ), $network_settings_link ); ?>
                                </p>
                        </p>
                </div>
                <?php 
	}        
        
	/**
	 * Message for Network to allow user registration requirement
	 *
	 * @access public
	 * @return null
	 */
	public function call_admin_user_registration_not_enabled( ) {                         

            
            $main_site_id = $this->get_network_main_site_id();

            // collect the Network registration setting
            switch_to_blog( $main_site_id );  

            // remove the hook so that we don't limit the user regeistration and get the true network setting for the 'registration' option
            remove_filter( 'pre_site_option_registration', array( $this, 'pre_site_option_registration' ), 10 );            
            $network_user_registration_configured = in_array( get_site_option( 'registration' ), array( 'user', 'all' ) );
            restore_current_blog();

            $screen = get_current_screen();               

            if ( ! $network_user_registration_configured && ( $screen->id == 'users_page_registration-settings' ) ) {               
                    add_action( 'admin_notices', array( $this, 'admin_user_registration_not_enabled' ));
            }  
                   
	}                
            
        
        /*
         * Get the ID of the main site in a multisite network
         *
         * @return int The blog_id of the main site
         */
        public function get_network_main_site_id() {
                global $current_site;
                
                return $current_site->blog_id;
        }

        /*
         * Get the ID of the network
         *
         * @return int The blog_id of the main site
         */
        public function get_network_id() {
                global $current_site;
                
                return $current_site->id;
        }

	/**
	 * Store the user selection from the rate the plugin prompt.
	 *
	 * @access public
	 * @return null
	 */
	public function catch_hide_notice() {
	
		if ( isset($_GET[NSUR_PROMPT_ARGUMENT]) && $_GET[NSUR_PROMPT_ARGUMENT] && current_user_can( 'install_plugins' )) {
			
			$user_user_hide_message = array( sanitize_key( $_GET[NSUR_PROMPT_ARGUMENT] )) ;				
			$user_responses =  array_filter( (array)get_user_meta( get_current_user_id(), NSUR_PROMPT_ARGUMENT, true ));	

			if ( ! empty( $user_responses )) {
				$response = array_unique( array_merge( $user_user_hide_message, $user_responses ));
			} else {
				$response =  $user_user_hide_message;
			}
			
			check_admin_referer();	
			update_user_meta( get_current_user_id(), NSUR_PROMPT_ARGUMENT, $response );

			if ( in_array( "doing_now", (array_values((array)$user_user_hide_message ))))  {
				$next_prompt_time = time() + ( 60*60*24*  NSUR_PROMPT_DELAY_IN_DAYS ) ;
				update_user_meta( get_current_user_id(), 'nsur_prompt_timeout' , $next_prompt_time );
				wp_redirect( 'http://wordpress.org/support/view/plugin-reviews/network-subsite-user-registration' );
				exit;					
			}

			if ( in_array( "not_now", (array_values((array)$user_user_hide_message ))))  {
				$next_prompt_time = time() + ( 60*60*24*  NSUR_PROMPT_DELAY_IN_DAYS ) ;
				update_user_meta( get_current_user_id(), 'nsur_prompt_timeout' , $next_prompt_time );		
			}
				
				
			wp_redirect( remove_query_arg( NSUR_PROMPT_ARGUMENT ) );
			exit;       	
		}
	}

        

        
        public function template_include( $template ) {

            $wp_version = (float)get_bloginfo( 'version' );
            
            if ( $wp_version >=  4.7 ) {
                $custom_page_signup_template = 'page-signup-wp47.php' ;
            } else {
                // else drop out to a default template that themes/child-themes can add themselves
                $custom_page_signup_template = 'page-signup.php' ;
            }
            
            if( ( basename( $_SERVER['REQUEST_URI'] ) == 'local-signup' )  && 
                    ( $template_found = $this->find_custom_template( $custom_page_signup_template ) ) )  {
               
                        return $template_found;
            }   

            if ( $GLOBALS['pagenow'] === 'wp-login.php' && 
                    ! empty( $_REQUEST['action'] ) && 
                    $_REQUEST['action'] === 'register' && 
                    ( $template_found = $this->find_custom_template( $custom_page_signup_template ) ) )  {
                        return $template_found;
            }                      
            
            // else return the original template
            return $template;

        }


        /**
         * Finds a custom template is available, if present allows the child or 
         * parent theme to override the plugin templates.
         *
         * @return   False if not found otherwise the template file location
         */
        public function find_custom_template( $template_wanted ) {

            $plugin_template_file = false;    

            if ( locate_template( $template_wanted ) != '' ) {

                // locate_template() returns path to file
                // if either the child theme or the parent theme have overridden the template
                $plugin_template_file = locate_template( $template_wanted );

            }
             else {

                /* 
                 * If neither the child nor parent theme have overridden the explicit template 
                 * we are allowing the pluing to use it's own template file.  However if the 
                 * plugin is missing the template file itself then we would drop out of the function
                 * returning the origianl template allowing the themes template hyerarchy to resolve
                 * to an existing template.
                 */

                if ( file_exists( NSUR_MYPLUGINNAME_PATH . "template/$template_wanted" ) ) {

                    // we load the template from the 'templates' sub-directory of the directory this file is in                                
                    $plugin_template_file = NSUR_MYPLUGINNAME_PATH . "template/$template_wanted";
                }

                return $plugin_template_file;


            }
        }
          
        /**
         * Creates or returns an instance of this class.
         *
         * @return   A single instance of this class.
         */
        public static function get_instance() {

            if ( null == self::$instance ) {
                self::$instance = new self;
            }

            return self::$instance;

        }	
    }





/**
 * Init NSUR
 */
NSUR::get_instance();


// code to run
if ( is_multisite( ) ) {

    
    $blogs = wp_list_pluck( get_sites(), 'blog_id' );

    if ( $blogs ) {
        
        // add user to the site
        function nsur_activate_user( $user_id, $password, $meta )  {
            global $blog_id;
            add_user_to_blog( $blog_id, $user_id, get_site_option( 'default_user_role', 'subscriber' ) );
        }
        
        // hook into the multisite user activation and add user if nsur settings are configured to allow this.
        foreach( $blogs as $blog ) {	
            if ( get_blog_option( $blog, 'nsur_join_site_enabled', false ) ) {
				add_action( 'wpmu_activate_user', 'nsur_activate_user', 10, 3 );
			}			            
        }

    }
}
