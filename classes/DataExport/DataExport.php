<?php
/**
 * @package         Atum
 * @subpackage      DataExport
 * @author          Be Rebel - https://berebel.io
 * @copyright       ©2018 Stock Management Labs™
 *
 * @since           1.2.5
 *
 * The class resposible to export the ATUM data to downloadable files
 * @uses "mpdf/mpdf"
 */

namespace Atum\DataExport;

defined( 'ABSPATH' ) or die;

use Atum\Inc\Globals;
use Atum\Inc\Helpers;
use Atum\StockCentral\StockCentral;
use Mpdf\Mpdf;


class DataExport {

	/**
	 * The number of columns in the report table
	 * @var int
	 */
	private $number_columns;


	public function __construct() {

		add_action( 'admin_enqueue_scripts', array($this, 'enqueue_scripts') );

		// Ajax action to export the ATUM data to file
		add_action( 'wp_ajax_atum_export_data', array( $this, 'export_data' ) );
		
	}

	/**
	 * Enqueue the required scripts
	 *
	 * @since 1.2.5
	 *
	 * @param string $hook
	 */
	public function enqueue_scripts( $hook ) {

		// Load the script on the "Stock Central" page by default
		$allowed_pages = (array) apply_filters( 'atum/data_export/allowed_pages', ['toplevel_page_' . StockCentral::UI_SLUG, Globals::ATUM_UI_HOOK . '_page_' . StockCentral::UI_SLUG] );
		if ( in_array($hook, $allowed_pages) ) {

			$min = (! ATUM_DEBUG) ? '.min' : '';
			wp_register_script( 'atum-data-export', ATUM_URL . "assets/js/atum.data.export$min.js", array('jquery'), ATUM_VERSION, TRUE );

			ob_start();
			wc_product_dropdown_categories( array(
				'show_count'         => 0,
				'option_select_text' => __( 'Show all categories', ATUM_TEXT_DOMAIN )
			) );
			$product_categories = ob_get_clean();
			$screen = get_current_screen();

			wp_localize_script( 'atum-data-export', 'atumExport', apply_filters( 'atum/data_export/js_settings', array(
				'screen'            => $screen->id,
				'tabTitle'          => __( 'Export Data', ATUM_TEXT_DOMAIN ),
				'submitTitle'       => __( 'Export', ATUM_TEXT_DOMAIN ),
				'outputFormatTitle' => __( 'Output Format', ATUM_TEXT_DOMAIN ),
				'outputFormats'     => array(
					'pdf'  => 'PDF',
					// TODO: ADD MORE OUTPUT FORMATS
					/*'csv'  => 'CSV',
					'xlsx' => 'XLSX'*/
				),
				'productTypesTitle' => __( 'Product Type', ATUM_TEXT_DOMAIN ),
				'productTypes'      => Helpers::product_types_dropdown(),
				'categoriesTitle'   => __('Product Category', ATUM_TEXT_DOMAIN),
				'categories'        => $product_categories,
				'titleLength'       => __('Product Name (number of characters)', ATUM_TEXT_DOMAIN),
				'maxLength'         => 20,
				'disableMaxLength'  => __('Disable', ATUM_TEXT_DOMAIN),
				'exportNonce'       => wp_create_nonce( 'atum-data-export-nonce' )
			), $hook ));

			wp_enqueue_script( 'atum-data-export' );

		}

	}

