<?php
/**
 * @package         Atum\Components
 * @subpackage      AtumListTables
 * @author          Be Rebel - https://berebel.io
 * @copyright       ©2018 Stock Management Labs™
 *
 * @since           0.0.1
 *
 * Extends WP_List_Table to display the stock management table
 */

namespace Atum\Components\AtumListTables;

defined( 'ABSPATH' ) or die;

use Atum\Components\AtumCapabilities;
use Atum\Components\AtumOrders\AtumOrderPostType;
use Atum\Inc\Globals;
use Atum\Inc\Helpers;
use Atum\PurchaseOrders\PurchaseOrders;
use Atum\Settings\Settings;
use Atum\Suppliers\Suppliers;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

abstract class AtumListTable extends \WP_List_Table {

	/**
	 * The post type used to build the table (WooCommerce product)
	 * @var string
	 */
	protected $post_type = 'product';

	/**
	 * Current product used
	 * @var \WC_Product
	 */
	protected $product;
	
	/**
	 * The table columns
	 * @var array
	 */
	protected $table_columns;

	/**
	 * The columns that are hidden by default
	 * @var array
	 */
	protected static $default_hidden_columns = array();

	/**
	 * What columns are numeric and searchable? and strings? append to this two keys.
	 * @var array string keys
	 */
	protected $default_searchable_columns = array();

	/**
	 * The previously selected items
	 * @var array
	 */
	protected $selected = array();

	/**
	 * Array of product IDs that are excluded from the list
	 * @var array
	 */
	protected $excluded = array();

	/**
	 * Group title columns
	 * @var array
	 */
	protected $group_columns = array();

	/**
	 * Group members
	 * @var array
	 */
	protected $group_members = array();

	/**
	 * The array of container products
	 * @var array
	 */
	protected $container_products = array(
		'variable'                  => [],
		'all_variable'              => [],
		'grouped'                   => [],
		'all_grouped'               => [],
		'variable_subscription'     => [],
		'all_variable_subscription' => []
	);

	/**
	 * The array of IDs of children products
	 *
	 * @var array
	 */
	protected $children_products = array();

	/**
	 * Elements per page (in order to obviate option default)
	 * @var int
	 */
	protected $per_page;

	/**
	 * Array with the id's of the products in current page
	 * @var array
	 */
	protected $current_products;

	/**
	 * Used to include product variations in the Supplier filterings
	 * @var array
	 */
	protected $supplier_variation_products = array();

	/**
	 * Taxonomies to filter by
	 * @var array
	 */
	protected $taxonomies = array();

	/**
	 * Extra meta args for the list query
	 * @var array
	 */
	protected $extra_meta = array();

	/**
	 * IDs for views
	 * @var array
	 */
	protected $id_views = array(
		'in_stock'   => [],
		'out_stock'  => [],
		'back_order' => [],
		'low_stock'  => [],
		'unmanaged'  => []
	);

	/**
	 * Counters for views
	 * @var array
	 */
	protected $count_views = array(
		'count_in_stock'   => 0,
		'count_out_stock'  => 0,
		'count_back_order' => 0,
		'count_low_stock'  => 0,
		'count_unmanaged'  => 0,
		'count_all'        => 0
	);

	/**
	 * Sale days from settings
	 * @var int
	 */
	protected $last_days;

	/**
	 * Whether the currently displayed product is an expandable child product
	 * @var bool
	 */
	protected $is_child = FALSE;

	/**
	 * Whether or not the current product should do the calculations for the columns
	 * @var bool
	 */
	protected $allow_calcs = TRUE;

	/**
	 * Default currency symbol
	 * @var string
	 */
	protected $default_currency;

	/**
	 * The user meta key used for first edit popup
	 * @var string
	 */
	protected $first_edit_key;

	/**
	 * Show the checkboxes in table rows
	 * @var bool
	 */
	protected $show_cb = FALSE;

	/**
	 * Whether to show products controlled by ATUM or not
	 * @var bool
	 */
	protected $show_controlled = TRUE;

	/**
	 * Columns that allow totalizers with their totals
	 * @var array
	 */
	protected $totalizers = array();

	/**
	 * Whether to show the totals row
	 * @var bool
	 */
	protected $show_totals = TRUE;

	/**
	 * Whether the current list query has a filter applied
	 * @var bool
	 */
	protected $is_filtering = FALSE;

	/**
	 * Filters being applied to the current query
	 * @var array
	 */
	protected $query_filters = array();

	/**
	 * Counter for the table rows
	 * @var int
	 */
	protected $row_count = 0;

	/**
	 * Whether to show or not the unmanaged counters
	 *
	 * @var bool
	 */
	protected $show_unmanaged_counters;

	/**
	 * Value for empty columns
	 */
	const EMPTY_COL = '&mdash;';


