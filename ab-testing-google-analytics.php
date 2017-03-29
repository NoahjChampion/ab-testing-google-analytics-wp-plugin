<?php
/*
Plugin Name: A/B Testing with Google Analytics
Plugin URI: https://wordpress.org/plugins/ab-testing-google-analytics/
Description: Adds options to enable Google Optimize integration with your Monster Insights Analytics.
Version: 1.0.0
Author: Daniel Iser
Author URI: https://danieliser.com/
Text Domain: ab-testing-google-analytics
*/

/**
 * Detect if an analytics plugin is active.
 *
 * @return bool|string
 */
function abga_analytics_detected() {
	if ( function_exists( 'MonsterInsights' ) ) {
		return 'montserinsights';
	}

	if ( class_exists( 'WP_Analytify' ) ) {
		return 'analytify';
	}

	return false;
}

/**
 * Initialize
 */
function abga_init() {
	if ( ! ( $plugin = abga_analytics_detected() ) ) {
		return;
	}

	switch ( abga_analytics_detected() ) {
		case 'monsterinsights':
			add_filter( 'monsterinsights_frontend_tracking_options_before_pageview', 'abga_monsterinsights_frontend_tracking_options' );
			add_action( 'monsterinsights_tracking_before', 'abga_tracking_before' );
			add_filter( 'monsterinsights_settings_tabs', 'abga_settings_tabs' );
			add_filter( 'monsterinsights_registered_settings', 'abga_registered_settings' );
			add_action( 'admin_head', 'abga_admin_head_styles', 100 );
			break;

		case 'analytify':
			add_filter( 'wp_analytify_pro_setting_tabs', 'abga_settings_tabs' );
			add_filter( 'wp_analytify_pro_setting_fields', 'abga_registered_settings' );
			add_action( 'ga_ecommerce_js', 'abga_render_frontend_tracking_code' );
			add_action( 'wp_head', 'abga_tracking_before', 9 );
			break;
	}
}

add_action( 'plugins_loaded', 'abga_init', 11 );

/**
 * Standard method to get an option from various plugins.
 *
 * @param $key
 * @param bool $default
 *
 * @return bool|string
 */
function abga_get_option( $key, $default = false ) {
	switch( abga_analytics_detected() ) {
		case 'monsterinsights':
			return monsterinsights_get_option( $key, $default );
		case 'analytify':
			return $GLOBALS['WP_ANALYTIFY']->settings->get_option( $key, 'wp-analytify-optimize', $default );
	}

	return false;
}

/**
 * Get the container ID.
 *
 * @return bool|string
 */
function abga_container_id() {
	return abga_get_option( 'optimize_container_id', '' );
}

/**
 * Checks if its enabled.
 *
 * @return bool
 */
function abga_optimize_enabled() {
	if ( abga_analytics_detected() == 'analytify' && 'on' !== $GLOBALS['WP_ANALYTIFY']->settings->get_option( 'install_ga_code', 'wp-analytify-profile', 'off' ) ) {
		return false;
	}

	return (bool) abga_get_option( 'optimize_container_id' );
}

/**
 * Is Page Hiding Enabled?
 *
 * @return bool
 */
function abga_page_hiding_enabled() {
	return (bool) abga_get_option( 'optimize_page_hiding' );
}

/**
 * Add the needed arguments to frontend GA scripts.
 */
function abga_render_frontend_tracking_code() {
	if ( ! abga_optimize_enabled() ) {
		return;
	}

	$id = abga_container_id();

	echo "ga('require', '$id');";
}

/**
 * Add the needed arguments to frontend GA scripts.
 *
 * @param array $options
 *
 * @return array
 */
function abga_monsterinsights_frontend_tracking_options( $options = array() ) {
	if ( ! abga_optimize_enabled() ) {
		return $options;
	}

	$id = abga_container_id();

	$options['optimize_enabled'] = "'require', '$id'";

	return $options;
}

