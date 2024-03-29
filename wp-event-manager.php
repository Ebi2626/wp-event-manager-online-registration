<?php
/*
Plugin Name: WP Event Manager Online Registration

Plugin URI: https://www.wp-eventmanager.com/

Description: Extension of plugin wp-event-manager

Author: WP Event Manager Online Registration

Author URI: https://www.wp-eventmanager.com

Text Domain: wp-event-manager-online-registration

Domain Path: /languages

Version: 0.0.1

Since: 1.0.0

Requires WordPress Version at least: 4.1

Copyright: 2019 WP Event Manager

License: GNU General Public License v3.0

License URI: http://www.gnu.org/licenses/gpl-3.0.html

**/

// Exit if accessed directly

if (!defined('ABSPATH')) {

	exit;
}

/**
 * WP_Event_Manager class.
 */

class WP_Event_Manager
{

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  2.5
	 */
	private static $_instance = null;

	/**
	 * REST API instance.
	 *
	 * @var WP_Event_Manager_REST_API
	 */
	private $rest_api = null;

	/**
	 * Main WP Event Manager Instance.
	 *
	 * Ensures only one instance of WP Event Manager is loaded or can be loaded.
	 *
	 * @since  2.5
	 * @static
	 * @see WP_Event_Manager()
	 * @return self Main instance.
	 */
	public static function instance()
	{
		if (is_null(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor - get the plugin hooked in and ready
	 */

	public function __construct()
	{
		// Define constants
		define('EVENT_MANAGER_VERSION', '3.1.22');
		define('EVENT_MANAGER_PLUGIN_DIR', untrailingslashit(plugin_dir_path(__FILE__)));
		define('EVENT_MANAGER_PLUGIN_URL', untrailingslashit(plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__))));

		//Core		
		include('core/wp-event-manager-install.php');
		include('core/wp-event-manager-post-types.php');
		include('core/wp-event-manager-ajax.php');
		include('core/wp-event-manager-geocode.php');
		include('core/wp-event-manager-filters.php');
		include('core/wp-event-manager-cache-helper.php');
		include('core/wp-event-manager-date-time.php');


		//shortcodes
		include('shortcodes/wp-event-manager-shortcodes.php');

		//forms
		include('forms/wp-event-manager-forms.php');

		if (is_admin()) {
			include('admin/wp-event-manager-admin.php');
		}

		//external 
		include('external/external.php');

		// Init classes
		$this->forms      = WP_Event_Manager_Forms::instance();
		$this->post_types = WP_Event_Manager_Post_Types::instance();

		// Activation - works with symlinks
		register_activation_hook(basename(dirname(__FILE__)) . '/' . basename(__FILE__), array($this, 'activate'));

		// Switch theme
		add_action('after_switch_theme', array('WP_Event_Manager_Ajax', 'add_endpoint'), 10);

		add_action('after_switch_theme', array($this->post_types, 'register_post_types'), 11);

		add_action('after_switch_theme', 'flush_rewrite_rules', 15);

		// Actions
		add_action('after_setup_theme', array($this, 'load_plugin_textdomain'));

		add_action('after_setup_theme', array($this, 'include_template_functions'), 11);

		add_action('widgets_init', array($this, 'widgets_init'));

		add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));

		add_action('admin_init', array($this, 'updater'));
		add_action('wp_logout', array($this, 'cleanup_event_posting_cookies'));

		// Defaults for core actions
		add_action('event_manager_notify_new_user', 'wp_event_manager_notify_new_user', 10, 2);

