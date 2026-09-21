<?php
/**
 * External URL helpers (UTM allowlist).
 *
 * @package DeleteDuplicatePosts
 */

namespace DeleteDuplicatePosts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds tracked URLs for owned marketing domains only.
 */
class DDP_Links {

	/**
	 * Hosts that may receive UTM parameters.
	 *
	 * @return string[]
	 */
	private static function allowlisted_hosts() {
		return array(
			'cleverplugins.com',
			'www.cleverplugins.com',
			'wpsecurityninja.com',
			'www.wpsecurityninja.com',
		);
	}

	/**
	 * Whether a URL host is allowlisted for UTM tagging.
	 *
	 * @param string $url Absolute URL.
	 * @return bool
	 */
	public static function is_allowlisted( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return false;
		}
		$host = strtolower( $host );
		return in_array( $host, self::allowlisted_hosts(), true );
	}

	/**
	 * Append campaign params to an allowlisted URL; leave others unchanged.
	 *
	 * @param string $url     Absolute URL.
	 * @param string $content utm_content slot (placement id).
	 * @param string $medium  utm_medium (wordpress-plugin|email).
	 * @param string $campaign utm_campaign.
	 * @return string Escaped-ready URL (not yet esc_url'd).
	 */
	public static function tracked_url( $url, $content, $medium = 'wordpress-plugin', $campaign = 'admin-cross-sell' ) {
		$url = (string) $url;
		if ( '' === $url || ! self::is_allowlisted( $url ) ) {
			return $url;
		}

		$args = array(
			'utm_source'   => 'delete-duplicate-posts',
			'utm_medium'   => sanitize_key( $medium ),
			'utm_campaign' => sanitize_title( $campaign ),
			'utm_content'  => sanitize_key( $content ),
		);

		return add_query_arg( $args, $url );
	}

	/**
	 * Escaped tracked URL for HTML attributes.
	 *
	 * @param string $url     Absolute URL.
	 * @param string $content utm_content slot.
	 * @param string $medium  utm_medium.
	 * @param string $campaign utm_campaign.
	 * @return string
	 */
	public static function esc_tracked_url( $url, $content, $medium = 'wordpress-plugin', $campaign = 'admin-cross-sell' ) {
		return esc_url( self::tracked_url( $url, $content, $medium, $campaign ) );
	}
}
