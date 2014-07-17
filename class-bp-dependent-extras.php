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
	const VERSION = '0.1.0';

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


		// 	2. BuddyPress Docs behavior changes
		//		a. Change default access settings to "group-members" if a group is associated with a doc.
				add_filter( 'bp_docs_get_doc_settings', array( $this, 'mod_bp_docs_access_defaults_for_groups' ), 20, 3 );
		// 		b. If this is a new child group, don't show the bp-docs create step but instead set it up to match the parent.
				add_filter('bp_docs_force_enable_at_group_creation', array( $this, 'disable_bp_docs_create_step' ), 12, 1);
		//		c. If this is a new child group, we'll set up BP docs to match the parent group's setup. This step copies the parent group's attributes over to the child group.
				add_filter('bp_docs_default_group_settings', array( $this, 'bp_docs_default_settings_for_child_groups' ), 12, 2);

		//	3. BP Group Hierarchy behavior changes
		// 		a. Make "only Group Admins can create member groups" the only option for create group form.
				add_filter('bp_group_hierarchy_subgroup_permission_options', array( $this, 'group_hierarchy_creators_default_option' ), 17, 2);



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

		if ( $button[ 'id' ] == 'request_membership' )
			$button[ 'wrapper_class' ] = str_replace( 'group-button', '', $button[ 'wrapper_class' ] );

		return $button;
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

}