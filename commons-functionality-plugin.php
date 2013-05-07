<?php
/*
Plugin Name: Commons Functionality Plugin
Description: Adds groups activity aggregation, maps & reports pane for groups, custom group home pages
Version: 0.1
Requires at least: 3.3
Tested up to: 3.5
License: GPL3
Author: David Cavins
*/
/* Contents:
	1. Aggregate group activity streams via a group meta checkbox
	1a. Identify "prime" groups via a group meta checkbox
	2. Add maps & reports pane to groups (not finished)
	3. Add custom group home pages (requires template modifications, too)
*/

// 	1. Aggregate group activity streams via a group meta checkbox
//	1a. Identify "prime" groups via a group meta checkbox
//////////////////////
//////////////////////

// Always attach functions that require BuddyPress to run to bp_init or bp_loaded
add_action( 'bp_init', 'bp_group_meta_init' );

function bp_group_meta_init() {
	
	function cc_group_custom_meta( $meta_key = '' ) {
	//get current group id and load meta_key value if passed. If not pass it blank
	return groups_get_groupmeta( bp_get_group_id(), $meta_key) ;
	}
	
	// This function is our custom field's form that is called when creating a group and when editing group details
	function cc_group_meta_form_markup() {
		global $bp, $wpdb;
		?>
		<p><label for="group_use_aggregated_activity"><input type="checkbox" id="group_use_aggregated_activity" name="group_use_aggregated_activity" <?php checked( cc_group_custom_meta('group_use_aggregated_activity'), 'on' ); ?> /> Include child group activity in this group&rsquo;s activity stream.</label></p>
		<p><label for="group_is_prime_group"><input type="checkbox" id="group_is_prime_group" name="group_is_prime_group" <?php checked( cc_group_custom_meta('group_is_prime_group'), 'on' ); ?> /> This group is a "prime" group.</label></p>
	<?php }

	
	function cc_group_meta_form_save( $group_id ) {
		global $bp, $wpdb;
		
		$checkboxes = array(
		'group_use_aggregated_activity',
		'group_is_prime_group'
		);
			foreach( $checkboxes as $field ) {
			$key = $field;
				$chk = ( isset( $_POST[$key] ) && $_POST[$key] ) ? 'on' : 'off';
				groups_update_groupmeta( $group_id, $field, $chk );
			}
	}
add_filter( 'groups_custom_group_fields_editable', 'cc_group_meta_form_markup' );
add_action( 'groups_group_details_edited', 'cc_group_meta_form_save' );
add_action( 'groups_created_group',  'cc_group_meta_form_save' );
	 

//Filtering the activity stream for parent groups that are set to include child group activity

function parent_group_activity_aggregation ( $query_string, $object ) {
  global $bp;

	  //Check to see that we're in the BuddyPress groups component, not the member stream or other. Also check that this is an activity request, not the group directory.
	  if ( bp_is_group() && ( $object == 'activity' ) ) {
	    //Get the group id
	    $group_id = $bp->groups->current_group->id ;

	    //Check if this group is set to aggregate child group activity
	    if ( cc_group_custom_meta('group_use_aggregated_activity' ) == 'on' ) {

		    //Get the children of the current group
		    $child_array = class_exists('BP_Groups_Hierarchy') ? BP_Groups_Hierarchy::has_children($group_id) : '' ;
		    //has_children() returns an array of group ids or an empty array if no children are found
		    if (!empty($child_array)) {
				
	    	foreach ($child_array as $children) {
					$next_gen = array(); //use this to run next loop?
					$newgen = BP_Groups_Hierarchy::has_children($children);
				    	if (!empty($newgen)) {
						    $child_array = array_merge($child_array, $newgen);
						    $next_gen = array_merge($next_gen, $newgen);
						    if (!empty($next_gen)){
							    foreach ($next_gen as $new_round) {
					    			$newgen = BP_Groups_Hierarchy::has_children($new_round);
								    $child_array = array_merge($child_array, $newgen);
						    	}
						    	//this is getting pretty ridiculous
						    }
						}
				}

			$group_family = array_merge(array($group_id),$child_array);

	    	// HACK: If the group is not a public group, all private subgroup activity will be shown. So we'll remove the groups from the array that the user doesn't belong to, for non-moderator users.
	    	// MAYBE TODO could use the user "scope" to resrict to user's groups intersected with family when in a private group with aggregated activity
			if ( ( 'public' != $bp->groups->current_group->status ) && !bp_current_user_can( 'bp_moderate' ) ) {
				$group_ids = BP_Groups_Member::get_group_ids( $bp->loggedin_user->id );
				$group_family = array_intersect($group_family, $group_ids['groups']);
			}

		    //attach the current group to the front of the list, if there are children, else return the parent only
		    $primary_id = implode( ',', $group_family ); 
		    
		    //Finally, append the result to the query string. This works because bp_has_activities() allows a comma-separated list of ids as the primary_id argument.
		    $query_string .= '&primary_id=' . $primary_id ;
	   		}//end if (!empty($child_array))

	  }// End check for aggregation turned on
  
	} // End check for bp groups component activity stream
	return $query_string;
}
add_filter( 'bp_ajax_querystring', 'parent_group_activity_aggregation', 99, 2 );
}

