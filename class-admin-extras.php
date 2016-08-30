<?php
/**
 * Plugin class for pieces that do not rely on BP. This class should ideally be used to work with the
 * admin side of the WordPress site.
 *
 * @package CC Functionality Plugin
 * @author  David Cavins
 */
class CC_Functionality_Admin_Extras {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   0.1.0
	 *
	 * @var     string
	 */
	protected $version = '';

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

		$this->version = CC_FUNCTIONALITY_PLUGIN_VERSION;

		//	1. AJAX listeners for geography lookups.
			add_action( 'wp_ajax_get_lat_long', array( $this, 'ajax_get_lat_long' ) );
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
	 * Handle ajax requests to geocode locations using the Google API.
	 *
	 * @since 0.1.8
	 *
	 * @return JSON response
	 */
	public function ajax_get_lat_long() {
		/* format requests to this listener like this:
		 $.ajax({
			url: ajaxurl,
			data: {
				action: "get_lat_long",
				location: 'location string',
				_ajax_nonce: "<?php echo wp_create_nonce( 'get_lat_long_for_user_' . get_current_user_id() ); ?>"
			},
			cache: false,
			error: function() {
				alert("Could not compute a latitude/longitude for this location. Please modify your location.");
			},
			success: function(k) {
				// Do something
			}
		});
		*/

		check_ajax_referer( 'get_lat_long_for_user_' . get_current_user_id() );

		if ( ! isset( $_REQUEST['location'] ) ) {
			wp_send_json_error( 'Location data is required.' );
		}

		$location = urlencode( $_REQUEST['location'] );

		$details_url = "http://maps.googleapis.com/maps/api/geocode/json?address=" . $location . "&sensor=false";

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $details_url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		$response = json_decode( curl_exec($ch), true );

		// If Status Code is ZERO_RESULTS, OVER_QUERY_LIMIT, REQUEST_DENIED or INVALID_REQUEST
		if ( $response['status'] != 'OK' ) {
			// A location is provided, but it's not recognized by Google.
			wp_send_json_error( 'Location is not recognized.' );
		}

		if ( isset( $response['results'][0]['geometry'] ) ) {
			wp_send_json( array(
				'latitude'  => $response['results'][0]['geometry']['location']['lat'],
				'longitude' => $response['results'][0]['geometry']['location']['lng']
			) );
		}

		// If we've reached this point, it's not a good thing.
		wp_send_json_error( 'Location cannot be geocoded.' );
	}
}