	/**
	 * Set the reponse header to make the returning file downloadable
	 *
	 * @since 1.2.5
	 *
	 * @param string $filename  The output file name
	 * @param string $type       The file type
	 */
	private function set_file_headers($filename, $type){

		if ( strpos($filename, ".$type") === FALSE ){
			$filename .= ".$type";
		}

		$mime_type = '';
		switch ( $type ) {

			case 'pdf':
				$mime_type = 'application/pdf';
		        break;

			case 'xls':
				$mime_type = 'application/vnd.ms-excel';
				break;

			case 'xslx':
				$mime_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
				break;

			case 'csv':
				$mime_type = 'text/csv';
				break;

		}

		header( 'Content-Description: File Transfer' );
		header( "Content-type: $mime_type" );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );
		header( 'Pragma: public' );

	}

	/**
	 * Export the ATUM data to file
	 *
	 * @since 1.2.5
	 */
	public function export_data() {

		check_ajax_referer( 'atum-data-export-nonce', 'token' );

		$html_report  = $this->generate_html_report( $_GET );
		$report_title = apply_filters( 'atum/data_export/report_title', __( 'ATUM Stock Central Report', ATUM_TEXT_DOMAIN ) );

		// Landscape or Portrait format
		$max_columns = (int) apply_filters('atum/data_export/max_portrait_cols', 12);
		$format = $this->number_columns > $max_columns ? 'A4-L' : 'A4';
		$mpdf = new Mpdf( [ 'mode' => 'utf-8', 'format' => $format ]);


		$mpdf->SetTitle( $report_title );

		// Add the icon fonts to mPDF
		$fontdata = array(
			"dashicons" => array(
				'R' => "../../../../assets/fonts/dashicons.ttf"
			),
			"woocommerce" => array(
				'R' => "../../../../assets/fonts/WooCommerce.ttf"
			)
		);

		foreach ($fontdata as $f => $fs) {
			$mpdf->fontdata[$f] = $fs;

			foreach (['R', 'B', 'I', 'BI'] as $style) {
				if (isset($fs[$style]) && $fs[$style]) {
					$mpdf->available_unifonts[] = $f . trim($style, 'R');
				}
			}
		}

		$mpdf->default_available_fonts = $mpdf->available_unifonts;

		// Set the document header sections
		$header = (array) apply_filters( 'atum/data_export/report_page_header', array(
			'L'    => array(
				'content'     => $report_title,
				'font-size'   => 8,
				'font-style'  => 'I',
				'font-family' => 'serif',
				'color'       => '#666666'
			),
			'C'    => array(
				'content'     => '',
				'font-size'   => 8,
				'font-style'  => 'I',
				'font-family' => 'serif',
				'color'       => '#666666'
			),
			'R'    => array(
				'content'     => '{DATE ' . get_option( 'date_format' ) . '}',
				'font-size'   => 8,
				'font-style'  => 'I',
				'font-family' => 'serif',
				'color'       => '#666666'
			),
			'line' => 0
		) );

		$mpdf->SetHeader($header, 'O');

		// Set the document footer sections
		$footer = (array) apply_filters( 'atum/data_export/report_page_footer', array(
			'L'    => array(
				'content'     => __( 'Report generated by DATA EXPORT, a free ATUM add-on.', ATUM_TEXT_DOMAIN ),
				'font-size'   => 8,
				'font-style'  => 'I',
				'font-family' => 'serif',
				'color'       => '#666666'
			),
			'C'    => array(
				'content'     => '',
				'font-size'   => 8,
				'font-style'  => 'I',
				'font-family' => 'serif',
				'color'       => '#666666'
			),
			'R'    => array(
				'content'     => sprintf( __( 'Page %s of %s', ATUM_TEXT_DOMAIN ), '{PAGENO}', '{nb}' ),
				'font-size'   => 8,
				'font-style'  => 'I',
				'font-family' => 'serif',
				'color'       => '#666666'
			),
			'line' => 0
		) );

		$mpdf->SetFooter($footer, 'O');

		$common_stylesheet = file_get_contents( ABSPATH . 'wp-admin/css/common.css');
		$mpdf->WriteHTML($common_stylesheet, 1);

		$wc_admin_stylesheet = file_get_contents( WC_ABSPATH . 'assets/css/admin.css');
		$mpdf->WriteHTML($wc_admin_stylesheet, 1);

		$atum_stylesheet = file_get_contents( ATUM_PATH . 'assets/css/atum-list.css');
		$mpdf->WriteHTML($atum_stylesheet, 1);

		$mpdf->WriteHTML( $html_report );

		$date_now = date_i18n('Y-m-d');
		echo $mpdf->Output("atum-inventory-report-$date_now.pdf", 'I');

	}

	/**
	 * Generate a full HTML data report
	 *
	 * @since 1.2.5
	 *
	 * @param array $args The export settings array
	 *
	 * @return string   The HTML table report
	 */
	private function generate_html_report( $args = array() ) {

		$report_settings = (array) apply_filters( 'atum/data_export/html_report_settings', array( 'per_page' => -1 ) );
		$args = (array) apply_filters( 'atum/data_export/export_args', $args );

		if ( isset( $args['title_max_length'] ) ) {
			$report_settings['title_max_length'] = $args['title_max_length'];
		}

		if ( isset( $args['screen'] ) ) {
			$report_settings['screen'] = $args['screen'];
		}

		ob_start();

		// Allow using other classes for the report
		$html_report_class = apply_filters( 'atum/data_export/html_report_class', '\Atum\DataExport\Reports\HtmlReport');

		if ( ! class_exists($html_report_class) ) {
			wp_die( __('Report class not found', ATUM_TEXT_DOMAIN) );
		}

		$html_list_table = new $html_report_class( $report_settings );
		$table_columns   = $html_list_table->get_table_columns();
		$group_members   = $html_list_table->get_group_members();
		$primary_column  = $html_list_table->get_primary_column();

		// Hide all the columns that were unchecked in export settings
		foreach ($table_columns as $column_key => $column_title) {

			// The primary column is not hideable
			if ($column_key == $primary_column) {
				continue;
			}

			if ( ! in_array( "$column_key-hide", array_keys($args) ) ) {
				unset( $table_columns[$column_key] );

				// Remove the column from the group (and the group itself if become empty)
				foreach ($group_members as $group_key => $group_data) {

					$key_found = array_search($column_key, $group_data['members']);

					// In some columns like SKU, the column key starts with underscore
					if ($key_found === FALSE) {
						$key_found = array_search("_$column_key", $group_data['members']);
					}

					if ($key_found !== FALSE) {
						 array_splice( $group_members[$group_key]['members'], $key_found, 1);

						// If no columns available for this group, get rid of it
						if ( empty( $group_members[$group_key]['members'] ) ) {
							unset( $group_members[$group_key] );
						}
					}

				}

			}

		}

		$this->number_columns = count($table_columns);
		$html_list_table->set_table_columns($table_columns);
		$html_list_table->set_group_members($group_members);
		$html_list_table->prepare_items();
		$html_list_table->display();

		return ob_get_clean();

	}

}