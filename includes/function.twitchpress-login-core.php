<?php
/**
 * TwitchPress Login Extension - Core Functions

 * @author   Ryan Bayne
 * @category Core
 * @package  TwitchPress Login Extension
 * @since    1.0.0
 */
 
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if( !function_exists( 'twitchpress_login_error' ) ) {
    /**
    * Use the TwitchPress Custom Login Notices class to generate a 
    * a new notice on the login scree
    * 
    * @param mixed $message
    * 
    * @version 1.0
    */
    function twitchpress_login_error( $message ) {
        $login_notices = new TwitchPress_Custom_Login_Notices();
        $login_notices->add_error( $message );
        unset( $login_notices );                 
    }
}
