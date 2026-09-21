<?php
/**
 * Plugin settings.
 *
 * @package DeleteDuplicatePosts
 */

namespace DeleteDuplicatePosts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class DDP_Settings {

	public static $options_name = 'delete_duplicate_posts_options_v4';
	public static $options      = null;

	/**
	 * Return default options
	 *
	 * @author   Lars Koudal
	 * @since    v0.0.1
	 * @version  v1.0.0  Friday, July 2nd, 2021.
	 * @access   public static
	 * @return   mixed
	 */
	public static function default_options() {
		$defaults = array(
			'ddp_running'              => 'false',
			'ddp_keep'                 => 'oldest',
			'ddp_deletemode'           => 'trash',
			'ddp_pts'                  => array( 'post', 'page' ),
			'ddp_exclude_ids'          => '',
			'ddp_statusmail_recipient' => '',
			'ddp_statusmail'           => 0,
			'ddp_resultslimit'         => 0,
			'ddp_enabled'              => 0,
			'ddp_cron_mode'            => 'report',
			'ddp_pstati'               => array( 'publish' ),
			'ddp_redirects'            => 0,
			'ddp_redirect_provider'    => 'builtin',
		);
		return $defaults;
	}


	/**
	 * get plugin's options
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Thursday, June 9th, 2022.
	 * @access  public static
	 * @return  mixed
	 */
	public static function get_options() {
		if ( null !== DDP_Settings::$options ) {
			return DDP_Settings::$options;
		}

		$stored = get_option( DDP_Settings::$options_name, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$had_cron_mode = array_key_exists( 'ddp_cron_mode', $stored );
		$options       = array_merge( DDP_Settings::default_options(), $stored );

		// Sites already running automatic deletion keep deleting until they opt into report-only.
		if ( ! empty( $options['ddp_enabled'] ) && ! $had_cron_mode ) {
			$options['ddp_cron_mode'] = 'delete';
		}

		$options = self::normalize_options( $options );

		DDP_Settings::$options = $options;

		return $options;
	}

	/**
	 * Allowed per-run result limits (Settings UI + cron).
	 *
	 * @return int[]
	 */
	public static function allowed_result_limits() {
		return array( 0, 10, 50, 100, 250, 500, 1000, 2500, 5000, 10000 );
	}

	/**
	 * Normalize keep preference used in MIN/MAX SQL.
	 *
	 * @param mixed $value Raw value.
	 * @return string 'oldest' or 'latest'.
	 */
	public static function normalize_keep( $value ) {
		$value = is_string( $value ) ? $value : '';
		return in_array( $value, array( 'oldest', 'latest' ), true ) ? $value : 'oldest';
	}

	/**
	 * Normalize cron result limit.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public static function normalize_resultslimit( $value ) {
		if ( is_string( $value ) && ! is_numeric( trim( $value ) ) ) {
			return 0;
		}
		$limit = (int) $value;
		if ( $limit < 0 ) {
			return 0;
		}
		return in_array( $limit, self::allowed_result_limits(), true ) ? $limit : 0;
	}

	/**
	 * Normalize cron schedule slug against registered schedules.
	 *
	 * @param mixed $value Raw schedule key.
	 * @return string
	 */
	public static function normalize_schedule( $value ) {
		$interval  = is_string( $value ) ? $value : '';
		$schedules = function_exists( 'wp_get_schedules' ) ? wp_get_schedules() : array();
		if ( $interval && is_array( $schedules ) && isset( $schedules[ $interval ] ) ) {
			return $interval;
		}
		return 'hourly';
	}

	/**
	 * Keep only registered post type names.
	 *
	 * @param mixed $types Raw list.
	 * @return string[]
	 */
	public static function normalize_post_types( $types ) {
		if ( ! is_array( $types ) ) {
			$types = array();
		}

		$clean = array();
		foreach ( $types as $type ) {
			if ( ! is_string( $type ) && ! is_numeric( $type ) ) {
				continue;
			}
			$type = sanitize_key( (string) $type );
			if ( '' === $type ) {
				continue;
			}
			if ( function_exists( 'post_type_exists' ) && ! post_type_exists( $type ) ) {
				continue;
			}
			$clean[] = $type;
		}

		$clean = array_values( array_unique( $clean ) );
		return ! empty( $clean ) ? $clean : array( 'post', 'page' );
	}

	/**
	 * Keep only registered post status keys.
	 *
	 * @param mixed $statuses Raw list.
	 * @return string[]
	 */
	public static function normalize_post_statuses( $statuses ) {
		if ( ! is_array( $statuses ) ) {
			$statuses = array();
		}

		$registered = function_exists( 'get_post_stati' ) ? get_post_stati() : array( 'publish' => true );
		if ( ! is_array( $registered ) ) {
			$registered = array( 'publish' => true );
		}

		$clean = array();
		foreach ( $statuses as $status ) {
			if ( ! is_string( $status ) && ! is_numeric( $status ) ) {
				continue;
			}
			$status = sanitize_key( (string) $status );
			if ( '' === $status || ! array_key_exists( $status, $registered ) ) {
				continue;
			}
			$clean[] = $status;
		}

		$clean = array_values( array_unique( $clean ) );
		return ! empty( $clean ) ? $clean : array( 'publish' );
	}

	/**
	 * Normalize compare method; empty meta key falls back to title compare.
	 *
	 * @param mixed  $method   Raw method.
	 * @param string $meta_key Meta key when method is metacompare.
	 * @return string
	 */
	public static function normalize_method( $method, $meta_key = '' ) {
		$method = is_string( $method ) ? $method : '';
		$allowed = array( 'titlecompare', 'metacompare', 'excerptcompare', 'contentcompare' );
		if ( ! in_array( $method, $allowed, true ) ) {
			return 'titlecompare';
		}
		if ( 'metacompare' === $method && '' === trim( (string) $meta_key ) ) {
			return 'titlecompare';
		}
		return $method;
	}

	/**
	 * Normalize deletion mode.
	 *
	 * @param mixed $value Raw value.
	 * @return string 'trash' or 'permanent'.
	 */
	public static function normalize_deletemode( $value ) {
		$value = is_string( $value ) ? $value : '';
		return in_array( $value, array( 'trash', 'permanent' ), true ) ? $value : 'trash';
	}

	/**
	 * Normalize cron mode.
	 *
	 * @param mixed $value Raw value.
	 * @return string 'report' or 'delete'.
	 */
	public static function normalize_cron_mode( $value ) {
		$value = is_string( $value ) ? $value : '';
		return in_array( $value, array( 'report', 'delete' ), true ) ? $value : 'report';
	}

	/**
	 * Normalize redirect storage provider.
	 *
	 * @param mixed $value Raw value.
	 * @return string 'builtin' or 'redirection'.
	 */
	public static function normalize_redirect_provider( $value ) {
		$value = is_string( $value ) ? $value : '';
		return in_array( $value, array( 'builtin', 'redirection' ), true ) ? $value : 'builtin';
	}

	/**
	 * Apply allowlists to a full options array (save + read defense in depth).
	 *
	 * @param array $options Options.
	 * @return array
	 */
	public static function normalize_options( $options ) {
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$options['ddp_keep']         = self::normalize_keep( isset( $options['ddp_keep'] ) ? $options['ddp_keep'] : 'oldest' );
		$options['ddp_resultslimit'] = self::normalize_resultslimit( isset( $options['ddp_resultslimit'] ) ? $options['ddp_resultslimit'] : 0 );
		$options['ddp_schedule']     = self::normalize_schedule( isset( $options['ddp_schedule'] ) ? $options['ddp_schedule'] : 'hourly' );
		$options['ddp_pts']          = self::normalize_post_types( isset( $options['ddp_pts'] ) ? $options['ddp_pts'] : array() );
		$options['ddp_pstati']       = self::normalize_post_statuses( isset( $options['ddp_pstati'] ) ? $options['ddp_pstati'] : array() );
		$options['ddp_cron_mode']    = self::normalize_cron_mode( isset( $options['ddp_cron_mode'] ) ? $options['ddp_cron_mode'] : 'report' );
		$options['ddp_deletemode']   = self::normalize_deletemode( isset( $options['ddp_deletemode'] ) ? $options['ddp_deletemode'] : 'trash' );
		$options['ddp_redirect_provider'] = self::normalize_redirect_provider(
			isset( $options['ddp_redirect_provider'] ) ? $options['ddp_redirect_provider'] : 'builtin'
		);

		$meta_key = isset( $options['ddp_compare_metatag'] ) ? sanitize_text_field( (string) $options['ddp_compare_metatag'] ) : '';
		$options['ddp_compare_metatag'] = $meta_key;
		$options['ddp_method']          = self::normalize_method(
			isset( $options['ddp_method'] ) ? $options['ddp_method'] : 'titlecompare',
			$meta_key
		);

		return $options;
	}


	/**
	 * Saves options
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Thursday, June 9th, 2022.
	 * @access  public static
	 * @param   mixed   $newoptions
	 * @return  mixed
	 */
	public static function save_options( $newoptions ) {
		DDP_Settings::$options = $newoptions;
		return update_option( DDP_Settings::$options_name, $newoptions );
	}


	/**
	 * Parse a comma-, semicolon-, or whitespace-separated list of post IDs.
	 *
	 * @param string|array $raw Raw exclude field value.
	 * @return int[] Unique positive post IDs.
	 */
	public static function parse_exclude_ids( $raw ) {
		if ( is_array( $raw ) ) {
			$parts = $raw;
		} elseif ( is_string( $raw ) && '' !== trim( $raw ) ) {
			$parts = preg_split( '/[\s,;]+/', $raw );
		} else {
			return array();
		}

		if ( ! is_array( $parts ) ) {
			return array();
		}

		$ids = array();
		foreach ( $parts as $part ) {
			$part = is_string( $part ) ? trim( $part ) : $part;
			if ( '' === $part || null === $part || ! is_numeric( $part ) ) {
				continue;
			}
			$id = (int) $part;
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Excluded post IDs from current options (never listed for deletion).
	 *
	 * @return int[]
	 */
	public static function get_exclude_ids() {
		$options = self::get_options();
		$raw     = isset( $options['ddp_exclude_ids'] ) ? $options['ddp_exclude_ids'] : '';
		return self::parse_exclude_ids( $raw );
	}

	/**
	 * Parse a comma- or semicolon-separated list of email addresses.
	 *
	 * @param string $raw Raw recipient field value.
	 * @return string[] Valid, unique email addresses.
	 */
	public static function parse_email_recipients( $raw ) {
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return array();
		}

		$parts = preg_split( '/[,;]+/', $raw );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		$valid = array();
		foreach ( $parts as $part ) {
			$email = sanitize_email( trim( $part ) );
			if ( $email && is_email( $email ) ) {
				$valid[] = $email;
			}
		}

		return array_values( array_unique( $valid ) );
	}


	/**
	 * Fetch plugin version from plugin PHP header
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Thursday, June 9th, 2022.
	 * @access  public static
	 * @return  mixed
	 */
	public static function get_plugin_version() {
		$plugin_data = get_file_data( DDP_PLUGIN_FILE, array( 'version' => 'Version' ), 'plugin' );
		return $plugin_data['version'];
	}


	/**
	 * add_freemius_extra_permission.
	 *
	 * @author  Lars Koudal
	 * @since   v0.0.1
	 * @version v1.0.0  Thursday, June 9th, 2022.
	 * @access  public static
	 * @param   mixed   $permissions
	 * @return  mixed
	 */
	public static function add_freemius_extra_permission( $permissions ) {

		$permissions['helpscout'] = array(
			'icon-class' => 'dashicons dashicons-sos',
			'label'      => 'Help Scout',
			'desc'       => __( 'Rendering Help Scouts beacon for easy help and support', 'delete-duplicate-posts' ),
			'priority'   => 16,
		);

		$permissions['newsletter'] = array(
			'icon-class' => 'dashicons dashicons-email-alt2',
			'label'      => 'Newsletter',
			'desc'       => __( 'Your email is added to cleverplugins.com newsletter. Unsubscribe any time.', 'delete-duplicate-posts' ),
			'priority'   => 18,
		);

		return $permissions;
	}

}
