<?php
/**
 * @package         Atum
 * @subpackage      Inc
 * @author          Salva Machí and Jose Piera - https://sispixels.com
 * @copyright       ©2017 Stock Management Labs™
 *
 * @since           0.0.1
 *
 * Initializate the plugin
 */

namespace Atum\Inc;

defined( 'ABSPATH' ) or die;

use Atum\Addons\Addons;
use Atum\Components\HelpPointers;
use Atum\Settings\Settings;
use Atum\StockCentral\StockCentral;


class Main {
	
	/**
	 * The singleton instance holder
	 * @var Main
	 */
	private static $instance;
	
	/**
	 * The Settings page object
	 * @var Settings
	 */
	private $sp_obj;
	
	/**
	 * The Stock central object
	 * @var StockCentral
	 */
	private $sc_obj;

	/**
	 * The Addons object
	 * @var Addons
	 */
	private $ad_obj;

	/**
	 * Singleton constructor
	 *
	 * @since 0.0.1
	 */
	private function __construct() {
		
		if ( is_admin() ) {
			
			// Add the menus
			add_action( 'admin_menu', array( $this, 'add_plugin_menu' ), 1 );
			
			// Load dependencies
			add_action( 'init', array( $this, 'admin_load' ) );
			
			// Check if ATUM has the "Manage Stock" option enabled
			if ( Helpers::get_option( 'manage_stock', 'no' ) == 'yes' ) {
				add_action( 'init', array( $this, 'atum_manage_stock_hooks' ) );
			}
			else {

				// Delete ATUM's transients on saving WC products
				add_action( 'save_post_product', array($this, 'delete_transients') );

				// Add the WC stock management option to grouped products
				add_action( 'init', array( $this, 'wc_manage_stock_hooks' ) );

			}
			
		}

		// Load ATUM add-ons
		add_action( 'setup_theme', array( $this, 'load_addons' ) );
		
		// Save the date when any product goes out of stock
		add_action( 'woocommerce_product_set_stock' , array($this, 'record_out_of_stock_date'), 20 );
		
	}

	/**
	 * Load the ATUM add-ons
	 *
	 * @since 1.1.2
	 */
	public function load_addons () {
		$this->ad_obj = Addons::get_instance();
	}
	
