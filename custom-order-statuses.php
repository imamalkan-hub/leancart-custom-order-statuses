<?php
/**
 * Plugin Name: LeanCart Custom Order Statuses
 * Plugin URI:  https://qsedationdentistry.com/
 * Description: Create custom order statuses such as On Hold, In Production, or Shipped. New statuses appear in WooCommerce’s standard status list and in bulk actions.
 * Version:     1.0.0
 * Author:      LeanCart
 * Author URI:  https://leancart.dev
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: custom-order-statuses
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LEANCART_CUSTOM_ORDER_STATUSES_DEMO_SITE' ) ) {
	define( 'LEANCART_CUSTOM_ORDER_STATUSES_DEMO_SITE', 'https://qsedationdentistry.com' );
}

require_once __DIR__ . '/includes/class-leancart-framework.php';

final class LeanCart_Custom_Order_Statuses {

	const SLUG = 'custom-order-statuses';

	public static function init() {
		LeanCart_Framework::boot( array(
			'slug'    => self::SLUG,
			'name'    => 'LeanCart Custom Order Statuses',
			'version' => '1.0.0',
			'file'    => __FILE__,
			'site'    => LEANCART_CUSTOM_ORDER_STATUSES_DEMO_SITE,
		) );

		// WooCommerce may load after these plugins; defer the check to runtime.
		add_action( 'admin_notices', array( __CLASS__, 'wc_notice' ) );

		load_plugin_textdomain( 'custom-order-statuses', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		add_action( 'leancart_' . self::SLUG . '_settings_fields', array( __CLASS__, 'fields' ) );

		// WooCommerce may load after this plugin; hooks are registered once WC is ready.
		if ( LeanCart_Framework::is_wc() ) {
			self::hooks();
		} else {
			add_action( 'woocommerce_loaded', array( __CLASS__, 'hooks' ) );
		}
	}

	public static function wc_notice() {
		if ( LeanCart_Framework::is_wc() ) { return; }
		echo '<div class="notice notice-warning"><p><strong>' . esc_html( 'LeanCart Custom Order Statuses' ) . '</strong>: ';
		esc_html_e( 'This plugin requires WooCommerce to be active.', 'custom-order-statuses' );
		echo '</p></div>';
	}

	public static function fields() {
		LeanCart_Framework::field_text( 'statuses', __('Custom statuses', 'custom-order-statuses'), __('One per line: slug|Label. Example: hazir|Preparing', 'custom-order-statuses'), 'textarea' );
	}


	public static function hooks() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'wc_order_statuses', array( __CLASS__, 'add_statuses' ) );
		add_filter( 'bulk_actions-edit-shop_order', array( __CLASS__, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( __CLASS__, 'handle_bulk' ), 10, 3 );
		// HPOS equivalent.
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( __CLASS__, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( __CLASS__, 'handle_bulk' ), 10, 3 );
	}

	private static function parsed() {
		$raw = LeanCart_Framework::setting( 'statuses' );
		$out = array();
		foreach ( explode( "\n", $raw ) as $line ) {
			$line = trim( $line );
			if ( ! $line || false === strpos( $line, '|' ) ) { continue; }
			list( $slug, $label ) = array_map( 'trim', explode( '|', $line, 2 ) );
			$slug = substr( sanitize_key( $slug ), 0, 17 );
			if ( $slug && $label ) { $out[ 'wc-' . $slug ] = sanitize_text_field( $label ); }
		}
		return $out;
	}

	public static function register() {
		foreach ( self::parsed() as $name => $label ) {
			$label = esc_html( $label );
			register_post_status( $name, array(
				'label'                     => $label,
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( $label . ' <span class="count">(%s)</span>', $label . ' <span class="count">(%s)</span>', 'custom-order-statuses' ),
			) );
		}
	}

	public static function add_statuses( $statuses ) {
		$new = array();
		foreach ( $statuses as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'wc-completed' === $key ) {
				foreach ( self::parsed() as $slug => $name ) { $new[ $slug ] = $name; }
			}
		}
		return $new;
	}

	public static function bulk_actions( $actions ) {
		foreach ( self::parsed() as $slug => $label ) {
			$key = str_replace( 'wc-', '', $slug );
			$actions[ 'lc_status_' . $key ] = sprintf( __( 'Status: %s', 'custom-order-statuses' ), $label );
		}
		return $actions;
	}

	public static function handle_bulk( $redirect, $action, $ids ) {
		if ( ! current_user_can( 'edit_shop_orders' ) ) { return $redirect; }
		if ( 0 !== strpos( $action, 'lc_status_' ) ) { return $redirect; }
		$slug = 'wc-' . substr( $action, 10 );
		if ( ! array_key_exists( $slug, self::parsed() ) ) { return $redirect; }
		foreach ( $ids as $id ) {
			$order = wc_get_order( $id );
			if ( $order ) { $order->update_status( $slug ); }
		}
		return add_query_arg( 'lc_status_updated', count( $ids ), $redirect );
	}


}

LeanCart_Custom_Order_Statuses::init();

