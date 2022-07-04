<?php
/**
 * Plugin Name: Network Subsite User Registration
 * Plugin URI: http://justinandco.com/plugins/network-subsite-user-registration/
 * Description: Allows subsite user registration for a Network (multisite) installation
 * Version: 3.9beta
 * Author: Justin Fletcher
 * Author URI: http://justinandco.com
 * Text Domain: network-subsite-user-registration
 * Domain Path: /languages/
 * License: GPLv2 or later
 * Network: true
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

                // admin prompt and drop out with warning if not a Network
                if ( ! is_multisite() ) {
                        add_action( 'admin_notices', array( $this, 'admin_not_a_network_notice' ));
                        return;
                }

                // Load the functions files.
                // hook earlier (with 9) to make available for user access.					
                add_action( 'admin_menu', array( $this, 'includes' ), 9 );

                // admin prompt and drop out with warning if not at the supported WordPress versions
                // drop out with warning if the WP version is not supported (eg. we have no tested page template yet)
                if ( version_compare( get_bloginfo( 'version' ), '4.7', '<') ) {
                        add_action( 'admin_notices', array( $this, 'admin_not_supported_wp_version' ));
                        return;
                }

                // Load admin error message for Newtwork not allowing user registration
                add_action( 'current_screen', array( $this, 'call_admin_user_registration_not_enabled' ) );

                // register admin side - Loads the textdomain, upgrade routine.
                add_action( 'admin_init', array( $this, 'nsur_admin_init' ) );

                // register the selected-active custom post types
                add_action( 'init', array( $this, 'init' ) );

                // whitelist plugin query variable within WordPress
                add_action( 'query_vars', array( $this, 'nsur_query_vars' ) );                    

                // redirect to the signup template file              
                add_action( 'parse_request', array( $this, 'nsur_parse_request' ) );                 

                // remove the ability for users to register if not selected in this plugins settings.
                //add_action( 'current_screen', array( $this, 'remove_users_can_register' ) );            
                add_action( 'plugins_loaded', array( $this, 'remove_users_can_register' ) );            

                // Load admin error messages
                add_action( 'admin_notices', array( $this, 'nsur_admin_notices' ) );         

                // redirect to the sign-up page
				// earlier hook to win over other plugins pointing to the standard registration page at the default priority of 10
                add_filter( 'wp_signup_location', array( $this, 'nsur_signup_page' ), 9 );
                                                                                
                // check and add users if already on the network 
                add_filter( 'wpmu_validate_user_signup', array( $this, 'nsur_wpmu_validate_user_signup' ) );

                // auto register logged in user attempting to go to a different subsite admin area
                // priority of the hook is 98 to be just before the wordpress notice.
                add_action( 'admin_page_access_denied', array( $this, 'nsur_admin_page_access_denied' ), 98 );

                // auto register during a login attempt to a different Network subsite when registration is enabled
                add_action( 'wp_login', array( $this, 'nsur_add_subsite_to_logged_in_user' ), 10, 2 );

                // This is an extra step to store the locale of the registering subsite into the User profile/preferences.
                // To store the subsite locale to the new user account request for future reference.
                add_filter( 'signup_user_meta', array( $this, 'nsur_signup_user_meta'), 10, 4 );

                // Inject code into the activation template and force the locale to follow the subsite locale
                add_action( 'activate_header', array( $this, 'nsur_switch_to_subsite_locale') );
                                                                                                
                // Inject code into the activation template to add filters during the Activation Process
                // which change the get_home_url() and get_site_url() functions force these to return
                // the subsite urls instead
                add_action( 'activate_header', array( $this, 'nsur_override_urls_during_activation') );

                // force redirect to the NSUR sign-up page.	
                add_action('init', array( $this, 'nsur_load_wp_signup') ); 

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
         * Redirect the wp-signup page url incase manually entered
         *
         * @return void
         */
        public function nsur_load_wp_signup( ) {


                if ( ! $this->is_signup_page( ) ) {
                        return;
                } 

                wp_redirect( site_url( '/local-signup' ) );
                exit;
                        
        }

        /**
         * Function to check if the current page is the user sign-up page.
         *
         * @return bool
         */
        private function is_signup_page() {

                // ref source from ..
                                // https://wordpress.stackexchange.com/questions/12863/check-if-wp-login-is-current-page
                $ABSPATH_MY = str_replace(array('\\','/'), DIRECTORY_SEPARATOR, ABSPATH);
                                
                return ( ( in_array( $ABSPATH_MY.'wp-signup.php', get_included_files( ) ) 
                                                        || in_array( $ABSPATH_MY.'wp-register.php', get_included_files( ) ) ) 
                                                        || ( isset($_GLOBALS['pagenow'] ) && $GLOBALS['pagenow'] === 'wp-signup.php' ) 
                                                        || $_SERVER['PHP_SELF']== '/wp-signup.php' );

         }

                        
        /**
         * remove the global network "users can register" if defined in the 
         * network settings this is so NSUR can control this be filtering 
         * out the 'registratoin' setting as NSUR wants.
         *
         * @return void
         */
        public function remove_users_can_register( ) {

        add_filter( 'pre_site_option_registration', array( $this, 'pre_site_option_registration' ), 10, 3 ); 

        }        

        /**
         * remote the users can register if not selected in settings. 
         *
         * @return $network_registration setting updated with account of this plugins settings.
         */
        public function pre_site_option_registration( $option, $network_id, $default ) {


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

                // allow new blog registration option through but block user registrations.
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
        public function nsur_wpmu_validate_user_signup( $result ) {

                if ( is_user_logged_in() ) {

                        return $result;
                }

                $submitted_user_email = $result['user_email'];
                $submitted_user_name = $result['user_name'];

                if ( empty( $submitted_user_email ) || empty( $submitted_user_name ) ) {
                        return $result;
                }
                                
                $original_error = $result['errors'];  
                                
                // we are basing the existing user on the email address field
                foreach( $original_error->get_error_codes() as $code ){


                        foreach(  $original_error->get_error_messages( $code ) as $message ){  

                                // Find if user exists based on username from the signup form 
                                                                // then collect the existing username's related id & email
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
                                // as if we have an existing registered email and the user name provided has not been used
                                // we register the email address to this site immediately
                                if ( $user_id 
                                        && ( empty( $existing_user_email ) 
                                                || $existing_user_email == $submitted_user_email 
                                        ) 
                                        )
                                                                {
                                        $this->nsur_add_user_to_site( $user_id );
                                                                                
                                        // Throw a page with the list of sites that the user belongs to.
                                        if ( is_user_logged_in() ) {
                                                include( $this->find_custom_template( 'page-registration-notice-logged-in.php' ) );
                                                exit();
                                        } else {
                                                include( $this->find_custom_template( 'page-registration-notice-logged-out.php' ) );
                                                exit();
                                        }
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
                add_user_to_blog( $blog_id, $user_id, get_blog_option( $blog_id, 'default_role', 'subscriber' ) );
                
        }


                
        /**
         * Initialise the plugin by handling upgrades
         *
         * @return void
         */
        public function nsur_admin_init() {                

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
         * Before user activation add 'locale' to meta data ready for use during the activation.
         *
         * Set the user locale to the same as the locale of the site registered on.
         * This is extra meta to the standard WP details so that we can use this during the
         * user account activation and therefore get the "Account Activated" message in the same 
         * locale as the subsite during the uesr frontend registration
                 *
         * @param array  $meta       Optional. Signup meta data. Default empty array.
         * @param string $user       The user's requested login name.
         * @param string $user_email The user's email address.
         * @param string $key        The user's activation key.	
         * @return meta
         */			
                
        public function nsur_signup_user_meta(  $meta, $user, $user_email, $key ) {

                $meta['locale'] = get_locale();
                return $meta;
                        
        }
                
                                                        
        /**
        * Function to override the WP standard functionality and 
        * force the returned locale to come from the subsite local settings.
        *
        * @return void
        */			
                        
        public function locale( ) {
                
                // WPLANG was defined in wp-config.
                if ( defined( 'WPLANG' ) ) {
                        $locale = WPLANG;
                }
                
                $db_locale = get_option( 'WPLANG' );
                if ( $db_locale !== false ) {
                        $locale = $db_locale;
                }
                
                if ( empty( $locale ) ) {
                        $locale = 'en_US';
                }
                
                return $locale;

        }
                
                
                
                


        /**
         * Function to change the activation urls experienced by the user through
         * collecting the origainating Blog-id from a cookie stored during user
         * activation.
         *
         * @return void
         */			
                
        public function nsur_override_urls_during_activation( ) {

                // on the activate account page redirect to the subsite home page
                // now removed as we are force to overwrite the $blog-id due to changes at WP 5.5
                        //add_filter( 'network_home_url', array( $this, 'nsur_network_home_url' ), 10, 3 );
                
                // on the activate account page redirect to the subsite login page
                // now removed as we are force to overwrite the $blog-id due to changes at WP 5.5
                        //add_filter( 'network_site_url', array( $this, 'nsur_network_site_url' ), 10, 3 );
                
                // on the activate account page WP5.5. started to use get_blog_details 
                // calls for the return url buildup.  
                // wp-activate.php line 121 ..." $blog_details = get_blog_details();"
                // No arguments are used for "get_blog_details();" 
                // this is since the activation is expected to always occur on the network main site.
                // get_blog_details() is located in ms_blogs.php line 128 and executes
                // ms_blogs.php line 175 "$blog_id = get_current_blog_id();"
                // so get_current_blog_id() is used for the activation blog id
                // and this simply returns the global $blog_id;
                // therefore we need to temporarily overwrite the $blog_id global varabile to
                // get the wp-activate.php page to at links from NSUR
                add_action( 'activate_header', array( $this, 'nsur_blog_global_overwrite' ), 10 );

                // add hook to remove the add-action hook above in a moments time at the end of the wp-activate.php page
                add_action( 'get_footer', array( $this, 'nsur_remove_override_urls_at_end_of_activation'), 10, 1 );
        }	


        /**
         * Function to unhook/return the urls calls to normal WordPress Functionality
         * now that the user activation page is complete.
         *
         * @return void
         */			
                
        public function nsur_remove_override_urls_at_end_of_activation( $name ) {

                // if on the activation page unhook
                if ( 'wp-activate' === $name ) {  

                        // unhook the $blog_is overwrite
                        remove_action( 'blog_details', array( $this, 'nsur_blog_global_overwrite' ), 10 );
                        // reset $blog_id to the expected mainsite (e.g. get_main_site_id() ) value as we are activeating the user on the
                        // main site for the network
                        global $blog_id;
                        $blog_id = get_main_site_id();
                        
                        // so far we haven't needed to unhook these.
                        //remove_filter( 'network_home_url', array( $this, 'nsur_network_home_url' ), 10 );
                        //remove_filter( 'network_site_url', array( $this, 'nsur_network_site_url' ), 10 );
                        
                }
        }	






        /**
         * Function to switch to the initiating subsite defined locale
         * Collects origainating Blog-id from a cookie stored during user
         * activation.
                 *
         * @return void
         */			

        public function nsur_switch_to_subsite_locale( ) {

                //always use the subsite previously defined locale.

                $subsite_blog_id_cookie = 'wp-nsur-blog-id-' . COOKIEHASH;
                
                if ( isset( $_COOKIE[ $subsite_blog_id_cookie ] ) ) {
                        
                        // collect cookie info stored during user activation
                        $blog_id = $_COOKIE[ $subsite_blog_id_cookie ];
                        
                        // clean up cookie 
                        list( $activate_path ) = explode( '?', wp_unslash( $_SERVER['REQUEST_URI'] ) );
                        setcookie( $subsite_blog_id_cookie, ' ', time() - YEAR_IN_SECONDS, $activate_path, COOKIE_DOMAIN, is_ssl(), true );
                        
                        // switch to the locale of the initiating subsite
                        switch_to_blog( $blog_id );
                        
                        //override the get_locale() functionality as we can't use this in its default form
                        //since subsite locale settings are not used if wp_installing() or if multisite.
                        add_filter( 'locale', array( $this, 'locale') );
                        $ms_locale = get_locale( );
                        remove_filter( 'locale', array( $this, 'locale') );
                        restore_current_blog();
                        
                        switch_to_locale( $ms_locale );
                
                } else {
                        
                        // switch to the locale of the initiating subsite
                        switch_to_blog( get_current_blog_id() );
                        //override the get_locale() functionality as we can't use this in its default form
                        //since subsite locale settings are not used if wp_installing() or if multisite.
                        add_filter( 'locale', array( $this, 'locale') );
                        $ms_locale = get_locale( );
                        remove_filter( 'locale', array( $this, 'locale') );
                        restore_current_blog();
                        
                        switch_to_locale( $ms_locale );

                }

        }	
                
                        
        /**
         * Provides an upgrade path for older versions of the plugin
         *
         * @param float $current_plugin_version the local plugin version prior to an update 
         * @return void
         */
        public function upgrade( $current_plugin_version ) {
        
                if ( wp_is_large_network() ) {
                return;  // for large networks we will take a long time 
                                // to flush_rewrite_rules so get admins to resave
                                // permalinks manually.
                }
                
                $networks = get_networks();

                if ( $networks ) {
                
                foreach( $networks as $network ) {

                        if ( ! $this->network_user_registration_enabled( $network->id ) ) {

                        Continue;
                        }

                        switch_to_blog( get_main_site_id( $network->id ) );

                        // actually this will not run if on a network where the plugin isn't activated so its a little meaningless
                        if ( ! is_plugin_active_for_network( 'network-subsite-user-registration/network-subsite-user-registration.php' ) ) {
                        restore_current_blog( );
                        Continue;
                        } else {
                        restore_current_blog( );
                        }
                                
                                

                        // get all sites on the network.
                        $sites = get_sites( array( 'network_id' => $network->id, 'public' => 1, 'deleted' => 0, 'archived' => 0 ) );  
                        foreach( $sites as $site ) {
                                

                        switch_to_blog( $site->blog_id );
                        if ( get_option( 'nsur_join_site_enabled', false ) ) {

                                flush_rewrite_rules( );   
                        }
                        restore_current_blog( );
                        
                        } // end loop of sites within a network    
                } // end loop of networks    
                }
        }

        /**
         * Add user to site if attempting to login
        *
        * @access public	 
        * @return void
        */
        public function nsur_add_subsite_to_logged_in_user( $username, $user ) {

                if ( is_user_member_of_blog( $user->ID ) ) {
                        return;
                }
                
                $network_user_registration_configured = $this->network_user_registration_enabled();
                $local_join_site_enabled = get_option( 'nsur_join_site_enabled', false );

                if ( $network_user_registration_configured     
                        && $local_join_site_enabled
                ) {
                // add the user automatically 
                        $this->nsur_add_user_to_site( $user->ID );

                $query_args = array();
                $parts = wp_parse_url( wp_get_referer() );

                if ( isset( $parts["query"] )) {
                        wp_parse_str( $parts["query"], $query_args );
                        $redirect_url = isset( $query_args["redirect_to"] )? $query_args["redirect_to"]  : '';                                                               
                }

                if ( ! empty( $redirect_url )) {
                        wp_redirect( $redirect_url );
                        exit();		
                }		
                }
        }


        /**
         * Add user to site if attempting to access a protected area ( e.g. admin )
        *
        * @access public	 
        * @return void
        */
        public function nsur_admin_page_access_denied( ) {

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

                        $parts = wp_parse_url( home_url() );
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
         * override the network_site_url() functionality when used to create the login link.
                 * we redirect to the originating sub-site for creation the login link.
         *
         * @access public	 
         * @return if not relating to 'login' this $url is unchanged 
                 * 		   otherwise $url is overriden for the subsite login link.
         */
        public function nsur_network_site_url( $url, $path, $scheme  ) {

                // "login "		    - relates to the the loging url collection
                // "login_post" 	- relates to the 'Get New Password' request
                if ( 'login' === $scheme || 'login_post' === $scheme ) {  
                                
                //collect the site ID where registration occurred.
                $subsite_blog_id_cookie = 'wp-nsur-blog-id-' . COOKIEHASH;

                if ( isset( $_COOKIE[ $subsite_blog_id_cookie ] ) ) {
                        
                        // collect cookie info stored during user activation
                        $blog_id = $_COOKIE[ $subsite_blog_id_cookie ];

                } else {
                        
                        $blog_id = get_current_blog_id();

                }

                        // override the network_site_url() functionality when used to create the login link.				
                        $url = get_site_url( $blog_id, $path) ;						


                }

                return $url ;
                  
                  
        }       


        /**
         * override the network_home_url() functionality when used to create the return to home link.
                 * we redirect to the originating sub-site during activation on the main site.
         *
         * @access public	 
         * @return if not relating to 'login' this $url is unchanged 
                 * 		   otherwise $url is overriden for the subsite home link.
         */
        public function nsur_network_home_url( $url, $path, $orig_scheme  ) {

                // $orig_scheme is not used on the wp-activate.php template so we can't use this.
                
                //collect the site ID where registration occurred.
                $subsite_blog_id_cookie = 'wp-nsur-blog-id-' . COOKIEHASH;

                if ( isset( $_COOKIE[ $subsite_blog_id_cookie ] ) ) {
                        
                        // collect cookie info stored during user activation
                        $blog_id = $_COOKIE[ $subsite_blog_id_cookie ];
                
                } else {
                
                        $blog_id = get_current_blog_id();
                        
                }

                // override the network_home_url() functionality when used to create the login link.				
                $url = get_home_url( $blog_id, $path) ;		
                                                
                return $url ;

                
        }



        /**
         * override the network_home_url() functionality when used to create the return to home link.
         * we redirect to the originating sub-site during activation on the main site.
         *
         * @access public	 
         * @return if not relating to 'login' this $url is unchanged 
         * 		   otherwise $url is overriden for the subsite home link.
         */
        public function nsur_blog_global_overwrite( $details ) {

                global $blog_id;

                // this code is associated with a hook to the activation process on the Network.
                // main site.
                
                //collect the site ID where registration occurred.
                $subsite_blog_id_cookie = 'wp-nsur-blog-id-' . COOKIEHASH;

                if ( isset( $_COOKIE[ $subsite_blog_id_cookie ] ) ) {
                        
                        // collect cookie info stored during user activation
                        // and overwrite the global $blog_id
                        $blog_id = $_COOKIE[ $subsite_blog_id_cookie ];

                } 
                        
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
        public function nsur_query_vars( $query_vars ) {
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
        public function nsur_parse_request( &$wp ) {
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
        public function nsur_admin_notices() {

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
                                <div class="notice notice-warning is-dismissible">

                                        <p>
                                        <?php esc_html(printf( __("You've been using <b><strong>'Network-Subsite-User-Registration'</strong></b'> for more than %s.  How about giving it a review by logging in at wordpress.org ?", 'network-subsite-user-registration'), human_time_diff( $plugin_user_start_date ) ) ); ?>
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
                                <?php esc_html_e( __("The 'Network-Subsite-User-Registration' plugin only functions on WordPress Multisite.", 'network-subsite-user-registration' ) ); ?>
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
                <div class="notice notice-warning is-dismissible">
                                <p>
                                <?php esc_html_e( __("The 'Network-Subsite-User-Registration' plugin currently only supports WordPress version 4.7 upwards.", 'network-subsite-user-registration' ) ); ?>
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
                <div class="notice notice-warning is-dismissible">
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
         * 
         * @param int    $NetworkID    An ID for the Network.
         * 
         * @return null
         */
        public function network_user_registration_enabled( $NetworkID = null ) {                         

                // remove the hook so that we don't limit the user regeistration and get the true network setting for the 'registration' option
                remove_filter( 'pre_site_option_registration', array( $this, 'pre_site_option_registration' ), 10 );   
                
                // now that the filter to override against the network 
                // 'registration' option value has been is removed
                // we can get the actual get_network_option( 'registration' ) setting.
                $network_user_registration_configured = in_array( get_network_option( $NetworkID, 'registration' ), array( 'user', 'all' ) );

                // re-add the removed filter back
                add_filter( 'pre_site_option_registration', array( $this, 'pre_site_option_registration' ), 10, 3 ); 
                
                
                // restore_current_blog();
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
                if ( version_compare( get_bloginfo( 'version' ), '6.0', '>=') ) {
                        $custom_page_signup_template = 'page-signup-wp60.php' ;
                                } elseif ( version_compare( get_bloginfo( 'version' ), '5.9', '>=') ) {				
						$custom_page_signup_template = 'page-signup-wp59.php' ;
                                } elseif ( version_compare( get_bloginfo( 'version' ), '5.8', '>=') ) {						
                        $custom_page_signup_template = 'page-signup-wp58.php' ;
                                } elseif ( version_compare( get_bloginfo( 'version' ), '5.7', '>=') ) {	
                        $custom_page_signup_template = 'page-signup-wp57.php' ;
                                } elseif ( version_compare( get_bloginfo( 'version' ), '5.6', '>=') ) {	
                        $custom_page_signup_template = 'page-signup-wp56.php' ;
                                } elseif ( version_compare( get_bloginfo( 'version' ), '5.5', '>=') ) {					
                        $custom_page_signup_template = 'page-signup-wp55.php' ;
                                } elseif ( version_compare( get_bloginfo( 'version' ), '5.4', '>=') ) {
                        $custom_page_signup_template = 'page-signup-wp54.php' ;
                                } elseif ( version_compare( get_bloginfo( 'version' ), '5.3', '>=') ) {
                        $custom_page_signup_template = 'page-signup-wp53.php' ;
                                } elseif ( version_compare( get_bloginfo( 'version' ), '5.1', '>=') ) {
                        $custom_page_signup_template = 'page-signup-wp51.php' ;
                                } elseif ( version_compare( get_bloginfo( 'version' ), '5.0', '>=') ) {
                        $custom_page_signup_template = 'page-signup-wp5.php' ;
                                } elseif ( version_compare( get_bloginfo( 'version' ), '4.9.6', '>=') ) {
                        $custom_page_signup_template = 'page-signup-wp496.php' ;
                                } elseif ( version_compare( get_bloginfo( 'version' ), '4.9', '>=') ) {
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

/**
 * Store originating blog_id to a cookie for the confirmation page (wp-activate.php) to follow correctly for the user.
 * it has been found necessary to break this code out of the NSUR class to fire in the right way.
 */
add_action( 'wpmu_activate_user' , 'my_hook_to_new_user', 10, 3 );

function my_hook_to_new_user (  $user_id, $password, $meta ) {

	// storing originating locale to user meta for future use
	$activation_subsite_locale = $meta['locale'] ; // this is the sub-site's locale 
	// set the user locale to the same as the locale of the site registered on.
	update_user_meta( $user_id, 'locale', $activation_subsite_locale );

	// store originating blog_id to a cookie for the confirmation page (wp-activate.php) to follow correctly for the user.
	// require( dirname(__FILE__) . '/wp-load.php' );				
	list( $activate_path ) = explode( '?', wp_unslash( $_SERVER['REQUEST_URI'] ) );

	$subsite_blog_id_cookie = 'wp-nsur-blog-id-' . COOKIEHASH;
	$subsite_blog_id = get_current_blog_id();
	setcookie( $subsite_blog_id_cookie, $subsite_blog_id, 0, $activate_path, COOKIE_DOMAIN, is_ssl(), true );

}



/*
 *  Code to run network side
 */
if ( is_multisite( ) ) {
	if ( get_blog_option( $blog_id, 'nsur_join_site_enabled', false ) ) {
		add_action( 'wpmu_activate_user', 'nsur_activate_user', 10, 3 );
	} else {
		remove_action( 'wpmu_activate_user', 'nsur_activate_user', 10 );
	}
	// add user to the site
	function nsur_activate_user( $user_id, $password, $meta )  {

		global $blog_id;
		$nsur_activate_user_default_role = get_blog_option( $blog_id, 'default_role', 'subscriber' );

		/**
		 * Filters the default role asigned to a new user.
		 *
		 * @param int   $blog_id  Blog ID.
		 * @param int   $user_id  User ID.
		 * @param array $meta     Signup meta data.
		 */
		$nsur_activate_user_default_role = apply_filters( 'nsur_activate_user_default_role', $nsur_activate_user_default_role, $blog_id, $user_id, $meta );

		add_user_to_blog( $blog_id, $user_id, $nsur_activate_user_default_role );
	}
}
