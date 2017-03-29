<?php

//if uninstall not called from WordPress exit
if ( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit ( );
}
	
if ( is_multisite( ) ) {

    $blogs = wp_list_pluck( get_sites(), 'blog_id' );

    if ( $blogs ) {
        foreach( $blogs as $blog ) {
            switch_to_blog( $blog );
            nsur_clean_database( );
        }
        restore_current_blog( );
    }
} else {
	nsur_clean_database( );
}
		
// remove all database entries for currently active blog on uninstall.
function nsur_clean_database( ) {
        delete_option( 'nsur_join_site_enabled' );
        delete_option( 'nsur_site_theme_tempate_available' );

        // user specific database entries
        delete_user_meta( get_current_user_id(), 'nsur_prompt_timeout' );
        delete_user_meta( get_current_user_id(), 'nsur_start_date' );
        delete_user_meta( get_current_user_id(), 'nsur_hide_notice' );
}

?>