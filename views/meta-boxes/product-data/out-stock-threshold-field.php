<?php
/**
 * Set an individual out of stock threshold on stock managed at product level items
 *
 * @since 1.4.10
 */

defined( 'ABSPATH' ) or die;

use Atum\Inc\Helpers;

if ( Helpers::get_option( 'out_stock_threshold', 'no' ) == 'yes' ): ?>

	<?php if ( empty($variation) ): ?>
    <div id="out_stock_threshold_field" class="options_group <?php echo implode(' ', $out_stock_threshold_classes) ?>">
    <?php endif; ?>

        <p class="form-field _out_stock_threshold_field <?php if ( ! empty($variation) ) echo ' show_if_variation_manage_stock form-row form-row-last' ?>">
            <label for="<?php echo $out_stock_threshold_field_id ?>">
                <?php _e( 'Out of stock threshold', ATUM_TEXT_DOMAIN ) ?>
            </label>

            <span class="atum-field input-group">
                    <?php Helpers::atum_field_input_addon() ?>

                <input type="number" class="short" style="" step="1" name="<?php echo $out_stock_threshold_field_name ?>"
                       id="<?php echo $out_stock_threshold_field_id ?>" value="<?php echo $out_stock_threshold ?>"
		               placeholder="<?php echo $woocommerce_notify_no_stock_amount ?>" data-onload-product-type="<?php echo $product_type ?>">

                <?php echo wc_help_tip( __( "This value will override the global WooComerce's 'Out of stock threshold' for this individual product.", ATUM_TEXT_DOMAIN ) ); ?>
            </span>
        </p>

	<?php if ( empty($variation) ): ?>
	    </div>
	<?php endif; ?>

<?php endif;
