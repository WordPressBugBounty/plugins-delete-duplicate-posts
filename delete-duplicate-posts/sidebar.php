<?php

namespace DeleteDuplicatePosts;

// this is an include only WP file
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

?>

<div id="sidebar-container">
	<?php

	global $ddp_fs;

	$my_current_user = wp_get_current_user();

	$ddp_deleted_duplicates = get_option( 'ddp_deleted_duplicates' );

	if ( $ddp_deleted_duplicates ) {
		?>
		<div class="sidebarrow">
			<h3>
				<?php
				printf(
					/* translators: %s: Number of deleted posts */
					esc_html__( '%s duplicates deleted!', 'delete-duplicate-posts' ),
					esc_html( number_format_i18n( $ddp_deleted_duplicates ) )
				);
				?>
			</h3>
		</div>
		<?php
	}
	?>

	<?php

	if ( ddp_fs()->is_not_paying() ) {
		// Adds a marketing sections with a link to in-dashboard pricing page.
		echo '<section><h1>Awesome Features</h1>';
		printf( '<a href="%s">Upgrade Now!</a>', esc_url( ddp_fs()->get_upgrade_url() ) );
		echo '</section>';
	}
	?>

	<div class="sidebarrow">
		<p class="warning">
			<?php esc_html_e( 'We recommend you always make a backup before running this tool.', 'delete-duplicate-posts' ); ?>
		</p>
	</div>

	<div class="sidebarrow">
		<h3><?php esc_html_e( 'Our other plugins', 'delete-duplicate-posts' ); ?></h3>
		<a href="https://wpsecurityninja.com" target="_blank" style="float: right;" rel="noopener"><img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'images/security-ninja-logo.png' ); ?>" alt="Visit wpsecurityninja.com" class="logo"></a>

		<strong>WordPress Security made easy</strong>
		<p>Complete WordPress site protection with firewall, malware scanner, scheduled scans, security tests and much more - all you need to keep your website secure. Free trial.</p>

		<p><a href="https://wpsecurityninja.com/" target="_blank" rel="noopener" class="button button-primary">Visit wpsecurityninja.com</a></p>
		<br />
		<a href="https://cleverplugins.com" target="_blank" style="float: right;" rel="noopener"><img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'images/seoboosterlogo.png' ); ?>" alt="Visit cleverplugins.com" class="logo"></a>
		<p>SEO Booster is a powerful tool for anyone serious about SEO. <a href="https://wordpress.org/plugins/seo-booster/" target="_blank" rel="noopener">wordpress.org/plugins/seo-booster/</a><br />
		<p><a href="https://cleverplugins.com/" target="_blank" rel="noopener" class="button button-primary">Visit cleverplugins.com</a></p>

	</div><!-- .sidebarrow -->
	<div class="sidebarrow">
		<h3>Need help?</h3>
		<p>Email support only for pro customers.</p>
		<p>Free users: <a href="https://wordpress.org/support/plugin/delete-duplicate-posts/" target="_blank" rel="noopener"><?php esc_html_e( 'Support Forum on WordPress.org', 'delete-duplicate-posts' ); ?></a></p>
		<form method="post" id="ddp_reactivate">
			<?php wp_nonce_field( 'ddp_reactivate_nonce' ); ?>
			<input class="button button-secondary button-small" type="submit" name="ddp_reactivate" value="<?php esc_html_e( 'Recreate Databases', 'delete-duplicate-posts' ); ?>" />
		</form>
	</div><!-- .sidebarrow -->
</div>