	/**
	 * AtumListTable Constructor
	 *
	 * The child class should call this constructor from its own constructor to override the default $args
	 *
	 * @since 0.0.1
	 *
	 * @param array|string $args          {
	 *      Array or string of arguments.
	 *
	 *      @type array  $table_columns     The table columns for the list table
	 *      @type array  $group_members     The column grouping members
	 *      @type bool   $show_cb           Optional. Whether to show the row selector checkbox as first table column
	 *      @type bool   $show_controlled   Optional. Whether to show items controlled by ATUM or not
	 *      @type int    $per_page          Optional. The number of posts to show per page (-1 for no pagination)
	 *      @type array  $selected          Optional. The posts selected on the list table
	 *      @type array  $excluded          Optional. The posts excluded from the list table
	 * }
	 */
	public function __construct( $args = array() ) {

		$this->last_days = absint( Helpers::get_option( 'sale_days', Settings::DEFAULT_SALE_DAYS ) );

		$this->is_filtering  = ! empty( $_REQUEST['s'] ) || ! empty( $_REQUEST['search_column'] ) || ! empty( $_REQUEST['product_cat'] ) || ! empty( $_REQUEST['product_type'] ) || ! empty( $_REQUEST['supplier'] );
		$this->query_filters = $this->get_filters_query_string();

		$args = wp_parse_args( $args, array(
			'show_cb'         => FALSE,
			'show_controlled' => TRUE,
			'per_page'        => Settings::DEFAULT_POSTS_PER_PAGE,
		) );

		$this->show_cb         = $args['show_cb'];
		$this->show_controlled = $args['show_controlled'];

		if ($this->show_totals &&  Helpers::get_option('show_totals', 'yes') == 'no' ) {
			$this->show_totals = FALSE;
		}

		if ( ! empty( $args['selected'] ) ) {
			$this->selected = is_array( $args['selected'] ) ? $args['selected'] : explode( ',', $args['selected'] );
		}

		if ( ! empty( $args['excluded'] ) ) {
			$this->excluded = is_array( $args['excluded'] ) ? $args['excluded'] : explode( ',', $args['excluded'] );
		}

		if ( ! empty($args['group_members']) ) {
			$this->group_members = $args['group_members'];

			if ( isset ($this->group_members['product-details']) && $this->show_cb == TRUE ) {
				array_unshift($this->group_members['product-details']['members'], 'cb');
		    }
		}

		//remove _out_stock_threshold columns if not set, or add filters to get availability etc.
		$is_out_stock_threshold_managed =  Helpers::get_option( 'out_stock_threshold', "no" ) ;
		if($is_out_stock_threshold_managed === "no"){
			unset($args['table_columns'][ Globals::OUT_STOCK_THRESHOLD_KEY ]);
			unset($args['group_members']['stock-counters'][ Globals::OUT_STOCK_THRESHOLD_KEY ]);
        }

		// Add the checkbox column to the table if enabled
		$this->table_columns = $this->show_cb == TRUE ? array_merge( array( 'cb' => 'cb' ), $args['table_columns'] ) : $args['table_columns'];
		$this->per_page      = isset( $args['per_page'] ) ? $args['per_page'] : Helpers::get_option( 'posts_per_page', Settings::DEFAULT_POSTS_PER_PAGE );

		$post_type_obj = get_post_type_object( $this->post_type );

		if ( ! $post_type_obj ) {
			return FALSE;
		}

		// Set \WP_List_Table defaults
		$args = array_merge( array(
			'singular' => strtolower( $post_type_obj->labels->singular_name ),
			'plural'   => strtolower( $post_type_obj->labels->name ),
			'ajax'     => TRUE
		), $args );

		parent::__construct( $args );

		add_filter( 'posts_search', array( $this, 'product_search' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Hook the default_hidden_columns filter used within get_hidden_columns() function
		if ( ! empty( static::$default_hidden_columns ) ) {
			add_filter( 'default_hidden_columns', array( $this, 'hidden_columns' ), 10, 2 );
		}

		$this->default_currency = get_woocommerce_currency();

	}

	/**
	 * Extra controls to be displayed in table nav sections
	 *
	 * @since  1.3.0
	 *
	 * @param string $which 'top' or 'bottom' table nav
	 */
	protected function extra_tablenav( $which ) {

		if ( $which == 'top' ): ?>

			<div class="alignleft actions">
				<div class="actions-wrapper">

					<?php $this->table_nav_filters() ?>

					<?php if ( Helpers::get_option( 'enable_ajax_filter', 'yes' ) == 'no' ): ?>
						<input type="submit" name="filter_action" class="button search-category" value="<?php _e('Filter', ATUM_TEXT_DOMAIN) ?>">
					<?php endif; ?>

				</div>
			</div>

		<?php endif;

	}

	/**
	 * Add the filters to the table nav
	 *
	 * @since 1.3.0
	 */
	protected function table_nav_filters() {

		// Category filtering
		wc_product_dropdown_categories( array(
			'show_count' => 0,
			'selected'   => ! empty( $_REQUEST['product_cat'] ) ? esc_attr( $_REQUEST['product_cat'] ) : '',
		) );

		// Product type filtering
		echo Helpers::product_types_dropdown( isset( $_REQUEST['product_type'] ) ? esc_attr( $_REQUEST['product_type'] ) : '' );

		// Supplier filtering
		echo Helpers::suppliers_dropdown( isset( $_REQUEST['supplier'] ) ? esc_attr( $_REQUEST['supplier'] ) : '', Helpers::get_option( 'enhanced_suppliers_filter', 'no' ) == 'yes' );

	}

	/**
	 * Loads the current product
	 *
	 * @since 0.0.1
	 *
	 * @param \WP_Post $item The WooCommerce product post
	 */
	public function single_row( $item ) {

		$this->product = wc_get_product( $item );
		$type          = $this->product->get_type();

		do_action('atum/list_table/before_single_row', $this->product, $this->post_type);

		// If a product is set as hidden from the catalog and is part of a Grouped product, don't display it on the list
		/*if ( $type == 'simple' && $this->product->visibility == 'hidden' && ! empty($this->product->post->post_parent) ) {
			return;
		}*/

		$this->allow_calcs = TRUE;
		$row_classes       = array( (++$this->row_count % 2 ? 'even' : 'odd') );

		// Inheritable products do not allow calcs
		if ( Helpers::is_inheritable_type($type) ) {

			$this->allow_calcs = FALSE;
			$class_type = $type == 'grouped' ? 'group' : 'variable';

			$row_classes[] = $class_type;

			if ( Helpers::get_option( 'expandable_rows', 'no' ) == 'yes' ) {
				$row_classes[] = 'expanded';
			}

		}

		$row_class = ' class="' . implode( ' ', $row_classes ) . '"';

		// Output the row
		echo '<tr data-id="' . $this->get_current_product_id() . '"' . $row_class . '>';
		$this->single_row_columns( $item );
		echo '</tr>';

		// Add the children products of each inheritable product type
		if ( !$this->allow_calcs ) {

			$product_type   = $type == 'grouped' ? 'product' : 'product_variation';
			$child_products = $this->get_children($type, [ $this->product->get_id() ], $product_type );

			if ( ! empty($child_products) ) {

				// If the post__in filter is applied, bypass the children that are not in the query var
				$post_in = get_query_var('post__in');

				$this->allow_calcs = TRUE;

				foreach ($child_products as $child_id) {

					if ( ! empty($post_in) && ! in_array($child_id, $post_in) ) {
						continue;
					}

					// Exclude some children if there is a "Views Filter" active
					if ( ! empty($_REQUEST['view']) ) {

						$view = esc_attr( $_REQUEST['view'] );
						if ( ! in_array($child_id, $this->id_views[ $view ]) ) {
							continue;
						}

					}

					$this->is_child = TRUE;
					$this->product  = wc_get_product( $child_id );
					$this->single_expandable_row($this->product, ($type == 'grouped' ? $type : 'variation'));
				}
			}

		}

		// Reset the child value
		$this->is_child = FALSE;

	}

	/**
	 * Generates content for a expandable row on the table
	 *
	 * @since 1.1.0
	 *
	 * @param \WC_Product $item The WooCommerce product
	 * @param string   $type The type of product
	 */
	public function single_expandable_row( $item, $type ) {

		do_action('atum/list_table/before_single_expandable_row', $item, $this->post_type);

		$row_style = Helpers::get_option('expandable_rows', 'no') != 'yes' ? ' style="display: none"' : '';

		echo '<tr class="expandable ' . $type . '"' . $row_style . ' data-id="' . $this->get_current_product_id() . '">';
		$this->single_row_columns( $item );
		echo '</tr>';

	}

	/**
	 * The default column (when no specific column method found)
	 *
	 * @since 0.0.1
	 *
	 * @param \WP_Post $item          The WooCommerce product post
	 * @param string   $column_name   The current column name
	 *
	 * @return string|bool
	 */
	protected function column_default( $item, $column_name ) {

		$id = $this->get_current_product_id();
		$column_item = '';

		// Check if it's a hidden meta key (will start with underscore)
		if ( substr( $column_name, 0, 1 ) == '_' ) {
			$column_item = get_post_meta( $id, $column_name, TRUE );
		}

		if ($column_item === '' || $column_item === FALSE) {
			$column_item = self::EMPTY_COL;
		}

		return apply_filters( "atum/list_table/column_default_$column_name", $column_item, $item, $this->product );

	}

	/**
	 * Column selector checkbox
	 *
	 * @since  0.0.1
	 *
	 * @param object $item
	 *
	 * @return string
	 */
	protected function column_cb( $item ) {

		$id = $this->get_current_product_id();

		return sprintf(
			'<input type="checkbox"%s name="%s[]" value="%s">',
			checked( in_array( $id, $this->selected ), TRUE, FALSE ),
			$this->_args['singular'],
			$id
		);
	}

	/**
	 * Column for thumbnail
	 *
	 * @since 0.0.1
	 *
	 * @param \WP_Post $item The WooCommerce product post
	 *
	 * @return string
	 */
	protected function column_thumb( $item ) {

		$product_id = $this->get_current_product_id();
		$thumb = '<a href="' . get_edit_post_link($product_id) .'" target="_blank">' . $this->product->get_image( [40, 40] ) . '</a>';
		return apply_filters( 'atum/list_table/column_thumb', $thumb, $item, $this->product );
	}

	/**
	 * Post title column
	 *
	 * @since  0.0.1
	 *
	 * @param \WP_Post $item The WooCommerce product post
	 *
	 * @return string
	 */
	protected function column_title( $item ) {

		$title      = '';
		$product_id = $this->get_current_product_id();

		if ( Helpers::is_child_type( $this->product->get_type() ) ) {

			$attributes = wc_get_product_variation_attributes($product_id);
			if ( ! empty($attributes) ) {
				$title = ucfirst( implode(' ', $attributes) );
			}

			// Get the variable product ID to get the right link
			$product_id = $this->product->get_parent_id();

		}
		else {
			$title = $this->product->get_title();
		}

		$title_length = absint( apply_filters( 'atum/list_table/column_title_length', 20 ) );

		if ( mb_strlen( $title ) > $title_length ) {

			$title = '<span class="tips" data-toggle="tooltip" title="' . $title . '">' . trim( mb_substr( $title, 0, $title_length ) ) .
			         '...</span><span class="atum-title-small">' . $title . '</span>';
		}

		$title = '<a href="' . get_edit_post_link($product_id) . '" target="_blank">' . $title . '</a>';

		return apply_filters( 'atum/list_table/column_title', $title, $item, $this->product );
	}

	/**
	 * Supplier column
	 *
	 * @since  1.3.1
	 *
	 * @param \WP_Post $item The WooCommerce product post
	 *
	 * @return string
	 */
	protected function column__supplier( $item ) {

		$supplier = self::EMPTY_COL;

		if ( ! AtumCapabilities::current_user_can('read_supplier') ) {
			return $supplier;
		}

		$supplier_id = get_post_meta( $this->get_current_product_id(), '_supplier', TRUE );

		if ($supplier_id) {

			$supplier_post = get_post($supplier_id);

			if ($supplier_post) {

				$supplier         = $supplier_post->post_title;
				$supplier_length  = absint( apply_filters( 'atum/list_table/column_supplier_length', 20 ) );
				$supplier_abb     = mb_strlen( $supplier ) > $supplier_length ? trim( mb_substr( $supplier, 0, $supplier_length ) ) . '...' : $supplier;
				$supplier_tooltip = sprintf( __( '%s (ID: %d)', ATUM_TEXT_DOMAIN ), $supplier, $supplier_id );

				$supplier = '<span class="tips" data-toggle="tooltip" title="' . $supplier_tooltip . '">' . $supplier_abb . '</span>' .
				            '<span class="atum-title-small">' . $supplier_tooltip . '</span>';

			}

		}

		return apply_filters( 'atum/list_table/column_supplier', $supplier, $item, $this->product );

	}

	/**
	 * Product SKU column
	 *
	 * @since  1.1.2
	 *
	 * @param \WP_Post $item     The WooCommerce product post
	 * @param bool     $editable Whether the SKU will be editable
	 *
	 * @return string
	 */
	protected function column__sku( $item, $editable = TRUE ) {

		$id  = $this->get_current_product_id();
		$sku = get_post_meta( $id, '_sku', true );
		$sku = $sku ?: self::EMPTY_COL;

		if ($editable) {

			$args = array(
				'post_id'    => $id,
				'meta_key'   => 'sku',
				'value'      => $sku,
				'input_type' => 'text',
				'tooltip'    => __( 'Click to edit the SKU', ATUM_TEXT_DOMAIN )
			);

			$sku = $this->get_editable_column( $args );

		}

		return apply_filters( 'atum/list_table/column_sku', $sku, $item, $this->product );

	}

	/**
	 * Post ID column
	 *
	 * @since  0.0.1
	 *
	 * @param \WP_Post $item The WooCommerce product post
	 *
	 * @return int
	 */
	protected function column_ID( $item ) {
		return apply_filters( 'atum/list_table/column_ID', $this->get_current_product_id(), $item, $this->product );
	}

	/**
	 * Column for product type
	 *
	 * @since 1.1.0
	 *
	 * @param \WP_Post $item The WooCommerce product post
	 *
	 * @return string
	 */
	protected function column_calc_type( $item ) {

		$type          = $this->product->get_type();
		$product_tip   = '';
		$product_types = wc_get_product_types();

		if ( isset($product_types[$type]) || $this->is_child ) {

			if ( ! $this->is_child ) {
				$product_tip = $product_types[ $type ];
			}

			switch ( $type ) {
				case 'simple':

					if ($this->is_child) {
						$type        = 'grouped-item';
						$product_tip = __( 'Grouped item', ATUM_TEXT_DOMAIN );
					}
					elseif ( $this->product->is_downloadable() ) {
						$type        = 'downloadable';
						$product_tip = __( 'Downloadable product', ATUM_TEXT_DOMAIN );
					}
					elseif ( $this->product->is_virtual() ) {
						$type        = 'virtual';
						$product_tip = __( 'Virtual product', ATUM_TEXT_DOMAIN );
					}

					break;

				case 'variable':
				case 'grouped':
				case 'variable-subscription': // WC Subscriptions compatibility

					if ($this->is_child) {
						$type        = 'grouped-item';
						$product_tip = __( 'Grouped item', ATUM_TEXT_DOMAIN );
					}
					elseif ( $this->product->has_child() ) {

						$product_tip .= '<br>' . sprintf(
							__('(click to show/hide the %s)', ATUM_TEXT_DOMAIN),
							( $type == 'grouped' ? __('Grouped items', ATUM_TEXT_DOMAIN) : __('Variations', ATUM_TEXT_DOMAIN) )
						);
						$type .= ' has-child';

					}

					break;

				case 'variation':

					$product_tip = __('Variation', ATUM_TEXT_DOMAIN);
					break;

				// WC Subscriptions compatibility
				case 'subscription_variation':

					$type        = 'variation';
					$product_tip = __('Subscription Variation', ATUM_TEXT_DOMAIN);
					break;
			}

			return apply_filters( 'atum/list_table/column_type', '<span class="product-type tips ' . $type . '" data-toggle="tooltip" title="' . $product_tip . '"></span>', $item, $this->product );

		}

		return '';

	}

	/**
	 * Column for product location
	 *
	 * @since 1.4.2
	 *
	 * @param \WP_Post $item The WooCommerce product post
	 *
	 * @return string
	 */
	protected function column_calc_location( $item ) {

		$locations = self::EMPTY_COL;
		$location_terms = wp_get_post_terms($this->product->get_id(), Globals::PRODUCT_LOCATION_TAXONOMY);

		if ( ! empty($location_terms) ) {
			$locations = '<a href="#" class="show-locations dashicons dashicons-editor-table tips" data-toggle="tooltip" title="' . __('Show Locations', ATUM_TEXT_DOMAIN) . '" data-locations=""></a>';
		}

		return apply_filters( 'atum/list_table/column_locations', $locations, $item, $this->product );

	}

	/**
	 * Column for purchase price
	 *
	 * @since  1.2.0
	 *
	 * @param \WP_Post $item The WooCommerce product post to use in calculations
	 *
	 * @return float
	 */
	protected function column__purchase_price( $item ) {

		$purchase_price = self::EMPTY_COL;

		if ( ! AtumCapabilities::current_user_can('view_purchase_price') ) {
			return $purchase_price;
		}

		$product_id = $this->get_current_product_id();

		if ( $this->allow_calcs ) {

			$purchase_price_value = get_post_meta( $product_id, '_purchase_price', TRUE );
			$purchase_price_value = is_numeric( $purchase_price_value ) ? Helpers::format_price( $purchase_price_value, [ 'trim_zeros' => TRUE, 'currency'   => $this->default_currency ] ) : $purchase_price;

			$args = apply_filters( 'atum/stock_central_list/args_purchase_price', array(
				'post_id'  => $product_id,
				'meta_key' => 'purchase_price',
				'value'    => $purchase_price_value,
				'symbol'   => get_woocommerce_currency_symbol(),
				'currency' => $this->default_currency,
				'tooltip'  => __( 'Click to edit the purchase price', ATUM_TEXT_DOMAIN )
			) );

			$purchase_price = $this->get_editable_column( $args );
		}

		return apply_filters( 'atum/stock_central_list/column_purchase_price', $purchase_price, $item, $this->product );

	}


	/**
	 * Column out_stock_threshold column
	 *
	 * @since  v1.4.6
	 *
	 * @param \WP_Post $item The WooCommerce product post
	 *
	 * @return double
	 */
	protected function column__out_stock_threshold( $item,  $editable = TRUE ) {

		$product_id          = $this->get_current_product_id();
		$out_stock_threshold = get_post_meta( $this->product->get_id(), '_out_stock_threshold', $single = true );
		$out_stock_threshold = $out_stock_threshold ?: self::EMPTY_COL;

		// Check type and managed stock at product level
		$product_type = $this->product->get_type();
		if ( ! in_array( $product_type, GLOBALS::OUT_STOCK_THRESHOLD_PRODUCT_TYPES ) ) {
			$editable = false;
		}

		$manage_stock = get_post_meta( $this->product->get_id(), '_manage_stock', $single = true );
		if ($manage_stock === "no"){
			$editable = false;
			//TODO CHECK THAT
			$out_stock_threshold = self::EMPTY_COL;
        }

		if ($editable) {

			$args = array(
				'post_id'  => $product_id,
				'meta_key' => 'out_stock_threshold',
				'value'    => $out_stock_threshold,
				'input_type' => 'number',
				'tooltip'  => __( 'Click to edit the out of stock threshold', ATUM_TEXT_DOMAIN )
			);

			$out_stock_threshold = $this->get_editable_column( $args );

		}

		return apply_filters( 'atum/list_table/column__out_stock_threshold', $out_stock_threshold, $item, $this->product );
	}

    /**
     * Column Weight column
     *
     * @since  v1.4.6
     *
     * @param \WP_Post $item The WooCommerce product weight
     *
     * @return double
     */
    protected function column__weight( $item,  $editable = TRUE ) {

	    $product_id = $this->get_current_product_id();
	    $weight = get_post_meta( $this->product->get_id(), '_weight', $single = true );
	    $weight = $weight ?: self::EMPTY_COL;

	    if ($editable) {

		    $args = array(
			    'post_id'  => $product_id,
			    'meta_key' => 'weight',
			    'value'    => $weight,
			    'input_type' => 'number',
			    'tooltip'  => __( 'Click to edit the weight', ATUM_TEXT_DOMAIN )
		    );

		    $weight = $this->get_editable_column( $args );

	    }

        return apply_filters( 'atum/list_table/column_weight', $weight, $item, $this->product );
    }

	/**
	 * Column for stock amount
	 *
	 * @since  0.0.1
	 *
	 * @param \WP_Post $item The WooCommerce product post to use in calculations
	 * @param bool     $editable Whether the stock will be editable
	 *
	 * @return string|int
	 */
	protected function column__stock( $item, $editable = TRUE ) {

		$stock = self::EMPTY_COL;

		$product_id = $this->get_current_product_id();

		if ($this->allow_calcs) {

			// Do not show the stock if the product is not managed by WC
			if ( ! $this->product->managing_stock() || 'parent' === $this->product->managing_stock() )  {
				return $stock;
			}

			$stock = wc_stock_amount( $this->product->get_stock_quantity() );
			$this->increase_total('_stock', $stock);

			if ($editable) {

				$args = array(
					'post_id'  => $product_id,
					'meta_key' => 'stock',
					'value'    => $stock,
					'tooltip'  => __( 'Click to edit the stock quantity', ATUM_TEXT_DOMAIN )
				);

				$stock = $this->get_editable_column( $args );

			}

		}

		return apply_filters( 'atum/stock_central_list/column_stock', $stock, $item, $this->product );

	}

	/**
	 * Column for inbound stock: shows sum of inbound stock within Purchase Orders.
	 *
	 * @since  1.3.0
	 *
	 * @param \WP_Post $item The WooCommerce product post to use in calculations
	 *
	 * @return int
	 */
	protected function column_calc_inbound( $item ) {

		$inbound_stock = self::EMPTY_COL;

		if ($this->allow_calcs) {

			// Calculate the inbound stock from pending purchase orders
			global $wpdb;

			$sql = $wpdb->prepare("
				SELECT SUM(oim2.`meta_value`) AS quantity 			
				FROM `{$wpdb->prefix}" . AtumOrderPostType::ORDER_ITEMS_TABLE . "` AS oi 
				LEFT JOIN `{$wpdb->atum_order_itemmeta}` AS oim ON oi.`order_item_id` = oim.`order_item_id`
				LEFT JOIN `{$wpdb->atum_order_itemmeta}` AS oim2 ON oi.`order_item_id` = oim2.`order_item_id`
				LEFT JOIN `{$wpdb->posts}` AS p ON oi.`order_id` = p.`ID`
				WHERE oim.`meta_key` IN ('_product_id', '_variation_id') AND `order_item_type` = 'line_item' 
				AND p.`post_type` = %s AND oim.`meta_value` = %d AND `post_status` = 'atum_pending' AND oim2.`meta_key` = '_qty'	
				GROUP BY oim.`meta_value`;",
				PurchaseOrders::POST_TYPE,
				$this->product->get_id()
			);

			$inbound_stock = $wpdb->get_var($sql);
			$inbound_stock = $inbound_stock ?: 0;
			$this->increase_total('calc_inbound', $inbound_stock);

		}

		return apply_filters( 'atum/stock_central_list/column_inbound_stock', $inbound_stock, $item, $this->product );
	}

	/**
	 * Column for stock indicators
	 *
	 * @since  0.0.1
	 *
	 * @param \WP_Post $item The WooCommerce product post to use in calculations
	 * @param string   $classes
	 * @param string   $data
	 * @param string   $primary
	 */
	protected function _column_calc_stock_indicator( $item, $classes, $data, $primary ) {

		$product_id = $this->product->get_id();
		$content    = '';

		// Add css class to the <td> elements depending on the quantity in stock compared to the last days sales
		if ( !$this->allow_calcs ) {
			$content = self::EMPTY_COL;
		}
		// Stock not managed by WC
		elseif( ! $this->product->managing_stock() || 'parent' === $this->product->managing_stock() ) {

			$wc_stock_status = $this->product->get_stock_status();

			switch ( $wc_stock_status ) {
				case 'instock':

					$classes .= ' cell-green';
					$content = '<span class="dashicons dashicons-hidden" data-toggle="tooltip" title="' . __('In Stock (not managed by WC)', ATUM_TEXT_DOMAIN) . '"></span>';
			        break;

		        case 'outofstock':

					$classes .= ' cell-red';
					$content = '<span class="dashicons dashicons-hidden" data-toggle="tooltip" title="' . __('Out of Stock (not managed by WC)', ATUM_TEXT_DOMAIN) . '"></span>';
			        break;

				case 'onbackorder':
					$classes .= ' cell-blue';
					$content = '<span class="dashicons dashicons-hidden" data-toggle="tooltip" title="' . __('On Backorder (not managed by WC)', ATUM_TEXT_DOMAIN) . '"></span>';
					break;
			}

		}
		// Out of stock
		elseif ( in_array( $product_id, $this->id_views['out_stock'] ) ) {

			$classes .= ' cell-red';
			$content = '<span class="dashicons dashicons-dismiss" data-toggle="tooltip" title="' . __( 'Out of Stock', ATUM_TEXT_DOMAIN ) . '"></span>';

		}
		// Back Orders
		elseif ( in_array( $product_id, $this->id_views['back_order'] ) ) {

			$content = '<span class="dashicons dashicons-visibility" data-toggle="tooltip" title="' . __( 'Out of Stock (back orders allowed)', ATUM_TEXT_DOMAIN ) . '"></span>';
		}
		// Low Stock
		elseif ( in_array($product_id, $this->id_views['low_stock']) ) {
			$classes .= ' cell-yellow';
			$content = '<span class="dashicons dashicons-warning" data-toggle="tooltip" title="' . __('Low Stock', ATUM_TEXT_DOMAIN) . '"></span>';
		}
		// In Stock
		elseif ( in_array($product_id, $this->id_views['in_stock']) ) {
			$classes .= ' cell-green';
			$content = '<span class="dashicons dashicons-yes" data-toggle="tooltip" title="' . __('In Stock', ATUM_TEXT_DOMAIN) . '"></span>';
		}

		//TODO out_stock_trheshold
		if ( in_array($product_id, $this->id_views['all_below_out_stock_threshold']) ) {
			$content .= '<span class="dashicons dashicons-dismiss cell-yellow" data-toggle="tooltip" title="' . __('Below Out Of Stock Trheshold', ATUM_TEXT_DOMAIN) . '"></span>';
		}


		$classes = $classes ? ' class="' . $classes . '"' : '';

		echo '<td ' . $data . $classes . '>' .
		     apply_filters( 'atum/list_table/column_stock_indicator', $content, $item, $this->product ) .
		     $this->handle_row_actions( $item, 'calc_stock_indicator', $primary ) . '</td>';

	}

	/**
	 * REQUIRED! This method dictates the table's columns and titles
	 * This should return an array where the key is the column slug (and class) and the value
	 * is the column's title text.
	 *
	 * @see WP_List_Table::single_row_columns()
	 *
	 * @since 0.0.1
	 *
	 * @return array An associative array containing column information: 'slugs'=>'Visible Titles'
	 */
	public function get_columns() {

		$result = array();

		foreach ( $this->table_columns as $table => $slug ) {
			$group = $this->search_group_columns( $table );
			$result[ $table ] = $group ? "<span class='col-$group'>$slug</span>" : $slug;
		}

		return apply_filters( 'atum/list_table/columns', $result );
	}

	/**
	 * Returns primary column name
	 *
	 * @since 0.0.8
	 *
	 * @return string   Name of the default primary column
	 */
	protected function get_default_primary_column_name() {
		return 'title';
	}

	/**
	 * Create an editable meta cell
	 *
	 * @since 1.2.0
	 *
	 * @param array $args {
	 *      Array of arguments.
	 *
	 *      @type int    $post_id           The current post ID
	 *      @type string $meta_key          The meta key name (without initial underscore) to be saved
	 *      @type mixed  $value             The new value for the meta key cell
	 *      @type string $symbol            Whether to add any symbol to value
	 *      @type string $tooltip           The informational tooltip text
	 *      @type string $input_type        The input type field to use to edit the column value
	 *      @type array  $extra_meta        Any extra fields will be appended to the popover (as JSON array)
	 *      @type string $tooltip_position  Where to place the tooltip
	 *      @type string $currency          Product prices currency
	 * }
	 *
	 * @return string
	 */
	protected function get_editable_column ($args) {

		/**
		 * @var int    $post_id
		 * @var string $meta_key
		 * @var mixed  $value
		 * @var string $symbol
		 * @var string $tooltip
		 * @var string $input_type
		 * @var array  $extra_meta
		 * @var string $tooltip_position
		 * @var string $currency
		 */
		extract( wp_parse_args( $args, array(
			'post_id'          => NULL,
			'meta_key'         => '',
			'value'            => '',
			'symbol'           => '',
			'tooltip'          => '',
			'input_type'       => 'number',
			'extra_meta'       => array(),
			'tooltip_position' => 'top',
			'currency'         => $this->default_currency
		) ) );

		$extra_meta_data = ! empty( $extra_meta ) ? ' data-extra-meta="' . htmlspecialchars( json_encode( $extra_meta ), ENT_QUOTES, 'UTF-8' ) . '"' : '';
		$symbol_data     = ! empty( $symbol ) ? ' data-symbol="' . esc_attr( $symbol ) . '"' : '';

		$editable_col = '<span class="set-meta tips" data-toggle="tooltip" title="' . $tooltip . '" data-placement="' . $tooltip_position .
		       '" data-item="' . $post_id . '" data-meta="' . $meta_key . '" ' . $symbol_data . $extra_meta_data . ' data-input-type="' .
		                $input_type . '" data-currency="' . $currency . '">' . $value . '</span>';

		return apply_filters('atum/list_table/editable_column', $editable_col, $args);

	}

	/**
	 * All columns are sortable by default except cb and thumbnail
	 *
	 * Optional. If you want one or more columns to be sortable (ASC/DESC toggle),
	 * you will need to register it here. This should return an array where the
	 * key is the column that needs to be sortable, and the value is db column to
	 * sort by. Often, the key and value will be the same, but this is not always
	 * the case (as the value is a column name from the database, not the list table).
	 *
	 * This method merely defines which columns should be sortable and makes them
	 * clickable - it does not handle the actual sorting. You still need to detect
	 * the ORDERBY and ORDER querystring variables within prepare_items() and sort
	 * your data accordingly (usually by modifying your query).
	 *
	 * @return array An associative array containing all the columns that should be sortable: 'slugs' => array('data_values', bool)
	 */
	protected function get_sortable_columns() {

		$not_sortable     = array( 'thumb', 'cb' );
		$sortable_columns = array();

		foreach ( $this->table_columns as $key => $column ) {
			if ( ! in_array( $key, $not_sortable ) && ! ( strpos( $key, 'calc_' ) === 0 ) ) {
				$sortable_columns[ $key ] = array( $key, FALSE );
			}
		}

		return apply_filters( 'atum/list_table/sortable_columns', $sortable_columns );
	}

	/**
	 * Get an associative array ( id => link ) with the list of available views on this table.
	 *
	 * @since 1.3.0
	 *
	 * @return array
	 */
	protected function get_views() {

		$views = array();
		$view  = ! empty( $_REQUEST['view'] ) ? esc_attr( $_REQUEST['view'] ) : 'all_stock';

		$views_name = array(
			'all_stock'  => __( 'All', ATUM_TEXT_DOMAIN ),
			'in_stock'   => __( 'In Stock', ATUM_TEXT_DOMAIN ),
			'out_stock'  => __( 'Out of Stock', ATUM_TEXT_DOMAIN ),
			'back_order' => __( 'on Back Order', ATUM_TEXT_DOMAIN ),
			'low_stock'  => __( 'Low Stock', ATUM_TEXT_DOMAIN ),
			'unmanaged'  => __( 'Unmanaged by WC', ATUM_TEXT_DOMAIN ),
		);

		if ( $this->show_unmanaged_counters ) {
			unset( $views_name['unmanaged'] );

			$views = array(
				'all_stock'  => array(
					'all'       => 'all_stock',
					'managed'   => 'managed',
					'unmanaged' => 'unmanaged'
				),
				'in_stock'   => array(
					'all'       => 'all_in_stock',
					'managed'   => 'in_stock',
					'unmanaged' => 'unm_in_stock'
				),
				'out_stock'  => array(
					'all'       => 'all_out_stock',
					'managed'   => 'out_stock',
					'unmanaged' => 'unm_out_stock'
				),
				'back_order' => array(
					'all'       => 'all_back_order',
					'managed'   => 'back_order',
					'unmanaged' => 'unm_back_order'
				),
			);

		}

		global $plugin_page;

		if ( !$plugin_page && ! empty( $this->_args['screen'] ) ) {
			$plugin_page = str_replace( Globals::ATUM_UI_HOOK . '_page_', '', $this->_args['screen'] );
		}

		$url = esc_url( add_query_arg( 'page', $plugin_page, admin_url()) );

		foreach ( $views_name as $key => $text ) {

			$class = $id = '';
			$classes= array();

			$current_all = ! empty( $views[ $key ]['all'] ) ? $views[ $key ]['all'] : $key;

			if ( $current_all == 'all_stock' ) {
				$count = $this->count_views['count_all'];
				$view_url = $url;
			}
			else{

				if ( ! empty( $views[ $key ] ) ) {
					$count = $this->count_views[ 'count_' . $views[ $key ]['all'] ];
				}
				else {
					$count = $this->count_views[ 'count_' . $key ];
				}

				$view_url = esc_url( add_query_arg( array( 'view' => $current_all), $url ) );
				$id       = ' id="' . $current_all . '"';
			}

			$query_filters = $this->query_filters;

			if ( $current_all == $view || ( ! $view && $current_all == 'all_stock' ) ) {
				$classes[] = 'current';
			}
			else {
				$query_filters['paged'] = 1;
			}
			if (!$count) {
				$classes[] = 'empty';
 			}

 			if ( $classes ) {
				$class = ' class="' . implode( ' ', $classes) . '"';
		    }
		    else {
				$class = '';
		    }

			$hash_params = http_build_query( array_merge( $query_filters, array( 'view' => $current_all ) ) );

			if ( ! empty( $views[ $key ] ) && $this->show_controlled ) {

				$extra_links = '';

				if ( ! empty( $views[ $key ]['managed'] ) ) {

					$man_class = array();

					$man_url   = esc_url( add_query_arg( array( 'view' => $views[ $key ]['managed'] ), $url ) );
					$man_id    = ' id="' . $views[ $key ]['managed'] . '"';
					$man_count = $this->count_views[ 'count_' . $views[ $key ]['managed'] ];

					$query_filters = $this->query_filters;

					if ( ( $views[ $key ]['managed'] == $view ) ) {
						$man_class[] = 'current';
					}
					else {
						$query_filters['paged'] = 1;
					}
					if ( ! $man_count ) {
						$man_class[] = 'empty';
					}
					if ( $man_class ) {
						$man_class = ' class="' . implode( ' ', $man_class ) . '"';
					}
					else {
						$man_class = '';
					}
					$man_hash_params = http_build_query( array_merge( $query_filters, array( 'view' => $views[ $key ]['managed'] ) ) );

					$extra_links .= '<a' . $man_id . $man_class . ' href="' . $man_url . '" rel="address:/?' . $man_hash_params . '" data-toggle="tooltip" title="' . __('Managed by WC', ATUM_LEVELS_TEXT_DOMAIN) . '">' . $man_count . '</a>';
				}

				if ( ! empty( $views[ $key ]['unmanaged'] ) ) {

					$unm_class = array();

					$unm_url         = esc_url( add_query_arg( array( 'view' => $views[ $key ]['unmanaged'] ), $url ) );
					$unm_id          = ' id="' . $views[ $key ]['unmanaged'] . '"';
					$unm_count       = $this->count_views[ 'count_' . $views[ $key ]['unmanaged'] ];

					$query_filters = $this->query_filters;

					if ( ( $views[ $key ]['unmanaged'] == $view ) ) {
						$unm_class[] = 'current';
					}
					else {
						$query_filters['paged'] = 1;
					}
					if ( ! $unm_count ) {
						$unm_class[] = 'empty';
					}
					if ( $unm_class ) {
						$unm_class = ' class="' . implode( ' ', $unm_class ) . '"';
					}
					else {
						$unm_class = '';
					}
					$unm_hash_params = http_build_query( array_merge( $query_filters, array( 'view' => $views[ $key ]['unmanaged'] ) ) );

					$extra_links .= ', <a' . $unm_id . $unm_class . ' href="' . $unm_url . '" rel="address:/?' . $unm_hash_params . '" data-toggle="tooltip" title="' . __('UnManaged by WC', ATUM_LEVELS_TEXT_DOMAIN) . '">'  . $unm_count . '</a>';
				}

				$views[ $key ] = '<span>' . $text . ' ' . '<a' . $id . $class . ' href="' . $view_url . '" rel="address:/?' . $hash_params . '">' . $count . '</a> (' . $extra_links . ')</span>';
			}
			else {
				$views[ $key ] = '<a' . $id . $class . ' href="' . $view_url . '" rel="address:/?' . $hash_params . '"><span>' . $text . ' (' . $count . ')</span></a>';
			}

		}

		return apply_filters( 'atum/list_table/view_filters', $views );

	}

	/**
	 * @inheritdoc
	 *
	 * @since 1.4.3
	 */
	public function views() {

		$views = $this->get_views();
		$views = apply_filters( "views_{$this->screen->id}", $views );

		if ( empty( $views ) )
			return;

		$this->screen->render_screen_reader_content( 'heading_views' );

		?>
		<ul class="subsubsub">
			<?php
			foreach ( $views as $class => $view ) {
				$views[ $class ] = "\t<li class='$class'>$view";
			}
			echo implode( " |</li>\n", $views ) . "</li>\n";
			?>

			<li>
				<button type="button" class="reset-filters hidden" data-toggle="tooltip" title="<?php _e('Reset Filters', ATUM_TEXT_DOMAIN) ?>"><i class="dashicons dashicons-update"></i></button>
			</li>
		</ul>
		<?php

	}

	/**
	 * Bulk actions are an associative array in the format 'slug' => 'Visible Title'
	 *
	 * @since 0.0.1
	 *
	 * @return array An associative array containing all the bulk actions: 'slugs'=>'Visible Titles'
	 */
	protected function get_bulk_actions() {

		$bulk_actions = array(
			'manage_stock'   => __( "Enable WC's Manage Stock", ATUM_TEXT_DOMAIN ),
			'unmanage_stock' => __( "Disable WC's Manage Stock", ATUM_TEXT_DOMAIN )
		);

		if (
			( isset( $_GET['uncontrolled'] ) && $_GET['uncontrolled'] == 1 ) ||
			( isset( $_REQUEST['show_controlled'] ) && $_REQUEST['show_controlled'] == 0 )
		) {
			$bulk_actions['control_stock'] = __( "Enable ATUM's Stock Control", ATUM_TEXT_DOMAIN );
		}
		else {
			$bulk_actions['uncontrol_stock'] = __( "Disable ATUM's Stock Control", ATUM_TEXT_DOMAIN );
		}

		return apply_filters( 'atum/list_table/bulk_actions', $bulk_actions, $this );

	}

	/**
	 * Display the bulk actions dropdown
	 *
	 * @since 1.4.1
	 *
	 * @param string $which The location of the bulk actions: 'top' or 'bottom'.
	 *                      This is designated as optional for backward compatibility.
	 */
	protected function bulk_actions( $which = '' ) {

		if ( is_null( $this->_actions ) ) {
			$this->_actions = $this->get_bulk_actions();
			$this->_actions = apply_filters( "atum/list_table/bulk_actions-{$this->screen->id}", $this->_actions );
			$two = '';
		}
		else {
			$two = '2';
		}

		if ( empty( $this->_actions ) ) {
			return;
		}
		?>

		<label for="bulk-action-selector-<?php echo esc_attr( $which ) ?>" class="screen-reader-text"><?php _e( 'Select bulk action', ATUM_TEXT_DOMAIN ) ?></label>
		<select name="action<?php echo $two ?>" id="bulk-action-selector-<?php echo esc_attr( $which ) ?>" autocomplete="off">
			<option value="-1"><?php _e( 'Bulk Actions', ATUM_TEXT_DOMAIN ) ?></option>

			<?php foreach ( $this->_actions as $name => $title ): ?>
				<option value="<?php echo $name ?>"<?php if ('edit' === $name) echo ' class="hide-if-no-js"' ?>><?php echo $title ?></option>
			<?php endforeach; ?>
		</select>
		<?php

	}

	/**
	 * Adds the Bulk Actions' apply button to the List Table view
	 *
	 * @since 1.4.1
	 */
	public function add_apply_bulk_action_button() {
		?><button type="button" class="apply-bulk-action page-title-action hidden"></button><?php
	}

	/**
	 * Prepare the table data
	 *
     * @since  0.0.1
	 */
	public function prepare_items() {

        /*
		 * Define our column headers
		 */
		$columns             = $this->get_columns();
		$products            = array();
		$sortable            = $this->get_sortable_columns();
        $hidden              = get_hidden_columns( $this->screen );
		$this->group_columns = $this->calc_groups( $this->group_members, $hidden );

		/*
		 * REQUIRED. Build an array to be used by the class for column headers
		 */
		$this->_column_headers = array( $columns, $hidden, $sortable );

		$args = array(
			'post_type'      => $this->post_type,
			'post_status'    => current_user_can( 'edit_private_products' ) ? ['private', 'publish'] : ['publish'],
			'posts_per_page' => $this->per_page,
			'paged'          => $this->get_pagenum()
		);

		/*
		 * Get Controlled or Uncontrolled items
		 */
		if ($this->show_controlled) {

			$args['meta_query'] = array(
				array(
					'key'   => Globals::ATUM_CONTROL_STOCK_KEY,
					'value' => 'yes'
				)
			);

		}
		else {

			$args['meta_query'] = array(
				array(
					'relation' => 'OR',
					array(
						'key'     => Globals::ATUM_CONTROL_STOCK_KEY,
						'compare' => 'NOT EXISTS'
					),
					array(
						'key'   => Globals::ATUM_CONTROL_STOCK_KEY,
						'value' => 'no'
					),
					array(
						'key'   => Globals::IS_INHERITABLE_KEY,
						'value' => 'yes'
					)
				)
			);

		}

		/*
		 * Tax filter
		 */

		// Add product category to the tax query
		if ( ! empty( $_REQUEST['product_cat'] ) ) {
			$this->taxonomies[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => esc_attr( $_REQUEST['product_cat'] )
			);
		}

		// Change the product type tax query (initialized in constructor) to the current queried type
		if ( ! empty( $_REQUEST['product_type'] ) ) {

			$type = esc_attr( $_REQUEST['product_type'] );

			foreach($this->taxonomies as $index => $taxonomy) {

				if ($taxonomy['taxonomy'] == 'product_type') {

					if ( in_array($type, ['downloadable', 'virtual']) ) {
						$this->taxonomies[$index]['terms'] = 'simple';

						$this->extra_meta = array(
							'key'   => "_$type",
							'value' => 'yes'
						);

					}
					else {
						$this->taxonomies[$index]['terms'] = $type;
					}

					break;
				}

			}

		}

		if ( $this->taxonomies ) {
			$args['tax_query'] = (array) apply_filters( 'atum/list_table/taxonomies', $this->taxonomies );
		}

		/*
		 * Supplier filter
		 */
		if ( ! empty( $_REQUEST['supplier'] ) && AtumCapabilities::current_user_can('read_supplier') ) {

			$supplier = absint( $_REQUEST['supplier'] );

			if ( ! empty($args['meta_query']) ) {
				$args['meta_query']['relation'] = 'AND';
			}

			$args['meta_query'][] = array(
				'key'   => '_supplier',
				'value' => $supplier,
				'type'  => 'numeric'
			);

			// This query does not get product variations and as each variation may have a distinct supplier,
			// we have to get them separately and to add their variables to the results
			$this->supplier_variation_products = Suppliers::get_supplier_products($supplier, 'product_variation');

			if ( ! empty($this->supplier_variation_products) ) {
				add_filter( 'atum/list_table/views_data_products', array($this, 'add_supplier_variables_to_query') );
				add_filter( 'atum/list_table/items', array($this, 'add_supplier_variables_to_query') );
				add_filter( 'atum/list_table/views_data_variations', array($this, 'add_supplier_variations_to_query'), 10, 2 );
			}

		}

		/*
		 * Extra meta args
		 */
		if ( ! empty($this->extra_meta) ) {
			$args['meta_query'][] = $this->extra_meta;
		}

		/*
		 * Sorting
		 */
		if ( ! empty( $_REQUEST['orderby'] ) ) {

			$args['order'] = ( isset( $_REQUEST['order'] ) && $_REQUEST['order'] == 'asc' ) ? 'ASC' : 'DESC';

			// Columns starting by underscore are based in meta keys, so can be sorted
			if ( substr( $_REQUEST['orderby'], 0, 1 ) == '_' ) {

				// All the meta key based columns are numeric except the SKU
				if ( $_REQUEST['orderby'] == '_sku' ) {
					$args['orderby']  = 'meta_value';
				}
				else {
					$args['orderby']  = 'meta_value_num';
				}

				$args['meta_key'] = $_REQUEST['orderby'];

			}
			// Calculated column... Can be sorted?
			/*elseif ( strpos( $_REQUEST['orderby'], 'calc_' ) === 0 ) {

			}*/
			// Standard Fields
			else {
				$args['orderby'] = $_REQUEST['orderby'];
			}

		}
		else{
		    //TODO 1598179 order by
			$args['orderby'] = 'menu_order';
			$args['order']   = 'ASC';
        }

		/*
		 * Searching
		 */
		if ( ! empty( $_REQUEST['search_column'] ) ) {
			$args['search_column'] = esc_attr( $_REQUEST['search_column'] );
		}
		if ( ! empty( $_REQUEST['s'] ) ) {
			$args['s'] = esc_attr( $_REQUEST['s'] );
		}

		// Let others play
		$args = apply_filters( 'atum/list_table/prepare_items/args', $args );

		// Build "Views Filters" and calculate totals
		$this->set_views_data( $args );

		$allow_query = TRUE;

		/*
	     * REQUIRED. Register our pagination options & calculations
		 */
		$found_posts  = isset( $this->count_views['count_all'] ) ? $this->count_views['count_all'] : 0;

		if ( ! empty( $_REQUEST['view'] ) ) {

			$view = esc_attr( $_REQUEST['view'] );
			$allow_query = FALSE;

			foreach ( $this->id_views as $key => $post_ids ) {

				if ( $view == $key && ! empty($post_ids) ) {

					$get_parents = FALSE;
					foreach ( Globals::get_inheritable_product_types() as $inheritable_product_type ) {
						if ( ! empty( $this->container_products[ $inheritable_product_type ] ) ) {
							$get_parents = TRUE;
							break;
						}
					}

					// Add the parent products again to the query
					$args['post__in'] = $get_parents ? $this->get_parents( $post_ids ) : $post_ids;
					$allow_query      = TRUE;
					$found_posts      = $this->count_views["count_$key"];

				}

			}
		}

		if ( $allow_query ) {

			if ( ! empty($this->excluded) ) {

				if ( isset($args['post__not_in']) ) {
					$args['post__not_in'] = array_merge($args['post__not_in'], $this->excluded);
				}
				else {
					$args['post__not_in'] = $this->excluded;
				}

			}

			// Setup the WP query
			global $wp_query;
			$wp_query = new \WP_Query( $args );

			$products    = $wp_query->posts;
			$product_ids = wp_list_pluck( $products, 'ID' );

			$this->current_products = $product_ids;
			$total_pages = ( $this->per_page == -1 || ! $wp_query->have_posts() ) ? 0 : ceil( $wp_query->found_posts / $this->per_page );

		}
		else {
			$found_posts = $total_pages = 0;
		}

		/**
		 * REQUIRED!!!
		 * Save the sorted data to the items property, where can be used by the rest of the class
		 */
		$this->items = apply_filters( 'atum/list_table/items', $products );

		//TODO 1598179 here is where we order all the fathers
		$this->set_pagination_args( array(
			'total_items' => $found_posts,
			'per_page'    => $this->per_page,
			'total_pages' => $total_pages,
			'orderby'     => ! empty( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : 'date',
			'order'       => ! empty( $_REQUEST['order'] ) ? $_REQUEST['order'] : 'desc'
		) );

	}

	/**
	 * Add the supplier's variable products to the filtered query
	 *
	 * @since 1.4.1.1
	 *
	 * @param array $products
	 *
	 * @return array
	 */
	public function add_supplier_variables_to_query($products) {

		foreach ($this->supplier_variation_products as $index => $variation_id) {
			$variation_product = wc_get_product( $variation_id );

			if ( ! is_a($variation_product, '\WC_Product_Variation') ) {
				unset( $this->supplier_variation_products[$index] );
				continue;
			}

			$is_controlled = Helpers::is_atum_controlling_stock( $variation_product->get_id() );

			if ( ( $this->show_controlled && !$is_controlled ) || ( !$this->show_controlled && $is_controlled ) ) {
				unset( $this->supplier_variation_products[$index] );
				continue;
			}

			$variable_id = $variation_product->get_parent_id();

			if ( ! is_array($products) || ! in_array($variable_id, $products) ) {
				$products[] = $this->container_products['all_variable'][] = $this->container_products['variable'][] = $variable_id;
			}
		}

		return $products;
	}

	/**
	 * Add the supplier's variation products to the filtered query
	 *
	 * @since 1.4.1.1
	 *
	 * @param array $variations
	 * @param array $products
	 *
	 * @return array
	 */
	public function add_supplier_variations_to_query($variations, $products) {

		return array_merge($variations, $this->supplier_variation_products);
	}

	/**
	 * Set views for table filtering and calculate total value counters for pagination
	 *
	 * @since 0.0.2
	 *
	 * @param array $args WP_Query arguments
	 */
	protected function set_views_data( $args ) {

		global $wpdb;

		if ( $this->show_unmanaged_counters ) {

			$this->id_views = array_merge( $this->id_views, array(
				'managed'                 => [],
				'unm_in_stock'            => [],
				'unm_out_stock'           => [],
				'unm_back_order'          => [],
				'all_in_stock'            => [],
				'all_out_stock'           => [],
				'all_back_order'          => [],
				'all_below_out_stock_threshold' => [],
			) );

			$this->count_views = array_merge( $this->count_views, array(
				'count_managed'        => 0,
				'count_unm_in_stock'   => 0,
				'count_unm_out_stock'  => 0,
				'count_unm_back_order' => 0,
				'count_all_in_stock'   => 0,
				'count_all_out_stock'  => 0,
				'count_all_back_order' => 0,
			) );

		}

		// Get all the IDs in the two queries with no pagination
		$args['fields']         = 'ids';
		$args['posts_per_page'] = - 1;
		unset( $args['paged'] );

		$all_transient = Helpers::get_transient_identifier( $args, 'list_table_all' );
		$products = Helpers::get_transient( $all_transient );

		if ( ! $products ) {

			global $wp_query;
			$wp_query = new \WP_Query( apply_filters( 'atum/list_table/set_views_data/all_args', $args ) );
			$products = $wp_query->posts;

			// Save it as a transient to improve the performance
			Helpers::set_transient( $all_transient, $products );

		}

		// Let others play here
		$products = apply_filters( 'atum/list_table/views_data_products', $products );

		$this->count_views['count_all'] = count( $products );

		if ( $this->is_filtering && empty($products) ) {
			return;
		}

		// If it's a search or a product filtering, include only the filtered items to search for children
		$post_in = $this->is_filtering ? $products : array();

		foreach($this->taxonomies as $index => $taxonomy) {

			if ( $taxonomy['taxonomy'] == 'product_type' ) {

				if ( in_array('variable', (array) $taxonomy['terms']) ) {

					$variations = apply_filters( 'atum/list_table/views_data_variations', $this->get_children( 'variable', $post_in, 'product_variation' ), $post_in );

					// Remove the variable containers from the array and add the variations
					$products = array_unique( array_merge( array_diff( $products, $this->container_products['all_variable'] ), $variations ) );

				}

				if ( in_array('grouped', (array) $taxonomy['terms']) ) {

					$group_items = apply_filters( 'atum/list_table/views_data_grouped', $this->get_children( 'grouped', $post_in ), $post_in );

					// Remove the grouped containers from the array and add the group items
					$products = array_unique( array_merge( array_diff( $products, $this->container_products['all_grouped'] ), $group_items ) );

				}

				// WC Subscriptions compatibility
				if ( class_exists('\WC_Subscriptions') && in_array('variable-subscription', (array) $taxonomy['terms']) ) {

					$sc_variations = apply_filters( 'atum/list_table/views_data_sc_variations', $this->get_children( 'variable-subscription', $post_in, 'product_variation' ), $post_in );

					// Remove the variable subscription containers from the array and add the subscription variations
					$products = array_unique( array_merge( array_diff( $products, $this->container_products['all_variable_subscription'] ), $sc_variations ) );

				}

				// Re-count the resulting products
				$this->count_views['count_all'] = count( $products );

				// The grouped items must count once per group they belongs to and once individually
				if ( ! empty($group_items) ) {
					$this->count_views['count_all'] += count( $group_items );
				}

				do_action('atum/list_table/after_children_count', $taxonomy['terms'], $this);

				break;
			}

		}

		// For the Uncontrolled items, we don't need to calculate stock totals
		if (!$this->show_controlled) {
			return;
		}

		if ( $products ) {

			$post_types = ( ! empty($variations) || ! empty($sc_variations) ) ? [$this->post_type, 'product_variation'] : [$this->post_type];

			/*
			 * Unmanaged products
			 */

			if ( $this->show_unmanaged_counters ) {

				$products_unmanaged = array();
				$products_unmanaged_status = Helpers::get_unmanaged_products( $post_types, TRUE );

				if ( ! empty( $products_unmanaged_status ) ) {

					// Filter the unmanaged (also removes uncontrolled)
					$products_unmanaged_status = array_filter( $products_unmanaged_status, function ( $row ) use ( $products ) {

						return in_array( $row[0], $products );
					} );

					$this->id_views['unm_in_stock']          = array_column( array_filter( $products_unmanaged_status, function ( $row ) {

						return ( $row[1] == 'instock' );
					} ), 0 );
					$this->count_views['count_unm_in_stock'] = count( $this->id_views['unm_in_stock'] );

					$this->id_views['unm_out_stock']          = array_column( array_filter( $products_unmanaged_status, function ( $row ) {

						return ( $row[1] == 'outofstock' );
					} ), 0 );
					$this->count_views['count_unm_out_stock'] = count( $this->id_views['unm_out_stock'] );

					$this->id_views['unm_back_order']          = array_column( array_filter( $products_unmanaged_status, function ( $row ) {

						return ( $row[1] == 'onbackorder' );
					} ), 0 );
					$this->count_views['count_unm_back_order'] = count( $this->id_views['unm_back_order'] );

					$products_unmanaged = array_column( $products_unmanaged_status, 0 );

					$this->id_views['managed']          = array_diff( $products, $products_unmanaged );
					$this->count_views['count_managed'] = count( $this->id_views['managed'] );
				}

			}
			else {
				$products_unmanaged = array_column( Helpers::get_unmanaged_products( $post_types ), 0 );
			}


			// Remove the unmanaged from the products list
			if ( ! empty( $products_unmanaged ) ) {
				// Filter the unmanaged (also removes uncontrolled)
				$products_unmanaged = array_intersect( $products, $products_unmanaged );

				$this->id_views['unmanaged']          = $products_unmanaged;
				$this->count_views['count_unmanaged'] = count( $products_unmanaged );

				if ( ! empty( $products_unmanaged ) ) {
					$products = ! empty( $this->count_views['count_managed'] ) ? $this->id_views['managed'] : array_diff( $products, $products_unmanaged );
				}

			}

			/*
			 * Products in stock
			 */
			$args = array(
				'post_type'      => $post_types,
				'posts_per_page' => - 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => '_stock',
						'value'   => 0,
						'type'    => 'numeric',
						'compare' => '>',
					),
				),
				'post__in'       => $products
			);

			$in_stock_transient = Helpers::get_transient_identifier( $args, 'list_table_in_stock' );
			$products_in_stock  = Helpers::get_transient( $in_stock_transient );

			if ( empty($products_in_stock) ) {
				$products_in_stock = new \WP_Query( apply_filters( 'atum/list_table/set_views_data/in_stock_args', $args ) );
				Helpers::set_transient( $in_stock_transient, $products_in_stock );
			}

			$products_in_stock = $products_in_stock->posts;

			$this->id_views['in_stock']          = $products_in_stock;
			$this->count_views['count_in_stock'] = count( $products_in_stock );

			$products_not_stock = array_diff( $products, $products_in_stock, $products_unmanaged);

			/**
			 * Products on Back Order
			 */
			$args = array(
				'post_type'      => $post_types,
				'posts_per_page' => - 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => '_stock',
						'value'   => 0,
						'type'    => 'numeric',
						'compare' => '<=',
					),
					array(
						'key'     => '_backorders',
						'value'   => array( 'yes', 'notify' ),
						'type'    => 'char',
						'compare' => 'IN',
					),

				),
				'post__in'       => $products_not_stock
			);

			$back_order_transient = Helpers::get_transient_identifier( $args, 'list_table_back_order' );
			$products_back_order  = Helpers::get_transient( $back_order_transient );

			if ( empty($products_back_order) ) {
				$products_back_order = new \WP_Query( apply_filters( 'atum/list_table/set_views_data/back_order_args', $args ) );
				Helpers::set_transient( $back_order_transient, $products_back_order );
			}

			$products_back_order = $products_back_order->posts;

			$this->id_views['back_order']          = $products_back_order;
			$this->count_views['count_back_order'] = count( $products_back_order );


			// As the Group items might be displayed multiple times, we should count them multiple times too
			if ( ! empty($group_items) && ( empty($_REQUEST['product_type']) || $_REQUEST['product_type'] != 'grouped' )) {
				$this->count_views['count_in_stock'] += count( array_intersect($group_items, $products_in_stock) );
				$this->count_views['count_back_order'] += count( array_intersect($group_items, $products_back_order) );

			}

			/*
			 * Products with low stock
			 */
			if ( $this->count_views['count_in_stock'] ) {

				$low_stock_transient = Helpers::get_transient_identifier( $args, 'list_table_low_stock' );
				$products_low_stock  = Helpers::get_transient( $low_stock_transient );

				if ( empty($products_low_stock) ) {

					// Compare last seven days average sales per day * re-order days with current stock
					$str_sales = "(SELECT			   
					    (SELECT MAX(CAST( meta_value AS SIGNED )) AS q FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key IN('_product_id', '_variation_id') AND order_item_id = `item`.`order_item_id`) AS IDs,
					    CEIL(SUM((SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = '_qty' AND order_item_id = `item`.`order_item_id`))/7*$this->last_days) AS qty
						FROM `{$wpdb->posts}` AS `order`
						    INNER JOIN `{$wpdb->prefix}woocommerce_order_items` AS `item` ON (`order`.`ID` = `item`.`order_id`)
							INNER JOIN `{$wpdb->postmeta}` AS `order_meta` ON (`order`.ID = `order_meta`.`post_id`)
						WHERE (`order`.`post_type` = 'shop_order'
						    AND `order`.`post_status` IN ('wc-completed', 'wc-processing') AND `item`.`order_item_type` ='line_item'
						    AND `order_meta`.`meta_key` = '_paid_date'
						    AND `order_meta`.`meta_value` >= '" . Helpers::date_format( '-7 days' ) . "')
						GROUP BY IDs) AS sales";

					$str_states = "(SELECT `{$wpdb->posts}`.`ID`,
						IF( CAST( IFNULL(`sales`.`qty`, 0) AS DECIMAL(10,2) ) <= 
							CAST( IF( LENGTH(`{$wpdb->postmeta}`.`meta_value`) = 0 , 0, `{$wpdb->postmeta}`.`meta_value`) AS DECIMAL(10,2) ), TRUE, FALSE) AS state
						FROM `{$wpdb->posts}`
						    LEFT JOIN `{$wpdb->postmeta}` ON (`{$wpdb->posts}`.`ID` = `{$wpdb->postmeta}`.`post_id`)
						    LEFT JOIN " . $str_sales . " ON (`{$wpdb->posts}`.`ID` = `sales`.`IDs`)
						WHERE (`{$wpdb->postmeta}`.`meta_key` = '_stock'
				            AND `{$wpdb->posts}`.`post_type` IN ('" . implode("', '", $post_types) . "')
				            AND (`{$wpdb->posts}`.`ID` IN (" . implode( ', ', $products_in_stock ) . ")) )) AS states";

					$str_sql = apply_filters( 'atum/list_table/set_views_data/low_stock', "SELECT `ID` FROM $str_states WHERE state IS FALSE;" );

					$products_low_stock = $wpdb->get_results( $str_sql );
					$products_low_stock = wp_list_pluck( $products_low_stock, 'ID' );
					Helpers::set_transient( $low_stock_transient, $products_low_stock );

				}

