<?php
/**
 * View for the ATUM Dashboard widgets wrapper
 *
 * @since 1.4.0
 */

defined( 'ABSPATH' ) or die;
?>

<div class="atum-widget <?php echo $widget->get_id() ?> grid-stack-item"<?php echo $widget_data ?>>

	<div class="widget-wrapper grid-stack-item-content">
		<div class="widget-header">
			<h2><?php echo $widget->get_title() ?></h2>

			<span class="controls">
				<i class="lnr lnr-cog widget-settings" title="<?php _e('Widget Settings', ATUM_TEXT_DOMAIN) ?>"></i>
				<i class="lnr lnr-cross widget-close" title="<?php _e('Close', ATUM_TEXT_DOMAIN) ?>"></i>
			</span>
		</div>

		<div class="widget-body">
			<?php $widget->render(); ?>
		</div>
	</div>

</div>