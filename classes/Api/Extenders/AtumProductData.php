<?php
/**
 * Extender for the WC's products endpoint
 * Adds the ATUM Product Data to this endpoint
 *
 * @since       1.6.2
 * @author      Be Rebel - https://berebel.io
 * @copyright   ©2019 Stock Management Labs™
 *
 * @package     Atum\Api
 * @subpackage  Extenders
 */

namespace Atum\Api\Extenders;

defined( 'ABSPATH' ) || die;

use Atum\Components\AtumCapabilities;
use Atum\Inc\Helpers;
use Atum\Modules\ModuleManager;


class AtumProductData {

	/**
	 * The singleton instance holder
	 *
	 * @var AtumProductData
	 */
	private static $instance;

	/**
	 * Custom ATUM API's product field names, indicating support for getting/updating.
	 *
	 * @var array
	 */
	private $product_fields = array(
		'purchase_price'        => [ 'get', 'update' ],
		'supplier_id'           => [ 'get', 'update' ],
		'supplier_sku'          => [ 'get', 'update' ],
		'atum_controlled'       => [ 'get', 'update' ],
		'out_stock_date'        => [ 'get', 'update' ],
		'out_stock_threshold'   => [ 'get', 'update' ],
		'inheritable'           => [ 'get', 'update' ],
		'bom_sellable'          => [ 'get', 'update' ],
		'minimum_threshold'     => [ 'get', 'update' ],
		'available_to_purchase' => [ 'get', 'update' ],
		'selling_priority'      => [ 'get', 'update' ],
		'inbound_stock'         => [ 'get', 'update' ],
		'stock_on_hold'         => [ 'get', 'update' ],
		'sold_today'            => [ 'get', 'update' ],
		'sales_last_days'       => [ 'get', 'update' ],
		'reserved_stock'        => [ 'get', 'update' ],
		'customer_returns'      => [ 'get', 'update' ],
		'warehouse_damage'      => [ 'get', 'update' ],
		'lost_in_post'          => [ 'get', 'update' ],
		'other_logs'            => [ 'get', 'update' ],
		'out_stock_days'        => [ 'get', 'update' ],
		'lost_sales'            => [ 'get', 'update' ],
		'has_location'          => [ 'get', 'update' ],
		'update_date'           => [ 'get', 'update' ],
		'calculated_stock'      => [ 'get', 'update' ],
	);

	/**
	 * AtumProductData constructor
	 *
	 * @since 1.6.2
	 */
	private function __construct() {

		/**
		 * Pre-filter the data props according to the enabled modules and current user's capabilities.
		 */
		if ( ! ModuleManager::is_module_active( 'purchase_orders' ) ) {
			unset(
				$this->product_fields['purchase_price'],
				$this->product_fields['supplier_id'],
				$this->product_fields['supplier_sku'],
				$this->product_fields['inbound_stock'],
				$this->product_fields['has_location']
			);
		}
		elseif ( ! AtumCapabilities::current_user_can( 'view_purchase_price' ) ) {
			unset( $this->product_fields['purchase_price'] );
		}
		elseif ( ! AtumCapabilities::current_user_can( 'read_inbound_stock' ) ) {
			unset( $this->product_fields['inbound_stock'] );
		}
		elseif ( ! AtumCapabilities::current_user_can( 'read_private_suppliers' ) ) {
			unset( $this->product_fields['supplier_id'], $this->product_fields['supplier_sku'] );
		}
		elseif ( ! AtumCapabilities::current_user_can( 'manage_location_terms' ) ) {
			unset( $this->product_fields['has_location'] );
		}

		if ( ! ModuleManager::is_module_active( 'inventory_logs' ) ) {
			unset(
				$this->product_fields['reserved_stock'],
				$this->product_fields['customer_returns'],
				$this->product_fields['warehouse_damage'],
				$this->product_fields['lost_in_post'],
				$this->product_fields['other_logs']
			);
		}

		if ( ! defined( 'ATUM_LEVELS_VERSION' ) ) {
			unset(
				$this->product_fields['bom_sellable'],
				$this->product_fields['minimum_threshold'],
				$this->product_fields['available_to_purchase'],
				$this->product_fields['selling_priority'],
				$this->product_fields['calculated_stock']
			);
		}

		/**
		 * Register the ATUM Product data custom fields to the WC API.
		 */
		add_action( 'rest_api_init', array( $this, 'register_product_fields' ), 0 );

	}

