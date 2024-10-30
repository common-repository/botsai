<?php
/**
* Plugin Name: Botsai
* Plugin URI: http://www.botsai.com/wordpress
* Version: 1.0.0
* Author: Botsai Dev Team
* Author URI: http://www.botsai.com/
* Description: Allows you to add Botsai to your WordPress site
* License: GPL2
* Text Domain: Botsai
* Domain Path: languages
*/

/*  Copyright 2019 Ascendum

*/

/**
* Insert Class
*/
class InsertBotsai {
	/**
	* Constructor
	*/
	public function __construct() {

		// Plugin Details
		$this->plugin               = new stdClass;
		$this->plugin->name         = 'Botsai'; // Plugin Folder
		$this->plugin->displayName  = 'Botsai'; // Plugin Name
		$this->plugin->version      = '1.0.0';
		$this->plugin->folder       = plugin_dir_path( __FILE__ );
		$this->plugin->url          = plugin_dir_url( __FILE__ );
		$this->plugin->db_welcome_dismissed_key = $this->plugin->name . '_welcome_dismissed_key';

		// Check if the global wpb_feed_append variable exists. If not, set it.
		if ( ! array_key_exists( 'wpb_feed_append', $GLOBALS ) ) {
					$GLOBALS['wpb_feed_append'] = false;
		}

		// Hooks
		add_action( 'admin_init', array( &$this, 'registerSettings' ) );
		add_action( 'admin_menu', array( &$this, 'adminPanelsAndMetaBoxes' ) );
		add_action( 'wp_feed_options', array( &$this, 'dashBoardRss' ), 10, 2 );
		add_action( 'admin_notices', array( &$this, 'dashboardNotices' ) );
		add_action( 'wp_ajax_' . $this->plugin->name . '_dismiss_dashboard_notices', array( &$this, 'dismissDashboardNotices' ) );

		// Hooks
		add_action( 'wp_footer', array( &$this, 'frontendFooter' ) );

		// Filters
		add_filter( 'dashboard_secondary_items', array( &$this, 'dashboardSecondaryItems' ) );
	}

	/**
	 * Number of Secondary feed items to show
	 */
	function dashboardSecondaryItems() {
		return 6;
	}

	/**
	 * 
	 */
	function dashboardRss( $feed, $url ) {
			// Used if we want to add an RSS feed to the plugin admin in the future.
	}

	/**
	 * Show relevant notices for the plugin
	 */
	function dashboardNotices() {
		global $pagenow;
		// Pulls notices for sidebar from the dashboard-notices file.
		if ( !get_option( $this->plugin->db_welcome_dismissed_key ) ) {
			if ( ! ( $pagenow == 'options-general.php' && isset( $_GET['page'] ) && $_GET['page'] == 'Botsai' ) ) {
					$setting_page = admin_url( 'options-general.php?page=' . $this->plugin->name );
					// load the notices view
					include_once( $this->plugin->folder . '/views/dashboard-notices.php' );
			}
		}
	}

	/**
	 * Dismiss the welcome notice for the plugin
	 */
	function dismissDashboardNotices() {
		check_ajax_referer( $this->plugin->name . '-nonce', 'nonce' );
		// user has dismissed the welcome notice
		update_option( $this->plugin->db_welcome_dismissed_key, 1 );
		exit;
	}

	/**
	* Register Settings
	*/
	function registerSettings() {
		register_setting( $this->plugin->name, 'botsai_insert_footer', 'trim' );
	}

	/**
    * Register the plugin settings panel
    */
    function adminPanelsAndMetaBoxes() {
    	add_submenu_page( 'options-general.php', $this->plugin->displayName, $this->plugin->displayName, 'manage_options', $this->plugin->name, array( &$this, 'adminPanel' ) );
	}

	/**
	* Output the Administration Panel
	* Save POST data from the Administration Panel into a WordPress option
	*/
	function adminPanel() {
		// only admin user can access this page
		if ( !current_user_can( 'administrator' ) ) {
			echo '<p>' . __( 'Sorry, you are not allowed to access this page.', 'Botsai' ) . '</p>';
			return;
		}

		// Save Settings
		if ( isset( $_REQUEST['submit'] ) ) {
			// Check nonce
			if ( !isset( $_REQUEST[$this->plugin->name.'_nonce'] ) ) {
					// Missing nonce
					$this->errorMessage = __( 'nonce field is missing. Settings NOT saved.', 'Botsai' );
			} elseif ( !wp_verify_nonce( $_REQUEST[$this->plugin->name.'_nonce'], $this->plugin->name ) ) {
					// Invalid nonce
					$this->errorMessage = __( 'Invalid nonce specified. Settings NOT saved.', 'Botsai' );
			} else {
				// Save
				// $_REQUEST has already been slashed by wp_magic_quotes in wp-settings

				$embedRegex = '/^[0-9a-fA-F]{24}$/';
				$embedCode = sanitize_text_field( $_REQUEST['botsai_insert_footer'] );

				if ( preg_match( $embedRegex, $embedCode ) ) {
					update_option( 'botsai_insert_footer', $embedCode );
					update_option( $this->plugin->db_welcome_dismissed_key, 1 );
					$this->message = __( 'Settings Saved.', 'Botsai' );
				} else {
					$this->errorMessage = __( 'Invalid embed code specified. Settings NOT saved.', 'Botsai' );
				}
			}
		}

		// Get latest settings
		$this->settings = array(
			'botsai_insert_footer' => esc_html( wp_unslash( get_option( 'botsai_insert_footer' ) ) ),
		);

		// Load Settings Form
		include_once( $this->plugin->folder . '/views/settings.php' );
	}

	/**
	* Loads plugin textdomain
	*/
	function loadLanguageFiles() {
		//If we need multiple languages in the future
		load_plugin_textdomain( 'Botsai', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	* Outputs script to footer
	*/
	function frontendFooter() {
		$this->output( 'botsai_insert_footer' );
	}

	/**
	* Outputs the given setting, if conditions are met
	*
	* @param string $setting Setting Name
	* @return output
	*/
	function output( $setting ) {
		// Ignore admin, feed, robots or trackbacks
		if ( is_admin() || is_feed() || is_robots() || is_trackback() ) {
			return;
		}

		// provide the opportunity to Ignore botsai via filters
		if ( apply_filters( 'disable_botsai', false ) ) {
			return;
		}

		// Get meta
		$meta = get_option( $setting );
		if ( empty( $meta ) ) {
			return;
		}
		if ( trim( $meta ) == '' ) {
			return;
		}

		// Output
		echo wp_unslash( '<script id="botsai-embed-script" src="https://chat.botsai.com/embed.js" data-bot-id="'.$meta.'" data-root-url="https://chat.botsai.com"> </script>' );
	}
}

$botsai = new InsertBotsai();
