<?php
/*
Plugin Name: CC Functionality
Description: Adds functionality that is not theme dependent
Version: 0.1.5
License: GPLv3
Author: David Cavins
*/

define( 'CC_FUNCTIONALITY_PLUGIN_VERSION', '0.1.1' );

/**
 * Creates instance of CC_Functionality_BP_Dependent_Extras
 * BuddyPress-dependent filters should be added to this class.
 *
 * @package CC Functionality Plugin
 * @since 0.1.0
 */

function cc_functionality_buddypress_class_init(){
	// Get the class fired up
	require( dirname( __FILE__ ) . '/class-bp-dependent-extras.php' );
	add_action( 'bp_include', array( 'CC_Functionality_BP_Dependent_Extras', 'get_instance' ), 17 );
}
add_action( 'bp_include', 'cc_functionality_buddypress_class_init' );