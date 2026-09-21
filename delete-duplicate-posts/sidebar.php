<?php

namespace DeleteDuplicatePosts;

// this is an include only WP file
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

?>

<aside id="sidebar-container" aria-label="<?php esc_attr_e( 'Products and updates', 'delete-duplicate-posts' ); ?>">
	<?php

	global $ddp_fs;

	$my_current_user = wp_get_current_user();

	$ddp_deleted_duplicates = get_option( 'ddp_deleted_duplicates' );

	// 1) Current cleanup result / status
	if ( $ddp_deleted_duplicates ) {
		?>
		<div class="sidebarrow ddp-sidebar-status">
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
	// 2) One compact contextual Pro card for free users
	if ( ddp_fs()->is_not_paying() ) {
		?>
		<div class="sidebarrow ddp-sidebar-pro">
			<h3><?php esc_html_e( 'Need deeper matching or URL protection?', 'delete-duplicate-posts' ); ?></h3>
			<p><?php esc_html_e( 'Pro adds content, excerpt, and meta matching, permanent deletion, 301 URL preservation (built-in or Redirection), and CSV export.', 'delete-duplicate-posts' ); ?></p>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( ddp_fs()->get_upgrade_url() ); ?>">
					<?php esc_html_e( 'Compare Free and Pro', 'delete-duplicate-posts' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
	?>

	<?php // 3) Other-product discovery remains visible and comes first. ?>
	<div class="sidebarrow ddp-sidebar-products">
		<a href="<?php echo esc_url( DDP_Links::tracked_url( 'https://wpsecurityninja.com/', 'sidebar-wsn-logo', 'wordpress-plugin', 'security-ninja' ) ); ?>" target="_blank" rel="noopener noreferrer"><img src="<?php echo esc_url( plugin_dir_url( __FILE__ ) . 'images/security-ninja-logo.png' ); ?>" alt="<?php esc_attr_e( 'WP Security Ninja', 'delete-duplicate-posts' ); ?>" class="logo" loading="lazy"></a>

		<h3><?php esc_html_e( 'WP Security Ninja', 'delete-duplicate-posts' ); ?></h3>
		<p class="ddp-wsn-proof"><?php esc_html_e( 'Trusted by 100,000+ WordPress sites.', 'delete-duplicate-posts' ); ?></p>
		<p><?php esc_html_e( 'Firewall, malware scanning, and 50+ security tests in one plugin. Start free. Pro adds a cloud firewall, scheduled scans, and login hardening.', 'delete-duplicate-posts' ); ?></p>

		<p><a href="<?php echo esc_url( DDP_Links::tracked_url( 'https://wpsecurityninja.com/', 'sidebar-wsn-cta', 'wordpress-plugin', 'security-ninja' ) ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary ddp-wsn-cta"><?php esc_html_e( 'Get protected', 'delete-duplicate-posts' ); ?></a></p>
	</div><!-- .sidebarrow -->

	<?php // 4) Newsletter. ?>
	<div class="sidebarrow ddpnewsletter">
		<h3><?php esc_html_e( 'Newsletter', 'delete-duplicate-posts' ); ?></h3>
		<p><?php esc_html_e( 'Get occasional tips and updates from cleverplugins.com. Unsubscribe any time.', 'delete-duplicate-posts' ); ?></p>
		<form class="ddp-newsletter-form" action="https://assets.mailerlite.com/jsonp/16490/forms/106309157552916248/subscribe" method="post" target="_blank">
			<p>
				<label class="screen-reader-text" for="ddp-nl-name"><?php esc_html_e( 'Name', 'delete-duplicate-posts' ); ?></label>
				<input type="text" id="ddp-nl-name" name="fields[name]" placeholder="<?php esc_attr_e( 'Name', 'delete-duplicate-posts' ); ?>" autocomplete="given-name" value="<?php echo esc_attr( $my_current_user->display_name ); ?>">
			</p>
			<p>
				<label class="screen-reader-text" for="ddp-nl-email"><?php esc_html_e( 'Email', 'delete-duplicate-posts' ); ?></label>
				<input type="email" id="ddp-nl-email" name="fields[email]" placeholder="<?php esc_attr_e( 'Email', 'delete-duplicate-posts' ); ?>" autocomplete="email" value="<?php echo esc_attr( $my_current_user->user_email ); ?>" required="required">
			</p>
			<input type="hidden" name="fields[signupsource]" value="PluginInstall">
			<input type="hidden" name="ml-submit" value="1">
			<input type="hidden" name="anticsrf" value="true">
			<p class="ddp-newsletter-consent">
				<label for="ddp-nl-consent">
					<input type="checkbox" id="ddp-nl-consent" name="ddp_nl_consent" value="1" required="required">
					<?php esc_html_e( 'Yes, sign me up for the newsletter. I agree to receive emails and can unsubscribe anytime.', 'delete-duplicate-posts' ); ?>
				</label>
			</p>
			<p>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Subscribe', 'delete-duplicate-posts' ); ?></button>
			</p>
		</form>
		<p class="ppolicy">
			<?php
			printf(
				/* translators: %s: Privacy Policy link, linked text is "Privacy Policy". */
				esc_html__( 'You can unsubscribe anytime. For more details, review our %s.', 'delete-duplicate-posts' ),
				'<a href="https://cleverplugins.com/privacy-policy/" target="_blank" class="privacy-policy" rel="noopener noreferrer">' . esc_html__( 'Privacy Policy', 'delete-duplicate-posts' ) . '</a>'
			);
			?>
		</p>
	</div><!-- .sidebarrow -->
</aside>
