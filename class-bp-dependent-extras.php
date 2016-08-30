<?php
/**
 * Plugin class for BuddyPress-dependent pieces. This class should ideally be used to work with the
 * public-facing side of the WordPress site.
 *
 * If you're interested in introducing administrative or dashboard
 * functionality, then refer to `class-plugin-name-admin.php`
 *
 * @TODO: Rename this class to a proper name for your plugin.
 *
 * @package CC Functionality Plugin
 * @author  David Cavins
 */
class CC_Functionality_BP_Dependent_Extras {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   0.1.0
	 *
	 * @var     string
	 */
	const VERSION = '0.1.6';

	/**
	 *
	 * Unique identifier for your plugin.
	 *
	 *
	 * The variable name is used as the text domain when internationalizing strings
	 * of text. Its value should match the Text Domain file header in the main
	 * plugin file.
	 *
	 * @since    0.1.0
	 *
	 * @var      string
	 */
	protected $plugin_slug = 'cc-functionality-plugin';

	/**
	 * Instance of this class.
	 *
	 * @since    0.1.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 *
	 * @since     0.1.0
	 */
	private function __construct() {

		// Load plugin text domain
		// add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Activate plugin when new blog is added
		// add_action( 'wpmu_new_blog', array( $this, 'activate_new_site' ) );

		// Load public-facing style sheet and JavaScript.
		// add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		// add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );


		//	1. BuddyPress behavior changes
		//		a. Remove "Create a Group" button from the groups directory if not a site admin.
				add_filter('bp_get_group_create_button', array( $this, 'remove_create_a_group_button' ) );
		// 		b. Restrict who can create groups and in what circumstances.
				// This is handled by setting bp_group_hierarchy's setting to "no one can create top-level group"
				// Then, if a user tries to access groups/create and cannot create subgroups, they'll get bounced.
		// 		c. Change group "Request membership" button behavior
				add_filter( 'bp_get_group_join_button', array( $this, 'request_membership_redirect' )  );
		//		d. Add more information to the group invites screen
				add_action( 'bp_group_send_invites_item', array( $this, 'add_invitation_meta' ) );
		// 		e. Use wp_editor for the activity post_form and dump the media-adding plugin, as it's broken as of BP 2.1.
				// add_action( 'whats_new_textarea', array( $this, 'post_form_wp_editor' ) );
		// 		f. Don't show certain types of activity items on the site activity stream's default view.
				add_filter( 'bp_ajax_querystring', array( $this, 'activity_stream_hide_activity_types' ), 22, 2 );
				// add_filter( 'bp_activity_set_groups_scope_args', array( $this, 'group_activity_stream_hide_activity_types' ), 12, 2 );


		// 	2. BuddyPress Docs behavior changes
		//		a. Change default access settings to "group-members" if a group is associated with a doc.
				add_filter( 'bp_docs_get_doc_settings', array( $this, 'mod_bp_docs_access_defaults_for_groups' ), 20, 3 );
		// 		b. If this is a new child group, don't show the bp-docs create step but instead set it up to match the parent.
				add_filter('bp_docs_force_enable_at_group_creation', array( $this, 'disable_bp_docs_create_step' ), 12, 1);
		//		c. If this is a new child group, we'll set up BP docs to match the parent group's setup. This step copies the parent group's attributes over to the child group.
				add_filter('bp_docs_default_group_settings', array( $this, 'bp_docs_default_settings_for_child_groups' ), 12, 2);
		//		d. Allow comment functionality on BP Docs.
				// add_filter('bp_docs_allow_comment_section', array( $this, 'bp_docs_allow_comments' ), 12, 2);
		// 		e. Make links and such in BP Docs clickable
				add_filter( 'bp_docs_get_the_content', array( $this, 'make_bp_docs_content_clickable' ), 28, 1 );
		//      f. No folders for now
				add_filter( 'bp_docs_enable_folders', array( $this, 'bp_docs_no_folders' ) );
				add_filter( 'bp_docs_enable_folders_for_current_context', array( $this, 'bp_docs_no_folders' ) );

		//	3. BP Group Hierarchy behavior changes
		// 		a. Make "only Group Admins can create member groups" the only option for create group form.
				add_filter('bp_group_hierarchy_subgroup_permission_options', array( $this, 'group_hierarchy_creators_default_option' ), 17, 2);
				// The component name messes up BuddyPress's string generator, so it doesn't get translated
				add_filter( 'bp_get_search_default_text', array( $this, 'groups_dir_search_placeholder_text' ), 12, 2 );

		//		c. Don't show the "Hublets" tab to non-logged-in or non-member visitors
				// Why did we do this? Seems strange. The plugin only shows the hublets tab if the user is a group admin, can create subgroups or there are sub groups.
				// Maybe only show if there are subgroups that the user can see.
				add_filter('bp_group_hierarchy_show_member_groups', array( $this, 'group_hierarchy_hublet_tab_visibility' ), 10, 4 );

				// Break the-content filter
				// add_action( 'wp_init', array($this, 'bust_formatting'), 1 );

		// 4. BuddyPress Group Email Subscription changes
			// Don't show the group email digest subscription nav item
			add_filter( 'bp_group_email_subscription_enable_nav_item', array( $this, 'disable_bpge_nav_item' ) );
			add_filter( 'ass_digest_format_item', array( $this, 'bpge_strip_shortcodes' ) );
			add_filter( 'bp_ass_activity_notification_message', array( $this, 'bpge_strip_shortcodes_not_digest' ) );



		// 5. Invite Anyone behavior changes
			// Don't render the directory on the group invites page
			// Invite Anyone uses a check on the number of users to decide if it should build the list of users with checkboxes on the group's "send invites" page. The list is too long for us, but under WP's definition of a large network. So we filter the result to true.
			add_filter( 'invite_anyone_is_large_network', array( $this, 'change_ia_large_network_value' ), 22, 2 );

		// 6. Relevanssi behavior changes
			// This prevents relevanssi from breaking our archive searches
			// For BP Docs, Help articles, SA stuff
			add_filter('relevanssi_prevent_default_request', array( $this, 'stop_relevanssi_on_archives' ) );

		// 7. Gravity Forms changes
			// Always add the user ID as entry meta
			// add_filter( 'gform_entry_meta', array( $this, 'cc_gf_entry_add_user_id' ), 10, 2);

		// 8. WangGuard modifications
			// Add "about me" to the user row. Helps to ID spammers.
			add_filter( 'wg_users_table_info_cell', array( $this, 'wg_after_info_about_me' ), 10, 3 );

		// 9. BJ Lazy Load modifications
			// Disable Lazy load on user and group avatar upload/crop pages.
			add_filter( 'bj_lazy_load_run_filter', array( $this, 'skip_lazy_load' ), 12 );

		// 10. Changes to the AgSite group
			add_action( 'bp_before_group_header_meta', array( $this, 'add_mu_extension_logo' ) );

		// 11. Add "Report sender as spammer" link to private message threads.
			// Uses Wangguard functionality.
			// Add "report as spammer" to messages overview screen.
			// This has some complications, so punting for now. Complications:
			// If there are multiple recipients, who gets marked, or maybe no link is shown when it's confusing?
			// Is it weird to have the button show up sometimes and not others?
			// add_action( 'bp_messages_loop_after_action_links', array( $this, 'add_report_sender_link' ) );

			// Add "report sender as spammer" link to individual messages in a message thread.
			add_action( 'bp_after_message_content', array( $this, 'add_report_message_as_spam_link' ) );

		// 13. WP Email: Change default from and from email address.
			add_filter( 'wp_mail_from', array( $this, 'change_wp_default_from_email_address' ) );
			add_filter( 'wp_mail_from_name', array( $this, 'change_wp_default_from_email_name' ) );

		// Testing
			// add_filter( 'bp_core_fetch_avatar', array( $this, 'test_bp_core_fetch_avatar_filter' ), 10, 9 );
			// add_filter( 'bp_legacy_theme_ajax_querystring', array( $this, 'check_querystring' ), 99, 7 );
			// add_action( 'bp_include', array( $this, 'trap_component_at_include' ), 88 );
			// add_action( 'init', array( $this, 'trap_component_at_init' ), 9 );
			// add_filter('custom_menu_order', array( $this, 'wp_admin_menu_order' ) ); // Activate custom_menu_order
			// add_filter('menu_order', array( $this, 'wp_admin_menu_order' ) );
			// add_action( 'admin_footer', array( $this, 'show_wp_menu_positions' ) );






	}

