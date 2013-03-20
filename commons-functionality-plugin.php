<?php
/*
Plugin Name: Commons Functionality Plugin
Description: Adds functionality that is not theme-based
Version: 0.1
Requires at least: 3.3
Tested up to: 3.5
License: GPL3
Author: David Cavins
*/

// Add meta to groups
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
	<?php }

	
	function cc_group_meta_form_save( $group_id ) {
		global $bp, $wpdb;
		
		$checkboxes = array(
		'group_use_aggregated_activity'
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
  
	return $query_string;
	} // End check for bp groups component activity stream
}
add_filter( 'bp_ajax_querystring', 'parent_group_activity_aggregation', 99, 2 );
}
/* If you have code that does not need BuddyPress to run, then add it here. */