// 	2. Add maps & reports pane to groups (not finished)
//////////////////////
//////////////////////
// add_action( 'bp_init', 'bp_add_map_pane_init' );

function bp_add_map_pane_init() {
	global $bp;
	
	bp_core_new_subnav_item( array(
	   'name' => 'My Group Page',
	   'slug' => 'my-group-page',
	   'parent_url' => $bp->loggedin_user->domain . $bp->groups->slug . '/',
	   'parent_slug' => $bp->groups->slug,
	   'screen_function' => 'my_groups_page_function_to_show_screen',
	   'position' => 40 ) );

	function my_groups_page_function_to_show_screen() {

	    //add title and content here - last is to call the members plugin.php template
	    // add_action( 'bp_template_title', 'my_groups_page_function_to_show_screen_title' );
	    add_action( 'bp_template_content', 'my_groups_page_function_to_show_screen_content' );
	    bp_core_load_template( apply_filters( 'bp_core_template_plugin', 'members/single/plugins' ) );
	}

	function my_groups_page_function_to_show_screen_title() {
	        echo 'My Page Title';
	}
	function my_groups_page_function_to_show_screen_content() { 

	        // page content goes here
	}

} // End bp_add_map_pane_init()

// 	3. Add custom group home pages (requires template modifications, too)
//////////////////////
//////////////////////
add_action( 'bp_actions', 'add_group_activity_tab', 8 );

function add_group_activity_tab() {
	  global $bp;
	  // Only check if we're on a group page
	  if( bp_is_group() ) { 

	  // Only add the "Home" tab if the group has a custom front page, so check for an associated post. 
	  // Only add the new "Activity" tab if the group is visible to the user.
	    $group_id = $bp->groups->current_group->id ;
	    $visible = $bp->groups->current_group->is_visible ;
	    $args =  array(
	       'post_type'   => 'group_home_page',
	       'posts_per_page' => '1',
	       'meta_query'  => array(
	                           array(
	                            'key'           => 'group_home_page_association',
	                            'value'         => $group_id,
	                            'compare'       => '=',
	                            'type'          => 'NUMERIC'
	                            )
	                        )
	    ); 
	    $custom_front_query = new WP_Query( $args );

	    if( $custom_front_query->have_posts() && $visible ) { 
	      bp_core_new_subnav_item( 
	        array( 
	          'name' => 'Activity', 
	          'slug' => 'activity', 
	          'parent_slug' => $bp->groups->current_group->slug, 
	          'parent_url' => bp_get_group_permalink( $bp->groups->current_group ), 
	          'position' => 11, 
	          'item_css_id' => 'nav-activity',
	          'screen_function' => create_function('',"bp_core_load_template( apply_filters( 'groups_template_group_home', 'groups/single/home' ) );"),
	          'user_has_access' => 1
	        ) 
	      );
	   
	      if ( bp_is_current_action( 'activity' ) ) {
	        add_action( 'bp_template_content_header', create_function( '', 'echo "' . esc_attr( 'Activity' ) . '";' ) );
	        add_action( 'bp_template_title', create_function( '', 'echo "' . esc_attr( 'Activity' ) . '";' ) );
	      } // END if ( bp_is_current_action( 'activity' ) ) 
	    } // END if( $custom_front_query->have_posts() )
	  } //END if( bp_is_group() )
	}