	/**
	 * Return the plugin slug.
	 *
	 * @since    0.1.0
	 *
	 * @return    Plugin slug variable.
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     0.1.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    0.1.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Activate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       activated on an individual blog.
	 */
	public static function activate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide  ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_activate();
				}

				restore_current_blog();

			} else {
				self::single_activate();
			}

		} else {
			self::single_activate();
		}

	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    0.1.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Deactivate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       deactivated on an individual blog.
	 */
	public static function deactivate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_deactivate();

				}

				restore_current_blog();

			} else {
				self::single_deactivate();
			}

		} else {
			self::single_deactivate();
		}

	}

	/**
	 * Fired when a new site is activated with a WPMU environment.
	 *
	 * @since    0.1.0
	 *
	 * @param    int    $blog_id    ID of the new blog.
	 */
	public function activate_new_site( $blog_id ) {

		if ( 1 !== did_action( 'wpmu_new_blog' ) ) {
			return;
		}

		switch_to_blog( $blog_id );
		self::single_activate();
		restore_current_blog();

	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 *
	 * @since    0.1.0
	 *
	 * @return   array|false    The blog ids, false if no matches.
	 */
	private static function get_blog_ids() {

		global $wpdb;

		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";

		return $wpdb->get_col( $sql );

	}

	/**
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since    0.1.0
	 */
	private static function single_activate() {
		// @TODO: Define activation functionality here
	}

	/**
	 * Fired for each blog when the plugin is deactivated.
	 *
	 * @since    0.1.0
	 */
	private static function single_deactivate() {
		// @TODO: Define deactivation functionality here
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    0.1.0
	 */
	public function load_plugin_textdomain() {

		$domain = $this->plugin_slug;
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, FALSE, basename( plugin_dir_path( dirname( __FILE__ ) ) ) . '/languages/' );

	}

	/**
	 * Register and enqueue public-facing style sheet.
	 *
	 * @since    0.1.0
	 */
	public function enqueue_styles() {
		if ( function_exists( 'bp_is_groups_component' ) && ccgn_is_component() )
			wp_enqueue_style( $this->plugin_slug . '-plugin-styles', plugins_url( 'assets/css/public.css', __FILE__ ), array(), self::VERSION );
	}

	/**
	 * Register and enqueues public-facing JavaScript files.
	 *
	 * @since    0.1.0
	 */
	public function enqueue_scripts() {
		// only fetch the js file if on the groups directory
		// bp_is_groups_directory() is available at 2.0.0.
		if ( bp_is_groups_component() && ! bp_current_action() && ! bp_current_item() )
			wp_enqueue_script( $this->plugin_slug . '-plugin-script', plugins_url( 'channel-select.js', __FILE__ ), array( 'jquery' ), self::VERSION, TRUE );
	}

	/**
	 * NOTE:  Actions are points in the execution of a page or process
	 *        lifecycle that WordPress fires.
	 *
	 *        Actions:    http://codex.wordpress.org/Plugin_API#Actions
	 *        Reference:  http://codex.wordpress.org/Plugin_API/Action_Reference
	 *
	 * @since    0.1.0
	 */
	public function action_method_name() {
		// @TODO: Define your action hook callback here
	}

	/**
	 * NOTE:  Filters are points of execution in which WordPress modifies data
	 *        before saving it or sending it to the browser.
	 *
	 *        Filters: http://codex.wordpress.org/Plugin_API#Filters
	 *        Reference:  http://codex.wordpress.org/Plugin_API/Filter_Reference
	 *
	 * @since    0.1.0
	 */
	public function filter_method_name() {
		// @TODO: Define your filter hook callback here
	}

	/* 1. BuddyPress behavior changes
	*****************************************************************************/
	/**
	 * 1a. Remove "Create a Group" button from the groups directory if not a site admin.
	 *
	 * @since    0.1.0
	 */
	public function remove_create_a_group_button( $args ){
	  if ( ! current_user_can( 'delete_others_pages' ) )
	    return false;

	  return $args;
	}

	/**
	 * 1b. Restrict who can create groups and in what circumstances.
	 *
	 * @since    0.1.0
	 */
	// No function needed. Handled by bp-group-hierarchy settings.

	/**
	 * 1c. Change group "Request membership" button behavior-- always redirect to request membership pane, no AJAX requests.
	 *
	 * @since    0.1.1
	 */
	public function request_membership_redirect( $button ) {
		// To prevent buddypress.js from acting on the request membership button click, we'll need to remove the class .group-button from the button wrapper. See buddypress.js line 1252.

		if ( $button['id'] == 'request_membership' ) {
			$button['wrapper_class'] = str_replace( 'group-button', '', $button[ 'wrapper_class' ] );
			$button['link_class'] = str_replace( 'group-button', '', $button[ 'link_class' ] );
		}

		return $button;
	}
	/**
	 * 1d. Add more information to the group invites screen
	 *
	 * @since    0.1.2
	 */
	public function add_invitation_meta(){
		global $invites_template;
		$invite = $invites_template->invite;
		// echo '<pre>';
		// var_dump( $invites_template->invite );
		// echo '</pre>';
		$invite_sent = $invites_template->invite->user->invite_sent;
		if ( $invite_sent ) {
			$invited_date = date( "m-d-Y", strtotime( $invites_template->invite->user->date_modified ) );
			echo '<br /><span class="invite-status meta">Invited ' . $invited_date . '</span>';
		} else {
			echo '<br /><span class="invite-status meta alert">Invitation has not yet been sent.</span>';
		}

	}

	/**
	 * 1e. Use wp_editor for the activity post_form and dump the media-adding plugin, as it's broken as of BP 2.1.
	 *
	 * @since    0.1.3
	 */
	public function post_form_wp_editor() {
		// deactivation of the visual tab, so user can't play with template styles
		add_filter ( 'user_can_richedit' , create_function( '$a' , 'return false;' ), 50 );

		if ( isset( $_GET['r'] ) )
			$content = esc_textarea( $_GET['r'] );

		// adding tinymce tools
		$editor_id = 'whats-new';
		$settings = array(
			'textarea_name' => 'whats-new',
			'teeny' => true,
			'media_buttons' => true,
			'drag_drop_upload' => true,
			'quicktags' => array(
				'buttons' => 'strong,em,link,img'
				)
			);

		// get the editor
		wp_editor( $content, $editor_id, $settings );
	}

	/**
	 * 1f. Don't show certain types of activity items on the site activity stream's default view.
	 *
	 * @since    0.1.7
	 *
	 * @param string querystring Current query string.
	 * @param string $object     Current template component.
	 *
	 * @return array querystring options in array format
	 */
	public function activity_stream_hide_activity_types( $querystring, $object ) {
		$exclude_types = array();

	    if ( 'activity' == $object && ! isset( $querystring['scope'] ) && ! isset( $querystring['type'] ) ) {
	        // If no $type has been set, the user hasn't used the filters.
	        // If no $scope is set, this is the "all members" list.

			// Remove some types from the main site activity feed.
			if ( bp_is_activity_directory() ) {
	            $exclude_types = array( 'new_member', 'new_avatar', 'updated_profile' );
			} // elseif ( bp_is_group() ) {
				// Remove some types from group activity feed.
				// This doesn't work as of 2.4.3 because 'filter_query' is ignored if scope is set.
				// This will probably change.
				// $exclude_types = array( 'joined_group' );
			// }
		}

		// Is there anything for us to do?
		// Note that 'filter_query' is ignored if scope is set in 2.4.3. Prob will change.
		if ( ! empty( $exclude_types ) ) {
			if ( is_string( $querystring ) ) {
				wp_parse_str( $querystring, $querystring );
			}
			$querystring['filter_query'] = array(
				array(
					'column' => 'type',
					'value'  => $exclude_types,
					'compare' => 'NOT IN'
				)
			);
		}

	    return $querystring;
	}


		}
		// $towrite .= PHP_EOL . '$args: ' . print_r($args, TRUE);
		// $towrite .= PHP_EOL . '$modded qs: ' . print_r($query_string, TRUE);
		// $towrite .= PHP_EOL . '------------------';
		// $fp = fopen('bp_legacy_theme_ajax_querystring.txt', 'a');
		// fwrite($fp, $towrite);
		// fclose($fp);

		return $query_string;
	}

	/* 2. BuddyPress Docs behavior changes
	*****************************************************************************/
	/**
	 * 2a. Change default access settings to "group-members" if a group is associated with a doc.
	 *
	 * @since    0.1.0
	 */
	function mod_bp_docs_access_defaults_for_groups( $doc_settings, $doc_id, $default_settings ) {
	  // A refresh_access_settings AJAX request is fired after the page loads.
	  // We'll apply our new defaults if a group id is passed as part of the request.
	  if ( ( defined('DOING_AJAX') && DOING_AJAX ) && ( isset( $_POST['group_id'] ) && $_POST['group_id'] ) ) {
	    if ( $doc_settings == $default_settings ) {
	      foreach ($doc_settings as $key=>$setting) {
	        $doc_settings[$key] = 'group-members';
	      }
	    }
	  }

	  return $doc_settings;
	}

	/**
	 * 2b. If this is a new child group, we don't show the bp-docs create step but instead set it up to match the parent.
	 *
	 * @since    0.1.0
	 */
	public function disable_bp_docs_create_step() {
	  //If this new group is a child group of another group, we'll set up BP docs to match the parent group's setup. This piece disables the docs create step if the new group has a parent group.
		if ( bp_is_groups_component() && bp_is_current_action( 'create' ) ) {
				$new_group_id = isset( $_COOKIE['bp_new_group_id'] ) ? $_COOKIE['bp_new_group_id'] : 0;
				if ( $new_group_id ) {
					if ( $parent_id = $this->get_parent_id( $new_group_id ) )
						return true;
				}
		}
		// false is the default.
		return false;
	}

	/**
	 * 2c. If this new group is a child group of another group, we'll set up BP docs to match the parent group's setup.
	 * This step copies the parent group's attributes over to the child group.
	 * This filter is only called if disable_bp_docs_create_step() returns true, above.
	 *
	 * @since    0.1.0
	 */
	public function bp_docs_default_settings_for_child_groups( $settings, $group_id ) {
	    if ( $parent_id = $this->get_parent_id( $group_id ) ) {
		    $parent_settings = groups_get_groupmeta( $parent_id, 'bp-docs');

		    if ( !empty( $parent_settings ) ) {
		      $settings = $parent_settings;
		    }
	    }
	  return $settings;
	}

	/**
	 * 2d. Allow comment functionality on BP Docs.
	 *
	 * @since    0.1.1
	 */
	public function bp_docs_allow_comments( $setting ) {
	  return true;
	}

	/**
	 * 2e. Make links and such in BP Docs clickable.
	 *
	 * @since    0.1.6
	 */
	public function make_bp_docs_content_clickable( $content ) {
		return make_clickable( $content );
	}
	/**
	 * 2f. DOn't use folders yet.
	 *
	 * @since    0.1.6
	 */
	public function bp_docs_no_folders( $on ) {
		return false;
	}

	/* 3. BP Group Hierarchy behavior changes
	*****************************************************************************/
	/**
	 * 3a. Make "only Group Admins can create member groups" the only option for create group form.
	 *
	 * @since    0.1.0
	 */
	function group_hierarchy_creators_default_option( $permission_options, $group ) {
		if ( current_user_can( 'delete_others_pages' ) )
			return $permission_options;

	    $new_options = array();
	    foreach ($permission_options as $key => $value) {
	    	if ( $key == 'group_admins' ) {
	    		$new_options[$key] = $value;
	    	}
	    }

	    return $new_options;
	}

	// Groups Hierarchy filters the value of the groups directory search box placeholder, so our language file doesn't take effect. So we re-filter it.
	public function groups_dir_search_placeholder_text( $default_text, $component ) {

		if ( $component == 'groups' || $component == 'tree' )
			$default_text = 'Search Hubs...';

		return $default_text;
	}

	/**
	 * 3c. Don't show the "Hublets" tab to non-logged-in or non-member visitors
	 *
	 * @since    0.1.5
	 */
	function group_hierarchy_hublet_tab_visibility( $show_tab, $group_id, $is_item_admin, $can_create ) {
				// $towrite = PHP_EOL . 'hierarchy enable nav item current group: ' . print_r($bp->groups->current_group, TRUE);
		$bp = buddypress();
		// $towrite = PHP_EOL . 'incoming show tab: ' . print_r($show_tab, TRUE);
		// $towrite .= PHP_EOL . '$group_id ' . print_r($group_id, TRUE);
		// $towrite .= PHP_EOL . '$is_item_admin: ' . print_r($is_item_admin, TRUE);
		// $towrite .= PHP_EOL . '$my_is_item_admin: ' . print_r($bp->is_item_admin, TRUE);
		// $towrite .= PHP_EOL . '$mycan_create: ' . print_r($can_create, TRUE);
		// $towrite .= PHP_EOL . '$can_create: ' . print_r($bp->groups->current_group->can_create_subitems, TRUE);
		// $towrite .= PHP_EOL . '$user_id: ' . print_r(get_current_user_id(), TRUE);
		// $fp = fopen('debug_hierarchy.txt', 'a');
		// fwrite($fp, $towrite);
		// fclose($fp);

		// If $is_item_admin and $can_create are false, we should make sure that the user could actually see any of the subgroups. So sometimes we'll turn a true into a false
		if ( $show_tab && ! $is_item_admin && ! $can_create ) {
			$show_tab = false;
			// Return true if the user belongs to any of the subgroups or if any of the groups are public.
			if ( $user_id = get_current_user_id() ) {

				$args = array(
					'parent_id' => $group_id,
					'per_page' => null, // The number of results to return per page
					'page' => null,
				);
				$subgroups = bp_group_hierarchy_get_by_hierarchy( $args );
				if ( ! empty( $subgroups['groups'] ) ) {
					foreach ( $subgroups['groups'] as $key => $group) {
						// $towrite = PHP_EOL . '$group: ' . print_r( $group, TRUE);
						// $fp = fopen('debug_hierarchy.txt', 'a');
						// fwrite($fp, $towrite);
						// fclose($fp);
						if ( $group->status == "public" || $group->is_member ) {
							return true;
						}
					}
				}
			}
		}

		// $fp = fopen('debug_hierarchy.txt', 'a');
		// fwrite($fp, $towrite);
		// fclose($fp);

		// if ( ! $user_id = get_current_user_id() ) {
		// 	return false;
		// }

		// if ( ! groups_is_user_member( $user_id, $group_id ) ) {
		// 	return false;
		// }

	    return $show_tab;
	}

	/* 4. BuddyPress Group Email Subscription changes
	*****************************************************************************/
		// Don't show the group email digest subscription nav item

	public function disable_bpge_nav_item( $enable_nav_item ) {
		return false;
	}

	// This removes shortcodes added by the add-photos-to-activity plugin.
	public function bpge_strip_shortcodes( $item ) {
		$item = strip_shortcodes( $item );

		return $item;

	}
	// It appears that if the message is sent immediately, it requires a different filter.
	public function bpge_strip_shortcodes_not_digest( $message, $message_array ) {
		return strip_shortcodes( $message );
	}


	// 5. Invite Anyone behavior changes
	// Don't render the directory on the group invites page
	function change_ia_large_network_value( $is_large, $count ) {
	  return true;
	}

	// 6. Relevanssi behavior changes
		// This prevents relevanssi from breaking our archive searches
		// For BP Docs, Help articles, SA stuff
	public function stop_relevanssi_on_archives( $prevent ) {
		if ( is_post_type_archive()
			 || is_page( 'groups' )
			 || ( function_exists( 'bp_docs_is_user_docs' ) && bp_docs_is_user_docs() )
			 || ( function_exists( 'bp_docs_is_docs_component' ) && bp_is_group() && bp_docs_is_docs_component() )
			 ) {
		    $prevent = false;
		}

		return $prevent;
	}

	// 7. Gravity Forms changes
		// Always add the user ID as entry meta
	public function cc_gf_entry_add_user_id( $entry_meta, $form_id ){
		//data will be stored with the meta key 'user_id'
		//label - displayed as the column header
		//is_numeric - used when sorting the entry list, indicates whether the data should be treated as numeric when sorting
		//is_default_column - when set to true automatically adds the column to the entry list, without having to edit and add the column for display
		//update_entry_meta_callback - indicates what function to call to update the entry meta upon form submission or editing an entry
	    $entry_meta['user_id'] = array(
	        'label' => 'User ID',
	        'is_numeric' => true,
	        'update_entry_meta_callback' => array( $this, 'cc_add_user_id_to_all_form_entries' ),
	        'is_default_column' => true
	    );
	    return $entry_meta;
	}

	public function cc_add_user_id_to_all_form_entries($key, $lead, $form){
	    //add user ID to all form entries. Why doesn't GF do this anyway?
	    $user_id = get_current_user_id();
	    return apply_filters( 'cc_add_user_id_to_all_form_entries', $user_id );
	}

	// 8. WangGuard
		// Add "about me" to the user row. Helps to ID spammers.
	public function wg_after_info_about_me( $contents, $user, $column_name ){
		$towrite = print_r($user, true);
	    $fp = fopen('wg_filter.txt', 'a');
	    fwrite($fp, $towrite);
	    fclose($fp);
		$args = array(
			'field' 	=> 'About Me',
			'user_id'	=> $user->ID
			);
		if ( $about_me = bp_get_profile_field_data( $args ) ) {
			$length = 100;
			if ( strlen( $about_me ) > $length ) {
				$about_me = substr( $about_me, 0, $length) . '&hellip;';
			}
			$contents .= '<br /> <em>' . $about_me . '</em>';
		}

		return $contents;
	}

	// 9. BJ Lazy Load modifications
		public function skip_lazy_load( $run_filter ) {
			// Disable Lazy load on user and group avatar upload/crop pages.
		    if ( bp_is_group_creation_step( 'group-avatar' )
		    	|| bp_is_group_admin_screen( 'group-avatar' )
		    	|| bp_is_user_change_avatar() ) {
				return false;
		    }

		    //Disable Lazy Load for old version of IE
			if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			    if ( strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE 8' ) !== false || strpos( $_SERVER['HTTP_USER_AGENT'], 'MSIE 7' ) !== false ) {
			    	return false;
			    }
			}

			return $run_filter;
		}

	// 10. Changes to the AgSite group
		public function add_mu_extension_logo() {
			if ( bp_get_current_group_id() == 661 ){
				// Two options:
				// http://www.communitycommons.org/wp-content/uploads/2015/10/MU-Extension-Logo-70x303.png
				// http://www.communitycommons.org/wp-content/uploads/2015/10/MU-Extension-Logo-80x346.png
				?><div class="alignright">
					<img src="http://www.communitycommons.org/wp-content/uploads/2015/10/MU-Extension-Logo-70x303.png" alt="University of Missouri Extension logo" class="wp-post-image"/>
				</div><?php
			}
		}

	// 11. Add "Report sender as spammer" link to private message threads.
		// This is attached at the thread level, so should probably only apply if there's only one other recipient in the conversation. It gets complicated.
		public function add_report_sender_link() {
			?>
			<a href="#" class="button confirm">Report as Spam</a>
			<?php
		}

		// Adds "Report as spammer" to individual messages in a message thread.
		// Relies on the WangGuard moderation queue.
		public function add_report_message_as_spam_link() {
			if ( bp_get_the_thread_message_sender_id() != bp_loggedin_user_id() ) {
				echo bp_get_button( array(	'id' => 'wangguard_report_user',
								'component' => 'members',
								'must_be_logged_in' => true,
								'block_self' => false,
								'wrapper_id' => 'wangguard_report_user-button',
								'link_href' => "javascript:void(0)",
								'link_class' =>'alignright wangguard-user-report wangguard-user-report-id-' . bp_get_the_thread_message_sender_id(),
								'link_title' => __('Report as Spam', 'wangguard'),
								'link_text' => __('Report as Spam', 'wangguard')) );
			}
		}

	// 12. Writing Helper
		// Filter "From" and "reply-to" values.
		public function filter_writing_helper_feedback_email_headers( $headers, $user ) {
			$headers = 'Reply-To: ' . $user->display_name . ' <' . $user->user_email . ">\r\n";
			$headers .= "From: " . $user->display_name . " <donotreply@communitycommons.org>\r\n";

			return $headers;
		}


	//Testing functions
	public function test_bp_core_fetch_avatar_filter( $output, $params, $params_item_id, $params_avatar_dir, $html_css_id, $html_width, $html_height, $avatar_folder_url, $avatar_folder_dir) {
		$args = array(
			'output' => $output,
			'params' => $params,
			'params_item_id' => $params_item_id,
			'params_avatar_dir' => $params_avatar_dir,
			'html_css_id' => $html_css_id,
			'html_width' => $html_width,
			'html_height' => $html_height,
			'avatar_folder_url' => $avatar_folder_url,
			'avatar_folder_dir' => $avatar_folder_dir,
			);
		$towrite = '';
		foreach ($args as $key => $value) {
		    $towrite .= PHP_EOL . $key . ' ' . print_r( $value, TRUE);
		}

	    $fp = fopen('bp_core_fetch_avatar_filter.txt', 'a');
	    fwrite($fp, $towrite);
	    fclose($fp);

	    return $output;

	}

	/**
	 * 1f. Don't show new site and group memberships on activity stream default view
	 *
	 * @since    0.1.4
	 */
	public function check_querystring( $query_string, $object, $object_filter, $object_scope, $object_page, $object_search_terms, $object_extras ){
		$towrite = PHP_EOL . '$query_string: ' . print_r($query_string, TRUE);
		$towrite .= PHP_EOL . '$object: ' . print_r($object, TRUE);
		$towrite .= PHP_EOL . '$object_filter: ' . print_r($object_filter, TRUE);
		$towrite .= PHP_EOL . '$object_scope: ' . print_r($object_scope, TRUE);
		$towrite .= PHP_EOL . '$object_page: ' . print_r($object_page, TRUE);
		$towrite .= PHP_EOL . '$object_search_terms: ' . print_r($object_search_terms, TRUE);
		$towrite .= PHP_EOL . '$object_extras: ' . print_r($object_extras, TRUE);
		// $towrite .= PHP_EOL . 'is groups activity? ' . print_r( bp_is_group() , TRUE);
		// $towrite .= PHP_EOL . 'is activity directory? ' . print_r( bp_is_activity_directory() , TRUE);


		// $towrite .= PHP_EOL . '$args: ' . print_r($args, TRUE);
		// $towrite .= PHP_EOL . '$modded qs: ' . print_r($query_string, TRUE);
		$towrite .= PHP_EOL . '------------------';
		$fp = fopen('bp_legacy_theme_ajax_querystring.txt', 'a');
		fwrite($fp, $towrite);
		fclose($fp);

		return $query_string;
	}

	public function trap_component_at_include(){
		$towrite = PHP_EOL . 'component at include: ' . print_r(  bp_current_component(), TRUE);
		$towrite .= PHP_EOL . '------------------';
		$fp = fopen('component_sniffing.txt', 'a');
		fwrite($fp, $towrite);
		fclose($fp);

	}

	public function trap_component_at_init(){
		$towrite = PHP_EOL . 'component at init: ' . print_r(  bp_current_component(), TRUE);
		$towrite .= PHP_EOL . '------------------';
		$fp = fopen('component_sniffing.txt', 'a');
		fwrite($fp, $towrite);
		fclose($fp);

	}

	// UTILITY FUNCTIONS
	/**
	 * Get the group's parent id while in the group create steps
	 *
	 * @since    0.1.0
	 */
	public function get_parent_id( $group_id ) {
		// The groups object returned by groups_get_group( array( 'group_id' => $new_group_id ) ) doesn't contain the parent id here, for some reason. We're going to do this directly:
		global $wpdb;
		$bp = buddypress();
		return $wpdb->get_var( $wpdb->prepare( "SELECT g.parent_id FROM {$bp->groups->table_name} g WHERE g.id = %d", $group_id ) );
	}

	public function bust_formatting(){
		// remove_all_shortcodes();
		remove_filter( 'the_content', 'do_shortcode' );
	}

	function wp_admin_menu_order( $menu_order ) {

		$towrite .= PHP_EOL . 'menu order:' . print_r( $menu_order, TRUE );
		$fp = fopen('wp-admin-menu-order.txt', 'a');
		fwrite($fp, $towrite);
		fclose($fp);

	    // if ( !$menu_ord ) return true;

	    // return array(
	    //     'index.php', // Dashboard
	    //     'separator1', // First separator
	    //     'edit.php', // Posts
	    //     'upload.php', // Media
	    //     'link-manager.php', // Links
	    //     'edit.php?post_type=page', // Pages
	    //     'edit-comments.php', // Comments
	    //     'separator2', // Second separator
	    //     'themes.php', // Appearance
	    //     'plugins.php', // Plugins
	    //     'users.php', // Users
	    //     'tools.php', // Tools
	    //     'options-general.php', // Settings
	    //     'separator-last', // Last separator
	    // );
	    return $menu_order;
	}

	function show_wp_menu_positions(){
		echo "<pre>";
		print_r($GLOBALS['menu']);
		echo "</pre>";
	}


}
