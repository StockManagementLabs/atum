<?php
/**
 * @package         Atum
 * @subpackage      InboundStock
 * @author          Salva Machí and Jose Piera - https://sispixels.com
 * @copyright       ©2017 Stock Management Labs™
 *
 * @since           1.3.0
 */

namespace Atum\InboundStock;

defined( 'ABSPATH' ) or die;

use Atum\Components\AtumListTables\AtumListPage;
use Atum\Components\AtumOrders\AtumOrderPostType;
use Atum\Inc\Globals;
use Atum\Inc\Helpers;
use Atum\PurchaseOrders\PurchaseOrders;
use Atum\Settings\Settings;
use Atum\InboundStock\Inc\ListTable;


class InboundStock extends AtumListPage {
	
	/**
	 * The singleton instance holder
	 * @var InboundStock
	 */
	private static $instance;

	/*
	 * The admin page slug
	 */
	const UI_SLUG = 'atum-inbound-stock';
	
	/**
	 * InboundStock singleton constructor
	 *
	 * @since 1.3.0
	 */
	private function __construct() {
		
		$user_option = get_user_meta( get_current_user_id(), 'products_per_page', TRUE );
		$this->per_page = ( $user_option ) ? $user_option : Helpers::get_option( 'posts_per_page', Settings::DEFAULT_POSTS_PER_PAGE );

		// Initialize on admin page load
		add_action( 'load-' . Globals::ATUM_UI_HOOK . '_page_' . self::UI_SLUG, array( $this, 'screen_options' ) );

		parent::init_hooks();
		
	}
	
	/**
	 * Display the Inbound Stock admin page
	 *
	 * @since 1.3.0
	 */
	public function display() {
		
		parent::display();

		Helpers::load_view( 'inbound-stock', array(
			'list' => $this->list,
			'ajax' => Helpers::get_option( 'enable_ajax_filter', 'yes' ),
		) );
		
	}
	
	/**
	 * Enable Screen options creating the list table before the Screen option panel is rendered and enable "per page" option
	 *
	 * @since 1.3.0
	 */
	public function screen_options() {

		// Add "Products per page" screen option
		$args   = array(
			'label'   => __('Products per page', ATUM_TEXT_DOMAIN),
			'default' => $this->per_page,
			'option'  => 'products_per_page'
		);
		
		add_screen_option( 'per_page', $args );

		// Add the help tab
		$help_tabs = array(
			array(
				'name'  => 'columns',
				'title' => __( 'Columns', ATUM_TEXT_DOMAIN ),
			)
		);

		Helpers::add_help_tab($help_tabs, $this);
		
		$this->list = new ListTable( array( 'per_page' => $this->per_page) );
		
	}

	/**
	 * Display the help tabs' content
	 *
	 * @since 0.0.2
	 *
	 * @param \WP_Screen $screen    The current screen
	 * @param array      $tab       The current help tab
	 */
	public function help_tabs_content( $screen, $tab ) {

		Helpers::load_view( 'help-tabs/inbound-stock/' . $tab['name'] );
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
	 * @return InboundStock instance
	 */
	public static function get_instance() {
		
		if ( ! ( self::$instance && is_a( self::$instance, __CLASS__ ) ) ) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
}