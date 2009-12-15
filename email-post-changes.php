<?php

/*
Plugin Name: Email Post Changes
Description: Whenever a change to a post or page is made, those changes are emailed to the blog's admin.
Author: Michael D Adams
Version: 0.1
Author URI: http://blogwaffe.com/
*/

class Email_Post_Changes {
	var $left_post;
	var $right_post;

	var $text_diff;

	function &init() {
		static $instance = null;

		if ( $instance )
			return $instance;

		$class = __CLASS__;
		$instance = new $class;

		add_action( 'wp_insert_post', array( &$instance, 'wp_insert_post' ), 10, 2 );
	}

	function wp_insert_post( $post_id, $post ) {
		if ( 'revision' == $post->post_type ) { // Revision is save first
			if ( wp_is_post_autosave( $post ) )
				return;
			$this->left_post = $post;
		} elseif ( !empty( $this->left_post ) && $this->left_post->post_parent == $post->ID ) { // Then new post
			$this->right_post = $post;
		}

		if ( !$this->left_post || !$this->right_post )
			return;

		$title = get_the_title( $this->right_post->ID );

		$html_diffs = array();
		$text_diffs = array();
		$identical = true;
		foreach ( _wp_post_revision_fields() as $field => $field_title ) {
			$left = apply_filters( "_wp_post_revision_field_$field", $this->left_post->$field, $field );
			$right = apply_filters( "_wp_post_revision_field_$field", $this->right_post->$field, $field );

			if ( !$diff = wp_text_diff( $left, $right ) )
				continue;
			$html_diffs[$field_title] = $diff;

			$left  = normalize_whitespace( $left );
			$right = normalize_whitespace( $right );

			$left_lines  = split( "\n", $left );
			$right_lines = split( "\n", $right );

			require_once( dirname( __FILE__ ) . '/unified.php' );

			$text_diff = new Text_Diff( $left_lines, $right_lines );
			$renderer  = new Text_Diff_Renderer_unified();
			$text_diffs[$field_title] = $renderer->render($text_diff);

			$identical = false;
		}

		if ( $identical )
			return;

		add_action( 'phpmailer_init', array( &$this, 'phpmailer_init_once' ) );
		add_action( 'wp_mail_from', array( &$this, 'wp_mail_from_once' ) );
		add_action( 'wp_mail_from_name', array( &$this, 'wp_mail_from_name_once' ) );

		$text_diff = '';
		foreach ( $text_diffs as $field_title => $diff ) {
			$text_diff .= "Index: $field_title\n";
			$text_diff .= "===================================================================\n";
			$text_diff .= "--- $field_title	(revision {$this->left_post->ID} @ {$this->left_post->post_date_gmt})\n";
			$text_diff .= "+++ $field_title	(revision {$this->right_post->ID} @ {$this->right_post->post_date_gmt})\n";
			$text_diff .= "$diff\n\n";
		}

		$text_diff = rtrim( $text_diff );

		$this->text_diff = $text_diff;

		$left_title = ucfirst( $this->left_post->post_type );
		$right_title = ucfirst( $this->right_post->post_type );

		$html_diff  = "<pre>--- $left_title	(revision {$this->left_post->ID} @ {$this->left_post->post_date_gmt})\n";
		$html_diff .= "+++ $right_title	(revision {$this->right_post->ID} @ {$this->right_post->post_date_gmt})</pre>";

		foreach ( $html_diffs as $field_title => $diff ) {
			$html_diff .= '<h2>' . esc_html( $field_title ) . '</h2>';
			$html_diff .= $diff;
		}

		$html_diff = str_replace( "class='diff'", 'style="width: 100%; white-space: pre-wrap; word-wrap: break-word; font-family: Consolas,Monaco,Courier,monospace"', $html_diff );
		$html_diff = str_replace( "class='content'", 'style="width: 50%"', $html_diff );
		$html_diff = str_replace( "class='diff-deletedline'", 'style="background-color: #fdd"', $html_diff );
		$html_diff = str_replace( "class='diff-addedline'", 'style="background-color: #dfd"', $html_diff );
		$html_diff = str_replace( '<del>', '<del style="text-decoration: none; background-color: #f99">', $html_diff );
		$html_diff = str_replace( '<ins>', '<ins style="text-decoration: none; background-color: #9f9">', $html_diff );

		$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$title = wp_specialchars_decode( $title, ENT_QUOTES );

		wp_mail(
			$to = get_option( 'admin_email' ),
			$sub = sprintf( __( '[%s] %s %d changed: %s' ), $blogname, $right_title, $this->right_post->ID, $title ),
			$html_diff
		);
	}

	function phpmailer_init_once( &$phpmailer ) {
		remove_action( 'phpmailer_init', array( &$this, 'phpmailer_init_once' ) );
		$phpmailer->AltBody = $this->text_diff;
	}

	function wp_mail_from_once( $email ) {
		return get_the_author_meta( 'email', $this->right_post->post_author );
		remove_action( 'wp_mail_from', array( &$this, 'wp_mail_from_once' ) );
	}

	function wp_mail_from_name_once( $name ) {
		remove_action( 'wp_mail_from_name', array( &$this, 'wp_mail_from_name_once' ) );
		return get_the_author_meta( 'display_name', $this->right_post->post_author );
	}
}

add_action( 'init', array( 'Email_Post_Changes', 'init' ) );
