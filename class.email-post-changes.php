<?php

class Email_Post_Changes {
	var $defaults;

	var $left_post;
	var $right_post;

	var $text_diff;

	const ADMIN_PAGE = 'email_post_changes';
	const OPTION_GROUP = 'email_post_changes';
	const OPTION = 'email_post_changes';

	function init() {
		static $instance = null;

		if ( $instance )
			return $instance;

		$class = __CLASS__;
		$instance = new $class;
		return $instance;
	}

	function __construct() {
		$this->defaults = apply_filters( 'email_post_changes_default_options', array(
			'enable'     => 1,
			'users'      => array(),
			'emails'     => array( get_option( 'admin_email' ) ),
			'post_types' => array( 'post', 'page' ),
			'drafts'     => 0,
		) );

		$options = $this->get_options();

		if ( $options['enable'] )
			add_action( 'wp_insert_post', array( $this, 'wp_insert_post' ), 10, 2 );
		if ( current_user_can( 'manage_options' ) )
			add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	function get_post_types() {
		$post_types = get_post_types( array( 'public' => true ) );
		$_post_types = array();

		foreach ( $post_types as $post_type ) {
			if ( post_type_supports( $post_type, 'revisions' ) )
				$_post_types[] = $post_type;
		}

		return $_post_types;
	}

	function get_options( $just_defaults = false ) {
		if ( $just_defaults )
			return $this->defaults;

		$options = (array) get_option( 'email_post_changes' );

		return wp_parse_args( $options, $this->defaults );
	}

	// The meat of the plugin
	function wp_insert_post( $post_id, $post ) {
		$options = $this->get_options();

		if ( ! $options['drafts'] && 'draft' == $post->post_status )
			return;

		if ( 'revision' == $post->post_type ) { // Revision is saved first
			if ( wp_is_post_autosave( $post ) )
				return;
			$this->left_post = $post;
		} elseif ( !empty( $this->left_post ) && $this->left_post->post_parent == $post->ID ) { // Then new post
			if ( !in_array( $post->post_type, $options['post_types'] ) )
				return;
			$this->right_post = $post;
		}

		if ( !$this->left_post || !$this->right_post )
			return;

		$html_diffs = array();
		$text_diffs = array();
		$identical = true;
		foreach ( _wp_post_revision_fields() as $field => $field_title ) {
			$left = apply_filters( "_wp_post_revision_field_$field", $this->left_post->$field, $field );
			$right = apply_filters( "_wp_post_revision_field_$field", $this->right_post->$field, $field );

			if ( !$diff = $this->wp_text_diff( $left, $right ) )
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

		if ( $identical ) {
			$this->left_post = null;
			$this->right_post = null;
			return;
		}

		// Grab the meta data
		$the_author = get_the_author_meta( 'display_name', $this->left_post->post_author ); // The revision
		$the_title = get_the_title( $this->right_post->ID ); // New title (may be same as old title)
		$the_date = gmdate( 'j F, Y \a\t G:i \U\T\C', strtotime( $this->right_post->post_modified_gmt . '+0000' ) ); // Modified time
		$the_permalink = clean_url( get_permalink( $this->right_post->ID ) );
		$the_edit_link = clean_url( get_edit_post_link( $this->right_post->ID ) );

		$left_title = __( 'Revision' );
		$right_title = sprintf( __( 'Current %s' ), $post_type = ucfirst( $this->right_post->post_type ) );

		$head_sprintf = __( '%s made the following changes to the %s %s on %s' );


		// HTML
		$html_diff_head  = '<h2>' . sprintf( __( '%s changed' ), $post_type ) . "</h2>\n";
		$html_diff_head .= '<p>' . sprintf( $head_sprintf,
			esc_html( $the_author ),
			sprintf( _x( '&#8220;%s&#8221; [%s]', '1 = link, 2 = "edit"' ),
				"<a href='$the_permalink'>" . esc_html( $the_title ) . '</a>',
				"<a href='$the_edit_link'>" . __( 'edit' ) . '</a>'
			),
			$this->right_post->post_type,
			$the_date
		) . "</p>\n\n";

		$html_diff_head .= "<table style='width: 100%; border-collapse: collapse; border: none;'><tr>\n";
		$html_diff_head .= "<td style='width: 50%; padding: 0; margin: 0;'>" . esc_html( $left_title ) . ' @ ' . esc_html( $this->left_post->post_date_gmt ) . "</td>\n";
		$html_diff_head .= "<td style='width: 50%; padding: 0; margin: 0;'>" . esc_html( $right_title ) . ' @ ' . esc_html( $this->right_post->post_modified_gmt ) . "</td>\n";
		$html_diff_head .= "</tr></table>\n\n";

		$html_diff = '';
		foreach ( $html_diffs as $field_title => $diff ) {
			$html_diff .= '<h3>' . esc_html( $field_title ) . "</h3>\n";
			$html_diff .= "$diff\n\n";
		}

		$html_diff = rtrim( $html_diff );

		// Replace classes with inline style
		$html_diff = str_replace( "class='diff'", 'style="width: 100%; border-collapse: collapse; border: none; white-space: pre-wrap; word-wrap: break-word; font-family: Consolas,Monaco,Courier,monospace;"', $html_diff );
		$html_diff = preg_replace( '#<col[^>]+/?>#i', '', $html_diff );
		$html_diff = str_replace( "class='diff-deletedline'", 'style="padding: 5px; width: 50%; background-color: #fdd;"', $html_diff );
		$html_diff = str_replace( "class='diff-addedline'", 'style="padding: 5px; width: 50%; background-color: #dfd;"', $html_diff );
		$html_diff = str_replace( "class='diff-context'", 'style="padding: 5px; width: 50%;"', $html_diff );
		$html_diff = str_replace( '<td>', '<td style="padding: 5px;">', $html_diff );
		$html_diff = str_replace( '<del>', '<del style="text-decoration: none; background-color: #f99;">', $html_diff );
		$html_diff = str_replace( '<ins>', '<ins style="text-decoration: none; background-color: #9f9;">', $html_diff );
		$html_diff = str_replace( array( '</td>', '</tr>', '</tbody>' ), array( "</td>\n", "</tr>\n", "</tbody>\n" ), $html_diff );

		$html_diff = $html_diff_head . $html_diff;


		// Refactor some of the meta data for TEXT
		$length = max( strlen( $left_title ), strlen( $right_title ) );
		$left_title = str_pad( $left_title, $length + 2 );
		$right_title = str_pad( $right_title, $length + 2 );

		// TEXT
		$text_diff  = sprintf( $head_sprintf, $the_author, '"' . $the_title . '"', $this->right_post->post_type, $the_date ) . "\n";
		$text_diff .= "URL:  $the_permalink\n";
		$text_diff .= "Edit: $the_edit_link\n\n";

		foreach ( $text_diffs as $field_title => $diff ) {
			$text_diff .= "$field_title\n";
			$text_diff .= "===================================================================\n";
			$text_diff .= "--- $left_title	({$this->left_post->post_date_gmt})\n";
			$text_diff .= "+++ $right_title	({$this->right_post->post_modified_gmt})\n";
			$text_diff .= "$diff\n\n";
		}

		$this->text_diff = $text_diff = rtrim( $text_diff );


		// Send email
		$charset = apply_filters( 'wp_mail_charset', get_option( 'blog_charset' ) );
		$blogname = html_entity_decode( get_option( 'blogname' ), ENT_QUOTES, $charset );
		$title = html_entity_decode( $the_title, ENT_QUOTES, $charset );

		add_action( 'phpmailer_init', array( $this, 'phpmailer_init_once' ) );

		wp_mail(
			null, // see hack in ::phpmailer_init_once()
			sprintf( __( '[%s] %s changed: %s' ), $blogname, $post_type, $title ),
			$html_diff
		);

		$this->left_post = null;
		$this->right_post = null;

		do_action( 'email_post_changes_email_sent' );
	}

	/* Email hook */
	function phpmailer_init_once( $phpmailer ) {
		global $blog_id;

		remove_action( 'phpmailer_init', array( $this, 'phpmailer_init_once' ) );
		$phpmailer->AltBody = $this->text_diff;

		$phpmailer->ClearAddresses(); // hack

		$options = $this->get_options();

		$user_emails = array();
		foreach( $options['users'] as $user_id ) {
			if ( function_exists( 'is_multisite' ) && is_multisite() ) {
				if ( is_user_member_of_blog( $user_id, $blog_id ) )
					$user_emails[] = get_user_option( 'user_email', $user_id );
			} else {
				if ( $user_email = get_user_option( 'user_email', $user_id ) )
					$user_emails[] = $user_email;
			}
		}

		$emails = array_unique( array_merge( $options['emails'], $user_emails ) );

		if ( !count( $emails ) )
			$emails[] = get_option( 'admin_email');

		$emails = apply_filters( 'email_post_changes_emails', $emails, $this->left_post->ID, $this->right_post->ID );

		foreach ( $emails as $email )
			$phpmailer->AddAddress( $email );

		$phpmailer->AddReplyTo(
			get_the_author_meta( 'email', $this->right_post->post_author ),
			get_the_author_meta( 'display_name', $this->right_post->post_author )
		);
	}

	function get_post_type_label( $post_type ) {
		// 2.9
		if ( !function_exists( 'get_post_type_object' ) )
			return ucwords( str_replace( '_', ' ', $post_type ) );

		// 3.0
		$post_type_object = get_post_type_object( $post_type );
		if ( empty( $post_type_object->label ) )
			return ucwords( str_replace( '_', ' ', $post_type ) );
		return $post_type_object->label;
	}

	/* Admin */
	function admin_menu() {
		register_setting( self::OPTION_GROUP, self::OPTION, array( $this, 'validate_options' ) );

		add_settings_section( self::ADMIN_PAGE, __( 'Email Post Changes' ), array( $this, 'settings_section' ), self::ADMIN_PAGE );
		add_settings_field( self::ADMIN_PAGE . '_enable', __( 'Enable' ), array( $this, 'enable_setting' ), self::ADMIN_PAGE, self::ADMIN_PAGE );
		add_settings_field( self::ADMIN_PAGE . '_users', __( 'Users to Email' ), array( $this, 'users_setting' ), self::ADMIN_PAGE, self::ADMIN_PAGE );
		add_settings_field( self::ADMIN_PAGE . '_emails', __( 'Additional Email Addresses' ), array( $this, 'emails_setting' ), self::ADMIN_PAGE, self::ADMIN_PAGE );
		add_settings_field( self::ADMIN_PAGE . '_post_types', __( 'Post Types' ), array( $this, 'post_types_setting' ), self::ADMIN_PAGE, self::ADMIN_PAGE );
		add_settings_field( self::ADMIN_PAGE . '_drafts', __( 'Drafts' ), array( $this, 'drafts_setting' ), self::ADMIN_PAGE, self::ADMIN_PAGE );

		add_options_page( __( 'Email Post Changes' ), __( 'Email Post Changes' ), 'manage_options', self::ADMIN_PAGE, array( $this, 'admin_page' ) );
	}

	function validate_options( $options ) {
		if ( !$options || !is_array( $options ) )
			return $this->defaults;

		$return = array();

		$return['enable'] = ( empty( $options['enable'] ) ) ? 0 : 1;

		if ( empty( $options['users'] ) || !is_array( $options ) ) {
			$return['users'] = $this->defaults['users'];
		} else {
			$return['users'] = $options['users'];
		}

		if ( empty( $options['emails'] ) ) {
			if ( count( $return['users'] ) )
				$return['emails'] = array();
			else
				$return['emails'] = $this->defaults['emails'];
		} else {
			if ( is_string( $options['emails'] ) )
				$_emails = preg_split( '(\n|\r)', $options['emails'], -1, PREG_SPLIT_NO_EMPTY );
			$_emails = array_unique( (array) $_emails );
			$emails = array_filter( $_emails, 'is_email' );
			if ( $diff = array_diff( $_emails, $emails ) )
				$return['invalid_emails'] = $diff;
			if ( $emails )
				$return['emails'] = $emails;
			elseif ( count( $return['users'] ) )
				$return['emails'] = array();
			else
				$return['emails'] = $this->defaults['emails'];
		}

		if ( empty( $options['post_types'] ) || !is_array( $options ) ) {
			$return['post_types'] = $this->defaults['post_types'];
		} else {
			$post_types = array_intersect( $options['post_types'], $this->get_post_types() );
			$return['post_types'] = $post_types ? $post_types : $this->defaults['post_types'];
		}

		$return['drafts'] = ( empty( $options['drafts'] ) ) ? 0 : 1;

		do_action( 'email_post_changes_validate_options', $this->get_options(), $return );

		return $return;
	}

	function admin_page() {
		$options = $this->get_options();
?>

<div class="wrap">
	<h2><?php _e( 'Email Post Changes' ); ?></h2>
<?php	if ( !empty( $options['invalid_emails'] ) && $_GET['updated'] ) : ?>
	<div class="error">
		<p><?php printf( _n( 'Invalid Email: %s', 'Invalid Emails: %s', count( $options['invalid_emails'] ) ), '<kbd>' . join( '</kbd>, <kbd>', array_map( 'esc_html', $options['invalid_emails'] ) ) ); ?></p>
	</div>
<?php	endif; ?>

	<form action="options.php" method="post">
		<?php settings_fields( self::OPTION_GROUP ); ?>
		<?php do_settings_sections( self::ADMIN_PAGE ); ?>
		<p class="submit">
			<input type="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes' ); ?>" />
		</p>
	</form>
</div>
<?php
	}

	function settings_section() {} // stub

	function enable_setting() {
		$options = $this->get_options();
?>
		<p><label><input type="checkbox" name="email_post_changes[enable]" value="1"<?php checked( $options['enable'], 1 ); ?> /> <?php _e( 'Send an email when a post or page changes.' ); ?></label></p>
<?php
	}

	function users_setting() {
		$options = $this->get_options();
?>
		<div style="overflow: auto; max-height: 300px;">
			<ul>
<?php		$users = get_users_of_blog();
		usort( $users, array( $this, 'sort_users_by_display_name' ) );

		foreach ( $users as $user ) : ?>
				<li><label><input type="checkbox" name="email_post_changes[users][]" value="<?php echo (int) $user->ID; ?>"<?php checked( in_array( $user->ID, $options['users'] ) ); ?> /> <?php echo esc_html( $user->display_name ); ?> ( <?php echo esc_html( $user->user_login ); ?> - <?php echo esc_html( $user->user_email ); ?> )</label></li>

<?php		endforeach; ?>
			</ul>
		</div>
<?php
	}

	function sort_users_by_display_name( $a, $b ) {
		return strcmp( strtolower( $a->display_name ), strtolower( $b->display_name ) );
	}

	function emails_setting() {
		$options = $this->get_options();
?>
		<textarea rows="4" cols="40" style="width: 40em;" name="email_post_changes[emails]"><?php echo esc_html( join( "\n", $options['emails'] ) ); ?></textarea>
		<p class="description"><?php _e( 'One email address per line.' ); ?></p>
<?php
	}

	function post_types_setting() {
		$options = $this->get_options();
?>
		<ul>
<?php		foreach ( $this->get_post_types() as $post_type ) :
			$label = $this->get_post_type_label( $post_type );
?>
			<li><label><input type="checkbox" name="email_post_changes[post_types][]" value="<?php echo esc_attr( $post_type ); ?>"<?php checked( in_array( $post_type, $options['post_types'] ) ); ?> /> <?php echo esc_html( $label ); ?></label></li>
<?php		endforeach; ?>
		</ul>
<?php
	}

	function drafts_setting() {
		$options = $this->get_options();
?>
		<p><label><input type="checkbox" name="email_post_changes[drafts]" value="1"<?php checked( $options['drafts'], 1 ); ?> /> <?php _e( 'Email changes to drafts, not just published items.' ); ?></label></p>
<?php
	}

	function wp_text_diff( $left_string, $right_string, $args = null ) {
		$defaults = array( 'title' => '', 'title_left' => '', 'title_right' => '' );
		$args = wp_parse_args( $args, $defaults );

		$left_string  = normalize_whitespace( $left_string );
		$right_string = normalize_whitespace( $right_string );
		$left_lines  = explode( "\n", $left_string );
		$right_lines = explode( "\n", $right_string );

		$text_diff = new Text_Diff( $left_lines, $right_lines );
		$renderer  = new Email_Post_Changes_Diff();
		$diff = $renderer->render( $text_diff );

		if ( !$diff )
			return '';

		$r  = "<table class='diff'>\n";
		$r .= "<col class='ltype' /><col class='content' /><col class='ltype' /><col class='content' />";

		if ( $args['title'] || $args['title_left'] || $args['title_right'] )
			$r .= "<thead>";
		if ( $args['title'] )
			$r .= "<tr class='diff-title'><th colspan='4'>$args[title]</th></tr>\n";
		if ( $args['title_left'] || $args['title_right'] ) {
			$r .= "<tr class='diff-sub-title'>\n";
			$r .= "\t<td></td><th>$args[title_left]</th>\n";
			$r .= "\t<td></td><th>$args[title_right]</th>\n";
			$r .= "</tr>\n";
		}
		if ( $args['title'] || $args['title_left'] || $args['title_right'] )
			$r .= "</thead>\n";
		$r .= "<tbody>\n$diff\n</tbody>\n";
		$r .= "</table>";
		return $r;
	}
}

if ( !class_exists( 'WP_Text_Diff_Renderer_Table' ) )
	require( ABSPATH . WPINC . '/wp-diff.php' );

class Email_Post_Changes_Diff extends WP_Text_Diff_Renderer_Table {
	var $_leading_context_lines  = 2;
	var $_trailing_context_lines = 2;
}