				$this->id_views['low_stock']          = $products_low_stock;
				$this->count_views['count_low_stock'] = count( $products_low_stock );

			}

			/**
			 * Products that are below _out_stock_threshold
			 */
			//TODO LIST ALL THRESHOLDS
			$query = "SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm
    
                INNER JOIN {$wpdb->postmeta} pm_manage_stock 			ON ( pm_manage_stock.meta_key = '_manage_stock'  				AND pm_manage_stock.post_id = pm.post_id)
                INNER JOIN {$wpdb->postmeta} pm_out_stock_threshold 	ON ( pm_out_stock_threshold.meta_key = '_out_stock_threshold' 	AND pm_out_stock_threshold.post_id = pm.post_id )
                INNER JOIN {$wpdb->postmeta} pm_stock 				ON ( pm_stock.meta_key = '_stock'  								AND pm_stock.post_id = pm.post_id)
                
                WHERE pm_manage_stock.meta_value = 'yes' AND pm_out_stock_threshold.meta_value > pm_stock.meta_value AND pm_out_stock_threshold.meta_value<>'' AND pm_stock.meta_value<>''";
			$products_below_out_stock_threshold = $wpdb->get_col( $query );

            $this->id_views['all_below_out_stock_threshold']      = $products_below_out_stock_threshold;
            $this->count_views['count_below_out_stock_threshold'] = count($products_below_out_stock_threshold);

			/*
			 * Products out of stock
			 */
			$products_out_stock = array_diff( $products_not_stock, $products_back_order );

			$this->id_views['out_stock']          = $products_out_stock;
			$this->count_views['count_out_stock'] = $this->count_views['count_all'] - $this->count_views['count_in_stock'] - $this->count_views['count_back_order'] - $this->count_views['count_unmanaged'];

			if ( $this->show_unmanaged_counters) {
				/*
				 * Calculate totals
				 */
				$this->id_views['all_in_stock']          = array_merge( $this->id_views['in_stock'], $this->id_views['unm_in_stock'] );
				$this->count_views['count_all_in_stock'] = $this->count_views['count_in_stock'] + $this->count_views['count_unm_in_stock'];

				$this->id_views['all_out_stock']          = array_merge( $this->id_views['out_stock'], $this->id_views['unm_out_stock'] );
				$this->count_views['count_all_out_stock'] = $this->count_views['count_out_stock'] + $this->count_views['count_unm_out_stock'];

				$this->id_views['all_back_order']          = array_merge( $this->id_views['back_order'], $this->id_views['unm_back_order'] );
				$this->count_views['count_all_back_order'] = $this->count_views['count_back_order'] + $this->count_views['count_unm_back_order'];

			}

		}

	}

	/**
	 * Print column headers, accounting for hidden and sortable columns
	 *
	 * @since 1.4.5
	 *
	 * @param bool $with_id Whether to set the id attribute or not
	 */
	public function print_column_headers( $with_id = TRUE ) {

		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		$current_url     = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		$current_url     = remove_query_arg( 'paged', $current_url );
		$current_orderby = isset( $_GET['orderby'] ) ? $_GET['orderby'] : '';
		$current_order   = ( isset( $_GET['order'] ) && 'desc' === $_GET['order'] ) ? 'desc' : 'asc';

		if ( ! empty( $columns['cb'] ) ) {
			static $cb_counter = 1;
			$columns['cb'] = '<label class="screen-reader-text" for="cb-select-all-' . $cb_counter . '">' . __( 'Select All' ) . '</label>'
			                 . '<input id="cb-select-all-' . $cb_counter . '" type="checkbox" />';
			$cb_counter++;
		}

		foreach ( $columns as $column_key => $column_display_name ) {

			$class = array( 'manage-column', "column-$column_key" );

			if ( in_array( $column_key, $hidden ) ) {
				$class[] = 'hidden';
			}

			if ( 'cb' === $column_key ) {
				$class[] = 'check-column';
			}
			elseif ( in_array( $column_key, array( 'posts', 'comments', 'links' ) ) ) {
				$class[] = 'num';
			}

			if ( $column_key === $primary ) {
				$class[] = 'column-primary';
			}

			if ( isset( $sortable[$column_key] ) ) {

				list( $orderby, $desc_first ) = $sortable[$column_key];

				if ( $current_orderby === $orderby ) {
					$order   = 'asc' === $current_order ? 'desc' : 'asc';
					$class[] = 'sorted';
					$class[] = $current_order;
				}
				else {
					$order   = $desc_first ? 'desc' : 'asc';
					$class[] = 'sortable';
					$class[] = $desc_first ? 'asc' : 'desc';
				}

				$sorting_params = compact( 'orderby', 'order' );
				$sorting_url    = esc_url( add_query_arg( $sorting_params, $current_url ) );
				$hash_params    = http_build_query( array_merge( $this->query_filters, $sorting_params ) );

				$column_display_name = '<a href="' . $sorting_url . '" rel="address:/?' . $hash_params . '"><span>' . $column_display_name . '</span><span class="sorting-indicator"></span></a>';

			}

			$tag   = 'cb' === $column_key ? 'td' : 'th';
			$scope = 'th' === $tag ? 'scope="col"' : '';
			$id    = $with_id ? "id='$column_key'" : '';

			if ( ! empty( $class ) ) {
				$class = "class='" . join( ' ', $class ) . "'";
			}

			echo "<$tag $scope $id $class>$column_display_name</$tag>";

		}

	}

	/**
	 * Adds the data needed for ajax filtering, sorting and pagination and displays the table
	 *
	 * @since 0.0.1
	 */
	public function display() {

		do_action( 'atum/list_table/before_display', $this );

		$singular = $this->_args['singular'];
		$this->display_tablenav( 'top' );
		$this->screen->render_screen_reader_content( 'heading_list' );

		?>
		<div class="atum-table-wrapper">
			<table class="wp-list-table atum-list-table <?php echo implode( ' ', $this->get_table_classes() ); ?>"
				data-currency-pos="<?php echo get_option( 'woocommerce_currency_pos', 'left' ) ?>">

				<thead>
					<?php $this->print_group_columns(); ?>

					<tr class="item-heads">
						<?php $this->print_column_headers(); ?>
					</tr>
				</thead>

				<tbody id="the-list"<?php if ( $singular ) echo " data-wp-lists='list:$singular'"; ?>>
					<?php $this->display_rows_or_placeholder(); ?>
				</tbody>

				<tfoot>

					<?php if ( $this->show_totals ): ?>
					<tr class="totals">
						<?php $this->print_column_totals(); ?>
					</tr>
					<?php endif ?>

					<tr class="item-heads">
						<?php $this->print_column_headers( FALSE ); ?>
					</tr>

				</tfoot>

			</table>

			<input type="hidden" name="atum-column-edits" id="atum-column-edits" value="">
		</div>
		<?php

		$this->display_tablenav( 'bottom' );
		global $plugin_page;

		// Prepare JS vars
		$vars = array(
			'listUrl'           => esc_url( add_query_arg( 'page', $plugin_page, admin_url() ) ),
			'perPage'           => $this->per_page,
			'showCb'            => $this->show_cb,
			'order'             => isset( $this->_pagination_args['order'] ) ? $this->_pagination_args['order'] : '',
			'orderby'           => isset( $this->_pagination_args['orderby'] ) ? $this->_pagination_args['orderby'] : '',
			'nonce'             => wp_create_nonce( 'atum-list-table-nonce' ),
			'ajaxFilter'        => Helpers::get_option( 'enable_ajax_filter', 'yes' ),
			'setValue'          => __( 'Set the %% value', ATUM_TEXT_DOMAIN ),
			'setButton'         => __( 'Set', ATUM_TEXT_DOMAIN ),
			'saveButton'        => __( 'Save Data', ATUM_TEXT_DOMAIN ),
			'ok'                => __( 'OK', ATUM_TEXT_DOMAIN ),
			'noItemsSelected'   => __( 'No Items Selected', ATUM_TEXT_DOMAIN ),
			'selectItems'       => __( 'Please, check the boxes for all the products you want to change in bulk', ATUM_TEXT_DOMAIN ),
			'applyBulkAction'   => __( 'Apply Bulk Action', ATUM_TEXT_DOMAIN ),
			'applyAction'       => __( 'Apply Action', ATUM_TEXT_DOMAIN ),
			'productLocations'  => __( 'Product Locations', ATUM_TEXT_DOMAIN ),
			'searchableColumns' => $this->default_searchable_columns
		);

		if ($this->first_edit_key) {
			$vars['firstEditKey'] = $this->first_edit_key;
			$vars['important']         = __( 'Important!', ATUM_TEXT_DOMAIN );
			$vars['preventLossNotice'] = __( "To prevent any loss of data, please, hit the blue 'Save Data' button at the top left after completing edits.", ATUM_TEXT_DOMAIN );
		}

		$vars = apply_filters( 'atum/list_table/js_vars', $vars );
		wp_localize_script( 'atum-list', 'atumListVars', $vars );

		do_action( 'atum/list_table/after_display', $this );

	}

	/**
	 * Prints the columns that groups the distinct header columns
	 *
	 * @since 0.0.1
	 */
	public function print_group_columns() {

		if ( ! empty( $this->group_columns ) ) {

			echo '<tr class="column-groups">';

			foreach ( $this->group_columns as $group_column ) {
				echo '<th class="' . $group_column['name'] . '" colspan="' . $group_column['colspan'] . '"><span>' . $group_column['title'] . '</span></th>';
			}

			echo '</tr>';

		}
	}

	/**
	 * Prints the totals row on table footer
	 *
	 * @since 1.4.2
	 */
	public function print_column_totals() {

		// Does not show the totals row if there are no results
		if ( empty($this->items) ) {
			return;
		}

		list( $columns, $hidden, $sortable, $primary ) = $this->get_column_info();

		$column_keys  = array_keys( $columns );
		$first_column = reset( $column_keys );

		foreach ( $columns as $column_key => $column_display ) {

			$class = array( 'manage-column', "column-$column_key" );

			if ( in_array( $column_key, $hidden ) ) {
				$class[] = 'hidden';
			}

			if ( $first_column == $column_key ) {
				$class[] = 'totals-heading';
				$column_display = '<span>' . __('Totals', ATUM_TEXT_DOMAIN) . '</span>';
			}
			elseif( in_array( $column_key, array_keys($this->totalizers) ) ) {
				$total = $this->totalizers[ $column_key ];
				$total_class = $total < 0 ? ' class="danger"' : '';
				$column_display = "<span{$total_class}>" . $total . '</span>';
			}
			else {
				$column_display = self::EMPTY_COL;
			}

			if ( $column_key == $primary ) {
				$class[] = 'column-primary';
			}

			$tag   = 'cb' == $column_key ? 'td' : 'th';
			$scope = 'th' == $tag ? 'scope="col"' : '';

			if ( ! empty( $class ) ) {
				$class = "class='" . join( ' ', $class ) . "'";
			}

			echo "<$tag $scope $class>$column_display</th>";

		}

	}

	/**
	 * Generate the table navigation above or below the table
	 * Just the parent function but removing the nonce fields that are not required here
	 *
	 * @since 0.0.1
	 *
	 * @param string $which 'top' or 'bottom' table nav
	 */
	protected function display_tablenav( $which ) {

		?>
		<div class="tablenav <?php echo esc_attr( $which ); ?>">

			<?php if ( ! empty( $this->get_bulk_actions() ) ): ?>
				<div class="alignleft actions bulkactions">
					<?php $this->bulk_actions( $which ); ?>
				</div>
				<?php
			endif;

			$this->extra_tablenav( $which );

			// Firefox fix to not preserve the pagination input value when reloading the page
			ob_start();
			$this->pagination( $which );
			echo str_replace( '<input ', '<input autocomplete="off" ', ob_get_clean() );
			?>

			<br class="clear"/>
		</div>
		<?php

	}

	/**
	 * Message to be displayed when there are no items
	 *
	 * @since 0.0.1
	 */
	public function no_items() {

		$post_type_obj = get_post_type_object( $this->post_type );
		echo $post_type_obj->labels->not_found;

		if ( ! empty( $_REQUEST['s'] ) ) {
			printf( __( " with query '%s'", ATUM_TEXT_DOMAIN ), esc_attr( $_REQUEST['s'] ) );
		}

	}

	/**
	 * @inheritdoc
	 *
	 * @since 1.4.3
	 */
	protected function pagination( $which ) {

		if ( empty( $this->_pagination_args ) ) {
			return;
		}

		$total_items = $this->_pagination_args['total_items'];
		$total_pages = $this->_pagination_args['total_pages'];

		if ( 'top' === $which && $total_pages > 1 ) {
			$this->screen->render_screen_reader_content( 'heading_pagination' );
		}

		$output = '<span class="displaying-num">' . sprintf( _n( '%s item', '%s items', $total_items, ATUM_TEXT_DOMAIN ), number_format_i18n( $total_items ) ) . '</span>';

		$current = $this->get_pagenum();
		$removable_query_args = wp_removable_query_args();

		$current_url        = set_url_scheme( 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
		$current_url        = remove_query_arg( $removable_query_args, $current_url );
		$page_links         = array();
		$total_pages_before = '<span class="paging-input">';
		$total_pages_after  = '</span></span>';

		$disable_first = $disable_last = $disable_prev = $disable_next = FALSE;

		if ( $current == 1 ) {
			$disable_first = TRUE;
			$disable_prev  = TRUE;
		}

		if ( $current == 2 ) {
			$disable_first = TRUE;
		}

		if ( $current == $total_pages ) {
			$disable_last = TRUE;
			$disable_next = TRUE;
		}

		if ( $current == $total_pages - 1 ) {
			$disable_last = TRUE;
		}

		if ( $disable_first ) {
			$page_links[] = '<span class="tablenav-pages-navspan" aria-hidden="true">&laquo;</span>';
		}
		else {

			$page_links[] = sprintf( "<a class='first-page' href='%s' rel='address:/?%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
				esc_url( remove_query_arg( 'paged', $current_url ) ),
				http_build_query( array_merge($this->query_filters, ['paged' => 1]) ),
				__( 'First page', ATUM_TEXT_DOMAIN ),
				'&laquo;'
			);

		}

		if ( $disable_prev ) {
			$page_links[] = '<span class="tablenav-pages-navspan" aria-hidden="true">&lsaquo;</span>';
		}
		else {

			$prev_page    = max( 1, $current - 1 );
			$page_links[] = sprintf( "<a class='prev-page' href='%s' rel='address:/?%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
				esc_url( add_query_arg( 'paged', $prev_page, $current_url ) ),
				http_build_query( array_merge($this->query_filters, ['paged' => $prev_page]) ),
				__( 'Previous page', ATUM_TEXT_DOMAIN ),
				'&lsaquo;'
			);

		}

		if ( 'bottom' === $which ) {
			$html_current_page  = $current;
			$total_pages_before = '<span class="screen-reader-text">' . __( 'Current Page', ATUM_TEXT_DOMAIN ) . '</span><span id="table-paging" class="paging-input"><span class="tablenav-paging-text">';
		}
		else {
			$html_current_page = sprintf( "%s<input class='current-page' id='current-page-selector' type='text' name='paged' value='%s' size='%d' aria-describedby='table-paging' /><span class='tablenav-paging-text'>",
				'<label for="current-page-selector" class="screen-reader-text">' . __( 'Current Page', ATUM_TEXT_DOMAIN ) . '</label>',
				$current,
				strlen( $total_pages )
			);
		}

		$html_total_pages = sprintf( "<span class='total-pages'>%s</span>", number_format_i18n( $total_pages ) );
		$page_links[] = $total_pages_before . sprintf( _x( '%1$s of %2$s', 'paging', ATUM_TEXT_DOMAIN ), $html_current_page, $html_total_pages ) . $total_pages_after;

		if ( $disable_next ) {
			$page_links[] = '<span class="tablenav-pages-navspan" aria-hidden="true">&rsaquo;</span>';
		}
		else {

			$next_page    = min( $total_pages, $current + 1 );
			$page_links[] = sprintf( "<a class='next-page' href='%s' rel='address:/?%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
				esc_url( add_query_arg( 'paged', $next_page, $current_url ) ),
				http_build_query( array_merge($this->query_filters, ['paged' => $next_page]) ),
				__( 'Next page', ATUM_TEXT_DOMAIN ),
				'&rsaquo;'
			);
		}

		if ( $disable_last ) {
			$page_links[] = '<span class="tablenav-pages-navspan" aria-hidden="true">&raquo;</span>';
		}
		else {

			$page_links[] = sprintf( "<a class='last-page' href='%s' rel='address:/?%s'><span class='screen-reader-text'>%s</span><span aria-hidden='true'>%s</span></a>",
				esc_url( add_query_arg( 'paged', $total_pages, $current_url ) ),
				http_build_query( array_merge($this->query_filters, ['paged' => $total_pages]) ),
				__( 'Last page', ATUM_TEXT_DOMAIN ),
				'&raquo;'
			);

		}

		$pagination_links_class = 'pagination-links';

		$output .= "\n<span class='$pagination_links_class'>" . join( "\n", $page_links ) . '</span>';

		if ( $total_pages ) {
			$page_class = $total_pages < 2 ? ' one-page' : '';
		}
		else {
			$page_class = ' no-pages';
		}

		$this->_pagination = "<div class='tablenav-pages{$page_class}'>$output</div>";

		echo $this->_pagination;

	}

	/**
	 * Get a list of CSS classes for the WP_List_Table table tag. Deleted 'fixed' from standard function
	 *
	 * @since  0.0.2
	 *
	 * @return array List of CSS classes for the table tag
	 */
	protected function get_table_classes() {

		return array( 'widefat', $this->_args['plural'] );
	}

	/**
	 * A wrapper to get the right product ID (or variation ID)
	 *
	 * @since 1.2.1
	 *
	 * @return int
	 */
	protected function get_current_product_id() {

		if ( $this->product->get_type() == 'variation' ) {
			/**
			 * @deprecated
			 * The get_variation_id() method was deprecated in WC 3.0.0
			 * In newer versions the get_id() method always be the variation_id if it's a variation
			 */
			return ( version_compare( WC()->version, '3.0.0', '<' ) == -1 ) ? $this->product->get_variation_id() : $this->product->get_id();
		}

		return $this->product->get_id();

	}

	/**
	 * Gets the array needed to print html group columns in the table
	 *
	 * @since 0.0.1
	 *
	 * @param   array $group_members Parameter from __contruct method
	 * @param   array $hidden        hidden columns
	 *
	 * @return  array
	 */
	public function calc_groups( $group_members, $hidden ) {

		$response = array();

		foreach ( $group_members as $name => $group ) {

			$counter = 0;

			foreach ( $group['members'] as $member ) {
				if ( ! in_array( $member, $hidden ) ) {
					$counter ++;
				}
			}

			// Add the group only if there are columns within
			if ($counter) {
				$response[] = array(
					'name'    => $name,
					'title'   => $group['title'],
					'colspan' => $counter
				);
			}
		}

		return $response;

	}

	/**
	 * Return the group of columns that a specific column belongs to or false
	 *
	 * @sinece 0.0.5
	 *
	 * @param $column  string  The column to search to
	 *
	 * @return bool|string
	 */
	public function search_group_columns( $column ) {

		foreach ( $this->group_members as $name => $group_member ) {
			if ( in_array( $column, $group_member['members'] ) ) {
				return $name;
			}
		}

		return FALSE;
	}

	/**
	 * Search products by: A (post_title, post_excerpt, post_content ), B (posts.ID), C (posts.title), D (other meta fields wich can be numeric or not)
	 *
	 * @since 1.4.8
	 *
	 * @param string $where "AND (((wp_posts.post_title LIKE '%s%') OR (wp_posts.post_excerpt LIKE '%s%') OR (wp_posts.post_content LIKE '%s%')))"
	 *
	 * @return string
	 */
	public function product_search( $where ) {

		global $pagenow, $wpdb;

		// Changed the WooCommerce's "product_search" filter to allow Ajax requests
		// @see \\WC_Admin_Post_Types\product_search

		if (
			! is_admin() ||
			! in_array( $pagenow, array( 'edit.php', 'admin-ajax.php' ) ) ||
			! isset( $_REQUEST['s'], $_REQUEST['action'] ) || strpos( $_REQUEST['action'], ATUM_PREFIX ) === FALSE
		) {
			return $where;
		}

		// Prevent keyUp problems (scenario: do a search with s and search_column, clean s, change search_column... and you will get nothing (s still set on url))
		if ( strlen( $_REQUEST['s'] ) == 0 ) {
			return "AND ( 1 = 1 )";
		}

		// If we don't get any result looking for a field, we must force an empty result before
		// WP tries to query {$wpdb->posts}.ID IN ( 'empty value' ), which raises an error
		$where_without_results = "AND ( {$wpdb->posts}.ID = -1 )";

		$search_column = esc_attr( $_REQUEST['search_column'] );

		// # case A # search in post_title, post_excerpt and post_content like a pro
		if ( empty( $search_column ) ) {

			// Sanitize inputs
			$term = $wpdb->esc_like( strtolower( sanitize_text_field( $_REQUEST['s'] ) ) );

			$query = "
				SELECT ID, post_type, post_parent FROM $wpdb->posts
		        WHERE post_type IN ('product', 'product_variation') 
		        AND (
	                lower(post_title) LIKE '%{$term}%' 
		            OR lower(post_excerpt) LIKE '%{$term}%' 
		            OR lower(post_content) LIKE '%{$term}%'
		        )
	         ";

			$search_terms_ids = $wpdb->get_results( $query, ARRAY_A );

			if ( count( $search_terms_ids ) == 0 ) {
				return $where_without_results;
			}

			// Remove duplicate values from a multi-dimensional array
			$search_terms_ids = array_map( 'unserialize', array_unique( array_map( 'serialize', $search_terms_ids ) ) );

			$search_terms_ids_arr = array();

			foreach ( $search_terms_ids as $product ) {

				if ( $product['post_type'] == 'product' ) {
					array_push( $search_terms_ids_arr, $product['ID'] );
				}
				// Add parent and current
				else {
					array_push( $search_terms_ids_arr, $product['ID'] );
					array_push( $search_terms_ids_arr, $product['post_parent'] );
				}
			}

			$search_terms_ids_arr = array_unique( $search_terms_ids_arr );
			$search_terms_ids_str = implode( ',', $search_terms_ids_arr );

			$where = "AND ( {$wpdb->posts}.ID IN ($search_terms_ids_str) )";

		}
		else {

			// Sanitize inputs
			$term = $wpdb->esc_like( strtolower( sanitize_text_field( $_REQUEST['s'] ) ) );

			if ( Helpers::in_multi_array( $search_column, Globals::SEARCHABLE_COLUMNS ) ) {

				// Case B # search in IDs
				if ( $search_column == 'ID' ) {

					$term = absint( $term );

					// Not numeric terms
					if ( empty( $term ) ) {
						return $where_without_results;
					}

					// Get all (parent and variations, and build where)
					$query = $wpdb->prepare( "
						SELECT ID, post_type, post_parent FROM $wpdb->posts
					    WHERE ID = %d
					    AND post_type IN ('product', 'product_variation')
				    ", $term );

					$search_term_id = $wpdb->get_row( $query );

					if ( empty( $search_term_id ) ) {
						return $where_without_results;
					}

					$search_terms_ids_str = '';

					if ( $search_term_id->post_type == 'product' ) {

						$search_terms_ids_str .= $search_term_id->ID . ',';

						// Has children? add them
						$product = wc_get_product( $search_term_id->ID );

						// return array of the children IDs if applicable
						$children = $product->get_children();

						if ( ! empty( $children ) ) {
							foreach ( $children as $child ) {
								$search_terms_ids_str .= $child . ',';
							}
						}

					}
					// Add parent and current
					else {
						$search_terms_ids_str .= $search_term_id->post_parent . ',';
						$search_terms_ids_str .= $search_term_id->ID . ',';
					}

					$search_terms_ids_str = rtrim( $search_terms_ids_str, ',' );
					$where                = "AND ( $wpdb->posts.ID IN ($search_terms_ids_str) )";

				}
				// Meta relational values
				elseif ( $search_column == '_supplier' ) {

					$term = $wpdb->esc_like( strtolower( $_REQUEST['s'] ) );

					// Get suppliers
					$query = $wpdb->prepare("
						SELECT p.ID FROM $wpdb->posts p
					    WHERE p.post_type = %s AND p.post_title LIKE '%%{$term}%%'
				    ", Suppliers::POST_TYPE );

					$search_supplier_ids = $wpdb->get_col( $query );

					if ( empty( $search_supplier_ids ) ) {
						return $where_without_results;
					}

					$supplier_products = array();

					foreach ($search_supplier_ids as $supplier_id) {
						$supplier_products = array_unique( array_merge( $supplier_products, Suppliers::get_supplier_products($supplier_id) ) );
					}

					if ( empty( $supplier_products ) ) {
						return $where_without_results;
					}

                    $where = "AND ( $wpdb->posts.ID IN ( " . implode( ',', $supplier_products ) . " ))";

                }
				//  # case C and Ds # (post title and other meta fields)
				else {

					$term = $wpdb->esc_like( strtolower( $_REQUEST['s'] ) );

					// title field is not in meta
					if ( $search_column == 'title' ) {

						$query = "
							SELECT ID, post_type, post_parent FROM $wpdb->posts
					        WHERE lower(post_title) LIKE '%{$term}%' 
					        AND post_type IN ('product', 'product_variation')	
				         ";

					}
					elseif ( in_array( $search_column, Globals::SEARCHABLE_COLUMNS['numeric'] ) ) {

						// Not numeric terms
						if ( ! is_numeric( $term ) ) {
							return $where_without_results;
						}

						// WHERE meta_key = $search_column and lower(meta_value) like term%
						$meta_where = apply_filters('atum/list_table/product_search/numeric_meta_where', sprintf("pm.meta_key = '%s' AND pm.meta_value = '%s'", $search_column, $term ), $search_column, $term);
						
						$query = "SELECT DISTINCT p.ID, p.post_type, p.post_parent FROM $wpdb->posts p
						    LEFT JOIN $wpdb->postmeta pm ON (p.ID = pm.post_id)
						    WHERE p.post_type IN ('product', 'product_variation')
						    AND $meta_where
					    ";

					}
					// String fields (_sku ...)
					else {
						
						// WHERE meta_key = $search_column and lower(meta_value) like term%
						$meta_where = apply_filters('atum/list_table/product_search/string_meta_where', sprintf("pm.meta_key = '%s' AND pm.meta_value LIKE '%%%s%%'", $search_column, $term ), $search_column, $term);

						$query = "SELECT p.ID, p.post_type, p.post_parent FROM $wpdb->posts p
						    LEFT JOIN $wpdb->postmeta pm ON (p.ID = pm.post_id)
						    WHERE p.post_type IN ('product', 'product_variation')
						    AND  {$meta_where}
				         ";
					}

					$search_terms_ids = $wpdb->get_results( $query );

					if ( count( $search_terms_ids ) == 0 ) {
						return $where_without_results;
					}

					$search_terms_ids_str = '';

					foreach ( $search_terms_ids as $term_id ) {

						if ( $term_id->post_type == 'product' ) {

							$search_terms_ids_str .= "$term_id->ID,";
							$product              = wc_get_product( $term_id->ID );
							$children             = $product->get_children();

							if ( ! empty( $children ) ) {
								foreach ( $children as $child ) {
									$search_terms_ids_str .= $child . ',';
								}
							}

						}
						// Add parent and current
						else {
							$search_terms_ids_str .= "'$term_id->ID',";
							$search_terms_ids_str .= "'$term_id->post_parent',";
						}

					}

					// removes last ,
					$search_terms_ids_str = rtrim( $search_terms_ids_str, ',' );

					$where = "AND ( $wpdb->posts.ID IN ($search_terms_ids_str) )";
				}

			}

		}

		return $where;

	}

	/**
	 * Handle an incoming ajax request
	 * Called by the \Ajax class
	 *
	 * @since 0.0.1
	 */
	public function ajax_response() {

		$this->prepare_items();
		extract( $this->_args );
		extract( $this->_pagination_args, EXTR_SKIP );

		ob_start();

		if ( ! empty( $_REQUEST['no_placeholder'] ) ) {
			$this->display_rows();
		}
		else {
			$this->display_rows_or_placeholder();
		}

		$rows = ob_get_clean();

		ob_start();
		$this->print_column_headers();
		$headers = ob_get_clean();

		ob_start();
		$this->display_tablenav( 'top' );
		$extra_tablenav_top = ob_get_clean();

		ob_start();
		$this->display_tablenav( 'bottom' );
		$extra_tablenav_bottom = ob_get_clean();

		ob_start();
		$this->views();
		$views = ob_get_clean();

		$response = array(
			'rows'           => $rows,
			'extra_t_n'      => array(
				'top'    => $extra_tablenav_top,
				'bottom' => $extra_tablenav_bottom
			),
			'column_headers' => $headers,
			'views'          => $views
		);

		if ($this->show_totals) {
			ob_start();
			$this->print_column_totals();
			$response['totals'] = ob_get_clean();
		}

		if ( isset( $total_items ) ) {
			$response['total_items_i18n'] = sprintf( _n( '%s item', '%s items', $total_items ), number_format_i18n( $total_items ) );
		}

		if ( isset( $total_pages ) ) {
			$response['total_pages']      = $total_pages;
			$response['total_pages_i18n'] = number_format_i18n( $total_pages );
		}

		wp_send_json( $response );

	}

	/**
	 * Enqueue the required scripts
	 *
	 * @since 0.0.1
	 *
	 * @param string $hook
	 */
	public function enqueue_scripts( $hook ) {


		// jQuery.address
		wp_register_script( 'jquery.address', ATUM_URL . 'assets/js/vendor/jquery.address.min.js', array( 'jquery' ), ATUM_VERSION, TRUE );

		// jquery.floatThead
		wp_register_script( 'jquery.floatThead', ATUM_URL . 'assets/js/vendor/jquery.floatThead.min.js', array( 'jquery' ), ATUM_VERSION, TRUE );
		wp_enqueue_script( 'jquery.floatThead' );

		// jquery.filterbydata
		wp_register_script( 'jquery.filterbydata', ATUM_URL . 'assets/js/vendor/jquery.filterbydata.min.js', array( 'jquery' ), ATUM_VERSION, TRUE );
		wp_enqueue_script( 'jquery.filterbydata' );

		// jScrollPane
		wp_register_script( 'mousewheel', ATUM_URL . 'assets/js/vendor/jquery.mousewheel.min.js', array( 'jquery' ), ATUM_VERSION, TRUE );
		wp_register_script( 'jscrollpane', ATUM_URL . 'assets/js/vendor/jquery.jscrollpane.min.js', array( 'jquery', 'mousewheel' ), ATUM_VERSION, TRUE );

		// Sweet Alert 2
		wp_register_style( 'sweetalert2', ATUM_URL . 'assets/css/vendor/sweetalert2.min.css', array(), ATUM_VERSION );
		wp_register_script( 'sweetalert2', ATUM_URL . 'assets/js/vendor/sweetalert2.min.js', array(), ATUM_VERSION );
		Helpers::maybe_es6_promise();

		if ( wp_script_is('es6-promise', 'registered') ) {
			wp_enqueue_script( 'es6-promise' );
		}

		// jQuery EasyTree
		wp_register_script( 'jquery-easytree', ATUM_URL . 'assets/js/vendor/jquery.easytree.min.js', array('jquery'), ATUM_VERSION, TRUE );

		// jQuery UI datePicker
		if ( isset($this->load_datepicker) && $this->load_datepicker === TRUE ) {
			global $wp_scripts;
			$jquery_version = isset( $wp_scripts->registered['jquery-ui-core']->ver ) ? $wp_scripts->registered['jquery-ui-core']->ver : '1.11.4';
			wp_deregister_style('jquery-ui-style');
			wp_register_style( 'jquery-ui-style', '//code.jquery.com/ui/' . $jquery_version . '/themes/excite-bike/jquery-ui.min.css', array(), $jquery_version );

			wp_enqueue_style('jquery-ui-style');
			wp_enqueue_script('jquery-ui-datepicker');
		}

		// List Table styles
		wp_register_style( 'atum-list', ATUM_URL . 'assets/css/atum-list.css', array('woocommerce_admin_styles', 'sweetalert2'), ATUM_VERSION );
		wp_enqueue_style( 'atum-list' );

		$dependencies = array( 'jquery', 'jquery.address', 'jscrollpane', 'jquery-blockui', 'sweetalert2', 'jquery-easytree' );

		// If it's the first time the user edits the List Table, load the sweetalert to show the popup
		$first_edit_key = ATUM_PREFIX . "first_edit_$hook";
		if ( ! get_user_meta( get_current_user_id(), $first_edit_key, TRUE ) ) {
			$this->first_edit_key = $first_edit_key;
		}

		// List Table script
		$min = !ATUM_DEBUG ? '.min' : '';
		wp_register_script( 'atum-list', ATUM_URL . "assets/js/atum.list$min.js", $dependencies, ATUM_VERSION, TRUE );
		wp_enqueue_script( 'atum-list' );

	}

	/**
	 * Getter for the table_columns property
	 *
	 * @since 1.2.5
	 *
	 * @return array
	 */
	public function get_table_columns() {
		return $this->table_columns;
	}

	/**
	 * Setter for the table_columns property
	 *
	 * @since 1.2.5
	 *
	 * @param array $table_columns
	 */
	public function set_table_columns( $table_columns ) {
		$this->table_columns = $table_columns;
	}

	/**
	 * Getter for the group_members property
	 *
	 * @since 1.2.5
	 *
	 * @return array
	 */
	public function get_group_members() {
		return $this->group_members;
	}

	/**
	 * Setter for the group_members property
	 *
	 * @since 1.2.5
	 *
	 * @param array $group_members
	 */
	public function set_group_members( $group_members ) {
		$this->group_members = $group_members;
	}

	/**
	 * Get all the available children products of the published parent products (Variable and Grouped)
	 *
	 * @since 1.1.1
	 *
	 * @param string $parent_type   The parent product type
	 * @param array  $post_in       Optional. If is a search query, get only the children from the filtered products
	 * @param string $post_type     Optional. The children post type
	 *
	 * @return array|bool
	 */
	protected function get_children( $parent_type, $post_in = array(), $post_type = 'product' ) {

		// Get the published Variables first
        //TODO 1598179 order by menu_order ?
		$parent_args = array(
			'post_type'      => 'product',
			'post_status'    => current_user_can( 'edit_private_products' ) ? ['private', 'publish'] : ['publish'],
			'posts_per_page' => - 1,
			'fields'         => 'ids',
			'orderby'       => 'menu_order',
			'order'       => 'ASC',
			'tax_query'      => array(
				array(
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => $parent_type
				)
			)
		);

		if (! empty($post_in) ) {
			$parent_args['post__in'] = $post_in;
		}

		$parents = new \WP_Query( apply_filters( 'atum/list_table/get_children/parent_args', $parent_args ) );

		if ($parents->found_posts) {

			switch ( $parent_type ) {
				case 'variable':
					$this->container_products['all_variable'] = array_unique( array_merge($this->container_products['all_variable'], $parents->posts) );
			        break;

				case 'grouped':
					$this->container_products['all_grouped'] = array_unique( array_merge($this->container_products['all_grouped'], $parents->posts) );
					break;

				case 'variable-subscription':
					$this->container_products['all_variable_subscription'] = array_unique( array_merge($this->container_products['all_variable_subscription'], $parents->posts) );
					break;
			}

			//TODO 1598179 order by menu_order ?
			$children_args = array(
				'post_type'       => $post_type,
				'post_status'     => current_user_can( 'edit_private_products' ) ? ['private', 'publish'] : ['publish'],
				'posts_per_page'  => - 1,
				'post_parent__in' => $parents->posts,
				//'orderby'         => 'title',
				//'order'           => 'ASC'
                'orderby'       => 'menu_order',
			    'order'       => 'ASC',
			);

			/*
			 * NOTE: we should apply here all the query filters related to individual child products
			 * like the ATUM control switch or the supplier
			 */

			if ($this->show_controlled) {

				$children_args['meta_query'] = array(
					array(
						'key'   => Globals::ATUM_CONTROL_STOCK_KEY,
						'value' => 'yes'
					)
				);

			}
			else {

				$children_args['meta_query'] = array(
					array(
						'relation' => 'OR',
						array(
							'key'     => Globals::ATUM_CONTROL_STOCK_KEY,
							'compare' => 'NOT EXISTS'
						),
						array(
							'key'   => Globals::ATUM_CONTROL_STOCK_KEY,
							'value' => 'no'
						)
					)
				);

			}

			if ( ! empty($this->supplier_variation_products) ) {

				$children_args['meta_query'][] = array(
					'key'   => '_supplier',
					'value' => esc_attr( $_REQUEST['supplier'] ),
					'type'  => 'NUMERIC'
				);

				$children_args['meta_query']['relation'] = 'AND';

			}

			$children = new \WP_Query( apply_filters( 'atum/list_table/get_children/children_args', $children_args ) );

			if ($children->found_posts) {

				$parents_with_child = wp_list_pluck($children->posts, 'post_parent');

				switch ( $parent_type ) {
					case 'variable':

						$this->container_products['variable'] = array_unique( array_merge($this->container_products['variable'], $parents_with_child) );

						// Exclude all those variations with no children from the list
						$this->excluded = array_unique( array_merge( $this->excluded, array_diff( $this->container_products['all_variable'], $this->container_products['variable'] ) ) );
				        break;

					case 'grouped':

						$this->container_products['grouped'] = array_unique( array_merge($this->container_products['grouped'], $parents_with_child) );

						// Exclude all those grouped with no children from the list
						$this->excluded = array_unique( array_merge( $this->excluded, array_diff( $this->container_products['all_grouped'], $this->container_products['grouped'] ) ) );
						break;

					case 'variable-subscription':

						$this->container_products['variable_subscription'] = array_unique( array_merge($this->container_products['variable_subscription'], $parents_with_child) );

						// Exclude all those subscription variations with no children from the list
						$this->excluded = array_unique( array_merge( $this->excluded, array_diff( $this->container_products['all_variable_subscription'], $this->container_products['variable_subscription'] ) ) );
						break;
				}

				$children_ids = wp_list_pluck($children->posts, 'ID');
				$this->children_products = array_merge( $this->children_products, $children_ids);

				return $children_ids;
			}
			else {
				$this->excluded = array_unique( array_merge( $this->excluded, $parents->posts ) );
			}

		}

		return array();

	}

	/**
	 * Get the number of children for a given inheritable product
	 *
	 * @since 1.4.1
	 *
	 * @param \WC_Product   $product
	 * @param string        $product_type
	 *
	 * @return int
	 */
	protected function get_num_children($product, $product_type) {

		$children_count = 0;

		//  The grouped products have the children stored within the _children meta key
		if ($product_type == 'grouped') {

			$children = $product->get_children();

			foreach ($children as $child) {

				$atum_control_status = Helpers::get_atum_control_status($child);
				if (
					($this->show_controlled && $atum_control_status === 'yes') ||
					(!$this->show_controlled && $atum_control_status !== 'yes')
				) {
					$children_count++;
				}
			}

		}
		// For the variable products we can query the database directly to improve the performance
		else {

			global $wpdb;

			if ( $this->show_controlled ) {
				$join  = "$wpdb->posts.ID = $wpdb->postmeta.post_id";
				$where = "$wpdb->postmeta.meta_key = '_atum_manage_stock' AND $wpdb->postmeta.meta_value = 'yes'";
			}
			else {
				$join  = "($wpdb->posts.ID = $wpdb->postmeta.post_id AND $wpdb->postmeta.meta_key = '_atum_manage_stock')";
				$where = "$wpdb->postmeta.post_id IS NULL";
			}

			$sql = $wpdb->prepare( "
				SELECT COUNT(*) FROM $wpdb->posts 
				LEFT JOIN $wpdb->postmeta ON $join
				WHERE $wpdb->posts.post_type = 'product_variation' AND $wpdb->posts.post_parent = %d
				AND $where
			", $product->get_id() );

			$children_count = $wpdb->get_var( $sql );

		}

		return $children_count;

	}

	/**
	 * Get the parent products from a list of product IDs
	 *
	 * @since 1.1.1
	 *
	 * @param array $product_ids  The array of children product IDs
	 *
	 * @return array
	 */
	protected function get_parents ($product_ids) {

		// Filter the parents of the current values
		$parents = array();
		foreach ($product_ids as $product_id) {
			$product = wc_get_product($product_id);

			// For Variations
			if ( is_a($product, '\WC_Product_Variation') ) {
				$parents[] = $product->get_parent_id();
			}
			// For Group Items (these have the grouped ID as post_parent property)
			else {
				$product_post = get_post( $product_id );

				if ($product_post->post_parent) {
					$parents[] = $product_post->post_parent;
				}
			}
		}

		return array_merge( $product_ids, array_unique($parents) );

	}

	/**
	 * Increase the total of the specified column by the specified amount
	 *
	 * @since 1.4.2
	 *
	 * @param string    $column_name
	 * @param int|float $amount
	 */
	protected function increase_total($column_name, $amount) {
		if ( $this->show_totals && isset($this->totalizers[ $column_name ]) && is_numeric($amount) ) {
			$this->totalizers[ $column_name ] += floatval($amount);
		}
	}

	/**
	 * Builds a query string with the active filters
	 *
	 * @since 1.4.3
	 *
	 * @param string $format    Optional. The return format (array or string)
	 *
	 * @return string|array
	 */
	protected function get_filters_query_string($format = 'array') {

		$default_filters = array(
			'paged'        => 1,
			'order'        => 'desc',
			'orderby'      => 'date',
			'view'         => 'all_stock',
			'product_cat'  => '',
			'product_type' => '',
			'supplier'     => '',
			'extra_filter' => '',
			's'            => '',
			'search_column'=> ''
		);

		parse_str($_SERVER['QUERY_STRING'], $query_string);
		$params = array_filter( array_intersect_key($query_string, $default_filters) );

		// The filters with default values should be excluded
		foreach ($params as $param => $value) {
			if ($value == $default_filters[$param]) {
				unset($params[$param]);
			}
		}

		return $format == 'string' ? http_build_query($params) : $params;

	}

	/**
	 * Get columns hidden by default
	 *
	 * @since 1.2.1
	 *
	 * @return array
	 */
	public static function hidden_columns() {
		return apply_filters( 'atum/list_table/default_hidden_columns', static::$default_hidden_columns );
	}

}