/**
 * Add the page hiding script if enabled.
 */
function abga_tracking_before() {
	if ( ! abga_optimize_enabled() || ! abga_page_hiding_enabled() ) {
		return;
	}

	$id = abga_get_option( 'optimize_container_id', '' ); ?>

	<style>.async-hide { opacity: 0 !important} </style>
	<script>(function(a,s,y,n,c,h,i,d,e){s.className+=' '+y;h.start=1*new Date;
	h.end=i=function(){s.className=s.className.replace(RegExp(' ?'+y),'')};
	(a[n]=a[n]||[]).hide=h;setTimeout(function(){i();h.end=null},c);h.timeout=c;
	})(window,document.documentElement,'async-hide','dataLayer',4000,
	{'<?php esc_attr_e( $id ); ?>':true});</script><?php
}

/**
 * Add optimize settings tab
 *
 * @param array $tabs
 *
 * @return array
 */
function abga_settings_tabs( $tabs = array() ) {

	switch ( abga_analytics_detected() ) {
		case 'monsterinsights':
			$first_4 = array_slice( $tabs, 0, 4, true );

			$remaining = array_slice( $tabs, 4, count( $tabs ) - 3, true );

			$new = array(
				"optimize" => array(
					'title' => esc_html__( 'Optimize', 'ab-testing-google-analytics' ),
					'level' => 'lite',
				),
			);

			$tabs = $first_4 + $new + $remaining;
			break;

		case 'analytify':
			$tabs[] = array(
				'id' => 'wp-analytify-optimize',
				'title' => esc_html__( 'Optimize', 'ab-testing-google-analytics' ),
				'priority' => '15',
			);
			break;
	}

	return $tabs;
}

/**
 * Register optimize setting fields.
 *
 * @param array $settings
 *
 * @return array
 */
function abga_registered_settings( $settings = array() ) {

	switch ( abga_analytics_detected() ) {
		case 'monsterinsights':
			$settings['optimize'] = apply_filters( 'monsterinsights_settings_optimize', array(
				'optimize_container_id' => array(
					'id'   => 'optimize_container_id',
					'name' => __( 'Google Optimize Container ID:', 'ab-testing-google-analytics' ),
					'desc' => sprintf( esc_html__( 'This allows you to integrate %sGoogle Optimize%s with your site.', 'ab-testing-google-analytics' ), '<a href="https://optimize.google.com" target="_blank" rel="noopener noreferrer">', '</a>' ),
					'type' => 'text',
				),
				'optimize_page_hiding'  => array(
					'id'   => 'optimize_page_hiding',
					'name' => __( 'Enable Page Hiding', 'ab-testing-google-analytics' ),
					'desc' => __( 'Turns on page hiding. This will prevent users from seeing content before its replaced.', 'ab-testing-google-analytics' ),
					'type' => 'checkbox',
				),
			) );
			break;

		case 'analytify':
			$settings['wp-analytify-optimize'] = array(
				array(
					'name'  => 'optimize_container_id',
					'label' => __( 'Google Optimize Container ID:', 'ab-testing-google-analytics' ),
					'desc'  => sprintf( esc_html__( 'This allows you to integrate %sGoogle Optimize%s with your site.', 'ab-testing-google-analytics' ), '<a href="https://optimize.google.com" target="_blank" rel="noopener noreferrer">', '</a>' ),
					'type'  => 'text',
				),
				array(
					'name'  => 'optimize_page_hiding',
					'label' => __( 'Enable Page Hiding', 'ab-testing-google-analytics' ),
					'desc'  => __( 'Turns on page hiding. This will prevent users from seeing content before its replaced.', 'ab-testing-google-analytics' ),
					'type'  => 'checkbox',
				),
			);
			break;
	}


	return $settings;
}

/**
 * Add icon for admin settings tab.
 */
function abga_admin_head_styles() { ?>
	<style>.monstericon-optimize::before {font-family: "dashicons";content: "\f169";}</style> <?php
}
