<?php
/**
 * WordPress custom login form notices class.
 * 
 * Originally designed for TwitchPress systems by Ryan Bayne. 
 *
 * @author   Ryan Bayne
 * @category User Interface
 * @package  TwitchPress Login Extension
 * @since    1.0.0
 * 
 * @link https://gist.github.com/RyanBayne/3bc61fd4fbaa9bd7fb53f1d1350cd7c3
 */
 
 if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if( !class_exists( 'TwitchPress_Custom_Login_Notices') ) :

class TwitchPress_Custom_Login_Notices {
    public $twitchpress_login_notices = array();    
    
    public function __construct() {
        add_filter( 'login_message', array( $this, 'build_notices'), 8 );        
    }
    
    /**
    * Adds new error to custom array which we output above the
    * login form in a custom way. It's all a hack but clean enough.
    * 
    * @param mixed $message
    * 
    * @version 1.0
    */
    public function add_error( $message ) {     
        $this->twitchpress_login_notices[] = array( 
            'type' => 'error', 
            'message' => $message 
        );
    }
    
    /**
    * Build the block of HTML notices that will be output
    * above login form.
    * 
    * @version 1.0
    */
    public function build_notices( $message ) {
        
        foreach( $this->twitchpress_login_notices as $key => $error ) {
            if( $error['type'] == 'info' ) {
                $message .= "<p class='message'>" . esc_html( $error['message'] ) . "</p>";        
            } elseif( $error['type'] == 'error' ) {
                $message .= '<div id="login_error">' . esc_html( $error['message'] )  . '</div>';
            }
        } 
        
        return $message;
    }    
}             

endif;