	/**
	 * Load admin plugin dependencies and performs initial checkings
	 *
	 * @since 0.0.3
	 */
	public function admin_load() {
		
		// Delete transients if this is first execution after upgrade
		$db_version = get_option( ATUM_PREFIX . 'version' );
		
		if ( $db_version != ATUM_VERSION ) {
			Helpers::delete_transients();
			update_option( ATUM_PREFIX . 'version', ATUM_VERSION );
		}
		
		// Load language files
		load_plugin_textdomain( ATUM_TEXT_DOMAIN, FALSE, plugin_basename( ATUM_PATH ) . '/languages' );

		// Load dependencies
		Ajax::get_instance();
		$this->sp_obj = Settings::get_instance();
		$this->sc_obj = StockCentral::get_instance();

		// Add the help pointers
		add_action( 'admin_enqueue_scripts', array( $this, 'setup_help_pointers' ) );

		// Add the footer text to ATUM pages
		add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ), 1 );
		
	}
	
	/**
	 * Generate the plugin pages' menus
	 *
	 * @since 0.0.1
	 */
	public function add_plugin_menu() {
		
		// Add the main menu item
		add_menu_page(
			__( 'Stock Central', ATUM_TEXT_DOMAIN ),
			__( 'Stock Central', ATUM_TEXT_DOMAIN ),
			'manage_woocommerce',
			Globals::ATUM_UI_SLUG,
			'',
			'dashicons-chart-area',
			58 // Add the menu just after the WC Products
		);
		
		$menu_items = apply_filters( 'atum/admin/menu_items', array(
			'stock-central'   => array(
				'title'    => __( 'Stock Central', ATUM_TEXT_DOMAIN ),
				'callback' => array( $this->sc_obj, 'display' ),
				'slug'     => Globals::ATUM_UI_SLUG
			),
			'settings'        => array(
				'title'    => __( 'Settings', ATUM_TEXT_DOMAIN ),
				'callback' => array( $this->sp_obj, 'display' ),
				'slug'     => 'settings'
			),
			'addons'  => array(
				'title' => __( 'Add-ons', ATUM_TEXT_DOMAIN ),
				'callback' => array( $this->ad_obj, 'load_addons_page' ),
				'slug'     => 'addons'
			)
		) );
		
		// Build the submenu items
		foreach ( $menu_items as $key => $menu_item ) {
			
			$slug = $menu_item['slug'];
			
			if ( strpos( $slug, ATUM_TEXT_DOMAIN ) === FALSE ) {
				$slug = ATUM_TEXT_DOMAIN . "-$slug";
			}
			
			add_submenu_page(
				Globals::ATUM_UI_SLUG,
				$menu_item['title'],
				$menu_item['title'],
				'manage_woocommerce',
				$slug,
				$menu_item['callback']
			);
		}
		
	}
	
	/**
	 * Add Hooks when Atum "Manage Stock" option is enabled
	 *
	 * @since 0.1.0
	 */
	public function atum_manage_stock_hooks() {
		
		// Disable WooCommerce manage stock option for individual products
		add_action( 'woocommerce_product_options_stock', array( $this, 'disable_manage_stock' ) );
		add_action( 'woocommerce_product_options_stock_fields', array( $this, 'add_manage_stock' ) );
		
		// Disable WooCommerce manage stock option for product variations
		add_action( 'woocommerce_ajax_admin_get_variations_args', array($this, 'disable_variation_manage_stock'));
		
		// Set to yes the WooCommerce _manage_stock meta key for all the supported products
		add_action( 'update_post_metadata', array( $this, 'save_manage_stock' ), 10, 5 );
		
	}

	/**
	 * Add Hooks when WooCommerce is managing the individual products' stock
	 *
	 * @since 1.1.1
	 */
	public function wc_manage_stock_hooks() {

		// Add the WooCommerce manage stock option to grouped products
		add_action( 'woocommerce_product_options_stock_fields', array( $this, 'add_manage_stock' ) );

		// Allow saving the WooCommerce _manage_stock meta key for grouped products
		add_action( 'update_post_metadata', array( $this, 'save_manage_stock' ), 10, 5 );

	}
	
	/**
	 * Disable the WooCommerce "Manage Stock" checkbox for simple products
	 *
	 * @since 0.1.0
	 */
	public function disable_manage_stock() {
		
		// The external products don't have stock and the grouped depends on its own products' stock
		$product_type = wp_get_post_terms( get_the_ID(), 'product_type', array('fields' => 'names') );
		
		if ( ! is_wp_error($product_type) && ! in_array('external', $product_type) ) : ?>
			<script type="text/javascript">
				(function ($) {
					var $manageStockField = $('._manage_stock_field');
					$manageStockField.find('.checkbox').prop({'checked': true, 'readonly': true})
						.siblings('.description').html('<strong>**<?php _e('The stock is currently managed by ATUM plugin', ATUM_TEXT_DOMAIN) ?>**</strong>');

					$manageStockField.children().click(function(e) {
						e.stopImmediatePropagation();
						e.preventDefault();
					});
				})(jQuery);
			</script>
		<?php endif;
		
	}
	
	/**
	 * Disable the WooCommerce "Manage Stock" checkbox for variation products
	 *
	 * @since 1.1.1
	 *
	 * @param array $args
	 * @return array
	 */
	public function disable_variation_manage_stock ($args) {
		
		?>
		<script type="text/javascript">
			(function ($) {
				$('.variable_manage_stock').each(function() {
					$(this).prop({'checked': true, 'readonly': true})
						.siblings('.woocommerce-help-tip').attr('data-tip', '<?php _e('The stock is currently managed by ATUM plugin', ATUM_TEXT_DOMAIN) ?>');

					$(this).click(function(e) {
						e.stopImmediatePropagation();
						e.preventDefault();
					});
				});
			})(jQuery);
		</script>
		<?php
		
		return $args;
	}

	/**
	 * Add the WooCommerce's stock management checkbox to Grouped and External products
	 *
	 * @since 1.1.1
	 */
	public function add_manage_stock () {

		if ( get_post_type() != 'product' ) {
			return;
		}

		$product = wc_get_product();

		// Show the "Manage Stock" checkbox on Grouped products and hide the other stock fields
		if ( $product && is_a($product, '\\WC_Product') ) : ?>
			<script type="text/javascript">
				jQuery('._manage_stock_field').addClass('show_if_grouped show_if_product-part show_if_raw-material');

				<?php // NOTE: The "wp-menu-arrow" is a WP built-in class that adds "display: none!important" so doesn't conflict with WC JS ?>
				jQuery('#product-type').change(function() {
					var productType = jQuery(this).val();
					if (productType === 'grouped' || productType === 'external') {
						jQuery('.stock_fields').addClass('wp-menu-arrow');
					}
					else {
						jQuery('.stock_fields').removeClass('wp-menu-arrow');
					}
				});

				<?php if ( in_array($product->product_type, ['grouped', 'external'] ) ): ?>
				jQuery('.stock_fields').addClass('wp-menu-arrow');
				<?php endif; ?>
			</script>
		<?php endif;

	}
	
	/**
	 * Fires immediately after adding/updating the manage stock metadata
	 *
	 * @since 0.1.0
	 *
	 * @param mixed  $check         ID of updated metadata entry
	 * @param int    $product_id    The product ID
	 * @param string $meta_key      Meta key
	 * @param mixed  $meta_value    Meta value
	 * @param mixed  $prev_value    Previous valus for this meta field
	 *
	 * @return NULL|bool            NULL to continue saving the meta key ($check is always NULL) or any other value to not continue
	 */
	public function save_manage_stock( $check, $product_id, $meta_key, $meta_value, $prev_value ) {
		
		if ( $meta_key == '_manage_stock' && $meta_value == 'no' ) {
			$product = wc_get_product( $product_id );
			
			if ( $product && in_array( $product->product_type, Globals::get_product_types() ) ) {
				remove_action( 'update_post_metadata', array($this, 'save_manage_stock') );

				if ( Helpers::get_option( 'manage_stock', 'no' ) == 'yes' ) {
					$manage_stock = 'yes'; // Always enabled
					Helpers::delete_transients();
				}
				else {
					$manage_stock = ( isset($_POST['_manage_stock']) && $_POST['_manage_stock'] == 'yes' ) ? 'yes' : 'no';
				}

				update_post_meta( $product_id, '_manage_stock', $manage_stock );

				// Do not continue saving this meta key
				return TRUE;
			}
		}

		return $check;
		
	}
	
	/**
	 * Add/Remove the "Out of stock" date when WooCommerce updates the stock of a product
	 *
	 * @since 0.1.3
	 *
	 * @param \WC_Product $product    The product being changed
	 */
	public function record_out_of_stock_date ($product) {
		
		if ( in_array($product->product_type, Globals::get_product_types()) ) {
			
			$current_stock = $product->get_stock_quantity();
			$out_of_stock_date_key = Globals::get_out_of_stock_date_key();
			
			if (!$current_stock) {
				update_post_meta( $product->id, $out_of_stock_date_key, Helpers::date_format( time(), TRUE ) );
				Helpers::delete_transients();
			}
			elseif ( get_post_meta( $product->id, $out_of_stock_date_key, TRUE ) ) {
				// Meta key not needed anymore for this product
				delete_post_meta( $product->id, $out_of_stock_date_key );
				Helpers::delete_transients();
			}
			
		}
		
	}
	
	/**
	 * Delete the ATUM transients after saving a product
	 *
	 * @since 0.1.5
	 *
	 * @param int $product_id   The product ID
	 */
	public function delete_transients($product_id) {
		Helpers::delete_transients();
	}
	
	/**
	 * Setup help pointers for some Atum screens
	 *
	 * @since 0.1.6
	 */
	public function setup_help_pointers() {
		
		$pointers = array(
			array(
				'id'       => Globals::ATUM_UI_SLUG . '-help-tab',      // Unique id for this pointer
				'next'     => 'screen-tab',
				'screen'   => 'toplevel_page_' . Globals::ATUM_UI_SLUG, // This is the page hook we want our pointer to show on
				'target'   => '#contextual-help-link-wrap',             // The css selector for the pointer to be tied to, best to use ID's
				'title'    => __('ATUM Quick Help', ATUM_TEXT_DOMAIN),
				'content'  => __("Click the 'Help' tab to learn more about the ATUM's Stock Central.", ATUM_TEXT_DOMAIN),
				'position' => array(
					'edge'  => 'top',                                   // Top, bottom, left, right
					'align' => 'left'                                   // Top, bottom, left, right, middle
				)
			),
			array(
				'id'       => Globals::ATUM_UI_SLUG . '-screen-tab',
				'screen'   => 'toplevel_page_' . Globals::ATUM_UI_SLUG,
				'target'   => '#screen-options-link-wrap',
				'title'    => __('ATUM Screen Setup', ATUM_TEXT_DOMAIN),
				'content'  => __("Click the 'Screen Options' tab to setup your table view preferences.", ATUM_TEXT_DOMAIN),
				'position' => array(
					'edge'  => 'top',
					'align' => 'left'
				)
			)
		);
		
		// Instantiate the class and pass our pointers array to the constructor
		new HelpPointers( $pointers );
		
	}

	/**
	 * Change the admin footer text on ATUM admin pages
	 *
	 * @since  1.2.0
	 *
	 * @param  string $footer_text
	 * @return string
	 */
	public function admin_footer_text( $footer_text ) {

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$current_screen = get_current_screen();

		if ( isset( $current_screen->id ) && $current_screen->parent_base == Globals::ATUM_UI_SLUG ) {

			// Change the footer text
			if ( ! get_option( 'atum_admin_footer_text_rated' ) ) {

				$footer_text = sprintf( __( 'If you like <strong>ATUM</strong> please leave us a %s&#9733;&#9733;&#9733;&#9733;&#9733;%s rating. A huge thanks in advance!', ATUM_TEXT_DOMAIN ), '<a href="https://wordpress.org/support/plugin/atum-stock-manager-for-woocommerce/reviews/?filter=5#new-post" target="_blank" class="wc-rating-link" data-rated="' . esc_attr__( 'Thanks :)', ATUM_TEXT_DOMAIN ) . '">', '</a>' );
				wc_enqueue_js( "
					jQuery( 'a.wc-rating-link' ).click( function() {
						jQuery.post( '" . WC()->ajax_url() . "', { action: 'atum_rated' } );
						jQuery( this ).parent().text( jQuery( this ).data( 'rated' ) );
					});
				" );

			}
			else {
				$footer_text = __( 'Thank you for trusting in <strong>ATUM</strong> for managing your stock.', ATUM_TEXT_DOMAIN );
			}

		}

		return $footer_text;

	}
	
	
	/****************************
	 * Instance methods
	 ****************************/
	public function __clone() {
		
		// cannot be cloned
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', ATUM_TEXT_DOMAIN ), '1.0.0' );
	}
	
	public function __sleep() {
		
		// cannot be serialized
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', ATUM_TEXT_DOMAIN ), '1.0.0' );
	}
	
	/**
	 * Get Singleton instance
	 *
	 * @return Main instance
	 */
	public static function get_instance() {
		
		if ( ! ( self::$instance && is_a( self::$instance, __CLASS__ ) ) ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
}