	/**
	 * Register the WC API custom fields for product requests.
	 *
	 * @since 1.6.2
	 */
	public function register_product_fields() {

		foreach ( $this->product_fields as $field_name => $field_supports ) {

			$args = array(
				'schema' => $this->get_product_field_schema( $field_name ),
			);

			if ( in_array( 'get', $field_supports ) ) {
				$args['get_callback'] = array( $this, 'get_product_field_value' );
			}

			if ( in_array( 'update', $field_supports ) ) {
				$args['update_callback'] = array( $this, 'update_product_field_value' );
			}

			// Add the field to the product and product_variations endpoints.
			register_rest_field( 'product', $field_name, $args );
			register_rest_field( 'product_variation', $field_name, $args );

		}

	}

	/**
	 * Gets extended (unprefixed) schema properties for products.
	 *
	 * @since 1.6.2
	 *
	 * @return array
	 */
	private static function get_extended_product_schema() {

		return array(
			'purchase_price'        => array(
				'required'    => FALSE,
				'description' => __( "Product's purchase price.", ATUM_TEXT_DOMAIN ),
				'type'        => 'number',
			),
			'supplier_id'           => array(
				'required'    => FALSE,
				'description' => __( 'The ID of the ATUM Supplier that is linked to this product.', ATUM_TEXT_DOMAIN ),
				'type'        => 'integer',
			),
			'supplier_sku'          => array(
				'required'    => FALSE,
				'description' => __( "The Supplier's SKU for this product.", ATUM_TEXT_DOMAIN ),
				'type'        => 'string',
			),
			'atum_controlled'       => array(
				'required'    => FALSE,
				'description' => __( 'Whether this product is being controlled by ATUM.', ATUM_TEXT_DOMAIN ),
				'type'        => 'boolean',
				'default'     => FALSE,
			),
			'out_stock_date'        => array(
				'required'    => FALSE,
				'description' => __( 'The date when this product run out of stock.', ATUM_TEXT_DOMAIN ),
				'type'        => 'date-time',
			),
			'out_stock_threshold'   => array(
				'required'    => FALSE,
				'description' => __( 'Out of stock threshold at product level.', ATUM_TEXT_DOMAIN ),
				'type'        => 'number',
			),
			'inheritable'           => array(
				'required'    => FALSE,
				'description' => __( 'Whether this product may have children.', ATUM_TEXT_DOMAIN ),
				'type'        => 'boolean',
				'default'     => FALSE,
			),
			'bom_sellable'          => array(
				'required'    => FALSE,
				'description' => __( 'Whether this product may have children.', ATUM_TEXT_DOMAIN ),
				'type'        => 'boolean',
			),
			'minimum_threshold'     => array(
				'required'    => FALSE,
				'description' => __( "Product's minimum threshold (Product Levels).", ATUM_TEXT_DOMAIN ),
				'type'        => 'number',
			),
			'available_to_purchase' => array(
				'required'    => FALSE,
				'description' => __( "Product's available to purchase (Product Levels).", ATUM_TEXT_DOMAIN ),
				'type'        => 'number',
			),
			'selling_priority'      => array(
				'required'    => FALSE,
				'description' => __( "Product's selling priority (Product Levels).", ATUM_TEXT_DOMAIN ),
				'type'        => 'integer',
			),
			'inbound_stock'         => array(
				'required'    => FALSE,
				'description' => __( "Product's inbound stock.", ATUM_TEXT_DOMAIN ),
				'type'        => 'number',
			),
			'stock_on_hold'         => array(
				'required'    => FALSE,
				'description' => __( "Product's stock on hold.", ATUM_TEXT_DOMAIN ),
				'type'        => 'number',
			),
			'sold_today'            => array(
				'required'    => FALSE,
				'description' => __( 'Units sold today.', ATUM_TEXT_DOMAIN ),
				'type'        => 'number',
			),
			'sales_last_days'       => array(
				'required'    => FALSE,
				'description' => __( 'Sales the last 14 days.', ATUM_TEXT_DOMAIN ),
				'type'        => 'number',
			),
			'reserved_stock'        => array(
				'required'    => FALSE,
				'description' => __( 'Stock reserved by Inventory Logs.', ATUM_TEXT_DOMAIN ),
				'type'        => 'number',
			),
			'customer_returns'      => array(
				'required'    => FALSE,
				'description' => __( "Stock set as 'customer returns' within Inventory Logs.", ATUM_TEXT_DOMAIN ),
				'type'        => 'number',
			),
			'warehouse_damage'      => array(
				'required'    => FALSE,
				'description' => __( "Stock set as 'warehouse damage' within Inventory Logs.", ATUM_TEXT_DOMAIN ),
				'type'        => 'number',
			),
			'lost_in_post'          => array(
				'required'    => FALSE,
				'description' => __( "Stock set as 'lost in post' within Inventory Logs.", ATUM_TEXT_DOMAIN ),
				'type'        => 'number',
			),
			'other_logs'            => array(
				'required'    => FALSE,
				'description' => __( "Stock set as 'other' within Inventory Logs.", ATUM_TEXT_DOMAIN ),
				'type'        => 'number',
			),
			'out_stock_days'        => array(
				'required'    => FALSE,
				'description' => __( 'The number of days that the product is Out of stock.', ATUM_TEXT_DOMAIN ),
				'type'        => 'integer',
			),
			'lost_sales'            => array(
				'required'    => FALSE,
				'description' => __( 'Product lost sales.', ATUM_TEXT_DOMAIN ),
				'type'        => 'number',
			),
			'has_location'          => array(
				'required'    => FALSE,
				'description' => __( 'Whether this product has any ATUM location set.', ATUM_TEXT_DOMAIN ),
				'type'        => 'boolean',
			),
			'update_date'           => array(
				'required'    => FALSE,
				'description' => __( 'Last date when the ATUM product data was calculated and saved for this product.', ATUM_TEXT_DOMAIN ),
				'type'        => 'date-time',
			),
			'calculated_stock'      => array(
				'required'    => FALSE,
				'description' => __( 'Calculated stock quantity (Product Levels).', ATUM_TEXT_DOMAIN ),
				'type'        => 'number',
			),
		);

	}

