<?php
/**
 * Missing dependencies notice view - resources/back/views/missing-dependencies-notice.php
 *
 * @package kurve
 */

?>

<div class="error notice">
	<p>
		<?php
			printf(
				wp_kses(
					// translators: Missing dependencies.
					__(
						'<strong>Error:</strong> <mark>Kurve</mark> is dependant of %s. Please activate %s to use Kurve.'
					),
					[
						'strong' => [],
						'mark'   => [],
					]
				),
				esc_html( $missing_plugin_names ),
				esc_html( $missing_plugin_names ),
			);
			?>
	</p>
</div>
