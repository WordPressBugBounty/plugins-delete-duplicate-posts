<?php

/*
Plugin Name: Delete Duplicate Posts
Plugin Script: delete-duplicate-posts.php
Plugin URI: https://cleverplugins.com
Description: Remove duplicate blogposts on your blog! Searches and removes duplicate posts and their post meta tags. You can delete posts, pages and other Custom Post Types enabled on your website.
Version: 5.0.2
Author: cleverplugins.com
Author URI: https://cleverplugins.com
Min WP Version: 4.7
Max WP Version: 6.8.2
Text Domain: delete-duplicate-posts
Domain Path: /languages
*/
namespace DeleteDuplicatePosts;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
require_once __DIR__ . '/vendor/autoload.php';
if ( function_exists( '\\DeleteDuplicatePosts\\ddp_fs' ) ) {
    \DeleteDuplicatePosts\ddp_fs()->set_basename( false, __FILE__ );
} else {
    // DO NOT REMOVE THIS IF, IT IS ESSENTIAL FOR THE `function_exists` CALL ABOVE TO PROPERLY WORK.
    if ( !function_exists( '\\DeleteDuplicatePosts\\ddp_fs' ) ) {
        // Create a helper function for easy SDK access.
        function ddp_fs() {
            global $ddp_fs;
            if ( !isset( $ddp_fs ) ) {
                // Activate multisite network integration.
                if ( !defined( 'WP_FS__PRODUCT_925_MULTISITE' ) ) {
                    define( 'WP_FS__PRODUCT_925_MULTISITE', true );
                }
                // Include Freemius SDK.
                // SDK is auto-loaded through composer
                $ddp_fs = fs_dynamic_init( array(
                    'id'             => '925',
                    'slug'           => 'delete-duplicate-posts',
                    'type'           => 'plugin',
                    'public_key'     => 'pk_0af9f9e83f00e23728a55430a57dd',
                    'is_premium'     => false,
                    'premium_suffix' => 'Pro',
                    'has_addons'     => false,
                    'has_paid_plans' => true,
                    'menu'           => array(
                        'slug'       => 'delete-duplicate-posts',
                        'first-path' => 'tools.php?page=delete-duplicate-posts&welcome-message=true',
                        'contact'    => false,
                        'support'    => false,
                        'parent'     => array(
                            'slug' => 'tools.php',
                        ),
                    ),
                    'is_live'        => true,
                ) );
            }
            return $ddp_fs;
        }

        // Init Freemius.
        ddp_fs();
        // Signal that SDK was initiated.
        do_action( 'ddp_fs_loaded' );
    }
    ddp_fs()->add_action( 'after_uninstall', 'ddp_fs_uninstall_cleanup' );
    require plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
}
/**
 * Cleans up when uninstalling
 *
 * @author   Lars Koudal
 * @since    v0.0.1
 * @version  v1.0.0  Tuesday, January 12th, 2021.
 * @return   void
 */
function ddp_fs_uninstall_cleanup() {
    global $wpdb;
    $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %s', $wpdb->prefix . 'ddp_log' ) );
    $wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %s', $wpdb->prefix . 'ddp_redirects' ) );
    delete_option( 'ddp_deleted_duplicates' );
    delete_option( 'delete_duplicate_posts_options_v4' );
}

