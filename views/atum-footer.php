<?php
/**
 * View for the ATUM footer added to some pages
 *
 * @since 1.5.4
 *
 * @var bool   $footer_class
 * @var string $footer_text
 */

?>

<div class="footer-box <?php echo ! $footer_class ? 'no-style' : ''; ?>">
	<div class="footer-atum-content">
		<div class="footer-atum-logo">
			<img src="<?php echo esc_url( ATUM_URL ) ?>assets/images/atum-icon.svg" title="<?php esc_attr_e( 'Visit ATUM Website', ATUM_TEXT_DOMAIN ) ?>" alt="">
			<span>
				<?php echo esc_attr( __( 'ATUM', ATUM_TEXT_DOMAIN ) ) ?>
			</span>
		</div>
		<div class="footer-atum-text">
			<span><?php esc_html_e( 'HELP US TO IMPROVE!', ATUM_TEXT_DOMAIN ) ?></span>
			<?php echo wp_kses_post( $footer_text ) ?>
		</div>
	</div>

	<div class="footer-atum-buttons">
		<a target="_blank" href="https://forum.stockmanagementlabs.com/t/atum-wp-plugin-issues-bugs-discussions" class="btn btn-primary footer-button">
			<?php esc_html_e( 'Get Support', ATUM_TEXT_DOMAIN ); ?>
		</a>
		<a target="_blank" href="https://forum.stockmanagementlabs.com/t/atum-documentation" class="btn btn-success footer-button">
			<?php esc_html_e( 'View Tutorials', ATUM_TEXT_DOMAIN ); ?>
		</a>
	</div>
</div>
