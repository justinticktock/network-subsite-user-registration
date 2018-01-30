<?php
/*
Plugin Name: Network Subsite User Registration
Plugin URI: http://justinandco.com/plugins/network-subsite-user-registration/
Description: Allows subsite user registration for a Network (multisite) installation
Version: 2.0
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

        public  $plugin_file;

            /**
             * __construct function.
             *
             * @access public
             * @return void
             */
            private function __construct() {            

                    $this->plugin_full_path = __FILE__ ;

                    $this->plugin_file = trailingslashit( basename( dirname( __FILE__ ) )) . basename( __FILE__ ) ;

                    // Load the textdomain.
                    add_action( 'plugins_loaded', array( $this, 'i18n' ), 1 );

                    // Set the constants needed by the plugin.
                    add_action( 'plugins_loaded', array( $this, 'constants' ), 1 );

                    /* Load the functions files. */
                    add_action( 'admin_menu', array( $this, 'includes' ), 9 );

                    // admin prompt and drop out with warning if not a Network
                    if ( ! is_multisite() ) {
                            add_action( 'admin_notices', array( $this, 'admin_not_a_network_notice' ));
                            return;
                    }	

                    // admin prompt and drop out with warning if not at the supported WordPress versions
                    // drop out with warning if the WP version is not supported (eg. we have no tested page template yet)
                    if ( version_compare( get_bloginfo( 'version' ), '4.7', '<') ) {
                            add_action( 'admin_notices', array( $this, 'admin_not_supported_wp_version' ));
                            return;
                    }	

                    // Load admin error message for Newtwork not allowing user registration
                    add_action( 'current_screen', array( $this, 'call_admin_user_registration_not_enabled' ) );

                    // register admin side - Loads the textdomain, upgrade routine.
                    add_action( 'admin_init', array( $this, 'admin_init' ) );

                    // register the selected-active custom post types
                    add_action( 'init', array( $this, 'init' ) );
                    
                    // whitelist plugin query variable within WordPress
                    add_action( 'query_vars', array( $this, 'query_vars' ) );                    

                    // redirect to the signup template file
                    add_action( 'parse_request', array( $this, 'parse_request' ) );                   
                    
                    // remove the ability for users to register if not selected in this plugins settings.
                    //add_action( 'current_screen', array( $this, 'remove_users_can_register' ) );            
                    add_action( 'plugins_loaded', array( $this, 'remove_users_can_register' ) );            

                    // Load admin error messages
                    add_action( 'admin_notices', array( $this, 'action_admin_notices' ) );         

                    // redirect to the sign-up page
                    add_filter( 'wp_signup_location', array( $this, 'nsur_signup_page' ) );

                    // check and add users if already on the network 
                    add_filter( 'wpmu_validate_user_signup', array( $this, 'nsur_register_existing_user' ) );
					
                    // auto register logged in user attempting to go to a different subsite admin area
                    // priority of the hook is 98 to be just before the wordpress notice.
                    add_action( 'admin_page_access_denied', array( $this, 'nsur_add_subsite_to_logged_in_user' ), 98 );

                    // auto register during a login attempt to a different Network subsite when registration is enabled
                    add_action( 'wp_login', array( $this, 'nsur_add_subsite_to_logged_in_user' ) );					
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

                    // plugin version                                    
                    $plugin_current_version = get_option( 'nsur_plugin_version' );
                    define( 'NSUR_PLUGIN_CURRENT_VERSION', $plugin_current_version);  
                    
                    $plugin_new_version =  $this->plugin_get_version();                                    
                    define( 'NSUR_PLUGIN_NEW_VERSION', $plugin_new_version);


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
             * remove the users can register if defined in settings 
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
             * If users are not logged into the Network but already registered with the Network 
             * add them to this new site after a few checks and return a list of sites
             * that they belong to.
             *
             * @return $result or drops out
             */
            public function nsur_register_existing_user( $result ) {

                    if ( is_user_logged_in() ) {
                            return $result;
                    }

                    $submitted_user_email = $result['user_email'];
                    $submitted_user_name = $result['user_name'];
                    $original_error = $result['errors'];  


                    // we are basing the existing user on the email address field
                    foreach( $original_error->get_error_codes() as $code ){


                            foreach(  $original_error->get_error_messages( $code ) as $message ){  

                                    // Find if user exists based on username from the signup form and collect their email address
                                    if( $code == 'user_name' && $message == __( 'Sorry, that username already exists!') ){                    
                                            $user = get_user_by( 'login', $submitted_user_name );
                                            $user_id = $user->ID;
                                            $existing_user_email = $user->user_email;
                                    }

                                    // Find if user exists based on email address from signup form
                                    // email is the basis of how we are adding the user
                                    if( $code == 'user_email' && $message == __( 'Sorry, that email address is already used!') ){                    
                                            $user = get_user_by( 'email', $submitted_user_email );
                                            $user_id = $user->ID;
                                    }

                                    // finalise user registration to this site and show list of sites registered
                                    // if we have an existing registered email and the user name provided has not been used
                                    // we register the email address to this site imediately
                                    if ( $user_id 
                                            && ( empty( $existing_user_email ) 
                                                 || $existing_user_email == $submitted_user_email 
                                               ) 
                                           ) {
                                            $this->nsur_add_user_to_site( $user_id );
                                            include( NSUR_MYPLUGINNAME_PATH . 'template/page-registration-notice.php' );
                                            exit();
                                    }
                            }
                    }

                    return $result;

            }

            /**
             * Add an existing user to the current Network Site
             *
             * @access public	 
             * @param $user_id			 
             * @return void
             */
            public function nsur_add_user_to_site( $user_id ) {
                    $user = get_user_by( 'id', $user_id );
                    $blog_id = get_current_blog_id();
                    add_user_to_blog( $blog_id, $user_id, get_site_option( 'default_user_role', 'subscriber' ) );
        }


			
            /**
             * Initialise the plugin by handling upgrades
             *
             * @return void
             */
            public function admin_init() {                
                    
                    //Registers user installation date/time on first use
                    $this->action_init_store_user_meta();

                    // Admin notice hide prompt notice catch
                    $this->catch_hide_notice();
                    
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

                    /* At version 1.4 we started to user rewrites so force the flush rewrites 
                     * for current installations as these will not be going through activation.
                     */
                
                    if ( version_compare( $current_plugin_version, '1.4', '<') ) {
                            flush_rewrite_rules( );                     
                    }
            }

            /**
            * Redirect To New Signup Page
            *
            * @access public	 
            * @return void
            */
            public function nsur_add_subsite_to_logged_in_user() {

                    if ( is_user_member_of_blog() ) {
                            return;
                    }
                    
                    $network_user_registration_configured = $this->network_user_registration_enabled();
                    $local_join_site_enabled = get_option( 'nsur_join_site_enabled', false );

                    if ( $network_user_registration_configured     
                         && $local_join_site_enabled
                    ) {
                            // add the user automatically 
                            $this->nsur_add_user_to_site( get_current_user_id() );

                            // then redirect back to the current page to stop WordPress default "you don't have access" 
                            // from the first attempt.
                            $parts = parse_url( home_url() );
							
							// is this missing a "/"
                            $current_uri = "{$parts['scheme']}://{$parts['host']}" . add_query_arg( NULL, NULL );    
							
							// new attempt the above is probably missing a "/"
                            $current_uri = "{$parts['scheme']}://{$parts['host']}" . add_query_arg( NULL, NULL );        
                            wp_redirect( $current_uri );
                            exit();
                    }                
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
                    // add rewrite on the frontend and amdmin side for any furture flush of the rewrites
                    // the query variable 'nsur_signup' is used
                    add_rewrite_rule( 'local-signup/?$', 'index.php?nsur_signup=true', 'top' );

                    /* 
                     * Allow for upgrade code from older versions
                     */
                    if ( version_compare( NSUR_PLUGIN_CURRENT_VERSION, NSUR_PLUGIN_NEW_VERSION, '<') )  {

                        $this->upgrade( NSUR_PLUGIN_CURRENT_VERSION );

                        // Update the option again after upgrade() changes and set the current plugin revision	
                        update_option( 'nsur_plugin_version', NSUR_PLUGIN_NEW_VERSION ); 
                    }
            }


            
            /**
             * whitelist plugin defined query variable within WordPress
             *
             * @access public	 
             * @param $query_vars
             * @return $query_vars
             */
            public function query_vars( $query_vars ) {
                    $query_vars[] = 'nsur_signup';
                    return $query_vars;
            }

            
            /**
             * Allow the WP parse request to dropout to the template file found.
             * Themes can inculde the 'page-signup.php' template to overide the plugins
             * own signup page.
             *
             * @access public	 
             * @param &$wp
             * @return void
             */
            public function parse_request( &$wp ) {
                    if ( array_key_exists( 'nsur_signup', $wp->query_vars ) ) {    
                        include( $this->get_signup_template() );
                        exit();
                    }
            }

            
            /**
             * Add code for plugin activation.
             *
             * @access public
             * @return $settings
             */	
            public function plugin_do_on_activation() {

                    // Record plugin activation date.
                    add_option( 'nsur_install_date',  time() ); 

                    flush_rewrite_rules( );

            }

            /**
             * Returns current plugin version.
             *
             * @access public
             * @return $plugin_version
             */	
            public function plugin_get_version( ) {
                
                    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
                    $plugin_data = get_plugin_data( $this->plugin_full_path );	
                    $plugin_version = $plugin_data[ 'Version' ];

                    return filter_var( $plugin_version, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION );
            }

            /**
             * Returns current plugin filename.
             *
             * @access public
             * @return $plugin_file
             */	
            public function get_plugin_file( ) {

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
            public function network_user_registration_enabled( ) {                         


                    $main_site_id = $this->get_network_main_site_id();

                    // collect the Network registration setting
                    switch_to_blog( $main_site_id );  

                    // remove the hook so that we don't limit the user regeistration and get the true network setting for the 'registration' option
                    remove_filter( 'pre_site_option_registration', array( $this, 'pre_site_option_registration' ), 10 );            
                    $network_user_registration_configured = in_array( get_site_option( 'registration' ), array( 'user', 'all' ) );
                    restore_current_blog();

                    return $network_user_registration_configured;

            }   
            
            
            /**
             * Message for Network to allow user registration requirement
             *
             * @access public
             * @return null
             */
            public function call_admin_user_registration_not_enabled( ) {                         

                    $network_user_registration_configured = $this->network_user_registration_enabled();
                    $screen = get_current_screen();               

                    if ( ! $network_user_registration_configured 
                            && ( $screen->id == 'users_page_registration-settings' ) 
                    ) {               
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


            /**
             * Return the signup template this can be overidden by the Theme
             *
             * @access public
             * @return $template, either a custom template for signup or the 
             *          template, location given within the site
             */
            public function get_signup_template( ) {     

                    /* Allow themes to override the signup template with the file 'page-signup.php'
                     * in either the parent or child theme.
                     */                
                    if ( $template_found = $this->find_custom_template( 'page-signup.php' ) ) {               
                            return $template_found;
                    }

                    /* Otherwise the plugin template is provided for the sign-up page 
                     * and this is based on the version of WordPress so we can allow for variations.
                     */
                    if ( version_compare( get_bloginfo( 'version' ), '4.9', '>=') ) {
                                $custom_page_signup_template = 'page-signup-wp49.php' ;
					} elseif ( version_compare( get_bloginfo( 'version' ), '4.7', '>=') ) {
                                $custom_page_signup_template = 'page-signup-wp47.php' ;
                    } else {
                            // else dropout with a message that we are not supporting the version of WordPress
                            wp_die( __( "The 'Network-Subsite-User-Registration' does not support your version of WordPress, therefore you will need to create a new template 'page-signup.php' "
                                    . "and add this to your theme/child-theme to act as a sign-up page .", 'network-subsite-user-registration' ) );                
                    }    

                    // overwrite the Wordpress standard login page template 'wp-signup.php'
                    return $plugin_template_file = NSUR_MYPLUGINNAME_PATH . "template/$custom_page_signup_template";

            }


            /**
             * Finds a custom template is available, if present allows the child or 
             * parent theme to override the plugin templates.
             *
             * @return   False if not found otherwise the template file location given within the site
             */
            public function find_custom_template( $template_wanted = '' ) {
     
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

                    }
                    
                    return $plugin_template_file;
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




// Plugin Activation
function nsur_activation( ) {
    $nsur = NSUR::get_instance( );
    $nsur->plugin_do_on_activation( );
}
register_activation_hook( __FILE__, 'nsur_activation' );

// Plugin De-activation
function nsur_flush_rewrites_deactivate( ) {
    // tidy up rewrites
    flush_rewrite_rules( );
}
register_deactivation_hook( __FILE__, 'nsur_flush_rewrites_deactivate' );


/*
 *  Code to run network side
 */
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