<?php

/**
 * Admin UI and settings page.
 *
 * @package DeleteDuplicatePosts
 */
namespace DeleteDuplicatePosts;

if ( !defined( 'ABSPATH' ) ) {
    exit;
}
class DDP_Admin {
    /**
     * Returns a Pro badge label for settings teasers.
     *
     * @return string
     */
    public static function pro_badge() {
        return '<span class="ddp-pro-badge">' . esc_html__( 'Pro', 'delete-duplicate-posts' ) . '</span>';
    }

    /**
     * Freemius checkout URL for upgrades.
     *
     * @param string $billing Billing cycle (annually or lifetime).
     * @return string
     */
    public static function upgrade_url( $billing = 'annually' ) {
        $url = 'https://checkout.freemius.com/mode/dialog/plugin/925/plan/9473/licenses/1/?billing_cycle=' . rawurlencode( $billing );
        $user = wp_get_current_user();
        if ( $user && $user->user_email ) {
            $url = add_query_arg( 'user_email', $user->user_email, $url );
        }
        return $url;
    }

    /**
     * Short upgrade link for Pro-only setting rows.
     *
     * @return string
     */
    public static function pro_upgrade_link() {
        return sprintf( '<a href="%s" class="ddp-pro-upgrade" target="_blank" rel="noopener noreferrer">%s</a>', esc_url( self::upgrade_url() ), esc_html__( 'Upgrade to Pro', 'delete-duplicate-posts' ) );
    }

    /**
     * Renders a locked Pro option as a deliberate upgrade promo.
     *
     * Instead of a disabled form control (which looks broken and is skipped by
     * keyboard/screen-reader users), the whole row is a link to the upgrade page.
     *
     * @param string $label       The option label (plain text).
     * @param string $description  Optional supporting text (plain text).
     * @param string $note         Optional extra note appended to the label, e.g. a warning (plain text).
     * @return string
     */
    public static function pro_locked_row( $label, $description = '', $note = '' ) {
        $html = '<a class="ddp-pro-lock" href="' . esc_url( self::upgrade_url() ) . '" target="_blank" rel="noopener noreferrer">';
        $html .= '<span class="ddp-pro-lock__icon dashicons dashicons-lock" aria-hidden="true"></span>';
        $html .= '<span class="ddp-pro-lock__text">';
        $html .= '<span class="ddp-pro-lock__label">' . esc_html( $label ) . '</span>';
        $html .= self::pro_badge();
        if ( '' !== $note ) {
            $html .= '<span class="ddp-pro-lock__note">' . esc_html( $note ) . '</span>';
        }
        if ( '' !== $description ) {
            $html .= '<span class="ddp-pro-lock__desc">' . esc_html( $description ) . '</span>';
        }
        $html .= '</span>';
        $html .= '<span class="ddp-pro-lock__cta">' . esc_html__( 'Upgrade to Pro', 'delete-duplicate-posts' ) . '</span>';
        $html .= '</a>';
        return $html;
    }

