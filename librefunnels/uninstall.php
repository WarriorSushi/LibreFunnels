<?php
/**
 * Uninstall cleanup.
 *
 * @package LibreFunnels
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'librefunnels_version' );
