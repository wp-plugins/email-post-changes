<?php

/*
Plugin Name: Email Post Changes
Description: Whenever a change to a post or page is made, those changes are emailed to the users and email addresses you specify.
Plugin URI: http://wordpress.org/extend/plugins/email-post-changes/
Version: 1.3-beta
Author: Michael D Adams
Author URI: http://blogwaffe.com/
*/

require_once 'class.email-post-changes.php';

function email_post_changes_action_links( $links ) {
	array_unshift( $links, '<a href="options-general.php?page=' . Email_Post_Changes::ADMIN_PAGE . '">' . __( 'Settings' ) . "</a>" );
	return $links;
}

add_action( 'init', array( 'Email_Post_Changes', 'init' ) );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'email_post_changes_action_links' );
