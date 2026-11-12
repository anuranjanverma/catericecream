<?php
/**
 * Scoped enquiry access (BUILD_SPECIFICATION §13): the manage_cic_enquiries capability and an
 * "Enquiry manager" role that has only that capability plus read. Administrators also receive it.
 * Ordinary editors get nothing. Accounts for Yan and Web Karma are created by an administrator
 * once their email addresses are supplied; this code never creates users.
 *
 * Flamingo's own screens stay mapped to its default (administrator) capabilities, so the contact
 * book and Flamingo exports are not exposed to enquiry managers.
 *
 * @package CIC
 */

namespace CIC\Enquiries;

defined( 'ABSPATH' ) || exit;

final class Roles {

	public const CAPABILITY = 'manage_cic_enquiries';
	public const ROLE       = 'cic_enquiry_manager';

	/**
	 * WooCommerce redirects users without edit_posts away from wp-admin; enquiry managers need the
	 * dashboard to reach Enquiries (and nothing else is granted).
	 */
	public function register(): void {
		$allow = static fn( $prevent ) => current_user_can( self::CAPABILITY ) ? false : $prevent;
		add_filter( 'woocommerce_prevent_admin_access', $allow );
		add_filter( 'woocommerce_disable_admin_bar', $allow );
	}

	public static function install(): void {
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( self::CAPABILITY );
		}
		if ( ! get_role( self::ROLE ) ) {
			add_role( self::ROLE, __( 'Enquiry manager', 'cater-ice-cream-core' ), array( 'read' => true, self::CAPABILITY => true ) );
		}
	}

	/**
	 * Users who may be assigned enquiries (anyone holding the capability).
	 *
	 * @return array<int, \WP_User>
	 */
	public static function assignees(): array {
		$users = get_users( array( 'capability' => self::CAPABILITY, 'orderby' => 'display_name', 'fields' => 'all' ) );
		return array_values( array_filter( $users, static fn( $u ) => $u instanceof \WP_User ) );
	}
}