		// Schedule cron events
		self::check_schedule_crons();
	}

	/**
	 * Called on plugin activation
	 */

	public function activate()
	{

		WP_Event_Manager_Ajax::add_endpoint();
		unregister_post_type('event_listing');
		add_filter('pre_option_event_manager_enable_categories', '__return_true');
		add_filter('pre_option_event_manager_enable_event_types', '__return_true');
		$this->post_types->register_post_types();
		remove_filter('pre_option_event_manager_enable_categories', '__return_true');
		remove_filter('pre_option_event_manager_enable_event_types', '__return_true');
		WP_Event_Manager_Install::install();
		//show notice after activating plugin
		update_option('event_manager_rating_showcase_admin_notices_dismiss', '0');
		flush_rewrite_rules();
	}

	/**
	 * Handle Updates
	 */

	public function updater()
	{
		if (version_compare(EVENT_MANAGER_VERSION, get_option('wp_event_manager_version'), '>')) {

			WP_Event_Manager_Install::update();
			flush_rewrite_rules();
		}
	}

	/**
	 * Localisation
	 */

	public function load_plugin_textdomain()
	{

		$domain = 'wp-event-manager';

		$locale = apply_filters('plugin_locale', get_locale(), $domain);

		load_textdomain($domain, WP_LANG_DIR . "/wp-event-manager/" . $domain . "-" . $locale . ".mo");

		load_plugin_textdomain($domain, false, dirname(plugin_basename(__FILE__)) . '/languages/');
	}


	/**
	 * Load functions
	 */

	public function include_template_functions()
	{

		include('wp-event-manager-functions.php');

		include('wp-event-manager-template.php');
	}

	/**
	 * Widgets init
	 */

	public function widgets_init()
	{

		include_once('widgets/wp-event-manager-widgets.php');
	}

	/**
	 * Register and enqueue scripts and css
	 */

	public function frontend_scripts()
	{
		$ajax_url         = WP_Event_Manager_Ajax::get_endpoint();
		$ajax_filter_deps = array('jquery', 'jquery-deserialize');

		$chosen_shortcodes   = array('submit_event_form', 'event_dashboard', 'events');
		$chosen_used_on_page = has_wpem_shortcode(null, $chosen_shortcodes);


		//jQuery Chosen - vendor
		if (apply_filters('event_manager_chosen_enabled', $chosen_used_on_page)) {

			wp_register_script('chosen', EVENT_MANAGER_PLUGIN_URL . '/assets/js/jquery-chosen/chosen.jquery.min.js', array('jquery'), '1.1.0', true);
			wp_register_script('wp-event-manager-term-multiselect', EVENT_MANAGER_PLUGIN_URL . '/assets/js/term-multiselect.min.js', array('jquery', 'chosen'), EVENT_MANAGER_VERSION, true);
			wp_register_script('wp-event-manager-multiselect', EVENT_MANAGER_PLUGIN_URL . '/assets/js/multiselect.min.js', array('jquery', 'chosen'), EVENT_MANAGER_VERSION, true);
			wp_enqueue_style('chosen', EVENT_MANAGER_PLUGIN_URL . '/assets/css/chosen.css');

			$ajax_filter_deps[] = 'chosen';
		}

		//file upload - vendor
		if (apply_filters('event_manager_ajax_file_upload_enabled', true)) {

			wp_register_script('jquery-iframe-transport', EVENT_MANAGER_PLUGIN_URL . '/assets/js/jquery-fileupload/jquery.iframe-transport.js', array('jquery'), '1.8.3', true);
			wp_register_script('jquery-fileupload', EVENT_MANAGER_PLUGIN_URL . '/assets/js/jquery-fileupload/jquery.fileupload.js', array('jquery', 'jquery-iframe-transport', 'jquery-ui-widget'), '5.42.3', true);
			wp_register_script('wp-event-manager-ajax-file-upload', EVENT_MANAGER_PLUGIN_URL . '/assets/js/ajax-file-upload.min.js', array('jquery', 'jquery-fileupload'), EVENT_MANAGER_VERSION, true);

			ob_start();
			get_event_manager_template('form-fields/uploaded-file-html.php', array('name' => '', 'value' => '', 'extension' => 'jpg'));
			$js_field_html_img = ob_get_clean();

			ob_start();
			get_event_manager_template('form-fields/uploaded-file-html.php', array('name' => '', 'value' => '', 'extension' => 'zip'));
			$js_field_html = ob_get_clean();

			wp_localize_script('wp-event-manager-ajax-file-upload', 'event_manager_ajax_file_upload', array(
				'ajax_url'               => $ajax_url,
				'js_field_html_img'      => esc_js(str_replace("\n", "", $js_field_html_img)),
				'js_field_html'          => esc_js(str_replace("\n", "", $js_field_html)),
				'i18n_invalid_file_type' => __('Invalid file type. Accepted types:', 'wp-event-manager')
			));
		}

		//jQuery Deserialize - vendor
		wp_register_script('jquery-deserialize', EVENT_MANAGER_PLUGIN_URL . '/assets/js/jquery-deserialize/jquery.deserialize.js', array('jquery'), '1.2.1', true);

		wp_enqueue_style('wp-event-manager-frontend', EVENT_MANAGER_PLUGIN_URL . '/assets/css/frontend.min.css');

		//common js
		wp_register_script('wp-event-manager-common', EVENT_MANAGER_PLUGIN_URL . '/assets/js/common.min.js', array('jquery'), EVENT_MANAGER_VERSION, true);
		wp_enqueue_script('wp-event-manager-common');

		//event submission forms and validation js
		wp_register_script('wp-event-manager-event-submission', EVENT_MANAGER_PLUGIN_URL . '/assets/js/event-submission.min.js', array('jquery', 'jquery-ui-core', 'jquery-ui-datepicker'), EVENT_MANAGER_VERSION, true);
		wp_localize_script('wp-event-manager-event-submission', 'wp_event_manager_event_submission', array(

			'start_of_week' => get_option('start_of_week'),

			'i18n_datepicker_format' => WP_Event_Manager_Date_Time::get_datepicker_format(),

			'i18n_timepicker_format' => WP_Event_Manager_Date_Time::get_timepicker_format(),

			'i18n_timepicker_step' => WP_Event_Manager_Date_Time::get_timepicker_step(),

			'ajax_url' 	 => admin_url('admin-ajax.php'),

			'show_past_date' => apply_filters('event_manager_show_past_date_frontend', false),

		));

		//jQuery UI date rang picker
		wp_register_style('wp-event-manager-jquery-ui-daterangepicker', EVENT_MANAGER_PLUGIN_URL . '/assets/js/jquery-ui-daterangepicker/jquery.comiseo.daterangepicker.css');
		wp_register_style('wp-event-manager-jquery-ui-daterangepicker-style', EVENT_MANAGER_PLUGIN_URL . '/assets/js/jquery-ui-daterangepicker/styles.css');
		wp_register_script('wp-event-manager-jquery-ui-daterangepicker', EVENT_MANAGER_PLUGIN_URL . '/assets/js/jquery-ui-daterangepicker/jquery.comiseo.daterangepicker.js', array('jquery-ui-core', 'jquery-ui-button', 'jquery-ui-datepicker', 'jquery-ui-menu', 'jquery-ui-widget', 'moment'), EVENT_MANAGER_VERSION, true);


		wp_register_script('wp-event-manager-content-event-listing', EVENT_MANAGER_PLUGIN_URL . '/assets/js/content-event-listing.min.js', array('jquery', 'wp-event-manager-common'), EVENT_MANAGER_VERSION, true);
		wp_localize_script('wp-event-manager-content-event-listing', 'event_manager_content_event_listing', array(

			'i18n_datepicker_format' => WP_Event_Manager_Date_Time::get_datepicker_format(),

			'i18n_initialText' => __('Select Date Range', 'wp-event-manager'),
			'i18n_applyButtonText' => __('Apply', 'wp-event-manager'),
			'i18n_clearButtonText' => __('Clear', 'wp-event-manager'),
			'i18n_cancelButtonText' => __('Cancel', 'wp-event-manager'),

			'i18n_today' => __('Today', 'wp-event-manager'),
			'i18n_tomorrow' => __('Tomorrow', 'wp-event-manager'),
			'i18n_thisWeek' => __('This Week', 'wp-event-manager'),
			'i18n_nextWeek' => __('Next Week', 'wp-event-manager'),
			'i18n_thisMonth' => __('This Month', 'wp-event-manager'),
			'i18n_nextMonth' => __('Next Month', 'wp-event-manager'),
			'i18n_thisYear' => __('This Year', 'wp-event-manager'),
			'i18n_nextYear' => __('Next Year', 'wp-event-manager')
		));
		//ajax filters js
		wp_register_script('wp-event-manager-ajax-filters', EVENT_MANAGER_PLUGIN_URL . '/assets/js/event-ajax-filters.js', $ajax_filter_deps, EVENT_MANAGER_VERSION, true);
		wp_localize_script('wp-event-manager-ajax-filters', 'event_manager_ajax_filters', array(
			'ajax_url'                => $ajax_url,
			'is_rtl'                  => is_rtl() ? 1 : 0,
			'lang'                    => apply_filters('wpem_lang', null) //defined( 'ICL_LANGUAGE_CODE' ) ? ICL_LANGUAGE_CODE : '', // WPML workaround until this is standardized			
		));

		//dashboard
		wp_register_script('wp-event-manager-event-dashboard', EVENT_MANAGER_PLUGIN_URL . '/assets/js/event-dashboard.min.js', array('jquery'), EVENT_MANAGER_VERSION, true);
		wp_localize_script('wp-event-manager-event-dashboard', 'event_manager_event_dashboard', array(

			'i18n_btnOkLabel' => __('Delete', 'wp-event-manager'),

			'i18n_btnCancelLabel' => __('Cancel', 'wp-event-manager'),

			'i18n_confirm_delete' => __('Are you sure you want to delete this event?', 'wp-event-manager')

		));

		//organizer dashboard
		wp_register_script('wp-event-manager-organizer-dashboard', EVENT_MANAGER_PLUGIN_URL . '/assets/js/organizer-dashboard.min.js', array('jquery'), EVENT_MANAGER_VERSION, true);
		wp_localize_script('wp-event-manager-organizer-dashboard', 'event_manager_organizer_dashboard', array(

			'i18n_btnOkLabel' => __('Delete', 'wp-event-manager'),

			'i18n_btnCancelLabel' => __('Cancel', 'wp-event-manager'),

			'i18n_confirm_delete' => __('Are you sure you want to delete this organizer?', 'wp-event-manager')

		));

		//venue dashboard
		wp_register_script('wp-event-manager-venue-dashboard', EVENT_MANAGER_PLUGIN_URL . '/assets/js/venue-dashboard.min.js', array('jquery'), EVENT_MANAGER_VERSION, true);
		wp_localize_script('wp-event-manager-venue-dashboard', 'event_manager_venue_dashboard', array(

			'i18n_btnOkLabel' => __('Delete', 'wp-event-manager'),

			'i18n_btnCancelLabel' => __('Cancel', 'wp-event-manager'),

			'i18n_confirm_delete' => __('Are you sure you want to delete this venue?', 'wp-event-manager')

		));

		//organizer
		wp_register_script('wp-event-manager-organizer', EVENT_MANAGER_PLUGIN_URL . '/assets/js/organizer.min.js', array('jquery', 'wp-event-manager-common'), EVENT_MANAGER_VERSION, true);

		wp_localize_script('wp-event-manager-organizer', 'event_manager_organizer', array(
			'i18n_upcomingEventsTitle' => __('Upcoming Events', 'wp-event-manager'),

			'i18n_pastEventsTitle' => __('Past Events', 'wp-event-manager'),

			'i18n_currentEventsTitle' => __('Current Events', 'wp-event-manager')
		));


		//venue
		wp_register_script('wp-event-manager-venue', EVENT_MANAGER_PLUGIN_URL . '/assets/js/venue.min.js', array('jquery', 'wp-event-manager-common'), EVENT_MANAGER_VERSION, true);

		wp_localize_script('wp-event-manager-venue', 'event_manager_venue', array(
			'i18n_upcomingEventsTitle' => __('Upcoming Events', 'wp-event-manager'),

			'i18n_pastEventsTitle' => __('Past Events', 'wp-event-manager'),

			'i18n_currentEventsTitle' => __('Current Events', 'wp-event-manager')
		));


		//registration
		wp_register_script('wp-event-manager-event-registration', EVENT_MANAGER_PLUGIN_URL . '/assets/js/event-registration.min.js', array('jquery'), EVENT_MANAGER_VERSION, true);


		wp_enqueue_style('wp-event-manager-jquery-ui-css', EVENT_MANAGER_PLUGIN_URL . '/assets/js/jquery-ui/jquery-ui.css');

		wp_enqueue_style('wp-event-manager-jquery-timepicker-css', EVENT_MANAGER_PLUGIN_URL . '/assets/js/jquery-timepicker/jquery.timepicker.min.css');
		wp_register_script('wp-event-manager-jquery-timepicker', EVENT_MANAGER_PLUGIN_URL . '/assets/js/jquery-timepicker/jquery.timepicker.min.js', array('jquery', 'jquery-ui-core'), EVENT_MANAGER_VERSION, true);
		wp_enqueue_script('wp-event-manager-jquery-timepicker');

		wp_register_script('wp-event-manager-slick-script', EVENT_MANAGER_PLUGIN_URL . '/assets/js/slick/slick.min.js', array('jquery'));
		wp_register_style('wp-event-manager-slick-style', EVENT_MANAGER_PLUGIN_URL . '/assets/js/slick/slick.css', array());

		wp_register_style('wp-event-manager-grid-style', EVENT_MANAGER_PLUGIN_URL . '/assets/css/wpem-grid.min.css');
		wp_register_style('wp-event-manager-font-style', EVENT_MANAGER_PLUGIN_URL . '/assets/fonts/style.css');

		wp_enqueue_style('wp-event-manager-grid-style');
		wp_enqueue_style('wp-event-manager-font-style');
	}
	/**
	 * Cleanup event posting cookies.
	 */
	public function cleanup_event_posting_cookies()
	{
		if (isset($_COOKIE['wp-event-manager-submitting-event-id'])) {
			setcookie('wp-event-manager-submitting-event-id', '', 0, COOKIEPATH, COOKIE_DOMAIN, false);
		}
		if (isset($_COOKIE['wp-event-manager-submitting-event-key'])) {
			setcookie('wp-event-manager-submitting-event-key', '', 0, COOKIEPATH, COOKIE_DOMAIN, false);
		}
	}

	/**
	 * Check cron status
	 *
	 **/
	public function check_schedule_crons()
	{
		if (!wp_next_scheduled('event_manager_check_for_expired_events')) {
			wp_schedule_event(time(), 'hourly', 'event_manager_check_for_expired_events');
		}
		if (!wp_next_scheduled('event_manager_delete_old_previews')) {
			wp_schedule_event(time(), 'daily', 'event_manager_delete_old_previews');
		}
		if (!wp_next_scheduled('event_manager_clear_expired_transients')) {
			wp_schedule_event(time(), 'twicedaily', 'event_manager_clear_expired_transients');
		}
	}
}

/**
 * Create link on plugin page for event manager plugin settings
 */
function add_plugin_page_event_manager_settings_link($links)
{
	$links[] = '<a href="' .
		admin_url('edit.php?post_type=event_listing&page=event-manager-settings') .
		'">' . __('Settings', 'wp-event-manager') . '</a>';
	return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'add_plugin_page_event_manager_settings_link');

/**
 * Main instance of WP Event Manager.
 *
 * Returns the main instance of WP Event Manager to prevent the need to use globals.
 *
 * @since  2.5
 * @return WP_Event_Manager
 */
function WPEM()
{ // phpcs:ignore WordPress.NamingConventions.ValidFunctionName
	return WP_Event_Manager::instance();
}
$GLOBALS['event_manager'] =  WPEM();