//Generate Group Home Page custom post type to populate group home pages
add_action( 'init', 'register_cpt_group_home_page' );

	function register_cpt_group_home_page() {

	    $labels = array( 
	        'name' => _x( 'Group Home Pages', 'group_home_page' ),
	        'singular_name' => _x( 'Group Home Page', 'group_home_page' ),
	        'add_new' => _x( 'Add New', 'group_home_page' ),
	        'add_new_item' => _x( 'Add New Group Home Page', 'group_home_page' ),
	        'edit_item' => _x( 'Edit Group Home Page', 'group_home_page' ),
	        'new_item' => _x( 'New Group Home Page', 'group_home_page' ),
	        'view_item' => _x( 'View Group Home Page', 'group_home_page' ),
	        'search_items' => _x( 'Search Group Home Pages', 'group_home_page' ),
	        'not_found' => _x( 'No group home pages found', 'group_home_page' ),
	        'not_found_in_trash' => _x( 'No group home pages found in Trash', 'group_home_page' ),
	        'parent_item_colon' => _x( 'Parent Group Home Page:', 'group_home_page' ),
	        'menu_name' => _x( 'Group Homes', 'group_home_page' ),
	    );

	    $args = array( 
	        'labels' => $labels,
	        'hierarchical' => false,
	        'description' => 'This post type is queried when a group home page is requested.',
	        'supports' => array( 'title', 'editor' ),
	        'public' => true,
	        'show_ui' => true,
	        'show_in_menu' => true,
	        'menu_position' => 53,
	        //'menu_icon' => '',
	        'show_in_nav_menus' => false,
	        'publicly_queryable' => true,
	        'exclude_from_search' => true,
	        'has_archive' => false,
	        'query_var' => true,
	        'can_export' => true,
	        'rewrite' => false,
	        'capability_type' => 'post'//,
	        //'map_meta_cap'    => true
	    );

	    register_post_type( 'group_home_page', $args );
	}

	//Add meta box to Group Home Page custom post type to associate posts with the group home page

	/* Fire our meta box setup function on the post editor screen. */
	add_action( 'load-post.php', 'group_home_meta_boxes_setup' );
	add_action( 'load-post-new.php', 'group_home_meta_boxes_setup' );

	/* Meta box setup function. */
	function group_home_meta_boxes_setup() {

	  /* Add meta boxes on the 'add_meta_boxes' hook. */
	  add_action( 'add_meta_boxes', 'add_group_home_meta_boxes' );

	  /* Save post meta on the 'save_post' hook. */
	  add_action( 'save_post', 'save_group_home_meta', 10, 2 );
	}

	/* Create one or more meta boxes to be displayed on the group home page editor screen. */
	function add_group_home_meta_boxes() {

	  add_meta_box(
	    'group-home-page-association',      // Unique ID
	    esc_html__( 'Groups to Use this Home Page', 'group-home-page' ),    // Title
	    'group_home_page_meta_box',   // Callback function
	    'group_home_page',         // Admin page (or post type)
	    'normal',         // Context
	    'default'         // Priority
	  );
	}

	/* Display the post meta box. */
	function group_home_page_meta_box( $object, $box ) { ?>

	  <?php wp_nonce_field( basename( __FILE__ ), 'group_home_association_nonce' ); ?>
	<!-- Loop through Group Tree with the addition of checkboxes -->
	  <?php if (class_exists('BP_Groups_Hierarchy')) {
	    $tree = BP_Groups_Hierarchy::get_tree();
	    //print_r($tree);
	    $group_associations = get_post_meta( $object->ID, 'group_home_page_association', false); // Use false because we want an array of associations to be returned
	    //print_r($group_associations);

	    echo '<ul class="group-tree">';
	    foreach ($tree as $branch) {
	      ?>
	      <li><!-- ID: <?php echo $branch->id ;?> Name: <?php echo $branch->name;?> Parent ID:<?php echo $branch->parent_id ;?> -->
	        <input type="checkbox" id="group-home-page-assoc-<?php echo $branch->id ?>" name="group_home_page_association[]" value="<?php echo $branch->id ?>" <?php checked( in_array( $branch->id , $group_associations ) ); ?> />
	        <label for="group-home-page-assoc-<?php echo $branch->id ?>"><?php echo $branch->name; ?></label>
	      </li>
	      <?php
	    }
	    echo '</ul>';

	  }
	}

	/* Save the meta box's post metadata. */
	function save_group_home_meta( $post_id, $post ) {

	  /* Verify the nonce before proceeding. */
	  if ( !isset( $_POST['group_home_association_nonce'] ) || !wp_verify_nonce( $_POST['group_home_association_nonce'], basename( __FILE__ ) ) )
	    return $post_id;

	  /* Get the post type object. */
	  $post_type = get_post_type_object( $post->post_type );

	  /* Check if the current user has permission to edit the post. */
	  if ( !current_user_can( $post_type->cap->edit_post, $post_id ) )
	    return $post_id;

	  if (!empty($_POST['group_home_page_association']) && is_array($_POST['group_home_page_association'])) {
	        delete_post_meta($post_id, 'group_home_page_association');
	        foreach ($_POST['group_home_page_association'] as $association) {
	            add_post_meta($post_id, 'group_home_page_association', $association);
	        }
	    }

	}