    /**
     * Render scheduled scan status above the live duplicates table.
     *
     * The existing table remains the single list of duplicates. The status
     * explains whether scheduled scans only report or delete automatically.
     *
     * @param array<string,mixed> $options Plugin options.
     * @return void
     */
    private static function render_scheduled_scan_summary( $options ) {
        if ( empty( $options['ddp_enabled'] ) ) {
            return;
        }
        $next_scheduled = wp_next_scheduled( 'ddp_cron' );
        $cron_mode = ( isset( $options['ddp_cron_mode'] ) ? $options['ddp_cron_mode'] : 'report' );
        $is_report_mode = 'report' === $cron_mode;
        $last_dry_run = get_option( 'ddp_last_dry_run', array() );
        $summary_class = ( $is_report_mode ? 'ddp-scan-summary--preview' : 'ddp-scan-summary--automatic' );
        ?>
		<section class="ddp-scan-summary <?php 
        echo esc_attr( $summary_class );
        ?>" aria-labelledby="ddp-scan-summary-title">
			<div class="ddp-scan-summary__header">
				<h3 id="ddp-scan-summary-title">
					<?php 
        echo ( $is_report_mode ? esc_html__( 'Scheduled preview', 'delete-duplicate-posts' ) : esc_html__( 'Scheduled automatic deletion', 'delete-duplicate-posts' ) );
        ?>
				</h3>
				<?php 
        if ( $is_report_mode ) {
            ?>
					<strong class="ddp-preview-status"><?php 
            esc_html_e( 'Scheduled scans are preview only', 'delete-duplicate-posts' );
            ?></strong>
				<?php 
        }
        ?>
			</div>

			<ul class="ddp-scan-summary__meta">
				<li>
					<strong><?php 
        esc_html_e( 'Scheduled mode:', 'delete-duplicate-posts' );
        ?></strong>
					<?php 
        echo ( $is_report_mode ? esc_html__( 'Report only', 'delete-duplicate-posts' ) : esc_html__( 'Delete automatically', 'delete-duplicate-posts' ) );
        ?>
				</li>
				<?php 
        if ( $is_report_mode ) {
            ?>
					<li>
						<strong><?php 
            esc_html_e( 'Email report:', 'delete-duplicate-posts' );
            ?></strong>
						<?php 
            echo ( !empty( $options['ddp_statusmail'] ) ? esc_html__( 'Enabled', 'delete-duplicate-posts' ) : esc_html__( 'Disabled', 'delete-duplicate-posts' ) );
            ?>
					</li>
				<?php 
        }
        ?>
				<?php 
        if ( $next_scheduled ) {
            ?>
					<li>
						<strong><?php 
            esc_html_e( 'Next scheduled scan:', 'delete-duplicate-posts' );
            ?></strong>
						<?php 
            echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next_scheduled ) );
            ?>
					</li>
				<?php 
        }
        ?>
				<?php 
        if ( $is_report_mode && is_array( $last_dry_run ) && !empty( $last_dry_run['time'] ) && isset( $last_dry_run['count'] ) ) {
            ?>
					<li>
						<strong><?php 
            esc_html_e( 'Last preview:', 'delete-duplicate-posts' );
            ?></strong>
						<?php 
            printf( 
                /* translators: 1: Duplicate count, 2: When the report ran. */
                esc_html__( '%1$s duplicate(s) would be removed · ran %2$s', 'delete-duplicate-posts' ),
                esc_html( number_format_i18n( (int) $last_dry_run['count'] ) ),
                esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $last_dry_run['time'] ) )
             );
            ?>
					</li>
				<?php 
        }
        ?>
			</ul>

			<p class="ddp-scan-summary__settings-link">
				<a class="ddp-tab-link" href="#settings-tab"><?php 
        esc_html_e( 'Change scheduled scan settings', 'delete-duplicate-posts' );
        ?></a>
			</p>
		</section>
		<?php 
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
            $pluginver = DDP_Settings::get_plugin_version();
            $stylesheet_path = DDP_PLUGIN_DIR . 'css/delete-duplicate-posts.css';
            $script_path = DDP_PLUGIN_DIR . 'js/delete-duplicate-posts.js';
            $stylesheet_ver = ( is_readable( $stylesheet_path ) ? $pluginver . '.' . filemtime( $stylesheet_path ) : $pluginver );
            $script_ver = ( is_readable( $script_path ) ? $pluginver . '.' . filemtime( $script_path ) : $pluginver );
            wp_enqueue_script( 'jquery' );
            wp_enqueue_script(
                'dataTables',
                // Unique handle for your script
                plugin_dir_url( DDP_PLUGIN_FILE ) . 'js/DataTables/datatables.js',
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
                plugins_url( '/js/DataTables/datatables.css', DDP_PLUGIN_FILE ),
                array(),
                $pluginver
            );
            wp_enqueue_style(
                'delete-duplicate-posts',
                plugins_url( '/css/delete-duplicate-posts.css', DDP_PLUGIN_FILE ),
                array('dataTables'),
                $stylesheet_ver
            );
            wp_register_script(
                'delete-duplicate-posts',
                plugins_url( '/js/delete-duplicate-posts.js', DDP_PLUGIN_FILE ),
                array('jquery', 'dataTables'),
                $script_ver,
                true
            );
            $options = DDP_Settings::get_options();
            $delete_mode = 'trash';
            $keep = ( isset( $options['ddp_keep'] ) ? $options['ddp_keep'] : 'oldest' );
            $js_vars = array(
                'nonce'                          => wp_create_nonce( 'cp_ddp_return_duplicates' ),
                'loglines_nonce'                 => wp_create_nonce( 'cp_ddp_return_loglines' ),
                'deletedupes_nonce'              => wp_create_nonce( 'cp_ddp_delete_loglines' ),
                'dismiss_notice_nonce'           => wp_create_nonce( 'ddp_dismiss_notice' ),
                'text_areyousure'                => __( 'Are you sure you want to delete duplicates? There is no undo feature.', 'delete-duplicate-posts' ),
                'text_selectsomething'           => __( 'You have to select which duplicates to delete. Tip: You can click the top or bottom checkbox to select all.', 'delete-duplicate-posts' ),
                'fromUrlTitle'                   => __( 'From URL', 'delete-duplicate-posts' ),
                'targetUrlTitle'                 => __( 'Target URL', 'delete-duplicate-posts' ),
                'refreshingText'                 => __( 'Refreshing...', 'delete-duplicate-posts' ),
                'refreshText'                    => __( 'Refresh', 'delete-duplicate-posts' ),
                'errorDetailsText'               => __( 'Error details: ', 'delete-duplicate-posts' ),
                'redirectsErrorText'             => __( 'Redirects DataTables error occurred. ', 'delete-duplicate-posts' ),
                'processingMessage'              => __( 'Looking for duplicates', 'delete-duplicate-posts' ),
                'requestTimeText'                => __( 'Request: ', 'delete-duplicate-posts' ),
                'failedToLoadDataText'           => __( 'Failed to load data. ', 'delete-duplicate-posts' ),
                'duplicateTitle'                 => __( 'Remove', 'delete-duplicate-posts' ),
                'originalTitle'                  => __( 'Keep', 'delete-duplicate-posts' ),
                'selectDuplicateText'            => __( 'Select duplicate: %s', 'delete-duplicate-posts' ),
                'selectRowAlert'                 => __( 'Please select at least one row to delete.', 'delete-duplicate-posts' ),
                'serverResponseText'             => __( 'Response from the server: ', 'delete-duplicate-posts' ),
                'errorOccurredText'              => __( 'An error occurred: ', 'delete-duplicate-posts' ),
                'deleteSelectedText'             => __( 'Delete Selected', 'delete-duplicate-posts' ),
                'selectVisibleText'              => __( 'Select Visible', 'delete-duplicate-posts' ),
                'selectNoneText'                 => __( 'Select None', 'delete-duplicate-posts' ),
                'selectedSingularText'           => __( '%d duplicate selected', 'delete-duplicate-posts' ),
                'selectedPluralText'             => __( '%d duplicates selected', 'delete-duplicate-posts' ),
                'deleteSuccessTrashSingular'     => __( '%d duplicate moved to Trash. You can restore it from WordPress Trash.', 'delete-duplicate-posts' ),
                'deleteSuccessTrashPlural'       => __( '%d duplicates moved to Trash. You can restore them from WordPress Trash.', 'delete-duplicate-posts' ),
                'deleteSuccessPermanentSingular' => __( '%d duplicate permanently deleted.', 'delete-duplicate-posts' ),
                'deleteSuccessPermanentPlural'   => __( '%d duplicates permanently deleted.', 'delete-duplicate-posts' ),
                'unsavedSettingsText'            => __( 'You have unsaved changes.', 'delete-duplicate-posts' ),
                'logLoadFailedText'              => __( 'The activity log could not be loaded. Refresh the page and try again.', 'delete-duplicate-posts' ),
                'dataTablesErrorText'            => __( 'DataTables error occurred. ', 'delete-duplicate-posts' ),
                'unknownErrorText'               => __( 'Unknown error occurred', 'delete-duplicate-posts' ),
                'deleteMode'                     => $delete_mode,
                'keepPreference'                 => $keep,
                'deleteModalTitle'               => __( 'Confirm deletion', 'delete-duplicate-posts' ),
                'deleteModalCountSingular'       => __( 'You are about to delete %d duplicate post.', 'delete-duplicate-posts' ),
                'deleteModalCountPlural'         => __( 'You are about to delete %d duplicate posts.', 'delete-duplicate-posts' ),
                'deleteModalTrash'               => __( 'Action: move to Trash (recoverable from WordPress Trash).', 'delete-duplicate-posts' ),
                'deleteModalPermanent'           => __( 'Action: permanently delete. This cannot be undone.', 'delete-duplicate-posts' ),
                'deleteModalKeepOldest'          => __( 'Keeping the oldest original in each pair.', 'delete-duplicate-posts' ),
                'deleteModalKeepLatest'          => __( 'Keeping the latest original in each pair.', 'delete-duplicate-posts' ),
                'deleteModalPreview'             => __( 'Examples:', 'delete-duplicate-posts' ),
                'deleteModalMore'                => __( '…and %d more.', 'delete-duplicate-posts' ),
                'deleteModalConfirm'             => __( 'Confirm delete', 'delete-duplicate-posts' ),
                'deleteModalCancel'              => __( 'Cancel', 'delete-duplicate-posts' ),
                'deleteModalArrow'               => __( '→ keep', 'delete-duplicate-posts' ),
                'deletingText'                   => __( 'Deleting…', 'delete-duplicate-posts' ),
                'redirectMoveNonce'              => wp_create_nonce( 'ddp_redirect_move' ),
                'redirectMoveConfirm'            => __( 'Move built-in redirects into the Redirection “Delete Duplicate Posts” group and remove them here? Redirects that fail to move stay in the built-in list.', 'delete-duplicate-posts' ),
                'redirectMovePreview'            => __( 'Move %1$d built-in redirect(s) into the Redirection “Delete Duplicate Posts” group and remove them here. %2$d are already in that group and will only be removed here. Redirects that fail to move stay in the built-in list. Continue?', 'delete-duplicate-posts' ),
                'redirectMoveWorking'            => __( 'Moving redirects…', 'delete-duplicate-posts' ),
                'redirectMoveDone'               => __( 'Move finished: %1$d moved, %2$d already in Redirection and removed here, %3$d failed and kept.', 'delete-duplicate-posts' ),
                'redirectMoveButton'             => __( 'Move built-in redirects into Redirection (%d)', 'delete-duplicate-posts' ),
                'redirectSelectAll'              => __( 'Select all', 'delete-duplicate-posts' ),
                'redirectSelectNone'             => __( 'Select none', 'delete-duplicate-posts' ),
                'redirectSelectPage'             => __( 'Select redirects on this page', 'delete-duplicate-posts' ),
                'redirectSelectRedirect'         => __( 'Select redirect: %s', 'delete-duplicate-posts' ),
                'redirectSelectedCount'          => __( '%d redirects selected', 'delete-duplicate-posts' ),
                'redirectSelectAllWorking'       => __( 'Selecting redirects…', 'delete-duplicate-posts' ),
                'redirectSelectAllFailed'        => __( 'Could not select every redirect. Try again.', 'delete-duplicate-posts' ),
                'redirectSelectAllTruncated'     => __( 'Selected the first %d redirects. Delete those, then select all again for the rest.', 'delete-duplicate-posts' ),
                'redirectDeleteConfirm'          => __( 'Delete the selected redirects? This cannot be undone.', 'delete-duplicate-posts' ),
                'redirectDeleteWorking'          => __( 'Deleting redirects…', 'delete-duplicate-posts' ),
                'redirectDeleteDone'             => __( 'Deleted %d redirect(s).', 'delete-duplicate-posts' ),
                'redirectSelectNone'             => __( 'Select at least one redirect to delete.', 'delete-duplicate-posts' ),
                'redirectActionFailed'           => __( 'The redirect action failed. Check the log and try again.', 'delete-duplicate-posts' ),
            );
            wp_localize_script( 'delete-duplicate-posts', 'cp_ddp', $js_vars );
            wp_enqueue_script( 'delete-duplicate-posts' );
        }
    }

    /**
     * Returns the current user's dismissed-notice map.
     *
     * @return array Map of notice key => dismissal Unix timestamp.
     */
    private static function get_dismissed_notices() {
        $dismissed = get_user_meta( get_current_user_id(), 'ddp_dismissed_notices', true );
        return ( is_array( $dismissed ) ? $dismissed : array() );
    }

    /**
     * Checks whether an admin notice has been dismissed by the current user.
     *
     * @param string $key         Notice identifier.
     * @param int    $snooze_days Days to keep it hidden after dismissal. 0 means hide permanently.
     * @return bool True when the notice should stay hidden.
     */
    public static function is_notice_dismissed( $key, $snooze_days = 0 ) {
        $dismissed = self::get_dismissed_notices();
        if ( !isset( $dismissed[$key] ) ) {
            return false;
        }
        if ( 0 === $snooze_days ) {
            return true;
        }
        return time() - (int) $dismissed[$key] < $snooze_days * DAY_IN_SECONDS;
    }

    /**
     * Stores the dismissal timestamp for a notice against the current user.
     *
     * @param string $key Notice identifier.
     * @return void
     */
    private static function set_notice_dismissed( $key ) {
        $dismissed = self::get_dismissed_notices();
        $dismissed[$key] = time();
        update_user_meta( get_current_user_id(), 'ddp_dismissed_notices', $dismissed );
    }

    /**
     * AJAX handler that persists dismissal of a plugin admin notice.
     *
     * @return void
     */
    public static function dismiss_notice_ajax() {
        check_ajax_referer( 'ddp_dismiss_notice' );
        if ( !current_user_can( 'edit_posts' ) ) {
            wp_send_json_error();
        }
        $notice = ( isset( $_POST['notice'] ) ? sanitize_key( wp_unslash( $_POST['notice'] ) ) : '' );
        $allowed = array('welcome', 'leavereview');
        if ( !in_array( $notice, $allowed, true ) ) {
            wp_send_json_error();
        }
        self::set_notice_dismissed( $notice );
        wp_send_json_success();
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
            'manage_options',
            'delete-duplicate-posts',
            array(__CLASS__, 'admin_options_page'),
            41
        );
        add_filter(
            'plugin_action_links_' . plugin_basename( DDP_PLUGIN_FILE ),
            array(__CLASS__, 'filter_plugin_actions'),
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
                'content' => '<h4>' . __( 'What does this plugin do?', 'delete-duplicate-posts' ) . '</h4><p>' . __( 'Helps you clean duplicate posts from your blog. The plugin checks for blogposts on your blog with the same title.', 'delete-duplicate-posts' ) . '</p><p>' . __( "It can run automatically via WordPress's own internal CRON-system, or you can run it automatically.", 'delete-duplicate-posts' ) . '</p><p>' . __( 'It also has a nice feature that can send you an e-mail when Delete Duplicate Posts finds and deletes something (if you have turned on the CRON feature).', 'delete-duplicate-posts' ) . '</p><h4>' . __( 'Help! Something was deleted that was not supposed to be deleted!', 'delete-duplicate-posts' ) . '</h4><p>' . __( 'I am sorry for that, I can only recommend you restore the database you took just before you ran this plugin.', 'delete-duplicate-posts' ) . '</p><p>' . __( 'If you run this plugin, manually or automatically, it is at your OWN risk!', 'delete-duplicate-posts' ) . '</p><p>' . __( 'We have done our best to avoid deleting something that should not be deleted, but if it happens, there is nothing we can do to help you.', 'delete-duplicate-posts' ) . '</p><p><a href="' . esc_url( DDP_Links::tracked_url( 'https://cleverplugins.com', 'help-tab-footer' ) ) . '" target="_blank" rel="noopener noreferrer">cleverplugins.com</a>.</p>',
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
        // Hard gate: this screen saves settings, clears the log, recreates tables
        // and can trigger deletion, so require full admin capability before any
        // POST handling below runs.
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'delete-duplicate-posts' ) );
        }
        // SAVING OPTIONS
        if ( isset( $_POST['delete_duplicate_posts_save'], $_POST['_wpnonce'] ) ) {
            $nonce = wp_unslash( $_POST['_wpnonce'] );
            if ( !wp_verify_nonce( $nonce, 'ddp-update-options' ) ) {
                die( esc_html( __( 'Whoops! There was a problem with the data you posted. Please go back and try again.', 'delete-duplicate-posts' ) ) );
            }
            $options = DDP_Settings::get_options();
            $posttypes = array();
            if ( isset( $_POST['ddp_pts'] ) && is_array( $_POST['ddp_pts'] ) ) {
                $option_array = wp_unslash( $_POST['ddp_pts'] );
                foreach ( $option_array as $post_type ) {
                    $posttypes[] = sanitize_text_field( $post_type );
                }
            }
            if ( isset( $_POST['ddp_enabled'] ) ) {
                $options['ddp_enabled'] = 'on' === sanitize_text_field( wp_unslash( $_POST['ddp_enabled'] ) );
            } else {
                $options['ddp_enabled'] = false;
            }
            $cron_mode = ( isset( $_POST['ddp_cron_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['ddp_cron_mode'] ) ) : 'report' );
            $options['ddp_cron_mode'] = DDP_Settings::normalize_cron_mode( $cron_mode );
            $exclude_raw = ( isset( $_POST['ddp_exclude_ids'] ) ? wp_unslash( $_POST['ddp_exclude_ids'] ) : '' );
            $exclude_ids = DDP_Settings::parse_exclude_ids( ( is_string( $exclude_raw ) ? $exclude_raw : '' ) );
            $options['ddp_exclude_ids'] = ( !empty( $exclude_ids ) ? implode( ', ', $exclude_ids ) : '' );
            $options['ddp_statusmail'] = isset( $_POST['ddp_statusmail'] ) && 'on' === sanitize_text_field( wp_unslash( $_POST['ddp_statusmail'] ) );
            if ( isset( $_POST['ddp_statusmail_recipient'] ) ) {
                $recipients = DDP_Settings::parse_email_recipients( wp_unslash( $_POST['ddp_statusmail_recipient'] ) );
                $options['ddp_statusmail_recipient'] = implode( ', ', $recipients );
            }
            if ( isset( $_POST['ddp_schedule'] ) ) {
                $options['ddp_schedule'] = sanitize_text_field( wp_unslash( $_POST['ddp_schedule'] ) );
            }
            if ( isset( $_POST['ddp_keep'] ) ) {
                $options['ddp_keep'] = sanitize_text_field( wp_unslash( $_POST['ddp_keep'] ) );
            }
            $options['ddp_method'] = 'titlecompare';
            if ( isset( $_POST['ddp_resultslimit'] ) ) {
                $options['ddp_resultslimit'] = sanitize_text_field( wp_unslash( $_POST['ddp_resultslimit'] ) );
            }
            $options['ddp_redirects'] = false;
            $options['ddp_pts'] = $posttypes;
            $previous_interval = ( isset( $options['last_interval'] ) ? $options['last_interval'] : '' );
            $options = DDP_Settings::normalize_options( $options );
            $interval = $options['ddp_schedule'];
            if ( !empty( $options['ddp_enabled'] ) ) {
                $nextscheduled = wp_next_scheduled( 'ddp_cron' );
                $interval_changed = $previous_interval !== $interval;
                if ( !$nextscheduled || $interval_changed ) {
                    wp_clear_scheduled_hook( 'ddp_cron' );
                    wp_schedule_event( time(), $interval, 'ddp_cron' );
                }
                $options['last_interval'] = $interval;
            } else {
                wp_clear_scheduled_hook( 'ddp_cron' );
            }
            DDP_Settings::save_options( $options );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( __( 'Settings saved.', 'delete-duplicate-posts' ) ) . '</p></div>';
        }
        // CLEARING THE LOG
        if ( isset( $_POST['ddp_clearlog'], $_POST['_wpnonce'] ) ) {
            $nonce = wp_unslash( $_POST['_wpnonce'] );
            if ( !wp_verify_nonce( $nonce, 'ddp_clearlog_nonce' ) ) {
                die( esc_html( __( 'Whoops! Some error occured, try again, please!', 'delete-duplicate-posts' ) ) );
            }
            $table_name_log = $wpdb->prefix . 'ddp_log';
            $wpdb->query( "TRUNCATE {$table_name_log};" );
            //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            echo '<div class="updated"><p>' . esc_html( __( 'The log was cleared.', 'delete-duplicate-posts' ) ) . '</p></div>';
        }
        // REPAIR PLUGIN DATA TABLES
        if ( isset( $_POST['ddp_reactivate'], $_POST['_wpnonce'] ) ) {
            $nonce = wp_unslash( $_POST['_wpnonce'] );
            if ( !wp_verify_nonce( $nonce, 'ddp_reactivate_nonce' ) ) {
                die( esc_html( __( 'Whoops! Some error occured, try again, please!', 'delete-duplicate-posts' ) ) );
            }
            if ( !current_user_can( 'manage_options' ) ) {
                die( esc_html( __( 'You do not have sufficient permissions to perform this action.', 'delete-duplicate-posts' ) ) );
            }
            DDP_Install::install( false );
            DDP_Logger::log( 'Repaired plugin data tables' );
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Plugin data tables were repaired. Missing log and redirect tables were recreated. No posts were deleted.', 'delete-duplicate-posts' ) . '</p></div>';
        }
        $options = DDP_Settings::get_options();
        $is_pro = false;
        $show_redirects = false;
        $css_classes = ' free';
        $display_ads = true;
        ?>

		<div class="wrap fs-section <?php 
        echo esc_attr( $css_classes );
        ?>">
			<h1>Delete Duplicate Posts <span>v. <?php 
        echo esc_html( DDP_Settings::get_plugin_version() );
        ?></span></h1>
			<p class="ddp-page-intro"><?php 
        esc_html_e( 'Review duplicate pairs, choose what stays, and clean up with confidence.', 'delete-duplicate-posts' );
        ?></p>
			<?php 
        $totaldeleted = get_option( 'ddp_deleted_duplicates' );
        if ( isset( $_GET['welcome-message'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['welcome-message'] ) ) && !self::is_notice_dismissed( 'welcome' ) ) {
            ?>
				<div class="notice notice-success is-dismissible ddp-dismissible-notice ddp-welcome-message" data-ddp-dismiss="welcome">
					<h2>Delete Duplicate Posts</h2>
					<p><?php 
            esc_html_e( 'Thanks for installing. Scan your site for duplicate posts below to get started.', 'delete-duplicate-posts' );
            ?></p>
				</div>
				<?php 
        }
        ?>

			<nav class="nav-tab-wrapper" role="tablist" aria-label="<?php 
        esc_attr_e( 'Delete Duplicate Posts sections', 'delete-duplicate-posts' );
        ?>">
				<a id="ddp-tab-duplicates" href="#duplicates-tab" class="nav-tab fs-tab nav-tab-active home" role="tab" aria-selected="true" aria-controls="duplicates-tab"><?php 
        esc_html_e( 'Duplicates', 'delete-duplicate-posts' );
        ?></a>
				<a id="ddp-tab-log" href="#log-tab" class="nav-tab" role="tab" aria-selected="false" aria-controls="log-tab" tabindex="-1"><?php 
        esc_html_e( 'Log', 'delete-duplicate-posts' );
        ?></a>
				<a id="ddp-tab-settings" href="#settings-tab" class="nav-tab" role="tab" aria-selected="false" aria-controls="settings-tab" tabindex="-1"><?php 
        esc_html_e( 'Settings', 'delete-duplicate-posts' );
        ?></a>
				<a id="ddp-tab-redirects" href="#redirects-tab" class="nav-tab<?php 
        echo ( $show_redirects ? '' : ' pro' );
        ?>" role="tab" aria-selected="false" aria-controls="redirects-tab" tabindex="-1"><?php 
        esc_html_e( 'Redirects', 'delete-duplicate-posts' );
        ?></a>
			</nav>

			<div class="ddp_content_wrapper">
				<div class="ddp_content_cell">
					<div id="delete-duplicate-posts-tabs">
						<div id="duplicates-tab" class="tab-content" role="tabpanel" aria-labelledby="ddp-tab-duplicates">
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
                DDP_Settings::save_options( $options );
                wp_schedule_event( time(), $interval, 'ddp_cron' );
                //}
            }
        } else {
            wp_unschedule_hook( 'ddp_cron' );
        }
        $totaldeleted = get_option( 'ddp_deleted_duplicates' );
        $is_report_preview = !empty( $options['ddp_enabled'] ) && isset( $options['ddp_cron_mode'] ) && 'report' === $options['ddp_cron_mode'];
        $method_labels = array(
            'titlecompare'   => __( 'Matching titles', 'delete-duplicate-posts' ),
            'metacompare'    => __( 'Matching meta values', 'delete-duplicate-posts' ),
            'excerptcompare' => __( 'Matching excerpts', 'delete-duplicate-posts' ),
            'contentcompare' => __( 'Matching content', 'delete-duplicate-posts' ),
        );
        $current_method = ( isset( $options['ddp_method'], $method_labels[$options['ddp_method']] ) ? $method_labels[$options['ddp_method']] : $method_labels['titlecompare'] );
        $keep_label = ( isset( $options['ddp_keep'] ) && 'latest' === $options['ddp_keep'] ? __( 'Keep newest', 'delete-duplicate-posts' ) : __( 'Keep oldest', 'delete-duplicate-posts' ) );
        $is_permanent_removal = $is_pro && isset( $options['ddp_deletemode'] ) && 'permanent' === $options['ddp_deletemode'];
        $removal_label = ( $is_permanent_removal ? __( 'Delete permanently', 'delete-duplicate-posts' ) : __( 'Move to Trash', 'delete-duplicate-posts' ) );
        ?>
								<?php 
        self::render_scheduled_scan_summary( $options );
        ?>
								<section class="ddp-current-duplicates" aria-labelledby="ddp-current-duplicates-title">
									<header class="ddp-current-duplicates__header">
										<h3 id="ddp-current-duplicates-title">
											<?php 
        echo ( $is_report_preview ? esc_html__( 'Duplicate preview', 'delete-duplicate-posts' ) : esc_html__( 'Current duplicates', 'delete-duplicate-posts' ) );
        ?>
										</h3>
										<p>
											<?php 
        echo ( $is_report_preview ? esc_html__( 'This live table is your preview. Scheduled scans do not delete anything; deletion only happens if you select rows here and confirm the manual action.', 'delete-duplicate-posts' ) : esc_html__( 'This is a live review list. Nothing is removed until you select duplicates and confirm the manual deletion.', 'delete-duplicate-posts' ) );
        ?>
										</p>
										<ul class="ddp-review-rules" aria-label="<?php 
        esc_attr_e( 'Current duplicate review rules', 'delete-duplicate-posts' );
        ?>">
											<li><strong><?php 
        esc_html_e( 'Match:', 'delete-duplicate-posts' );
        ?></strong> <?php 
        echo esc_html( $current_method );
        ?></li>
											<li><strong><?php 
        esc_html_e( 'Keep:', 'delete-duplicate-posts' );
        ?></strong> <?php 
        echo esc_html( $keep_label );
        ?></li>
											<li><strong><?php 
        esc_html_e( 'Manual removal:', 'delete-duplicate-posts' );
        ?></strong> <?php 
        echo esc_html( $removal_label );
        ?></li>
										</ul>
										<p class="ddp-safety-note">
											<strong><?php 
        esc_html_e( 'Before deleting:', 'delete-duplicate-posts' );
        ?></strong>
											<?php 
        echo ( $is_permanent_removal ? esc_html__( 'Create a current backup. Manual removals cannot be undone with this setting.', 'delete-duplicate-posts' ) : esc_html__( 'Create a current backup. Manual removals go to WordPress Trash and can be restored.', 'delete-duplicate-posts' ) );
        ?>
										</p>
									</header>
								<div class="statusdiv">
									<div class="statusmessage"></div>
									<div class="errormessage"></div>
									<div id="ddp-operation-feedback" class="ddp-operation-feedback" role="status" aria-live="polite" hidden></div>
									<div class="dupelist">
										<div id="requestTime"></div>
										<table id="ddp_dupetable" class="wp-list-table widefat fixed striped table-view-list"></table>
									</div>
								</div>
								</section>
								<?php 
        if ( false !== $totaldeleted && 0 < $totaldeleted && $display_ads && !self::is_notice_dismissed( 'leavereview', 180 ) ) {
            $totaldeleted = number_format_i18n( $totaldeleted );
            ?>
									<div id="cp-ddp-reviewlink" class="updated notice notice-success is-dismissible ddp-dismissible-notice" data-ddp-dismiss="leavereview">
										<h3>
											<?php 
            /* translators: %s: Total number of deleted duplicates since install. */
            printf( esc_html__( '%s duplicates deleted in total since install!', 'delete-duplicate-posts' ), esc_html( $totaldeleted ) );
            ?>
										</h3>
										<p>
											<?php 
            /* translators: %s: Total number of deleted duplicates since install. */
            printf( esc_html__( "Hey, I noticed this plugin has deleted %s duplicate posts in total since install - that's awesome! Could you please do me a BIG favor and give it a 5-star rating on WordPress? Just to help us spread the word and boost our motivation.", 'delete-duplicate-posts' ), esc_html( $totaldeleted ) );
            ?>
										</p>
										<p>
											<a href="https://wordpress.org/support/plugin/delete-duplicate-posts/reviews/#new-post" class="button-secondary button button-small" target="_blank" rel="noopener noreferrer"><?php 
            esc_html_e( 'Ok, you deserve it', 'delete-duplicate-posts' );
            ?></a>
										</p>
									</div>
									<?php 
        }
        ?>



		<?php 
        $display_promotion = true;
        if ( $display_promotion ) {
            $target_url = self::upgrade_url( 'annually' );
            $lifetime_url = self::upgrade_url( 'lifetime' );
            ?>
	<div class="innerpromotion ddppro">
	<h3><?php 
            esc_html_e( 'Delete Duplicate Posts Pro', 'delete-duplicate-posts' );
            ?></h3>
	<p class="ddp-pro-intro"><?php 
            esc_html_e( 'When matching titles is not enough, Pro helps you find the right duplicates and protect SEO when you remove them.', 'delete-duplicate-posts' );
            ?></p>
	<ul class="linklist">
		<li><strong><?php 
            esc_html_e( 'Match by content, excerpt, or custom fields:', 'delete-duplicate-posts' );
            ?></strong> <?php 
            esc_html_e( 'Find duplicates with identical body content, the same excerpt, or any post meta value—not only matching titles.', 'delete-duplicate-posts' );
            ?></li>
		<li><strong><?php 
            esc_html_e( 'Scan more than published posts:', 'delete-duplicate-posts' );
            ?></strong> <?php 
            esc_html_e( 'Include drafts, scheduled, private, and other statuses in the same cleanup.', 'delete-duplicate-posts' );
            ?></li>
		<li><strong><?php 
            esc_html_e( 'Delete permanently when needed:', 'delete-duplicate-posts' );
            ?></strong> <?php 
            esc_html_e( 'Skip the trash and remove duplicates from the database when you are sure.', 'delete-duplicate-posts' );
            ?></li>
		<li><strong><?php 
            esc_html_e( '301 redirects you can manage:', 'delete-duplicate-posts' );
            ?></strong> <?php 
            esc_html_e( 'Send visitors from removed URLs to the post you kept, and review those redirects later.', 'delete-duplicate-posts' );
            ?></li>
	</ul>

			<a href="<?php 
            echo esc_url( $target_url );
            ?>" class="ddpprobutton button button-primary button-hero" target="_blank" rel="noopener noreferrer">
				<?php 
            /* translators: %s: Yearly price, e.g. $29.99/year. */
            printf( esc_html__( '%s/year', 'delete-duplicate-posts' ), '$29.99' );
            ?>
			</a>
	<p class="ddp-lifetime-offer">
			<?php 
            printf( 
                /* translators: 1: Lifetime price, 2: Link to lifetime checkout. */
                esc_html__( 'Prefer a one-time purchase? %1$s — %2$s', 'delete-duplicate-posts' ),
                '$59.99',
                '<a href="' . esc_url( $lifetime_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Get lifetime access', 'delete-duplicate-posts' ) . '</a>'
             );
            ?>
	</p>
	<div class="moneybackguarantee">
		<p><strong><?php 
            esc_html_e( '30-day money-back guarantee', 'delete-duplicate-posts' );
            ?></strong></p>
		<p><?php 
            esc_html_e( 'If the plugin does not work as expected and we cannot resolve the issue, we will consider a full refund within 30 days of purchase.', 'delete-duplicate-posts' );
            ?></p>
	</div>
	</div><!-- .sidebarrow -->

			<?php 
        }
        ?>









	
							</div><!-- #dashboard -->
						</div>

						<div id="log-tab" class="tab-content" role="tabpanel" aria-labelledby="ddp-tab-log" hidden>
							<div id="log">
								<div class="ddp-section-heading">
									<div>
										<h3><?php 
        esc_html_e( 'Activity log', 'delete-duplicate-posts' );
        ?></h3>
										<p><?php 
        esc_html_e( 'Recent scans, deletions, redirects, and email activity.', 'delete-duplicate-posts' );
        ?></p>
									</div>
									<form method="post" id="ddp_clearlog">
										<?php 
        wp_nonce_field( 'ddp_clearlog_nonce' );
        ?>
										<input class="button button-secondary" type="submit" name="ddp_clearlog" value="<?php 
        esc_attr_e( 'Clear activity log', 'delete-duplicate-posts' );
        ?>" data-confirm="<?php 
        esc_attr_e( 'Clear all activity log entries? This cannot be undone.', 'delete-duplicate-posts' );
        ?>" />
									</form>
								</div>
								<div class="spinner is-active"></div>
								<div class="ddp-log-viewport">
									<table id="ddp_log" class="wp-list-table widefat fixed striped table-view-list">
										<thead>
											<tr>
												<th scope="col" class="ddp-log-date"><?php 
        esc_html_e( 'Date and time', 'delete-duplicate-posts' );
        ?></th>
												<th scope="col"><?php 
        esc_html_e( 'Activity', 'delete-duplicate-posts' );
        ?></th>
											</tr>
										</thead>
										<tbody></tbody>
									</table>
									<p id="ddp-log-empty" class="ddp-empty-state" hidden><?php 
        esc_html_e( 'No activity has been recorded yet.', 'delete-duplicate-posts' );
        ?></p>
								</div>
							</div>
						</div>

						<div id="settings-tab" class="tab-content" role="tabpanel" aria-labelledby="ddp-tab-settings" hidden>
							<div id="ddp-configuration">
								<h3><?php 
        esc_html_e( 'Settings', 'delete-duplicate-posts' );
        ?></h3>
								<p class="ddp-section-intro"><?php 
        esc_html_e( 'Configure what counts as a duplicate, what happens during cleanup, and whether scans run automatically.', 'delete-duplicate-posts' );
        ?></p>
								<div class="ddp-settings-nav" role="tablist" aria-label="<?php 
        esc_attr_e( 'Settings groups', 'delete-duplicate-posts' );
        ?>">
									<button id="ddp-settings-tab-matching" type="button" class="ddp-settings-tab is-active" role="tab" aria-selected="true" aria-controls="ddp-settings-matching" data-ddp-settings-panel="ddp-settings-matching"><?php 
        esc_html_e( 'Matching', 'delete-duplicate-posts' );
        ?></button>
									<button id="ddp-settings-tab-cleanup" type="button" class="ddp-settings-tab" role="tab" aria-selected="false" aria-controls="ddp-settings-cleanup" data-ddp-settings-panel="ddp-settings-cleanup" tabindex="-1"><?php 
        esc_html_e( 'Cleanup', 'delete-duplicate-posts' );
        ?></button>
									<button id="ddp-settings-tab-automation" type="button" class="ddp-settings-tab" role="tab" aria-selected="false" aria-controls="ddp-settings-automation" data-ddp-settings-panel="ddp-settings-automation" tabindex="-1"><?php 
        esc_html_e( 'Automation', 'delete-duplicate-posts' );
        ?></button>
									<button id="ddp-settings-tab-maintenance" type="button" class="ddp-settings-tab" role="tab" aria-selected="false" aria-controls="ddp-settings-maintenance" data-ddp-settings-panel="ddp-settings-maintenance" tabindex="-1"><?php 
        esc_html_e( 'Support & maintenance', 'delete-duplicate-posts' );
        ?></button>
								</div>
							<form method="post" id="delete_duplicate_posts_options">
								<?php 
        wp_nonce_field( 'ddp-update-options' );
        ?>
								<table width="100%" cellspacing="2" cellpadding="5" class="form-table">
									<tbody id="ddp-settings-matching" class="ddp-settings-panel" role="tabpanel" aria-labelledby="ddp-settings-tab-matching">
									<tr class="ddp-settings-section">
										<td colspan="2">
											<h3><?php 
        esc_html_e( 'What to scan', 'delete-duplicate-posts' );
        ?></h3>
										</td>
									</tr>
									<tr valign="top">
										<th><label for="ddp_pts"><?php 
        esc_html_e( 'Post types', 'delete-duplicate-posts' );
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
                if ( 'attachment' === $pt ) {
                    echo '<small> ' . esc_html__( '(Media files are matched by title and removed with their files.)', 'delete-duplicate-posts' ) . '</small>';
                }
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

									<tr valign="top">
										<th><label for="ddp_exclude_ids"><?php 
        esc_html_e( 'Protected post IDs', 'delete-duplicate-posts' );
        ?></label></th>
										<td>
											<?php 
        $exclude_ids_value = ( isset( $options['ddp_exclude_ids'] ) ? $options['ddp_exclude_ids'] : '' );
        ?>
											<textarea name="ddp_exclude_ids" id="ddp_exclude_ids" class="large-text code" rows="2" cols="50"><?php 
        echo esc_textarea( $exclude_ids_value );
        ?></textarea>
											<p class="description">
												<?php 
        esc_html_e( 'Comma-separated post IDs to protect. They will never be listed for removal (manual or scheduled), but can still be kept as the original in a duplicate pair.', 'delete-duplicate-posts' );
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
        $stati = get_post_stati( array(), 'objects' );
        $checked_post_stati = $options['ddp_pstati'];
        if ( $stati ) {
            $locked_status_labels = array();
            ?>
												<ul class="checkbox">
													<?php 
            foreach ( $stati as $key => $st ) {
                if ( !$st->show_in_admin_status_list ) {
                    continue;
                }
                $is_free_status = 'publish' === $key;
                if ( !$is_pro && !$is_free_status ) {
                    $locked_status_labels[] = $st->label;
                    continue;
                }
                $checked = array_search( $key, $checked_post_stati, true );
                ?>
														<li>
															<input type="checkbox"
																name="ddp_pstati[]"
																id="ddp_pstatus-<?php 
                echo esc_attr( $key );
                ?>"
																value="<?php 
                echo esc_attr( $key );
                ?>"
																<?php 
                if ( false !== $checked ) {
                    echo ' checked';
                }
                if ( $is_free_status && !$is_pro ) {
                    echo ' checked';
                }
                ?>
															/>
															<label for="ddp_pstatus-<?php 
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
            }
            if ( !empty( $locked_status_labels ) ) {
                $locked_status_labels = array_map( static function ( $label ) {
                    return ( function_exists( 'mb_strtolower' ) ? mb_strtolower( $label ) : strtolower( $label ) );
                }, $locked_status_labels );
                $status_list = wp_sprintf_l( '%l', $locked_status_labels );
                ?>
														<li class="ddp-pro-teaser">
															<?php 
                echo wp_kses_post( self::pro_locked_row( __( 'Scan more post statuses', 'delete-duplicate-posts' ), sprintf( 
                    /* translators: %s: natural-language list of post statuses, e.g. "scheduled, draft, and private". */
                    __( 'Also scan %s posts.', 'delete-duplicate-posts' ),
                    $status_list
                 ) ) );
                ?>
														</li>
														<?php 
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
									<tr class="ddp-settings-section">
										<td colspan="2">
											<h3><?php 
        esc_html_e( 'How to identify duplicates', 'delete-duplicate-posts' );
        ?></h3>
										</td>
									</tr>
									<tr valign="top">
										<th><?php 
        esc_html_e( 'Comparison method', 'delete-duplicate-posts' );
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
        if ( $is_pro ) {
            ?>
													<li>
														<label>
															<input type="radio" name="ddp_method" value="metacompare" <?php 
            checked( 'metacompare', $comparemethod );
            ?> />
															<?php 
            esc_html_e( 'Compare by meta tag', 'delete-duplicate-posts' );
            ?>
															<span class="optiondesc"><?php 
            esc_html_e( 'Compare by any meta tag.', 'delete-duplicate-posts' );
            ?></span>
														</label>
														<?php 
            $metavalues = $wpdb->get_results( "SELECT DISTINCT meta_key FROM {$wpdb->postmeta} ORDER by meta_key;", ARRAY_A );
            ?>
														<div class="ddp-compare-details">

															<select name="ddp_compare_metatag" id="ddp_compare_metatag">
																<?php 
            if ( is_array( $metavalues ) ) {
                $selectedmeta = false;
                if ( isset( $options['ddp_compare_metatag'] ) ) {
                    $selectedmeta = $options['ddp_compare_metatag'];
                }
                if ( !$selectedmeta ) {
                    $selectedmeta = '';
                    $options['ddp_compare_metatag'] = '';
                }
                foreach ( $metavalues as $mv ) {
                    ?>
																		<option value="<?php 
                    echo esc_attr( $mv['meta_key'] );
                    ?>" <?php 
                    selected( esc_attr( $mv['meta_key'] ), $options['ddp_compare_metatag'] );
                    ?>>
																			<?php 
                    echo esc_attr( $mv['meta_key'] );
                    ?></option>
																		<?php 
                }
            }
            ?>
															</select>
														</div>

													</li>

													<li>
														<label>
															<input type="radio" name="ddp_method" value="excerptcompare" <?php 
            checked( 'excerptcompare', $comparemethod );
            ?> />
															<?php 
            esc_html_e( 'Compare by excerpt', 'delete-duplicate-posts' );
            ?>
															<span class="optiondesc"><?php 
            esc_html_e( 'Looks at the excerpt of the post. Posts with empty excerpts are never treated as duplicates.', 'delete-duplicate-posts' );
            ?></span>
														</label>
													</li>
													<li>
														<label>
															<input type="radio" name="ddp_method" value="contentcompare" <?php 
            checked( 'contentcompare', $comparemethod );
            ?> />
															<?php 
            esc_html_e( 'Compare by content', 'delete-duplicate-posts' );
            ?>
															<span class="optiondesc"><?php 
            esc_html_e( 'Matches posts with identical body content (hash compare). Posts with empty content are never treated as duplicates.', 'delete-duplicate-posts' );
            ?></span>
														</label>
													</li>
												<?php 
        }
        ?>

												<?php 
        if ( !$is_pro ) {
            ?>
													<li class="ddp-pro-teaser">
														<?php 
            echo wp_kses_post( self::pro_locked_row( __( 'Compare by meta tag', 'delete-duplicate-posts' ), __( 'Find duplicates by SKU, custom fields, or any post meta value.', 'delete-duplicate-posts' ) ) );
            ?>
													</li>
													<li class="ddp-pro-teaser">
														<?php 
            echo wp_kses_post( self::pro_locked_row( __( 'Compare by excerpt', 'delete-duplicate-posts' ), __( 'Catch posts that share a title but differ in content by matching on the excerpt instead. Empty excerpts are ignored.', 'delete-duplicate-posts' ) ) );
            ?>
													</li>
													<li class="ddp-pro-teaser">
														<?php 
            echo wp_kses_post( self::pro_locked_row( __( 'Compare by content', 'delete-duplicate-posts' ), __( 'Find exact body clones even when titles differ. Empty content is ignored.', 'delete-duplicate-posts' ) ) );
            ?>
													</li>
												<?php 
        }
        ?>
											</ul>
										</td>
									</tr>
									</tbody>
									<tbody id="ddp-settings-cleanup" class="ddp-settings-panel" role="tabpanel" aria-labelledby="ddp-settings-tab-cleanup" hidden>
									<tr class="ddp-settings-section">
										<td colspan="2">
											<h3><?php 
        esc_html_e( 'What to keep / remove', 'delete-duplicate-posts' );
        ?></h3>
										</td>
									</tr>
									<tr>
										<th><label for="ddp_keep"><?php 
        esc_html_e( 'Post to keep', 'delete-duplicate-posts' );
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
        $deletemode = ( isset( $options['ddp_deletemode'] ) ? $options['ddp_deletemode'] : 'trash' );
        if ( !$is_pro ) {
            $deletemode = 'trash';
        }
        ?>
									<tr valign="top">
										<th><?php 
        esc_html_e( 'Deletion method', 'delete-duplicate-posts' );
        ?></th>
										<td>
											<ul class="ddpcomparemethod">

												<li>
													<label>
														<input type="radio" name="ddp_deletemode" value="trash" <?php 
        checked( 'trash', $deletemode );
        ?> />
														<?php 
        esc_html_e( 'Move to trash (default)', 'delete-duplicate-posts' );
        ?>
														<span class="optiondesc"><?php 
        esc_html_e( 'Keeps posts recoverable from the WordPress trash.', 'delete-duplicate-posts' );
        ?></span>
													</label>
												</li>

												<?php 
        if ( $is_pro ) {
            ?>
													<li>
														<label>
															<input type="radio" name="ddp_deletemode" value="permanent" <?php 
            checked( 'permanent', $deletemode );
            ?> />
															<?php 
            esc_html_e( 'Delete permanently', 'delete-duplicate-posts' );
            ?>
															<span class="optiondesc"><?php 
            esc_html_e( 'Removes posts from the database. This cannot be undone.', 'delete-duplicate-posts' );
            ?></span>
														</label>
													</li>
												<?php 
        }
        ?>

												<?php 
        if ( !$is_pro ) {
            ?>
													<li class="ddp-pro-teaser">
														<?php 
            echo wp_kses_post( self::pro_locked_row( __( 'Delete permanently', 'delete-duplicate-posts' ), __( 'Remove duplicates from the database instead of moving them to trash.', 'delete-duplicate-posts' ) ) );
            ?>
													</li>
												<?php 
        }
        ?>
											</ul>
										</td>
									</tr>

									<tr class="ddp-settings-section">
										<td colspan="2">
											<h3><?php 
        esc_html_e( 'URL preservation', 'delete-duplicate-posts' );
        ?></h3>
										</td>
									</tr>
									<tr valign="top">
										<th><?php 
        esc_html_e( 'Preserve removed URLs with 301 redirects', 'delete-duplicate-posts' );
        ?></th>
										<td>
											<?php 
        if ( $is_pro ) {
            ?>
												<?php 
            $redirects_enabled = !empty( $options['ddp_redirects'] );
            $redirect_provider = ( isset( $options['ddp_redirect_provider'] ) ? $options['ddp_redirect_provider'] : 'builtin' );
            $redirection_ok = class_exists( 'Red_Item' ) && class_exists( 'Red_Group' );
            $provider_unavailable = 'redirection' === $redirect_provider && !$redirection_ok;
            ?>
												<label for="ddp_redirects">
													<input type="checkbox" id="ddp_redirects" name="ddp_redirects" <?php 
            checked( $redirects_enabled );
            ?>>
													<span class="description"><?php 
            esc_html_e( 'When a duplicate is removed, create a 301 redirect from its URL to the post you kept.', 'delete-duplicate-posts' );
            ?></span>
												</label>
												<div id="ddp-redirect-provider-wrap" class="ddp-redirect-provider-wrap" <?php 
            echo ( $redirects_enabled ? '' : 'hidden' );
            ?>>
													<p><strong><?php 
            esc_html_e( 'Store redirects in:', 'delete-duplicate-posts' );
            ?></strong></p>
													<ul class="ddpcomparemethod">
														<li>
															<label>
																<input type="radio" name="ddp_redirect_provider" value="builtin" <?php 
            checked( 'builtin', $redirect_provider );
            ?> />
																<?php 
            esc_html_e( 'Delete Duplicate Posts (built in)', 'delete-duplicate-posts' );
            ?>
																<span class="optiondesc"><?php 
            esc_html_e( 'Managed in this plugin’s Redirects tab.', 'delete-duplicate-posts' );
            ?></span>
															</label>
														</li>
														<?php 
            if ( $redirection_ok ) {
                ?>
														<li>
															<label>
																<input type="radio" name="ddp_redirect_provider" value="redirection" <?php 
                checked( 'redirection', $redirect_provider );
                ?> />
																<?php 
                esc_html_e( 'Redirection plugin', 'delete-duplicate-posts' );
                ?>
																<span class="optiondesc"><?php 
                esc_html_e( 'Stored in the Redirection “Delete Duplicate Posts” group and not listed here. Switching this setting does not move or delete existing records. Move on the Redirects tab only sends leftover built-in redirects into that group.', 'delete-duplicate-posts' );
                ?></span>
															</label>
														</li>
														<?php 
            } else {
                ?>
														<li class="description">
															<?php 
                esc_html_e( 'Install and activate the Redirection plugin to store redirects there instead. Until then, new redirects use the built-in store.', 'delete-duplicate-posts' );
                ?>
															<?php 
                if ( $provider_unavailable ) {
                    ?>
																<br /><strong><?php 
                    esc_html_e( 'Your preference is Redirection, but it is not available right now. New redirects fall back to the built-in store until Redirection is active again.', 'delete-duplicate-posts' );
                    ?></strong>
															<?php 
                }
                ?>
														</li>
														<?php 
            }
            ?>
													</ul>
													<?php 
            if ( 'builtin' === $redirect_provider || $provider_unavailable ) {
                ?>
													<p class="ddp-redirect-provider-actions">
														<a class="button button-secondary" href="<?php 
                echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ddp_export_redirects' ), 'ddp_export_redirects' ) );
                ?>">
															<?php 
                esc_html_e( 'Export managed redirects to CSV', 'delete-duplicate-posts' );
                ?>
														</a>
													</p>
													<?php 
            }
            ?>
												</div>
											<?php 
        }
        ?>
											<?php 
        if ( !$is_pro ) {
            ?>
												<div class="ddp-pro-teaser">
													<?php 
            echo wp_kses_post( self::pro_locked_row( __( 'Preserve traffic from removed URLs with 301 redirects', 'delete-duplicate-posts' ), __( 'Choose built-in storage, listed in this plugin, or the Redirection plugin. Redirects sent to Redirection are not listed here.', 'delete-duplicate-posts' ) ) );
            ?>
												</div>
											<?php 
        }
        ?>
										</td>
									</tr>

									</tbody>
									<tbody id="ddp-settings-automation" class="ddp-settings-panel" role="tabpanel" aria-labelledby="ddp-settings-tab-automation" hidden>
									<tr class="ddp-settings-section">
										<td colspan="2">
											<h3><?php 
        esc_html_e( 'Scheduled scans & notifications', 'delete-duplicate-posts' );
        ?></h3>
										</td>
									</tr>

									<tr valign="top">
										<th><?php 
        esc_html_e( 'Scheduled scans', 'delete-duplicate-posts' );
        ?>
										</th>
										<td><label for="ddp_enabled">
												<input type="checkbox" id="ddp_enabled" name="ddp_enabled" <?php 
        checked( !empty( $options['ddp_enabled'] ) );
        ?>>
												<p class="description">
													<?php 
        esc_html_e( 'Run a scheduled scan on the interval below. Choose report-only or automatic deletion in the next setting.', 'delete-duplicate-posts' );
        ?></p>
											</label>
										</td>
									</tr>

									<tr valign="top" class="ddp-schedule-dependent">
										<th><?php 
        esc_html_e( 'Scheduled scan mode', 'delete-duplicate-posts' );
        ?></th>
										<td>
											<?php 
        $cron_mode_setting = ( isset( $options['ddp_cron_mode'] ) ? $options['ddp_cron_mode'] : 'report' );
        ?>
											<ul class="ddpcomparemethod">
												<li>
													<label>
														<input type="radio" name="ddp_cron_mode" value="report" <?php 
        checked( 'report', $cron_mode_setting );
        ?> />
														<?php 
        esc_html_e( 'Report only (recommended)', 'delete-duplicate-posts' );
        ?>
														<span class="optiondesc"><?php 
        esc_html_e( 'Scan on schedule, log and email what would be removed. Nothing is deleted.', 'delete-duplicate-posts' );
        ?></span>
													</label>
												</li>
												<li>
													<label>
														<input type="radio" name="ddp_cron_mode" value="delete" <?php 
        checked( 'delete', $cron_mode_setting );
        ?> />
														<?php 
        esc_html_e( 'Delete automatically', 'delete-duplicate-posts' );
        ?>
														<span class="optiondesc"><?php 
        esc_html_e( 'Remove duplicates on schedule using your keep and deletion settings above.', 'delete-duplicate-posts' );
        ?></span>
													</label>
												</li>
											</ul>
										</td>
									</tr>

									<tr class="ddp-schedule-dependent">
										<th><label for="ddp_resultslimit"><?php 
        esc_html_e( 'Maximum per scan', 'delete-duplicate-posts' );
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

									<tr class="ddp-schedule-dependent">
										<th><label for="ddp_schedule"><?php 
        esc_html_e( 'Scan frequency', 'delete-duplicate-posts' );
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
                ?>" <?php 
                selected( ( isset( $options['ddp_schedule'] ) ? $options['ddp_schedule'] : '' ), $key );
                ?>><?php 
                echo esc_html( $sch['display'] );
                ?></option>
														<?php 
            }
        }
        ?>
											</select>
											<p class="description">
												<?php 
        esc_html_e( 'How often should the scheduled scan run?', 'delete-duplicate-posts' );
        ?></p>
										</td>
									</tr>
									<tr>
										<td colspan="2">
											<hr>
										</td>
									</tr>

									<tr class="ddp-schedule-dependent">
										<th><?php 
        esc_html_e( 'Email reports', 'delete-duplicate-posts' );
        ?></th>
										<td>
											<label for="ddp_statusmail">
												<input type="checkbox" id="ddp_statusmail" name="ddp_statusmail" <?php 
        checked( !empty( $options['ddp_statusmail'] ) );
        ?>>
												<p class="description">
													<?php 
        esc_html_e( 'Sends a status email if duplicates have been found.', 'delete-duplicate-posts' );
        ?>
												</p>
											</label>
										</td>
									</tr>

									<tr class="ddp-schedule-dependent ddp-email-dependent">
										<th><?php 
        esc_html_e( 'Email recipients', 'delete-duplicate-posts' );
        ?></th>
										<td>
											<label for="ddp_statusmail_recipient">

												<input type="text" class="regular-text" id="ddp_statusmail_recipient" name="ddp_statusmail_recipient" value="<?php 
        echo esc_attr( $options['ddp_statusmail_recipient'] );
        ?>">
												<p class="description">
													<?php 
        esc_html_e( 'Who should get the notification email. Separate multiple addresses with commas.', 'delete-duplicate-posts' );
        ?></p>
											</label>
										</td>
									</tr>



									</tbody>
								</table>
								<div class="ddp-settings-actions">
									<span class="ddp-settings-dirty" role="status" aria-live="polite"></span>
									<input type="submit" class="button button-primary" name="delete_duplicate_posts_save" value="<?php 
        esc_attr_e( 'Save settings', 'delete-duplicate-posts' );
        ?>" />
								</div>
							</form>
							<section id="ddp-settings-maintenance" class="ddp-settings-panel ddp-maintenance-panel" role="tabpanel" aria-labelledby="ddp-settings-tab-maintenance" hidden>
								<h3><?php 
        esc_html_e( 'Support and maintenance', 'delete-duplicate-posts' );
        ?></h3>
								<p><?php 
        esc_html_e( 'Email support is available to Pro customers.', 'delete-duplicate-posts' );
        ?></p>
								<p>
									<?php 
        esc_html_e( 'Free users:', 'delete-duplicate-posts' );
        ?>
									<a href="https://wordpress.org/support/plugin/delete-duplicate-posts/" target="_blank" rel="noopener noreferrer"><?php 
        esc_html_e( 'Visit the WordPress.org support forum', 'delete-duplicate-posts' );
        ?></a>
								</p>
								<hr />
								<h4><?php 
        esc_html_e( 'Repair plugin data tables', 'delete-duplicate-posts' );
        ?></h4>
								<p><?php 
        esc_html_e( 'Recreate missing log and redirect tables used by this plugin. This does not delete posts or change duplicate settings.', 'delete-duplicate-posts' );
        ?></p>
								<form method="post" id="ddp_reactivate">
									<?php 
        wp_nonce_field( 'ddp_reactivate_nonce' );
        ?>
									<input
										class="button button-secondary"
										type="submit"
										name="ddp_reactivate"
										id="ddp_reactivate_submit"
										value="<?php 
        esc_attr_e( 'Repair plugin data tables', 'delete-duplicate-posts' );
        ?>"
										data-confirm="<?php 
        esc_attr_e( 'Repair plugin data tables? This recreates missing Delete Duplicate Posts log and redirect tables. It does not delete any posts.', 'delete-duplicate-posts' );
        ?>"
									/>
								</form>
							</section>
							</div><!-- #configuration -->
						</div>


						<div id="redirects-tab" class="tab-content<?php 
        echo ( $show_redirects ? '' : ' pro' );
        ?>" role="tabpanel" aria-labelledby="ddp-tab-redirects" hidden>
							<?php 
        $rendered_pro_redirects = false;
        if ( !$rendered_pro_redirects ) {
            ?>
								<div class="ddp-redirects-upsell">
									<h3><?php 
            esc_html_e( 'URL protection for removed duplicates', 'delete-duplicate-posts' );
            ?></h3>
									<p><?php 
            esc_html_e( 'Preserve traffic from removed URLs with 301 redirects. Built-in redirects are listed here. Redirects sent to the Redirection plugin are not.', 'delete-duplicate-posts' );
            ?></p>
									<p>
										<a class="button button-primary" href="<?php 
            echo esc_url( ddp_fs()->get_upgrade_url() );
            ?>">
											<?php 
            esc_html_e( 'Explore Pro URL protection', 'delete-duplicate-posts' );
            ?>
										</a>
									</p>
								</div>
								<?php 
        }
        ?>
						</div>

					</div>
				</div>

				<?php 
        include_once DDP_PLUGIN_DIR . 'sidebar.php';
        ?>

				<div id="ddp-delete-dialog" class="ddp-modal" hidden>
					<div class="ddp-modal__backdrop" data-ddp-modal-close="1"></div>
					<div class="ddp-modal__panel" role="dialog" aria-modal="true" aria-labelledby="ddp-delete-dialog-title">
						<h2 id="ddp-delete-dialog-title"><?php 
        esc_html_e( 'Confirm deletion', 'delete-duplicate-posts' );
        ?></h2>
						<p id="ddp-delete-dialog-count"></p>
						<p id="ddp-delete-dialog-method"></p>
						<p id="ddp-delete-dialog-keep"></p>
						<p class="ddp-modal__preview-label" id="ddp-delete-dialog-preview-label"></p>
						<ul id="ddp-delete-dialog-list" class="ddp-modal__list"></ul>
						<p id="ddp-delete-dialog-more" class="ddp-modal__more" hidden></p>
						<div class="ddp-modal__actions">
							<button type="button" class="button" id="ddp-delete-dialog-cancel"><?php 
        esc_html_e( 'Cancel', 'delete-duplicate-posts' );
        ?></button>
							<button type="button" class="button button-primary ddp-delete-selected" id="ddp-delete-dialog-confirm"><?php 
        esc_html_e( 'Confirm delete', 'delete-duplicate-posts' );
        ?></button>
						</div>
					</div>
				</div>
				<div id="ddp-action-status" class="screen-reader-text" aria-live="polite"></div>

				<?php 
        if ( function_exists( 'ddp_fs' ) ) {
            global $ddp_fs;
        }
        ?>
			</div>

		</div>



		<script>
			jQuery(document).ready(function($) {
				const currentTabs = $('.nav-tab-wrapper [role="tab"]');
				const availableTabs = currentTabs.not('.pro');
				const tabPanels = $('#delete-duplicate-posts-tabs > .tab-content');

				function activateTab(tab, moveFocus) {
					const href = tab.attr('href');

					currentTabs
						.removeClass('nav-tab-active')
						.attr({'aria-selected': 'false', 'tabindex': '-1'});
					tab
						.addClass('nav-tab-active')
						.attr({'aria-selected': 'true', 'tabindex': '0'});

					tabPanels.prop('hidden', true);
					$(href).prop('hidden', false);
					window.history.replaceState(null, '', href);

					if (moveFocus) {
						tab.trigger('focus');
					}
				}

				const requestedTab = window.location.hash;
				const requestedLink = requestedTab ? currentTabs.filter('[href="' + requestedTab + '"]') : $();
				if (requestedLink.length) {
					activateTab(requestedLink, false);
				} else {
					activateTab(currentTabs.filter('.nav-tab-active').first(), false);
				}

				currentTabs.on('click', function(e) {
					const tab = $(this);
					if (tab.hasClass('pro')) {
						return;
					}
					e.preventDefault();
					activateTab(tab, false);
				});

				currentTabs.on('keydown', function(e) {
					const keys = ['ArrowLeft', 'ArrowRight', 'Home', 'End'];
					if (keys.indexOf(e.key) === -1) {
						return;
					}

					e.preventDefault();
					const currentIndex = availableTabs.index(this);
					let nextIndex = currentIndex;
					if ('Home' === e.key) {
						nextIndex = 0;
					} else if ('End' === e.key) {
						nextIndex = availableTabs.length - 1;
					} else if ('ArrowRight' === e.key) {
						nextIndex = (currentIndex + 1) % availableTabs.length;
					} else {
						nextIndex = (currentIndex - 1 + availableTabs.length) % availableTabs.length;
					}
					activateTab(availableTabs.eq(nextIndex), true);
				});
			});
		</script>








		<?php 
    }

}