if ( !class_exists( __NAMESPACE__ . '\\Delete_Duplicate_Posts' ) ) {
    class Delete_Duplicate_Posts {
        public static $options_name = 'delete_duplicate_posts_options_v4';

        public $localization_domain = 'delete-duplicate-posts';

        public static $options = null;

        public function __construct() {
            // Adds extra permissions to Freemius
            if ( function_exists( 'ddp_fs' ) ) {
                ddp_fs()->add_filter( 'permission_list', array(__CLASS__, 'add_freemius_extra_permission') );
            }
            global $ddp_fs;
            add_action(
                'admin_head',
                array(__CLASS__, 'set_custom_help_content'),
                1,
                2
            );
            self::get_options();
            add_action( 'wp_ajax_ddp_get_loglines', array(__CLASS__, 'return_loglines_ajax') );
            add_action( 'wp_ajax_ddp_get_duplicates', array(__CLASS__, 'return_duplicates_ajax') );
            add_action( 'init', array(__CLASS__, 'do_init') );
            add_action( 'wp_ajax_ddp_delete_duplicates', array(__CLASS__, 'delete_duplicates_ajax') );
            // loads admin notices
            add_action( 'admin_menu', array($this, 'admin_menu_link') );
            add_action( 'admin_enqueue_scripts', array($this, 'admin_enqueue_scripts') );
            add_action(
                'wp_insert_site',
                array($this, 'on_create_blog'),
                99999,
                1
            );
            add_filter( 'wpmu_drop_tables', array($this, 'on_delete_blog') );
            register_activation_hook( __FILE__, array($this, 'install') );
            add_action( 'ddp_cron', array($this, 'cleandupes') );
            add_action( 'cron_schedules', array($this, 'add_cron_intervals') );
        }

        /**
         * do_init.
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Monday, October 28th, 2024.
         * @access  public static
         * @return  void
         */
        public static function do_init() {
            global $ddp_fs;
            $determine_locale = determine_locale();
        }

        /**
         * delete_duplicates_ajax.
         *
         * @author  Lars Koudal
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, January 12th, 2021.
         * @version v1.0.1  Tuesday, October 31st, 2023.
         * @version v1.0.2  Wednesday, November 1st, 2023.
         * @version v1.0.3  Tuesday, April 2nd, 2024.
         * @version v1.0.4  Tuesday, May 7th, 2024.
         * @access  public static
         * @param   boolean $return_data    Default: false
         * @return  mixed
         */
        public static function delete_duplicates_ajax( $return_data = false ) {
            // Check user permissions
            if ( !current_user_can( 'manage_options' ) ) {
                wp_send_json_error( __( 'You do not have sufficient permissions to perform this action.', 'delete-duplicate-posts' ) );
                return;
            }
            // Verify the AJAX request, to prevent processing requests external of the site.
            check_ajax_referer( 'cp_ddp_delete_loglines' );
            // Log the cleaning action
            self::log( __( 'Cleaning duplicates', 'delete-duplicate-posts' ) );
            // Validate the POST data
            $checked_posts = $_POST['checked_posts'] ?? null;
            if ( empty( $checked_posts ) || !is_array( $checked_posts ) ) {
                wp_send_json_error( __( 'No duplicates were selected?', 'delete-duplicate-posts' ) );
                return;
            }
            // Process and sanitize the checked posts
            $cleaned_posts = array();
            foreach ( $checked_posts as $cp ) {
                if ( !empty( $cp['ID'] ) && !empty( $cp['orgID'] ) && is_numeric( $cp['ID'] ) && is_numeric( $cp['orgID'] ) ) {
                    $cleaned_posts[] = array(
                        'ID'    => intval( $cp['ID'] ),
                        'orgID' => intval( $cp['orgID'] ),
                    );
                }
            }
            // Check if any valid posts were found
            if ( empty( $cleaned_posts ) ) {
                wp_send_json_error( __( 'Invalid duplicates selected.', 'delete-duplicate-posts' ) );
                return;
            }
            // Attempt to clean duplicates and handle possible failures
            $result = self::cleandupes( true, $cleaned_posts );
            if ( !$result ) {
                $errorMessage = __( 'Error deleting duplicates.', 'delete-duplicate-posts' );
                // Assuming $result is an array or object that could be serialized safely:
                if ( is_array( $result ) || is_object( $result ) ) {
                    $errorData = array(
                        'additional_info' => json_encode( $result ),
                    );
                    wp_send_json_error( $errorMessage, $errorData );
                } elseif ( is_string( $result ) ) {
                    // Sanitize the string to be safe for output
                    $errorData = array(
                        'additional_info' => esc_html( $result ),
                    );
                    wp_send_json_error( $errorMessage, $errorData );
                } else {
                    // If result is not an array, object, or string, or if you want to keep the message generic
                    wp_send_json_error( $errorMessage );
                }
                return;
            }
            // Optionally return success data
            if ( $return_data ) {
                wp_send_json_success( array(
                    'message' => __( 'Duplicates deleted successfully.', 'delete-duplicate-posts' ),
                ) );
            }
        }

        /**
         * Returns log lines via AJAX.
         *
         * @author  Lars Koudal
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, January 12th, 2021.
         * @version v1.0.1  Wednesday, November 1st, 2023.
         * @access  public static
         * @param   boolean $return Default: false
         * @return  void|array
         */
        public static function return_loglines_ajax( $return_data = false ) {
            if ( !current_user_can( 'manage_options' ) ) {
                wp_send_json_error( __( 'You do not have sufficient permissions to perform this action.', 'delete-duplicate-posts' ) );
                return;
            }
            check_ajax_referer( 'cp_ddp_return_loglines' );
            $currstep = ( filter_input( INPUT_POST, 'step', FILTER_SANITIZE_NUMBER_INT ) ?: 0 );
            ++$currstep;
            $json_response = array(
                'step' => $currstep,
            );
            global $wpdb;
            $loglines = $wpdb->get_results( "SELECT datime, note FROM {$wpdb->prefix}ddp_log ORDER BY datime DESC LIMIT 100;" );
            if ( !empty( $loglines ) ) {
                $json_response['results'] = $loglines;
            } else {
                $json_response['msg'] = __( 'Error: Log is empty.. do something :-)', 'delete-duplicate' );
                if ( $return_data ) {
                    return $json_response;
                }
                wp_send_json_error( $json_response );
                return;
                // Make sure to exit here to prevent sending multiple responses.
            }
            if ( $return_data ) {
                return $json_response;
            }
            wp_send_json_success( $json_response );
        }

        /**
         * return_duplicates_ajax.
         *
         * @author  Lars Koudal
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, January 12th, 2021.
         * @version v1.0.1  Wednesday, November 1st, 2023.
         * @access  public static
         * @return  void
         */
        public static function return_duplicates_ajax() {
            check_ajax_referer( 'cp_ddp_return_duplicates', true );
            if ( !current_user_can( 'manage_options' ) ) {
                wp_send_json_error( __( 'You do not have sufficient permissions to perform this action.', 'delete-duplicate-posts' ) );
                return;
            }
            // Get duplicates
            $duplicates = self::return_duplicates( true );
            // Initialize DataTables response array
            $response = array(
                'draw'            => ( isset( $_POST['draw'] ) ? intval( $_POST['draw'] ) : 0 ),
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => array(),
            );
            if ( !empty( $duplicates ) && isset( $duplicates['dupes'] ) ) {
                $response['recordsTotal'] = $duplicates['dupescount'];
                $response['recordsFiltered'] = $duplicates['dupescount'];
                foreach ( $duplicates['dupes'] as $dupe ) {
                    $title = ( '' === $dupe['title'] ? '-empty-' : $dupe['title'] );
                    $orgTitle = ( '' === $dupe['orgtitle'] ? '-empty-' : $dupe['orgtitle'] );
                    $permalink = esc_url( get_permalink( $dupe['ID'] ) );
                    $orgPermalink = esc_url( get_permalink( $dupe['orgID'] ) );
                    $response['data'][] = array(
                        'ID'        => esc_html( $dupe['ID'] ),
                        'orgID'     => esc_html( $dupe['orgID'] ),
                        'foo'       => 'bar',
                        'duplicate' => sprintf(
                            '<a href="%s" target="_blank">%s</a> (ID #%s) <br><small>%s Type: %s Status: %s</small>',
                            esc_url( $permalink ),
                            esc_html( $title ),
                            esc_html( $dupe['ID'] ),
                            esc_html( $dupe['why'] ),
                            esc_html( $dupe['type'] ),
                            esc_html( $dupe['status'] )
                        ),
                        'original'  => sprintf(
                            '<a href="%s" target="_blank">%s</a> (ID #%s)',
                            esc_url( $orgPermalink ),
                            esc_html( $orgTitle ),
                            esc_html( $dupe['orgID'] )
                        ),
                    );
                }
            }
            wp_send_json( $response );
            exit;
        }

        /**
         * Converts a number to relevant unit size
         *
         * @author   Lars Koudal
         * @since    v0.0.1
         * @version  v1.0.0  Thursday, June 24th, 2021.
         * @param    mixed   $size
         * @return   void
         */
        public static function pretty_value( $size ) {
            $unit = array(
                'b',
                'kb',
                'mb',
                'gb',
                'tb',
                'pb'
            );
            $log = log( $size, 1024 );
            $i = floor( $log );
            $num = $size / pow( 1024, $i );
            $calc = round( $num, 2 ) . ' ' . $unit[$i];
            return $calc;
        }

        /**
         * Returns duplicates based on current settings - internal, not used via AJAX
         *
         * @author  Lars Koudal
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Tuesday, January 12th, 2021.
         * @version v1.0.1  Wednesday, November 1st, 2023.
         * @access  public static
         * @param   boolean $return Default: false
         * @return  void
         */
        public static function return_duplicates( $return = false ) {
            self::timerstart( 'return_duplicates' );
            $options = self::get_options();
            $comparemethod = 'titlecompare';
            $return_duplicates_time = false;
            global $ddp_fs;
            if ( isset( $currstep ) ) {
                ++$currstep;
            } else {
                $currstep = 0;
            }
            $json_response = array();
            if ( isset( $currstep ) ) {
                $json_response['step'] = $currstep;
            }
            // @ check compare method - maybe change lookup routine?
            global $wpdb;
            $table_name = $wpdb->prefix . 'posts';
            $resultslimit = $options['ddp_resultslimit'];
            $viewlimit = intval( $resultslimit );
            if ( 0 === $viewlimit ) {
                $viewlimit = 9999;
            }
            $ddp_pts_arr = $options['ddp_pts'];
            if ( isset( $ddp_pts_arr ) && is_array( $ddp_pts_arr ) ) {
                $ddp_pts = '"' . implode( '","', $ddp_pts_arr ) . '"';
            } else {
                $ddp_pts = '';
            }
            $ddp_pts = rtrim( $ddp_pts, ',' );
            $post_stati = '"publish"';
            $order = $options['ddp_keep'];
            // verify default value has been set
            if ( 'oldest' !== $order ) {
                // two choices, if its not the first its the second...
                $options['ddp_keep'] = 'latest';
                $order = 'latest';
            }
            if ( 'oldest' === $order ) {
                $minmax = 'MIN(id)';
            }
            if ( 'latest' === $order ) {
                $minmax = 'MAX(id)';
            }
            $ddpstatuscnt = array();
            $dupescount = 0;
            if ( '' !== $ddp_pts ) {
                $thisquery = false;
                // **** Compare by title ****
                if ( 'titlecompare' === $comparemethod ) {
                    $limit = ( isset( $_POST['length'] ) ? intval( $_POST['length'] ) : 10 );
                    // Default to 10 if not set
                    $offset = ( isset( $_POST['start'] ) ? intval( $_POST['start'] ) : 0 );
                    // Default to 0 if not set
                    if ( !wp_doing_ajax() && $return ) {
                        $limit = $options['ddp_resultslimit'];
                        // for returning results for cron job.
                    }
                    $wpdb->query( 'SET SQL_BIG_SELECTS=1' );
                    $resultsoutput = ' LIMIT ' . intval( $limit ) . ' OFFSET ' . intval( $offset );
                    if ( $options['ddp_debug'] ) {
                        self::log( 'DEBUG: SQL - Setting SET SQL_BIG_SELECTS=1' );
                    }
                    $thisquery = "SELECT * FROM (\n\t\t\t\t\t\t\t\t\t\t\t\t\tSELECT t1.ID, t1.post_title, t1.post_type, t1.post_status, save_this_post_id \n\t\t\t\t\t\t\t\t\t\t\t\t\tFROM {$table_name} AS t1 \n\t\t\t\t\t\t\t\t\t\t\t\t\tINNER JOIN ( \n\t\t\t\t\t\t\t\t\t\t\t\t\t\tSELECT post_title, {$minmax} AS save_this_post_id \n\t\t\t\t\t\t\t\t\t\t\t\t\t\tFROM {$table_name} \n\t\t\t\t\t\t\t\t\t\t\t\t\t\tWHERE post_type IN ( {$ddp_pts} ) \n\t\t\t\t\t\t\t\t\t\t\t\t\t\tAND post_status = 'publish' \n\t\t\t\t\t\t\t\t\t\t\t\t\t\tGROUP BY post_title \n\t\t\t\t\t\t\t\t\t\t\t\t\t\tHAVING COUNT(*) > 1 \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t) AS t2 ON t1.post_title = t2.post_title \n\t\t\t\t\t\t\t\t\t\t\t\t\t\tWHERE t1.post_status = 'publish'\n\t\t\t\t\t\t\t\t\t\t\t\t\t\tORDER BY t1.post_title, t1.post_date DESC\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t) AS derived_table\n\t\t\t\t\t\t\t\t\t\t\t\t\t\tWHERE ID != save_this_post_id\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t{$resultsoutput}";
                    if ( $options['ddp_debug'] ) {
                        self::log( 'DEBUG: SQL ' . esc_attr( $thisquery ) );
                    }
                    $json_response['lookup_query'] = $thisquery;
                    $dupes = $wpdb->get_results( $thisquery, ARRAY_A );
                    // here we get total dupes - not cute, but the other approach not working.
                    $total_dupes_query = "SELECT COUNT(*) FROM (\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tSELECT t1.ID, t1.post_title, t1.post_type, t1.post_status, save_this_post_id \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tFROM {$table_name} AS t1 \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tINNER JOIN ( \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tSELECT post_title, {$minmax} AS save_this_post_id \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tFROM {$table_name} \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tWHERE post_type IN (  {$ddp_pts} ) \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tAND post_type NOT IN ('nav_menu_item') \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tAND post_status IN ( {$post_stati} ) \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tGROUP BY post_title \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tHAVING COUNT(*)>1 \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t) AS t2 \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tON t1.post_title = t2.post_title \n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tAND post_status IN ( {$post_stati} )\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t) AS derived_table\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tWHERE ID != save_this_post_id";
                    $total_dupes = $wpdb->get_var( $total_dupes_query );
                    if ( $options['ddp_debug'] ) {
                        self::log( 'DEBUG: SQL total_dupes_query ' . esc_attr( $total_dupes_query ) );
                    }
                    if ( '' !== $wpdb->last_error ) {
                        $last_error = htmlspecialchars( $wpdb->last_error, ENT_QUOTES );
                        $json_response['lookup_error'] = htmlspecialchars( $wpdb->last_error, ENT_QUOTES );
                        self::log( __( 'Look up error: %1$s %2$s', 'delete-duplicate-posts' ) . $last_error . ' ' . $total_dupes_query );
                    }
                    if ( $dupes ) {
                        $json_response['dupescount'] = $total_dupes;
                        $stepcount = 0;
                        foreach ( $dupes as $dupe ) {
                            $mystatus = $dupe['post_status'];
                            if ( isset( $ddpstatuscnt[$mystatus] ) ) {
                                $ddpstatuscnt[$mystatus] = $ddpstatuscnt[$mystatus] + 1;
                            } else {
                                $ddpstatuscnt[$mystatus] = 1;
                            }
                            // Only save the dupes
                            if ( $dupe['ID'] !== $dupe['save_this_post_id'] ) {
                                $dupedetails = array(
                                    'ID'           => $dupe['ID'],
                                    'permalink'    => get_permalink( $dupe['ID'] ),
                                    'title'        => $dupe['post_title'],
                                    'type'         => $dupe['post_type'],
                                    'orgID'        => $dupe['save_this_post_id'],
                                    'orgtitle'     => $dupe['post_title'],
                                    'orgpermalink' => get_permalink( $dupe['save_this_post_id'] ),
                                    'status'       => $dupe['post_status'],
                                    'why'          => sprintf( __( 'Post ID %1$s has the same title as Post ID %2$s', 'delete-duplicate-posts' ), $dupe['ID'], $dupe['save_this_post_id'] ),
                                );
                                $json_response['dupes'][] = $dupedetails;
                            }
                            ++$stepcount;
                        }
                    }
                }
                $statusdata = '';
                if ( is_array( $ddpstatuscnt ) && count( $ddpstatuscnt ) > 1 ) {
                    $statusdata .= '(';
                    foreach ( $ddpstatuscnt as $key => $dsc ) {
                        $statusdata .= $key . ': ' . number_format_i18n( $dsc ) . ', ';
                    }
                    $statusdata = rtrim( $statusdata, ', ' );
                    $statusdata .= ')';
                }
                $return_duplicates_time = self::timerstop( 'return_duplicates' );
                if ( $options['ddp_debug'] ) {
                    $max = 5;
                    if ( isset( $json_response['dupes'] ) ) {
                        $idlist = array();
                        $step = 0;
                        foreach ( $json_response['dupes'] as $dupe ) {
                            if ( $step <= $max ) {
                                $details = '';
                                if ( isset( $dupe['ID'] ) ) {
                                    $details .= 'ID: ' . $dupe['ID'] . ' ';
                                }
                                if ( isset( $dupe['title'] ) ) {
                                    $details .= ' title: "' . $dupe['title'] . '" ';
                                }
                                if ( isset( $dupe['permalink'] ) ) {
                                    $details .= 'Permalink: ' . $dupe['permalink'] . ' ';
                                }
                                if ( isset( $dupe['status'] ) ) {
                                    $details .= 'Status: ' . $dupe['status'] . ' ';
                                }
                                if ( isset( $dupe['type'] ) ) {
                                    $details .= 'Type: ' . $dupe['type'] . ' ';
                                }
                                if ( isset( $dupe['orgID'] ) ) {
                                    $details .= 'orgID: ' . $dupe['orgID'] . ' ';
                                }
                                if ( isset( $dupe['orgtitle'] ) ) {
                                    $details .= 'orgtitle: ' . $dupe['orgtitle'] . ' ';
                                }
                                if ( isset( $dupe['orgpermalink'] ) ) {
                                    $details .= ' orgpermalink: ' . $dupe['orgpermalink'] . ' ';
                                }
                                self::log( $details );
                            }
                            ++$step;
                        }
                    }
                }
                if ( isset( $json_response['dupes'] ) ) {
                    self::log(
                        __( 'Duplicates found: %1$d, Time: %2$s sec. %3$s Mem usage: %4$s', 'delete-duplicate-posts' ),
                        count( $json_response['dupes'] ),
                        $return_duplicates_time,
                        $statusdata,
                        self::pretty_value( memory_get_peak_usage( true ) )
                    );
                }
            } else {
                $json_response['msg'] = __( 'Error: Choose post types to check.', 'delete-duplicate-posts' );
                $return_duplicates_time = self::timerstop( 'return_duplicates' );
                $json_response['time'] = $return_duplicates_time . ' sec';
                if ( $return ) {
                    return $json_response;
                }
                wp_send_json_error( $json_response );
            }
            if ( !$return_duplicates_time ) {
                $return_duplicates_time = self::timerstop( 'return_duplicates' );
            }
            if ( isset( $json_response['dupescount'] ) ) {
                $json_response['msg'] = sprintf( __( 'Duplicates found: %1$s. Time: %2$s sec.', 'delete-duplicate-posts' ), number_format_i18n( $json_response['dupescount'] ), esc_html( $return_duplicates_time ) );
            }
            if ( $return ) {
                return $json_response;
            }
            wp_send_json_success( $json_response );
        }

        /**
         * create_redirect.
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Friday, July 2nd, 2021.
         * @version v1.0.1  Tuesday, October 18th, 2022.
         * @access  public static
         * @param   mixed   $inurl
         * @param   mixed   $targeturl
         * @param   integer $code       Default: 301
         * @return  void
         */
        public static function create_redirect( $inurl, $targeturl, $code = 301 ) {
            global $wpdb, $ddp_fs;
        }

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
                'ddp_limit'                => 50,
                'ddp_pts'                  => array('post', 'page'),
                'ddp_statusmail_recipient' => '',
                'ddp_statusmail'           => 0,
                'ddp_resultslimit'         => 0,
                'ddp_enabled'              => 0,
                'ddp_pstati'               => array('publish'),
                'ddp_debug'                => 0,
                'ddp_redirects'            => 0,
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
            if ( null !== self::$options ) {
                return self::$options;
            }
            $options = get_option( self::$options_name, array() );
            if ( !is_array( $options ) ) {
                $options = array();
            }
            $options = array_merge( self::default_options(), $options );
            return $options;
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
            $plugin_data = get_file_data( __FILE__, array(
                'version' => 'Version',
            ), 'plugin' );
            return $plugin_data['version'];
        }

        /**
         * timerstart.
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, June 9th, 2022.
         * @access  public static
         * @param   mixed   $watchname
         * @return  void
         */
        public static function timerstart( $watchname ) {
            set_transient( 'ddp_' . $watchname, microtime( true ), 60 * 60 * 1 );
        }

        /**
         * timerstop.
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, June 9th, 2022.
         * @access  public static
         * @param   mixed   $watchname
         * @param   integer $digits     Default: 3
         * @return  mixed
         */
        public static function timerstop( $watchname, $digits = 3 ) {
            $return = round( microtime( true ) - get_transient( 'ddp_' . $watchname ), $digits );
            delete_transient( 'ddp_' . $watchname );
            return $return;
        }

        /**
         * Clean duplicates - not AJAX version
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, June 9th, 2022.
         * @access  public static
         * @param   boolean $manualrun  Default: false
         * @param   mixed   $to_delete  Default: array()
         * @return  void
         */
        public static function cleandupes( $manualrun = false, $to_delete = array() ) {
            global $wpdb, $ddp_fs;
            self::timerstart( 'ddp_totaltime' );
            // start total timer
            $options = self::get_options();
            $options['ddp_running'] = true;
            self::save_options( $options );
            if ( !$manualrun ) {
                self::log( __( 'Automatic CRON job running.', 'delete-duplicate-posts' ) );
            } else {
                self::log( __( 'Manually cleaning.', 'delete-duplicate-posts' ) );
            }
            // what to do with a manual run - no notices
            if ( count( $to_delete ) > 0 ) {
                $lookup_arr = array();
                foreach ( $to_delete as $td ) {
                    $new_item = array();
                    $new_item['ID'] = $td['ID'];
                    $new_item['orgID'] = $td['orgID'];
                    $new_item['type'] = get_post_type( $td['ID'] );
                    $new_item['title'] = get_the_title( $td['ID'] );
                    $lookup_arr['dupes'][] = $new_item;
                }
                $dupes = $lookup_arr;
            } else {
                $dupes = self::return_duplicates( true );
            }
            $resultnote = '';
            $dispcount = 0;
            if ( isset( $dupes['dupes'] ) ) {
                foreach ( $dupes['dupes'] as $dupe ) {
                    $postid = $dupe['ID'];
                    $title = substr( $dupe['title'], 0, 35 );
                    if ( $postid ) {
                        self::timerstart( 'deletepost_' . $postid );
                        /* @todo - implement a premium option to permanently delete or just use the WP setting.
                        
                        																							Options: Use WP setting (default), Delete Permantently, Trash posts (if enabled in WP)
                        
                        																							*/
                        $deleteresult = wp_trash_post( $postid );
                        $timespent = self::timerstop( 'deletepost_' . $postid );
                        ++$dispcount;
                        $totaldeleted = get_option( 'ddp_deleted_duplicates' );
                        if ( false !== $totaldeleted ) {
                            ++$totaldeleted;
                            update_option( 'ddp_deleted_duplicates', $totaldeleted, false );
                        } else {
                            update_option( 'ddp_deleted_duplicates', 1, false );
                        }
                        if ( $options['ddp_debug'] ) {
                            // translators: Debug notice. 1: type of duplicate. 2: The title of the post. 3: The ID. 4: Time spent deleting.
                            self::log( sprintf(
                                __( 'DEBUG: Deleted %1$s %2$s (id: %3$s) in %4$s sec.', 'delete-duplicate-posts' ),
                                $dupe['type'],
                                $title,
                                $postid,
                                $timespent
                            ) );
                        }
                    }
                }
            }
            $totaltimespent = self::timerstop( 'ddp_totaltime' );
            self::log( sprintf( __( 'A total of %1$s duplicate posts were deleted in %2$s sec.', 'delete-duplicate-posts' ), $dispcount, $totaltimespent ) );
            $json_response = array(
                'totaltimespent' => $totaltimespent,
                'deleted'        => $dispcount,
            );
            // Mail logic...
            if ( 0 < $dispcount && $options['ddp_statusmail'] ) {
                $blogurl = esc_url( site_url() );
                $recipient = sanitize_email( $options['ddp_statusmail_recipient'] );
                // translators: 1: Number of deleted posts, 2: Blog URL.
                $messagebody = sprintf( __( 'Hi Admin, I have deleted <strong>%1$d</strong> duplicated posts on your blog, %2$s.', 'delete-duplicate-posts' ), $dispcount, $blogurl );
                $messagebody .= '<br><br>' . esc_html__( 'You are receiving this e-mail because you have turned on e-mail notifications by the plugin', 'delete-duplicate-posts' );
                $messagebody .= ' <a href="https://cleverplugins.com/delete-duplicate-posts/" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Delete Duplicate Posts', 'delete-duplicate-posts' ) . '</a>';
                $messagebody .= '<br><br>' . esc_html__( 'Made by', 'delete-duplicate-posts' ) . " <a href='https://cleverplugins.com' target='_blank' rel='noopener noreferrer'>" . esc_html__( 'cleverplugins.com', 'delete-duplicate-posts' ) . '</a>';
                $mailstatus = false;
                if ( is_email( $recipient ) ) {
                    $subject = __( 'Deleted Duplicate Posts Status', 'delete-duplicate-posts' );
                    $mailstatus = wp_mail( $recipient, $subject, wp_kses_post( $messagebody ) );
                    if ( $options['ddp_debug'] ) {
                        // translators: %s: Email recipient.
                        self::log( sprintf( __( 'DEBUG: Sending email to: %s', 'delete-duplicate-posts' ), $recipient ) );
                    }
                    if ( $mailstatus ) {
                        // translators: %s: Email recipient.
                        self::log( sprintf( __( 'Status email sent to %s.', 'delete-duplicate-posts' ), $recipient ) );
                    }
                } else {
                    // translators: %s: Email address.
                    self::log( sprintf( __( 'Not a valid email %s.', 'delete-duplicate-posts' ), $recipient ) );
                }
            }
            $options['ddp_running'] = false;
            self::save_options( $options );
            // Lets return a response
            if ( 0 === $manualrun && !wp_doing_ajax() ) {
                $json_response = array(
                    'msg' => sprintf( esc_html__( 'A total of %s duplicates were deleted.', 'delete-duplicate-posts' ), intval( $dispcount ) ),
                );
            }
            wp_send_json_success( $json_response );
        }

        /**
         * add_cron_intervals.
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, June 9th, 2022.
         * @access  public static
         * @param   mixed   $schedules
         * @return  mixed
         */
        public static function add_cron_intervals( $schedules ) {
            $schedules['5min'] = array(
                'interval' => 300,
                'display'  => __( 'Every 5 minutes', 'delete-duplicate-posts' ),
            );
            $schedules['10min'] = array(
                'interval' => 600,
                'display'  => __( 'Every 10 minutes', 'delete-duplicate-posts' ),
            );
            $schedules['15min'] = array(
                'interval' => 900,
                'display'  => __( 'Every 15 minutes', 'delete-duplicate-posts' ),
            );
            $schedules['30min'] = array(
                'interval' => 1800,
                'display'  => __( 'Every 30 minutes', 'delete-duplicate-posts' ),
            );
            return $schedules;
        }

        /**
         * Log a notification to the database
         *
         * @author   Lars Koudal
         * @since    v0.0.1
         * @version  v1.0.0  Monday, January 11th, 2021.
         * @access   public static
         * @param    mixed   $text
         * @return   void
         */
        public static function log( $text ) {
            global $wpdb;
            $ddp_logtable = $wpdb->prefix . 'ddp_log';
            // Insert log entry
            $insert_result = $wpdb->insert( $ddp_logtable, array(
                'datime' => current_time( 'mysql' ),
                'note'   => $text,
            ), array('%s', '%s') );
            if ( false === $insert_result ) {
                // Handle error appropriately (e.g., log or throw exception)
                return;
            }
            // Efficiently check if row count exceeds 1000 and delete old entries
            $row_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$ddp_logtable};" );
            if ( $row_count > 1000 ) {
                $wpdb->query( "DELETE FROM {$ddp_logtable} WHERE id NOT IN (\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tSELECT id FROM (\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tSELECT id FROM {$ddp_logtable} ORDER BY datime DESC LIMIT 500\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t) AS sub\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t)" );
            }
        }

        /**
         * Enqueues scripts and styles
         *
         * @author   Lars Koudal
         * @since    v0.0.1
         * @version  v1.0.0  Monday, January 11th, 2021.
         * @access   public static
         * @return   void
         */
        public static function admin_enqueue_scripts() {
            $screen = get_current_screen();
            if ( is_object( $screen ) && 'tools_page_delete-duplicate-posts' === $screen->id ) {
                $pluginver = self::get_plugin_version();
                wp_enqueue_script( 'jquery' );
                wp_enqueue_style(
                    'delete-duplicate-posts',
                    plugins_url( '/css/delete-duplicate-posts-min.css', __FILE__ ),
                    array(),
                    $pluginver
                );
                wp_enqueue_script(
                    'dataTables',
                    // Unique handle for your script
                    plugin_dir_url( __FILE__ ) . 'js/DataTables/datatables.min.js',
                    // Path to your script file
                    array('jquery'),
                    // Dependencies, if any. This script depends on jQuery
                    $pluginver,
                    array(
                        'in_footer' => true,
                    )
                );
                wp_enqueue_style(
                    'dataTables',
                    plugins_url( '/js/DataTables/datatables.min.css', __FILE__ ),
                    array(),
                    $pluginver
                );
                wp_register_script(
                    'delete-duplicate-posts',
                    plugins_url( '/js/delete-duplicate-posts.js', __FILE__ ),
                    array('jquery', 'dataTables'),
                    $pluginver,
                    true
                );
                $js_vars = array(
                    'nonce'                  => wp_create_nonce( 'cp_ddp_return_duplicates' ),
                    'loglines_nonce'         => wp_create_nonce( 'cp_ddp_return_loglines' ),
                    'deletedupes_nonce'      => wp_create_nonce( 'cp_ddp_delete_loglines' ),
                    'text_areyousure'        => __( 'Are you sure you want to delete duplicates? There is no undo feature.', 'delete-duplicate-posts' ),
                    'text_selectsomething'   => __( 'You have to select which duplicates to delete. Tip: You can click the top or bottom checkbox to select all.', 'delete-duplicate-posts' ),
                    'fromUrlTitle'           => __( 'From URL', 'delete-duplicate-posts' ),
                    'targetUrlTitle'         => __( 'Target URL', 'delete-duplicate-posts' ),
                    'refreshingText'         => __( 'Refreshing...', 'delete-duplicate-posts' ),
                    'refreshText'            => __( 'Refresh', 'delete-duplicate-posts' ),
                    'errorDetailsText'       => __( 'Error details: ', 'delete-duplicate-posts' ),
                    'redirectsErrorText'     => __( 'Redirects DataTables error occurred. ', 'delete-duplicate-posts' ),
                    'processingMessage'      => __( 'Looking for duplicates', 'delete-duplicate-posts' ),
                    'requestTimeText'        => __( 'Request: ', 'delete-duplicate-posts' ),
                    'failedToLoadDataText'   => __( 'Failed to load data. ', 'delete-duplicate-posts' ),
                    'duplicateTitle'         => __( 'Duplicate', 'delete-duplicate-posts' ),
                    'originalTitle'          => __( 'Original', 'delete-duplicate-posts' ),
                    'selectRowAlert'         => __( 'Please select at least one row to delete.', 'delete-duplicate-posts' ),
                    'serverResponseText'     => __( 'Response from the server: ', 'delete-duplicate-posts' ),
                    'errorOccurredText'      => __( 'An error occurred: ', 'delete-duplicate-posts' ),
                    'deleteSelectedText'     => __( 'Delete Selected', 'delete-duplicate-posts' ),
                    'selectVisibleText'      => __( 'Select Visible', 'delete-duplicate-posts' ),
                    'selectNoneText'         => __( 'Select None', 'delete-duplicate-posts' ),
                    'somethingWentWrongText' => __( 'Something went wrong.', 'delete-duplicate-posts' ),
                );
                wp_localize_script( 'delete-duplicate-posts', 'cp_ddp', $js_vars );
                wp_enqueue_script( 'delete-duplicate-posts' );
            }
        }

        /**
         * Create plugin tables
         *
         * @author  Lars Koudal
         * @author  Unknown
         * @since   v0.0.1
         * @version v1.0.0  Monday, January 11th, 2021.
         * @version v1.0.1  Sunday, July 17th, 2022.
         * @version v1.0.2  Sunday, December 3rd, 2023.
         * @access  public static
         * @return  void
         */
        public static function create_table() {
            global $wpdb, $ddp_fs;
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $log_table_name = $wpdb->prefix . 'ddp_log';
            $sql_log = "CREATE TABLE {$log_table_name} (\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tid bigint(20) NOT NULL AUTO_INCREMENT,\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tdatime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tnote tinytext NOT NULL,\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tPRIMARY KEY  (id)\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            dbDelta( $sql_log );
            $redirects_table_name = $wpdb->prefix . 'ddp_redirects';
            $sql_redirects = "CREATE TABLE {$redirects_table_name} (\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tid bigint(20) NOT NULL AUTO_INCREMENT,\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tdatime timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tinurl varchar(1024) NOT NULL,\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\ttargeturl varchar(1024) NOT NULL,\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\thttpcode varchar(3) DEFAULT NULL,\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\tPRIMARY KEY  (id)\n\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t\t) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
            dbDelta( $sql_redirects );
            // Additional plugin setup
            $options = self::get_options();
            self::save_options( $options );
            wp_clear_scheduled_hook( 'ddp_cron' );
            self::log( __( 'Plugin activated.', 'delete-duplicate-posts' ) );
        }

        /**
         * Install routines - create database and default options
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, June 9th, 2022.
         * @access  public static
         * @param   mixed   $network_wide
         * @return  void
         */
        public static function install( $network_wide ) {
            global $wpdb;
            require_once ABSPATH . '/wp-admin/includes/upgrade.php';
            if ( is_multisite() && $network_wide ) {
                // Get all blogs in the network and activate plugin on each one
                $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
                foreach ( $blog_ids as $blog_id ) {
                    switch_to_blog( $blog_id );
                    self::create_table();
                    restore_current_blog();
                }
            } else {
                self::create_table();
            }
        }

        /**
         * Creating table when a new blog is created
         * https://sudarmuthu.com/blog/how-to-properly-create-tables-in-wordpress-multisite-plugins/
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, June 9th, 2022.
         * @version v1.0.1  Sunday, May 14th, 2023.
         * @access  public static
         * @param   mixed   $new_site
         * @return  void
         */
        public static function on_create_blog( $new_site ) {
            if ( is_plugin_active_for_network( 'delete-duplicate-posts/delete-duplicate-posts.php' ) ) {
                switch_to_blog( $new_site->blog_id );
                self::create_table();
                restore_current_blog();
            }
        }

        /**
         * Deleting the table whenever a blog is deleted
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, June 9th, 2022.
         * @access  public static
         * @param   mixed   $tables
         * @return  mixed
         */
        public static function on_delete_blog( $tables ) {
            global $wpdb, $ddp_fs;
            $tables[] = $wpdb->prefix . 'ddp_log';
            return $tables;
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
            return update_option( 'delete_duplicate_posts_options_v4', $newoptions );
        }

        /**
         * Adds link to menu under Tools
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, June 9th, 2022.
         * @access  public static
         * @return  void
         */
        public static function admin_menu_link() {
            // only for admins
            if ( !current_user_can( 'manage_options' ) ) {
                return;
            }
            add_management_page(
                'Delete Duplicate Posts',
                'Delete Duplicate Posts',
                'edit_posts',
                'delete-duplicate-posts',
                array(__NAMESPACE__ . '\\Delete_Duplicate_Posts', 'admin_options_page'),
                41
            );
            add_filter(
                'plugin_action_links_' . plugin_basename( __FILE__ ),
                array(__NAMESPACE__ . '\\Delete_Duplicate_Posts', 'filter_plugin_actions'),
                10,
                2
            );
        }

        /**
         * filter_plugin_actions.
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, June 9th, 2022.
         * @access  public static
         * @param   mixed   $links
         * @param   mixed   $file
         * @return  mixed
         */
        public static function filter_plugin_actions( $links, $file ) {
            $settings_link = '<a href="tools.php?page=delete-duplicate-posts">' . __( 'Settings', 'delete-duplicate-posts' ) . '</a>';
            array_unshift( $links, $settings_link );
            // before other links
            return $links;
        }

        /**
         * Adds help content to plugin page
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, June 9th, 2022.
         * @access  public static
         * @return  void
         */
        public static function set_custom_help_content() {
            $screen = get_current_screen();
            if ( 'tools_page_delete-duplicate-posts' === $screen->id ) {
                $screen->add_help_tab( array(
                    'id'      => 'ddp_help',
                    'title'   => __( 'Usage and FAQ', 'delete-duplicate-posts' ),
                    'content' => '<h4>' . __( 'What does this plugin do?', 'delete-duplicate-posts' ) . '</h4><p>' . __( 'Helps you clean duplicate posts from your blog. The plugin checks for blogposts on your blog with the same title.', 'delete-duplicate-posts' ) . '</p><p>' . __( "It can run automatically via WordPress's own internal CRON-system, or you can run it automatically.", 'delete-duplicate-posts' ) . '</p><p>' . __( 'It also has a nice feature that can send you an e-mail when Delete Duplicate Posts finds and deletes something (if you have turned on the CRON feature).', 'delete-duplicate-posts' ) . '</p><h4>' . __( 'Help! Something was deleted that was not supposed to be deleted!', 'delete-duplicate-posts' ) . '</h4><p>' . __( 'I am sorry for that, I can only recommend you restore the database you took just before you ran this plugin.', 'delete-duplicate-posts' ) . '</p><p>' . __( 'If you run this plugin, manually or automatically, it is at your OWN risk!', 'delete-duplicate-posts' ) . '</p><p>' . __( 'We have done our best to avoid deleting something that should not be deleted, but if it happens, there is nothing we can do to help you.', 'delete-duplicate-posts' ) . "</p><p><a href='https://cleverplugins.com' target='_blank'>cleverplugins.com</a>.</p>",
                ) );
            }
        }

        /**
         * admin_options_page.
         *
         * @author  Lars Koudal
         * @since   v0.0.1
         * @version v1.0.0  Thursday, June 9th, 2022.
         * @version v1.0.1  Thursday, June 9th, 2022.
         * @access  public static
         * @return  void
         */
        public static function admin_options_page() {
            global $ddp_fs, $wpdb;
            // DELETE NOW
            if ( isset( $_POST['deleteduplicateposts_delete'] ) && isset( $_POST['_wpnonce'] ) ) {
                if ( wp_verify_nonce( $_POST['_wpnonce'], 'ddp-clean-now' ) ) {
                    self::cleandupes( 1 );
                    // use the value 1 to indicate it is being run manually.
                }
            }
            // RUN NOW!!
            if ( isset( $_POST['ddp_runnow'] ) ) {
                if ( !wp_verify_nonce( $_POST['_wpnonce'], 'ddp-update-options' ) ) {
                    die( esc_html( __( 'Whoops! Some error occured, try again, please!', 'delete-duplicate-posts' ) ) );
                }
            }
            // SAVING OPTIONS
            if ( isset( $_POST['delete_duplicate_posts_save'] ) ) {
                if ( !wp_verify_nonce( $_POST['_wpnonce'], 'ddp-update-options' ) ) {
                    die( esc_html( __( 'Whoops! There was a problem with the data you posted. Please go back and try again.', 'delete-duplicate-posts' ) ) );
                }
                $posttypes = array();
                if ( isset( $_POST['ddp_pts'] ) ) {
                    $option_array = $_POST['ddp_pts'];
                    $option_count = count( $option_array );
                    for ($i = 0; $i < $option_count; $i++) {
                        $posttypes[] = sanitize_text_field( $option_array[$i] );
                    }
                }
                if ( isset( $_POST['ddp_enabled'] ) ) {
                    $options['ddp_enabled'] = ( 'on' === $_POST['ddp_enabled'] ? true : false );
                } else {
                    $options['ddp_enabled'] = false;
                }
                $options['ddp_statusmail'] = ( isset( $_POST['ddp_statusmail'] ) && 'on' === $_POST['ddp_statusmail'] ? true : false );
                $options['ddp_debug'] = ( isset( $_POST['ddp_debug'] ) && 'on' === $_POST['ddp_debug'] ? true : false );
                if ( isset( $_POST['ddp_statusmail_recipient'] ) ) {
                    $options['ddp_statusmail_recipient'] = sanitize_text_field( $_POST['ddp_statusmail_recipient'] );
                }
                if ( isset( $_POST['ddp_schedule'] ) ) {
                    $options['ddp_schedule'] = sanitize_text_field( $_POST['ddp_schedule'] );
                }
                if ( isset( $_POST['ddp_keep'] ) ) {
                    $options['ddp_keep'] = sanitize_text_field( $_POST['ddp_keep'] );
                }
                if ( isset( $_POST['ddp_method'] ) ) {
                    $options['ddp_method'] = sanitize_text_field( $_POST['ddp_method'] );
                }
                if ( isset( $_POST['ddp_resultslimit'] ) ) {
                    $options['ddp_resultslimit'] = sanitize_text_field( $_POST['ddp_resultslimit'] );
                }
                // 301 redirects
                if ( isset( $_POST['ddp_redirects'] ) ) {
                    $options['ddp_redirects'] = ( 'on' === $_POST['ddp_redirects'] ? true : false );
                } else {
                    $options['ddp_redirects'] = false;
                }
                $options['ddp_pts'] = $posttypes;
                // Previously sanitized
                if ( isset( $_POST['ddp_limit'] ) ) {
                    $options['ddp_limit'] = sanitize_text_field( $_POST['ddp_limit'] );
                }
                self::save_options( $options );
                if ( isset( self::$options['ddp_enabled'] ) ) {
                    wp_clear_scheduled_hook( 'ddp_cron' );
                    $interval = self::$options['ddp_schedule'];
                    if ( !$interval ) {
                        $interval = 'hourly';
                    }
                    $nextscheduled = wp_next_scheduled( 'ddp_cron' );
                    if ( !$nextscheduled ) {
                        wp_schedule_event( time(), $interval, 'ddp_cron' );
                    }
                }
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( __( 'Settings saved.', 'delete-duplicate-posts' ) ) . '</p></div>';
            }
            // CLEARING THE LOG
            if ( isset( $_POST['ddp_clearlog'] ) ) {
                if ( !wp_verify_nonce( $_POST['_wpnonce'], 'ddp_clearlog_nonce' ) ) {
                    die( esc_html( __( 'Whoops! Some error occured, try again, please!', 'delete-duplicate-posts' ) ) );
                }
                $table_name_log = $wpdb->prefix . 'ddp_log';
                $wpdb->query( "TRUNCATE {$table_name_log};" );
                //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                echo '<div class="updated"><p>' . esc_html( __( 'The log was cleared.', 'delete-duplicate-posts' ) ) . '</p></div>';
            }
            // REACTIVATE THE DATABASE
            if ( isset( $_POST['ddp_reactivate'] ) ) {
                if ( !wp_verify_nonce( $_POST['_wpnonce'], 'ddp_reactivate_nonce' ) ) {
                    die( esc_html( __( 'Whoops! Some error occured, try again, please!', 'delete-duplicate-posts' ) ) );
                }
                self::install( false );
                self::log( 'Reinstalled databases' );
            }
            $options = self::get_options();
            $css_classes = ' free';
            $display_ads = true;
            ?>

			<div class="wrap fs-section <?php 
            echo esc_attr( $css_classes );
            ?>">
				<h1>Delete Duplicate Posts <span>v. <?php 
            echo esc_html( self::get_plugin_version() );
            ?></span></h1>
				<?php 
            $totaldeleted = get_option( 'ddp_deleted_duplicates' );
            // check the code for get param 'welcome-message' er sat til true
            ?>

				<h2 class="nav-tab-wrapper">
					<a href="#duplicates-tab" class="nav-tab fs-tab nav-tab-active home"><?php 
            esc_html_e( 'Duplicates', 'delete-duplicate-posts' );
            ?></a>
					<a href="#log-tab" class="nav-tab"><?php 
            esc_html_e( 'Log', 'delete-duplicate-posts' );
            ?></a>
					<a href="#settings-tab" class="nav-tab"><?php 
            esc_html_e( 'Settings', 'delete-duplicate-posts' );
            ?></a>
					<a href="#redirects-tab" class="nav-tab<?php 
            echo ( $ddp_fs->is__premium_only() && $ddp_fs->can_use_premium_code() ? '' : ' pro' );
            ?>"><?php 
            esc_html_e( 'Redirects', 'delete-duplicate-posts' );
            ?></a>
				</h2>

				<div class="ddp_content_wrapper">
					<div class="ddp_content_cell">
						<div id="delete-duplicate-posts-tabs">
							<div id="duplicates-tab" class="tab-content">
								<div id="ddp-dashboard">
									<?php 
            if ( $options['ddp_enabled'] ) {
                $interval = $options['ddp_schedule'];
                if ( !$interval ) {
                    $interval = 'hourly';
                }
                $nextscheduled = wp_next_scheduled( 'ddp_cron' );
                if ( !$nextscheduled ) {
                    // plugin active, but the cron needs to be activated also..
                    $options['last_interval'] = $interval;
                    self::save_options( $options );
                    wp_schedule_event( time(), $interval, 'ddp_cron' );
                    //}
                }
            } else {
                wp_unschedule_hook( 'ddp_cron' );
            }
            $totaldeleted = get_option( 'ddp_deleted_duplicates' );
            ?>
									<div class="statusdiv">
										<div class="statusmessage"></div>
										<div class="errormessage"></div>
										<div class="dupelist">
											<div id="requestTime"></div>
											<table id="ddp_dupetable" class="wp-list-table widefat fixed striped table-view-list"></table>
										</div>
									</div>
									<?php 
            if ( false !== $totaldeleted && 0 < $totaldeleted && $display_ads ) {
                $totaldeleted = number_format_i18n( $totaldeleted );
                ?>
										<div id="cp-ddp-reviewlink" data-dismissible="ddp-leavereview-180" class="updated notice notice-success">
											<h3>
												<?php 
                /* translators: %s: Total number of deleted duplicates */
                printf( esc_html__( '%s duplicates deleted!', 'delete-duplicate-posts' ), esc_html( $totaldeleted ) );
                ?>
											</h3>
											<p>
												<?php 
                /* translators: %s: Total number of deleted duplicates */
                printf( esc_html__( "Hey, I noticed this plugin has deleted %s duplicate posts for you - that's awesome! Could you please do me a BIG favor and give it a 5-star rating on WordPress? Just to help us spread the word and boost our motivation.", 'delete-duplicate-posts' ), esc_html( $totaldeleted ) );
                ?>
											</p>
											<p>
												<a href="https://wordpress.org/support/plugin/delete-duplicate-posts/reviews/?filter=5#new-post" class="button-secondary button button-small" target="_blank" rel="noopener"><?php 
                esc_html_e( 'Ok, you deserve it', 'delete-duplicate-posts' );
                ?></a>
											</p>
										</div>
										<?php 
            }
            ?>
			<?php 
            $my_current_user = wp_get_current_user();
            ?>

<div class="ddpnewsletter">
			<p>Sign up to our newsletter to receive the latest tips and updates directly to your inbox. Ensure your WordPress site remains efficient and duplicate-free!</p>
			<form class="ml-block-form" action="https://assets.mailerlite.com/jsonp/16490/forms/106309157552916248/subscribe" data-code="" method="post" target="_blank">
				<input type="text" class="form-control" data-inputmask="" name="fields[name]" placeholder="Name" autocomplete="given-name" value="<?php 
            echo esc_html( $my_current_user->display_name );
            ?>" required="required">
				<input type="email" class="form-control" data-inputmask="" name="fields[email]" placeholder="Email" autocomplete="email" value="<?php 
            echo esc_html( $my_current_user->user_email );
            ?>" required="required">
				<input type="hidden" name="fields[signupsource]" value="PluginInstall">
				<input type="hidden" name="ml-submit" value="1">
				<input type="hidden" name="anticsrf" value="true">
				<button type="submit" class="button button-secondary">Subscribe</button>

			</form>
			<p class="ppolicy">You can unsubscribe anytime. For more details, review our <a href="https://cleverplugins.com/privacy-policy/" target="_blank" class="privacy-policy" rel="noopener">Privacy Policy</a>.</p>
		</div>


		






			<?php 
            $display_promotion = true;
            if ( $display_promotion ) {
                ?>
	<div class="innerpromotion ddppro">
	<h3><span class="dashicons dashicons-star-filled"></span> Upgrade to Delete Duplicate Posts Pro <span class="dashicons dashicons-star-filled"></span></h3>
		<ul class="linklist">
			<li><strong>301 Redirects for Deleted Posts:</strong> Seamlessly redirect deleted posts to original pages.</li>
			<li><strong>Search by Duplicate Post Meta:</strong> Advanced search options for identifying duplicates based on post metadata.</li>
			<li><strong>Automatic Deletion:</strong> Automatic management of duplicate posts.</li>
			<li><strong>Email Notifications:</strong> Alerts when duplicates are found and removed.</li>
			<li><strong>Search Any Post Status:</strong> Deeper search capabilities, including unpublished posts.</li>
			<li><strong>Filter and Delete Unpublished Duplicates:</strong> Proactively manage and prevent unseen duplicates.</li>
			<li><strong>WooCommerce Compatibility:</strong> Look for and delete duplicate products with same SKUs. (Custom post meta)</li>
			<li><strong>Support: </strong> We appreciate your support!</li>
		</ul>
		<p><strong></strong></p>

				<?php 
                $target_url = 'https://checkout.freemius.com/mode/dialog/plugin/925/plan/9473/licenses/1/?billing_cycle=annually';
                $lifetime_url = 'https://checkout.freemius.com/mode/dialog/plugin/925/plan/9473/licenses/1/?billing_cycle=lifetime';
                if ( $my_current_user->user_email ) {
                    $target_url = add_query_arg( 'user_email', $my_current_user->user_email, $target_url );
                    $lifetime_url = add_query_arg( 'user_email', $my_current_user->user_email, $lifetime_url );
                }
                ?>
				<a href="<?php 
                echo esc_url( $target_url );
                ?>" class="ddpprobutton button button-primary button-hero" target="_blank">Only $29.99 /year - <?php 
                esc_html_e( 'Click here', 'delete-duplicate-posts' );
                ?></a>
		<p>
			<center><em>OR get <a href="<?php 
                echo esc_url( $lifetime_url );
                ?>" target="_blank">a lifetime license for only $59.99</a></em> - You can transfer your license to other websites.</center>
		</p>
		<div class="moneybackguarantee">
			<p><strong>Money Back Guarantee!</strong></p>
			<p>You are fully protected by our 100% Money Back Guarantee. If during the next 30 days you experience an issue that makes the plugin unusable and we are unable to resolve it, we'll happily consider offering a full refund of your money.</p>
		</div>
	</div><!-- .sidebarrow -->

				<?php 
            }
            ?>









		
								</div><!-- #dashboard -->
							</div>

							<div id="log-tab" class="tab-content" style="display: none;">
								<div id="log">
									<h3><?php 
            esc_html_e( 'The Log', 'delete-duplicate-posts' );
            ?></h3>
									<div class="spinner is-active"></div>
									<ul class="large-text" name="ddp_log" id="ddp_log"></ul>
								</div>
								<p>
								<form method="post" id="ddp_clearlog">
									<?php 
            wp_nonce_field( 'ddp_clearlog_nonce' );
            ?>
									<input class="button-secondary" type="submit" name="ddp_clearlog" value="<?php 
            esc_html_e( 'Reset log', 'delete-duplicate-posts' );
            ?>" />
								</form>
								</p>
							</div>

							<div id="settings-tab" class="tab-content" style="display: none;">
								<div id="ddp-configuration">
									<h3><?php 
            esc_html_e( 'Settings', 'delete-duplicate-posts' );
            ?></h3>
									<p>
										<?php 
            $nextscheduled = wp_next_scheduled( 'ddp_cron' );
            if ( $nextscheduled ) {
                ?>
									<div class="notice notice-info is-dismissible">
										<h3><span class="dashicons dashicons-saved"></span> <?php 
                esc_html_e( 'Automatically Deleting Duplicates', 'delete-duplicate-posts' );
                ?></h3>
											<?php 
                echo '<p class="cronstatus center">' . esc_html__( 'You have enabled automatic deletion, so I am running on automatic. I will take care of everything...', 'delete-duplicate-posts' ) . '</p>';
                echo '<p class="center">';
                printf( 
                    // translators: Showing when the next check happens and what the current time is
                    esc_html( __( 'Next automated check %1$s. Current time %2$s', 'delete-duplicate-posts' ) ),
                    '<strong>' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $nextscheduled ) ) . '</strong>',
                    '<strong>' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), time() ) ) . '</strong>'
                 );
                echo '</p>';
                ?>
									</div>
											<?php 
            }
            ?>
								</p>
								<form method="post" id="delete_duplicate_posts_options">
									<?php 
            wp_nonce_field( 'ddp-update-options' );
            ?>
									<table width="100%" cellspacing="2" cellpadding="5" class="form-table">
										<tr valign="top">
											<th><label for="ddp_pts"><?php 
            esc_html_e( 'Which post types?:', 'delete-duplicate-posts' );
            ?></label>
											</th>
											<td>
												<?php 
            $builtin = array('post', 'page', 'attachment');
            $args = array(
                'public'   => true,
                '_builtin' => false,
            );
            $output = 'names';
            $operator = 'and';
            $post_types = get_post_types( $args, $output, $operator );
            $post_types = array_merge( $builtin, $post_types );
            $checked_post_types = $options['ddp_pts'];
            if ( $post_types ) {
                ?>
													<ul class="radio">
														<?php 
                $step = 0;
                if ( !is_array( $checked_post_types ) ) {
                    $checked_post_types = array();
                }
                foreach ( $post_types as $pt ) {
                    $checked = array_search( $pt, $checked_post_types, true );
                    ?>
															<li><input type="checkbox" name="ddp_pts[]" id="ddp_pt-<?php 
                    echo esc_attr( $step );
                    ?>" value="<?php 
                    echo esc_html( $pt );
                    ?>" 
															<?php 
                    if ( false !== $checked ) {
                        echo ' checked';
                    }
                    ?>
																																																																													/>
																<label for="ddp_pt-<?php 
                    echo esc_attr( $step );
                    ?>"><?php 
                    echo esc_html( $pt );
                    ?></label>
																<?php 
                    // Count for each post type
                    $postinfo = wp_count_posts( $pt );
                    $othercount = 0;
                    foreach ( $postinfo as $pi ) {
                        $othercount = $othercount + intval( $pi );
                    }
                    // translators: Total number of deleted duplicates
                    echo '<small>' . sprintf( esc_html__( '(%s total found)', 'delete-duplicate-posts' ), esc_html( number_format_i18n( $othercount ) ) ) . '</small>';
                    ?>
															</li>
															<?php 
                    ++$step;
                }
                ?>
													</ul>
													<?php 
            }
            ?>
												<p class="description">
													<?php 
            esc_html_e( 'Choose which post types to scan for duplicates.', 'delete-duplicate-posts' );
            ?>
												</p>
											</td>
										</tr>

										<tr>
											<th><label for="ddp_pstati"><?php 
            esc_html_e( 'Post status', 'delete-duplicate-posts' );
            ?></label>
											</th>
											<td>
												<?php 
            $stati = array(
                'publish' => (object) array(
                    'label'                     => 'Published',
                    'show_in_admin_status_list' => true,
                ),
            );
            $checked_post_stati = $options['ddp_pstati'];
            if ( $stati ) {
                ?>
													<ul class="checkbox">
														<?php 
                $staticount = count( $stati );
                foreach ( $stati as $key => $st ) {
                    if ( $st->show_in_admin_status_list ) {
                        $checked = array_search( $key, $checked_post_stati, true );
                        ?>
																<li>
																	<input type="checkbox" name="ddp_pstati[]" id="ddp_pstatus-<?php 
                        echo esc_attr( $key );
                        ?>" value="<?php 
                        echo esc_attr( $key );
                        ?>" 
																	<?php 
                        if ( false !== $checked ) {
                            echo ' checked';
                        }
                        if ( 1 === $staticount ) {
                            echo ' disabled';
                        }
                        ?>
																																																																																	/><label for="ddp_pstatus-<?php 
                        echo esc_attr( $key );
                        ?>">
																		<?php 
                        echo esc_html( $key . ' (' . $st->label . ')' );
                        if ( 'trash' === $key ) {
                            echo ' <small>' . esc_html__( 'Warning: Enabling this can give false results. Only enable if you know what you are doing.', 'delete-duplicate-posts' ) . '</small>';
                        }
                        ?>
																	</label>
																</li>
																<?php 
                        ++$step;
                    }
                }
                ?>
													</ul>
													<?php 
            }
            ?>
											</td>


										</tr>
										<?php 
            $comparemethod = 'titlecompare';
            global $ddp_fs;
            ?>
										<tr valign="top">
											<th><?php 
            esc_html_e( 'Comparison Method', 'delete-duplicate-posts' );
            ?></th>
											<td>
												<ul class="ddpcomparemethod">

													<li>
														<label>
															<input type="radio" name="ddp_method" value="titlecompare" <?php 
            checked( 'titlecompare', $comparemethod );
            ?> />
															<?php 
            esc_html_e( 'Compare by title (default)', 'delete-duplicate-posts' );
            ?>
															<span class="optiondesc"><?php 
            esc_html_e( 'Looks at the title of the post itself.', 'delete-duplicate-posts' );
            ?></span>
														</label>

													</li>

													<?php 
            global $ddp_fs;
            ?>
												</ul>
											</td>
										</tr>
										<tr>
											<th><label for="ddp_keep"><?php 
            esc_html_e( 'Delete which posts?:', 'delete-duplicate-posts' );
            ?></label></th>
											<td>

												<select name="ddp_keep" id="ddp_keep">
													<option value="oldest" 
													<?php 
            if ( 'oldest' === $options['ddp_keep'] ) {
                echo 'selected="selected"';
            }
            ?>
																									><?php 
            esc_html_e( 'Keep oldest', 'delete-duplicate-posts' );
            ?></option>
													<option value="latest" 
													<?php 
            if ( 'latest' === $options['ddp_keep'] ) {
                echo 'selected="selected"';
            }
            ?>
																									><?php 
            esc_html_e( 'Keep latest', 'delete-duplicate-posts' );
            ?></option>
												</select>
												<p class="description">
													<?php 
            esc_html_e( 'Keep the oldest or the latest version of duplicates? Default is keeping the oldest, and deleting any subsequent duplicate posts', 'delete-duplicate-posts' );
            ?>
												</p>
											</td>
										</tr>








										<?php 
            ?>
										<tr>
											<td colspan="2">
												<hr>
												<h3><?php 
            esc_html_e( 'Delete Duplicates Automatically', 'security-ninja' );
            ?></h3>
											</td>
										</tr>

										<tr valign="top">
											<th><?php 
            esc_html_e( 'Enable automatic deletion?:', 'delete-duplicate-posts' );
            ?>
											</th>
											<td><label for="ddp_enabled">
													<input type="checkbox" id="ddp_enabled" name="ddp_enabled" 
													<?php 
            if ( true === $options['ddp_enabled'] ) {
                echo 'checked="checked"';
            }
            ?>
																																											>
													<p class="description">
														<?php 
            esc_html_e( 'Clean duplicates automatically.', 'delete-duplicate-posts' );
            ?></p>
												</label>
											</td>
										</tr>

										<tr>
											<th><label for="ddp_resultslimit"><?php 
            esc_html_e( 'How many:', 'delete-duplicate-posts' );
            ?></label>
											</th>
											<td>

												<?php 
            $dupe_options = array(
                0     => __( 'No limit', 'delete-duplicate-posts' ),
                10000 => number_format_i18n( '10000' ),
                5000  => number_format_i18n( '5000' ),
                2500  => number_format_i18n( '2500' ),
                1000  => number_format_i18n( '1000' ),
                500   => '500',
                250   => '250',
                100   => '100',
                50    => '50',
                10    => '10',
            );
            ?>
												<select name="ddp_resultslimit" id="ddp_resultslimit">
													<?php 
            foreach ( $dupe_options as $key => $label ) {
                ?>
														<option value="<?php 
                echo esc_attr( $key );
                ?>" <?php 
                selected( $options['ddp_resultslimit'], $key );
                ?>>
															<?php 
                echo esc_attr( $label );
                ?></option>
														<?php 
            }
            ?>
												</select>

												<p class="description">
													<?php 
            esc_html_e( 'If you have many duplicates, the plugin might time out before finding them all. Try limiting the amount of duplicates here. Default: Unlimited.', 'delete-duplicate-posts' );
            ?><br>
													<strong><?php 
            esc_html_e( 'This only applies to automatic (CRON) jobs.', 'delete-duplicate-posts' );
            ?></strong>
												</p>
											</td>
										</tr>

										<tr>
											<th><label for="ddp_schedule"><?php 
            esc_html_e( 'How often?:', 'delete-duplicate-posts' );
            ?></label>
											</th>
											<td>

												<select name="ddp_schedule" id="ddp_schedule">
													<?php 
            $schedules = wp_get_schedules();
            if ( $schedules ) {
                foreach ( $schedules as $key => $sch ) {
                    ?>
															<option value="<?php 
                    echo esc_attr( $key );
                    ?>" 
															<?php 
                    if ( isset( $options['ddp_schedule'] ) && esc_attr( $key ) === $options['ddp_schedule'] ) {
                        echo esc_html( 'selected="selected"' );
                    }
                    ?>
																																						><?php 
                    echo esc_html( $sch['display'] );
                    ?></option>
															<?php 
                }
            }
            ?>
												</select>
												<p class="description">
													<?php 
            esc_html_e( 'How often should the cron job run?', 'delete-duplicate-posts' );
            ?></p>
											</td>
										</tr>
										<tr>
											<td colspan="2">
												<hr>
											</td>
										</tr>

										<tr>
											<th><?php 
            esc_html_e( 'Send status mail?:', 'delete-duplicate-posts' );
            ?></th>
											<td>
												<label for="ddp_statusmail">
													<input type="checkbox" id="ddp_statusmail" name="ddp_statusmail" 
													<?php 
            if ( isset( $options['ddp_statusmail'] ) && true === $options['ddp_statusmail'] ) {
                ?>
																																														checked="checked" 
																																														<?php 
            }
            ?>
																																																								>
													<p class="description">
														<?php 
            esc_html_e( 'Sends a status email if duplicates have been found.', 'delete-duplicate-posts' );
            ?>
													</p>
												</label>
											</td>
										</tr>

										<tr>
											<th><?php 
            esc_html_e( 'Email recipient:', 'delete-duplicate-posts' );
            ?></th>
											<td>
												<label for="ddp_statusmail_recipient">

													<input type="text" class="regular-text" id="ddp_statusmail_recipient" name="ddp_statusmail_recipient" value="<?php 
            echo esc_html( $options['ddp_statusmail_recipient'] );
            ?>">
													<p class="description">
														<?php 
            esc_html_e( 'Who should get the notification email.', 'delete-duplicate-posts' );
            ?></p>
												</label>
											</td>
										</tr>



										<tr>
											<td colspan="2">
												<hr>
											</td>
										</tr>

										<tr>
											<th><?php 
            esc_html_e( 'Enable debug logging?:', 'delete-duplicate-posts' );
            ?></th>
											<td>
												<label for="ddp_debug">
													<input type="checkbox" id="ddp_debug" name="ddp_debug" 
													<?php 
            if ( isset( $options['ddp_debug'] ) && true === $options['ddp_debug'] ) {
                echo 'checked="checked"';
            }
            ?>
																																									>
													<p class="description">
														<?php 
            esc_html_e( 'Should only be enabled if debugging a problem.', 'delete-duplicate-posts' );
            ?>
													</p>
												</label>
											</td>
										</tr>
										<th colspan=2><input type="submit" class="button-primary" name="delete_duplicate_posts_save" value="<?php 
            esc_html_e( 'Save Settings', 'delete-duplicate-posts' );
            ?>" /></th>
										</tr>
									</table>
								</form>
								</div><!-- #configuration -->
							</div>


							<div id="redirects-tab" class="tab-content<?php 
            echo ( $ddp_fs->is__premium_only() && $ddp_fs->can_use_premium_code() ? '' : ' pro' );
            ?>" style="display: none;">
								<?php 
            if ( $ddp_fs->is__premium_only() && $ddp_fs->can_use_premium_code() ) {
                ?>
									<h3><?php 
                esc_html_e( 'Redirects', 'delete-duplicate-posts' );
                ?></h3>
									<p><?php 
                esc_html_e( 'This table shows all redirects created by the plugin.', 'delete-duplicate-posts' );
                ?></p>
									<table id="ddp_redirtable" class="wp-list-table widefat fixed striped table-view-list">
										<thead>
											<tr>
												<th><?php 
                esc_html_e( 'ID', 'delete-duplicate-posts' );
                ?></th>
												<th><?php 
                esc_html_e( 'From URL', 'delete-duplicate-posts' );
                ?></th>
												<th><?php 
                esc_html_e( 'Target URL', 'delete-duplicate-posts' );
                ?></th>
											</tr>
										</thead>
										<tbody>
											<!-- DataTables will populate this -->
										</tbody>
									</table>
								<?php 
            } else {
                ?>
									<h3><?php 
                esc_html_e( 'Redirects', 'delete-duplicate-posts' );
                ?></h3>
									<p><?php 
                esc_html_e( 'Redirects are a premium feature. Please upgrade to access this functionality.', 'delete-duplicate-posts' );
                ?></p>
								<?php 
            }
            ?>
							</div>

						</div>
					</div>

					<?php 
            include_once 'sidebar.php';
            if ( function_exists( 'ddp_fs' ) ) {
                global $ddp_fs;
            }
            ?>
				</div>

			</div>



			<script>
				jQuery(document).ready(function($) {
					const navTabWrapper = $('.nav-tab-wrapper');
					const currentTabs = $('.nav-tab-wrapper a');

					currentTabs.each(function() {
						$(this).on('click', function(e) {
							const href = $(this).attr('href');
							console.log('Clicked tab href:', href);

							try {
								if (!href.startsWith('#')) {
									e.preventDefault(); // Prevent default tab behavior for full URLs
									window.location.href = href; // Load the page to the URL in the same window
								} else {
									e.preventDefault(); // Prevent default anchor behavior
									console.log('Switching to tab:', href);

									// Switch as a regular tab for href starting with '#'
									currentTabs.removeClass('nav-tab-active');
									$(this).addClass('nav-tab-active');

									// Hide all tab content
									$('.tab-content').hide();

									// Show the content for the clicked tab
									$(href).show();
								}
							} catch (error) {
								console.error('Error occurred:', error);
							}
						});
					});

					// Initially hide all tab content except the active one
					$('.tab-content').hide();
					$('.nav-tab-active').each(function() {
						const activeHref = $(this).attr('href');
						$(activeHref).show();
					});
				});
			</script>








			<?php 
        }

    }

    //End Class
}
if ( class_exists( __NAMESPACE__ . '\\Delete_Duplicate_Posts' ) ) {
    $delete_duplicate_posts_var = new Delete_Duplicate_Posts();
}