	/**
	 * Gets schema properties for ATUM product data fields
	 *
	 * @since 1.6.2
	 *
	 * @param string $field_name
	 *
	 * @return array
	 */
	public function get_product_field_schema( $field_name ) {

		$extended_schema = $this->get_extended_product_schema();
		$field_schema    = isset( $extended_schema[ $field_name ] ) ? $extended_schema[ $field_name ] : NULL;

		return $field_schema;

	}

	/**
	 * Gets values for ATUM product data fields
	 *
	 * @since 1.6.2
	 *
	 * @param array            $response
	 * @param string           $field_name
	 * @param \WP_REST_Request $request
	 *
	 * @return array
	 */
	public function get_product_field_value( $response, $field_name, $request ) {

		$data = NULL;

		if ( ! empty( $response['id'] ) ) {

			$product = Helpers::get_atum_product( $response['id'] );
			$getter  = "get_$field_name";

			if ( is_a( $product, '\WC_Product' ) && is_callable( array( $product, $getter ) ) ) {
				$data = call_user_func( array( $product, $getter ) );
			}

		}

		return $data;

	}

	/**
	 * Updates values for the ATUM product data fields
	 *
	 * @since 1.6.2
	 *
	 * @param mixed  $field_value
	 * @param mixed  $response
	 * @param string $field_name
	 *
	 * @return bool
	 *
	 * @throws \WC_REST_Exception
	 */
	public static function update_product_field_value( $field_value, $response, $field_name ) {

		if (
			( 'purchase_price' === $field_name && ! AtumCapabilities::current_user_can( 'edit_purchase_price' ) ) ||
			( 'out_stock_threshold' === $field_name && ! AtumCapabilities::current_user_can( 'edit_out_stock_threshold' ) )
		) {
			/* translators: the field name */
			throw new \WC_REST_Exception( 'atum_rest_invalid_product', sprintf( __( 'You are not allowed to edit the %s field.', ATUM_TEXT_DOMAIN ), $field_name ), 400 );
		}

		$product_id = NULL;

		if ( is_a( $response, '\WC_Product' ) ) {
			$product_id = $response->get_id();
		}
		elseif ( is_a( $response, '\WP_Post' ) ) {
			$product_id = absint( $response->ID );
		}

		$product = Helpers::get_atum_product( $product_id );

		if ( ! is_a( $product, '\WC_Product' ) ) {
			/* translators: the product ID */
			throw new \WC_REST_Exception( 'atum_rest_invalid_product', sprintf( __( 'Invalid product with ID #%s.', ATUM_TEXT_DOMAIN ), $product_id ), 400 );
		}

		$setter = "set_$field_name";

		if ( is_callable( array( $product, $setter ) ) ) {
			call_user_func( array( $product, $setter ), $field_value );
			$product->save_atum_data();
		}

		return TRUE;

	}


	/****************************
	 * Instance methods
	 ****************************/

	/**
	 * Cannot be cloned
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_attr__( 'Cheatin&#8217; huh?', ATUM_TEXT_DOMAIN ), '1.0.0' );
	}

	/**
	 * Cannot be serialized
	 */
	public function __sleep() {
		_doing_it_wrong( __FUNCTION__, esc_attr__( 'Cheatin&#8217; huh?', ATUM_TEXT_DOMAIN ), '1.0.0' );
	}

	/**
	 * Get Singleton instance
	 *
	 * @return AtumProductData instance
	 */
	public static function get_instance() {
		if ( ! ( self::$instance && is_a( self::$instance, __CLASS__ ) ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}
