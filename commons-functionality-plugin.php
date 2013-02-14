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
    $group_id = bp_get_group_id() ;

    //Check if this group is set to aggregate child group activity
    if ( cc_group_custom_meta('group_use_aggregated_activity' ) == 'on' ) {

	    //Get the children of the current group
	    $child_array = class_exists('BP_Groups_Hierarchy') ? BP_Groups_Hierarchy::has_children($group_id) : '' ;
	    //has_children() returns an array of group ids or an empty array if no children are found
	    $child_ids = implode( ',', $child_array );
	    //attach the current group to the front of the list, if there are children, else return the parent only
	    $primary_id = !empty($child_ids) ? $group_id . ',' . $child_ids : $group_id ; 
	    
	    //Finally, append the result to the query string. This works because bp_has_activities() allows a comma-separated list of ids as the primary_id argument.
	    $query_string .= '&primary_id=' . $primary_id ;
	}
  }
  
  return $query_string;
}
add_filter( 'bp_ajax_querystring', 'parent_group_activity_aggregation', 99, 2 );
}
/* If you have code that does not need BuddyPress to run, then add it here. */
