<?php 
/*
Plugin Name: TwitchPress Login Extension
Version: 1.0.0
Plugin URI: http://twitchpress.wordpress.com
Description: Official TwitchPress extension for adding login and registration via Twitch.
Author: Ryan Bayne
Author URI: http://ryanbayne.wordpress.com
Text Domain: twitchpress-login
Domain Path: /languages
Copyright: © 2017 Ryan Bayne
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
 
GPL v3 

This program is free software downloaded from WordPress.org: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. This means
it can be provided for the sole purpose of being developed further
and we do not promise it is ready for any one persons specific needs.
See the GNU General Public License for more details.

See <http://www.gnu.org/licenses/>.


    Planning to create a TwitchPress extension like this one?

    Step 1: Read WordPress.org plugin development guidelines
    https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/

    Step 2: Read the TwitchPress extension development guidelines.
    Full guide coming soon!
    
    
*/

// Prohibit direct script loading
defined( 'ABSPATH' ) || die( 'Direct script access is not allowed!' );

/**
 * Check if TwitchPress is active, else avoid activation.
 **/
if ( !in_array( 'channel-solution-for-twitch/twitchpress.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

/**
 * Required minimums and constants
 */
define( 'TWITCHPRESS_LOGIN_VERSION', '3.2.0' );
define( 'TWITCHPRESS_LOGIN_MIN_PHP_VER', '5.6.0' );
define( 'TWITCHPRESS_LOGIN_MIN_WC_VER', '2.5.0' );
define( 'TWITCHPRESS_LOGIN_MAIN_FILE', __FILE__ );
define( 'TWITCHPRESS_LOGIN_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'TWITCHPRESS_LOGIN_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

if ( ! class_exists( 'TwitchPress_Login' ) ) :

    class TwitchPress_Login {
        /**
         * @var Singleton
         */
        private static $instance;        
        
        public $twitchpress_login_errors = array();
        
        /**
         * Get a *Singleton* instance of this class.
         *
         * @return Singleton The *Singleton* instance.
         * 
         * @version 1.0
         */
        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        } 
        
        /**
         * Private clone method to prevent cloning of the instance of the
         * *Singleton* instance.
         *
         * @return void
         */
        private function __clone() {}

        /**
         * Private unserialize method to prevent unserializing of the *Singleton*
         * instance.
         *
         * @return void
         */
        private function __wakeup() {}    
        
        /**
         * Protected constructor to prevent creating a new instance of the
         * *Singleton* via the `new` operator from outside of this class.
         */
        protected function __construct() {
            
            $this->define_constants();
            $this->includes();
            $this->init_hooks();            

        }

        /**
         * Define TwitchPress Login Constants.
         * 
         * @version 1.0
         */
        private function define_constants() {
            
            $upload_dir = wp_upload_dir();
            
            // Main (package) constants.
            if ( ! defined( 'TWITCHPRESS_LOGIN_ABSPATH' ) ) {    define( 'TWITCHPRESS_LOGIN_ABSPATH', __FILE__ ); }
            if ( ! defined( 'TWITCHPRESS_LOGIN_BASENAME' ) ) {   define( 'TWITCHPRESS_LOGIN_BASENAME', plugin_basename( __FILE__ ) ); }
            if ( ! defined( 'TWITCHPRESS_LOGIN_DIR_PATH' ) ) {   define( 'TWITCHPRESS_LOGIN_DIR_PATH', plugin_dir_path( __FILE__ ) ); }
                                
        }  

        /**
         * Include required files.
         * 
         * @version 1.0
         */
        public function includes() {

            if ( twitchpress_is_request( 'admin' ) ) {
                include_once( 'includes/class.twitchpress-login-uninstall.php' );
            }   
             
            include_once( 'includes/class.twitchpress-custom-login-notices.php' );       
            include_once( 'includes/function.twitchpress-login-core.php' );       
        }

        /**
         * Hook into actions and filters.
         * 
         * @version 1.0
         */
        private function init_hooks() {
        
            // Load this extension after plugins loaded, we need TwitchPress core to load first mainly.
            add_action( 'plugins_loaded',      array( $this, 'init' ), 0 );

            register_activation_hook( __FILE__, array( 'TwitchPress_Install', 'install' ) );
            
            // Do not confuse deactivation of a plugin with deletion of a plugin - two very different requests.
            register_deactivation_hook( __FILE__, array( 'TwitchPress_Uninstall', 'deactivate' ) );
        }
                      
        /**
         * Init the plugin after plugins_loaded so environment variables are set.
         * 
         * @version 1.0
         */
        public function init() {
            // Make the Users view show on the TwitchPress core plugin. 
            define( 'TWITCHPRESS_SHOW_SETTINGS_USERS', true );
            
            add_action( 'login_enqueue_scripts', array( $this, 'twitchpress_login_styles'));
            add_action( 'login_form',            array( $this, 'login_add_twitch_button'), 2 );
            add_filter( 'login_errors',          array( $this, 'change_login_errors'), 1 );
            add_action( 'login_init',            array( $this, 'login_init_process_twitch_login'), 3 );
            add_action( 'authenticate',          array( $this, 'authenticate_login_by_twitch'), 5 );     
            add_action( 'wp_login',              array( $this, 'login_success' ), 10 );
                                             
            // Add sections and settings to core pages.
            add_filter( 'twitchpress_get_sections_users', array( $this, 'settings_add_section_users' ) );
            add_filter( 'twitchpress_get_settings_users', array( $this, 'settings_add_options_users' ) );

            // Other hooks.
            add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
            
            do_action( 'twitchpress_login_loaded' );
        }
        
        /**
        * Styles for login page hooked by login_enqueue_scripts
        * 
        * @version 1.0
        */
        public function twitchpress_login_styles() {
            wp_enqueue_script('jquery');

            wp_register_style( 'twitchpress_login_extension_styles', TwitchPress_Login::plugin_url() . '/assets/css/public.css' );

            wp_enqueue_style( 'twitchpress_login_extension_styles' );
        }
        
        /**
        * Add a new section to the User settings tab.
        * 
        * @param mixed $sections
        * 
        * @version 1.0
        */
        public function settings_add_section_users( $sections ) {  
            global $only_section;
            
            // We use this to apply this extensions settings as the default view...
            // i.e. when the tab is clicked and there is no "section" in URL. 
            if( empty( $sections ) ){ $only_section = true; } else { $only_section = false; }
            
            // Add sections to the User Settings tab. 
            $new_sections = array(
                'testsection'  => __( 'Test Section', 'twitchpress' ),
            );

            return array_merge( $sections, $new_sections );           
        }
        
        /**
        * Add options to this extensions own settings section.
        * 
        * @param mixed $settings
        * 
        * @version 1.0
        */
        public function settings_add_options_users( $settings ) {
            global $current_section, $only_section;
            
            $new_settings = array();
            
            // This first section is default if there are no other sections at all.
            if ( 'testsection' == $current_section || !$current_section && $only_section ) {
                $new_settings = apply_filters( 'twitchpress_testsection_users_settings', array(
     
                    array(
                        'title' => __( 'Testing New Settings', 'twitchpress-login' ),
                        'type'     => 'title',
                        'desc'     => 'Attempting to add new settings.',
                        'id'     => 'testingnewsettings',
                    ),

                    array(
                        'desc'            => __( 'Checkbox Three', 'twitchpress-login' ),
                        'id'              => 'loginsettingscheckbox3',
                        'default'         => 'yes',
                        'type'            => 'checkbox',
                        'checkboxgroup'   => '',
                        'show_if_checked' => 'yes',
                        'autoload'        => false,
                    ),
                            
                    array(
                        'type'     => 'sectionend',
                        'id'     => 'testingnewsettings'
                    ),

                ));   
                
            }
            
            return array_merge( $settings, $new_settings );         
        }
        
        /**
         * Adds plugin action links
         *
         * @since 1.0.0
         */
        public function plugin_action_links( $links ) {
            $plugin_links = array(

            );
            return array_merge( $plugin_links, $links );
        }        

        /**
        * Initial dection and processing of the response from Twitch 
        * oAuth2 request. Involves locating and providing access to
        * an existing WP account or creating a new one.
        *
        * @version 1.0
        */
        public function login_init_process_twitch_login() {               
            
            if ( $_SERVER['REQUEST_METHOD'] == 'GET' ) {
                if( isset( $_GET['code'], $_GET['scope'], $_GET['state'] ) ) {
                
                    $kraken = new TWITCHPRESS_Kraken5_Calls();

                    // Do some basic sanitization with no logging to avoid flooding.                    
                    if( !twitchpress_validate_code( $_GET['code'] ) ) {
                        twitchpress_login_error( __( 'Your request to login via Twitch has failed because values returned by Twitch appear invalid. Please try again or report the issue.', 'twitchpress-login' ) );    
                        return false;                   
                    }
                    
                    // Generate a token.
                    $new_token = $kraken->generateToken( $_GET['code'] );    
       
                    // Confirm token returned. 
                    if( !twitchpress_was_valid_token_returned( $new_token ) ) {
                        twitchpress_login_error( __( 'Your request to login via Twitch could not be complete.', 'twitchpress-login' ) );    
                        return false;                         
                    }
                    
                    // Get the visitors Twitch details.
                    $twitch_user = $kraken->getUserObject_Authd( $new_token['token'], $_GET['code'] );
                    
                    // ['email] is required. 
                    if( !isset( $twitch_user['email'] ) ) {
                        twitchpress_login_error( __( 'Your request to login via Twitch could not be complete because no email address was returned by Twitch. You will need to join this site using the standard Registration form.', 'twitchpress-login' ) );    
                        return false;                        
                    }
                    
                    // Santization, this will happen again with WP core functions but lets take care.
                    $twitch_user['email'] = sanitize_email( $twitch_user['email'] );
                    $twitch_user['name'] = sanitize_user( $twitch_user['name'], true );
                    $twitch_user['display_name'] = sanitize_text_field( $twitch_user['display_name'] );
                    
                    // ['email_verified] is required and must be bool(true) by default.
                    if( !isset( $twitch_user['email_verified'] ) || $twitch_user['email_verified'] !== true ) {
                        twitchpress_login_error( __( 'Your request to login via Twitch was refused because your email address has not been verified by Twitch. You will need to verify your email through Twitch and then register on this site.', 'twitchpress-login' ) );    
                        return false;                        
                    } 
                    
                    // We can log the user into WordPress if they have an existing account.
                    $wp_user = get_user_by( 'email', $twitch_user['email'] );
                    
                    // If visitor does not exist in WP database by email check for Twitch history using "_id".
                    if( $wp_user === false ) {

                        $args = array(
                            'meta_key'     => 'twitchpress_id',
                            'meta_value'   => $twitch_user['_id'],
                            'count_total'  => false,
                            'fields'       => 'all',
                        ); 
                        
                        $get_users_results = get_users( $args ); 
                        
                        // We will not continue if there are more than one WP account with the same Twitch ID.     
                        // This will be a very rare situation I think so we won't get too advanced on how to deal with it, yet! 
                        if( isset( $get_users_results[1] ) ) {
                            
                            twitchpress_login_error( __( 'Welcome back to this site. Your personal Twitch ID has been found linked to two or more accounts but neither of them contain the same email address found in your Twitch account. Please access your preferred account manually. Please also report this matter so we can consider deleting one of your accounts on this site.', 'twitchpress-login' ) );    
                            return false;                            
                        
                        } elseif( $get_users_results[0] ) {
                        
                            // A single user has been found with the Twitch "_id" associated with it.
                            // We will further marry the WP account to Twitch account.
                            update_user_meta( $get_users_results[0]['ID'], 'twitchpress_email', $twitch_user['email'] );
                            update_user_meta( $get_users_results[0]['ID'], 'twitchpress_auth_time', time() );
                            
                            // Send access event emails to WP stored email address and the Twitch address.
                                                  
                            // Run authorisation
                            return $this->do_authenticate_login_by_twitch = array( $get_users_results[0]['ID'], $twitch_user['name'] );
                            return self::authenticate_login_by_twitch( $get_users_results[0]['ID'], $twitch_user['name'] );
                        }
                        
                    } else {
                        
                        return $this->do_authenticate_login_by_twitch = array( $wp_user->data->ID, $wp_user->data->user_login );

                    }
                    
                    // Arriving here means no existing user by Twitch email or Twitch user ID so we create a WP account.
                    $new_user = array(
                        'user_login'    =>  $twitch_user['name'],
                        'display_name'  =>  $twitch_user['display_name'],
                        'user_url'      =>  'http://twitch.tv/' . $twitch_user['name'],
                        'user_pass'     =>  wp_generate_password( 12, true ), 
                        'user_email'     =>  $twitch_user['email'] 
                    );
                  
                    $user_id = wp_insert_user( $new_user ) ;

                    if ( is_wp_error( $user_id ) ) {
                        twitchpress_login_error( __( 'An existing account could not be found in our database so our system attempted to create one for you, but there was a failure. This is normally a rare event so please try again and report the problem to us if it continues.', 'twitchpress-login' ) );    
                        return false;                        
                    }

                    // Store code in our new users meta.
                    update_user_meta( $user_id, 'twitchpress_code', sanitize_text_field( $_GET['code'] ) );
                    update_user_meta( $user_id, 'twitchpress_token', $new_token );
                    update_user_meta( $user_id, 'twitchpress_auth_time', time() );

                    // Now authenticate the visitor so they are logged into WP. 
                    return $this->do_authenticate_login_by_twitch = array( $user_id, $twitch_user['name'] );
                    return self::authenticate_login_by_twitch( $user_id, $twitch_user['name'] );
                    
                } elseif( isset( $_GET['error'] ) && isset( $_GET['state'] ) && strpos( $_GET['state'], 'witchpresspub' ) ) { 

                    self::oauth2_failure();
                    
                }
            }    
        }        
        /**
        * Generates notice for a refusal or failure.
        * 
        * @version 1.0
        */
        public static function oauth2_failure() {           
            
            if( !isset( $_GET['error'] ) ) {
                return;
            }

            $message = '<strong>' . __( 'Twitch Refused Request: ', 'twitchpress-login') . '</strong>';
            
            $message .= sprintf( __( 'the %s error was returned.'), $_GET['error'] );            
            
            if( isset( $_GET['description'] ) ) {
                $message .= ' ' . $_GET['description'] . '.';        
            }
            
            $login_notices = new TwitchPress_Custom_Login_Notices();
            $login_notices->add_error( $message );
            unset( $login_notices );
        }

        /**
        * Add a Twitch login button. If the user has not registered it will also
        * register them.
        * 
        * @version 1.0
        */
        public function login_add_twitch_button() {

            // Is auto login active? (sends visitors straight to Twitch oAuth2)
            $do_autologin = false;
            $temp_option_autologin = false;

            // Generate oAuth2 URL.
            $kraken = new TWITCHPRESS_Kraken5_Interface();
            $kraken_permitted_scopes = $kraken->get_global_accepted_scopes();
            $states_array = array();
            
            $authUrl = $kraken->generate_authorization_url_public( $kraken_permitted_scopes, $states_array );
             
            // Lets make sure TwitchPress app is setup properly else do not display button/link.
            $is_app_set = $kraken->is_app_set();
            if( !$is_app_set ) {
                return;
            }

            // Auto-in via Twitch - all traffic going to wp-login.php is wp_redirect() to an oAuth2 URL 
            if ( $temp_option_autologin ) {
                
                // Respect the option unless GET params mean we should remain on login page (e.g. ?loggedout=true)
                if (count($_GET) == (isset($_GET['redirect_to']) ? 1 : 0) 
                                        + (isset($_GET['reauth']) ? 1 : 0) 
                                        + (isset($_GET['action']) && $_GET['action']=='login' ? 1 : 0)) {
                    $do_autologin = true;
                }
                
                if (isset($_POST['log']) && isset($_POST['pwd'])) { // This was a WP username/password login attempt
                    $do_autologin = false;
                }
                
            }
            
            if ( $do_autologin ) {
                
                if ( !headers_sent() ) {
                    
                    wp_redirect($authUrl);
                    exit;
                    
                } else { ?>
                
                    <p><b><?php printf( __( 'Redirecting to <a href="%s">%s</a>...' , 'twitchpress-login'), $authUrl, __( 'Login via Twitch', 'twitchpress' ) ); ?></b></p>
                    <script type="text/javascript">
                    window.location = "<?php echo $authUrl; ?>";
                    </script>
                    
                <?php 
                }
            }
            ?>
            
            <p class="twitchpresslogin"> 
                <a href="<?php echo $authUrl; ?>"><?php echo esc_html( $this->get_login_button_text() ); ?></a>
            </p>
            
            <script>
            jQuery(document).ready(function(){
                <?php ob_start(); /* Buffer javascript contents so we can run it through a filter */ ?>
                
                var loginform = jQuery('#loginform,#front-login-form');
                var googlelink = jQuery('p.twitchpresslogin');
                var poweredby = jQuery('p.twitchpresslogin-powered');

                    loginform.prepend("<h3 class='twitchpresslogin-or'><?php esc_html_e( 'or' , 'twitchpress-login'); ?></h3>");

                if (poweredby) {
                    loginform.prepend(poweredby);
                }
                loginform.prepend(googlelink);

                <?php 
                    $fntxt = ob_get_clean(); 
                    echo apply_filters('twitchpress_login_form_readyjs', $fntxt);
                ?>
            });
            </script>
        
        <?php     
        }
    
        /**
        * Authenticate the visitor using a user_id
        * 
        * @param mixed $user_id
        * @returns boolean false if authentication rejected else does exit
        * 
        * @version 1.0
        */
        public function authenticate_login_by_twitch() {
        
            if( !isset( $this->do_authenticate_login_by_twitch ) ) {
                return false;
            }
            
            if ( $_SERVER['REQUEST_METHOD'] !== 'GET' ) {
                return false;
            }
            
            // This method is only called when Twitch returns a code.
            if( isset( $_GET['code'], $_GET['scope'], $_GET['state'] ) ) {
                wp_set_current_user( $this->do_authenticate_login_by_twitch[0] );
                wp_set_auth_cookie( $this->do_authenticate_login_by_twitch[0], true ); 
                do_action( 'wp_login', $this->do_authenticate_login_by_twitch[1] ); 
                return true;    
            }
            
            return false;
        }
        
        public function login_success() {
            wp_safe_redirect( get_dashboard_url() ) ;
        }

        /**
        * Change an existing login related error message by code.
        * 
        * Use login_message() to add the HTML for new messages. It's a bit of a hack!
        * 
        * @version 1.0
        */
        public function change_login_errors() {
            global $errors;
            $err_codes = $errors->get_error_codes();

            // Invalid username.
            // Default: '<strong>ERROR</strong>: Invalid username. <a href="%s">Lost your password</a>?'
            if ( in_array( 'invalid_username', $err_codes ) ) {
                return '<strong>ERROR</strong>: Invalid username 2.';
            }

            // Incorrect password.
            // Default: '<strong>ERROR</strong>: The password you entered for the username <strong>%1$s</strong> is incorrect. <a href="%2$s">Lost your password</a>?'
            if ( in_array( 'incorrect_password', $err_codes ) ) {
                return '<strong>ERROR</strong>: The password you entered is incorrect 2.';
            }

        } 
        
        /**
        * Get the text for the public Twitch login button (link styled button and not a form)
        * 
        * @returns string
        * 
        * @version 1.0
        */
        protected function get_login_button_text() {
            $login_button_text = __('Login with Twitch', 'twitchpress-login');
            return apply_filters('twitchpress_login_button_text', $login_button_text);
        } 

        /**
        * Get the sites login URL with multisite and SSL considered.
        * 
        * @returns string URL with filter available
        * 
        * @version 1.0
        */
        protected function get_login_url() {

            $login_url = wp_login_url();

            if ( is_multisite() ) {
                $login_url = network_site_url('wp-login.php');
            } 

            if (force_ssl_admin() && strtolower(substr($login_url,0,7)) == 'http://') {
                $login_url = 'https://'.substr($login_url,7);
            }

            return apply_filters( 'twitchpress_login_url', $login_url );
        } 
        
        /** Force redirect default login to page with login shortcode */
        public function redirect_login_page() {

            if ( isset( $this->db_settings_data['set_login_url'] ) ) {
                $login_url = get_permalink( absint( $this->db_settings_data['set_login_url'] ) );

                $page_viewed = basename( esc_url( $_SERVER['REQUEST_URI'] ) );

                if ( $page_viewed == "wp-login.php" && $_SERVER['REQUEST_METHOD'] == 'GET' ) {
                    wp_redirect( $login_url );
                    exit;
                }
            }
        }

        /**
         * Modify the url returned by wp_registration_url().
         *
         * @return string page url with registration shortcode.
         */
        public function register_url_func() {
            if ( isset( $this->db_settings_data['set_registration_url'] ) ) {
                $reg_url = get_permalink( absint( $this->db_settings_data['set_registration_url'] ) );

                return $reg_url;
            }
        }

        /** force redirection of default registration to custom one */
        public function redirect_reg_page() {
            if ( isset( $this->db_settings_data['set_registration_url'] ) ) {

                $reg_url = get_permalink( absint( $this->db_settings_data['set_registration_url'] ) );

                $page_viewed = basename( esc_url( $_SERVER['REQUEST_URI'] ) );

                if ( $page_viewed == "wp-login.php?action=register" && $_SERVER['REQUEST_METHOD'] == 'GET' ) {
                    wp_redirect( $reg_url );
                    exit;
                }
            }
        }
        
        /** Force redirection of default registration to the page with custom registration. */
        public function redirect_password_reset_page() {
            if ( isset( $this->db_settings_data['set_lost_password_url'] ) ) {

                $password_reset_url = get_permalink( absint( $this->db_settings_data['set_lost_password_url'] ) );

                $page_viewed = basename( esc_url( $_SERVER['REQUEST_URI'] ) );

                if ( $page_viewed == "wp-login.php?action=lostpassword" && $_SERVER['REQUEST_METHOD'] == 'GET' ) {
                    wp_redirect( $password_reset_url );
                    exit;
                }
            }
        } 
        
        /**
         * Get the plugin url.
         * @return string
         */
        public function plugin_url() {                
            return untrailingslashit( plugins_url( '/', __FILE__ ) );
        }

        /**
         * Get the plugin path.
         * @return string
         */
        public function plugin_path() {              
            return untrailingslashit( plugin_dir_path( __FILE__ ) );
        }                                                         
    }
    
    $GLOBALS['twitchpress_login'] = TwitchPress_Login::get_instance();

endif;    

