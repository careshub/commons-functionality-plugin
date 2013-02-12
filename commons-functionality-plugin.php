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

// Always attach functions that require BuddyPress to run to bp_init or bp_loaded
add_action( 'bp_init', 'bp_group_meta_init' );

function bp_group_meta_init() {
	
	function cc_group_custom_meta($meta_key='') {
	//get current group id and load meta_key value if passed. If not pass it blank
	return groups_get_groupmeta( bp_get_group_id(), $meta_key) ;
	}
	
	// code if using seperate files require( dirname( __FILE__ ) . '/buddypress-group-meta.php' );
	// This function is our custom field's form that is called in create a group and when editing group details
	function cc_group_meta_form_markup() {
		global $bp, $wpdb;
		//$check_show_aggregated_activity = isset( cc_group_custom_meta('group-use-tag-activity') ) ? esc_attr( cc_group_custom_meta('group-use-tag-activity') ) : '';
		?>
		<p><label for="group_use_tag_activity"><input type="checkbox" id="group_use_tag_activity" name="group_use_tag_activity" <?php checked( cc_group_custom_meta('group_use_tag_activity'), 'on' ); ?> /> Show the aggregated activity of this group by tag.</label></p>
	<?php }

	// This saves the custom group meta – props to Boone for the function
	// Where $plain_fields = array.. you may add additional fields, eg
	//  $plain_fields = array(
	//      'field-one',
	//      'field-two'
	//  );
	function cc_group_meta_form_save( $group_id ) {
		global $bp, $wpdb;
		// $plain_fields = array();
		// 	foreach( $plain_fields as $field ) {
		// 	$key = $field;
		// 		if ( isset( $_POST[$key] ) ) {
		// 			$value = $_POST[$key];
		// 			groups_update_groupmeta( $group_id, $field, $value );
		// 		}
		// 	}

		$checkboxes = array(
		'group_use_tag_activity'
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
	 
	// Show the custom field in the group header
	function show_cc_meta_in_header( ) {
	echo "<p>Show the aggregated activity of this group by tag: " . cc_group_custom_meta('group_use_tag_activity') . "</p>";
	}
	//add_action('bp_group_header_meta' , 'show_cc_meta_in_header') ;
}
/* If you have code that does not need BuddyPress to run, then add it here. */

