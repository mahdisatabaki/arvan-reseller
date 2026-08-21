<?php
/**
 * Custom capabilities — requirement G4, "permission handling".
 *
 * Reading a customer's ledger, adjusting their balance and deleting a live
 * server are three different levels of trust, so they get three different
 * capabilities. A reseller can hand the support desk read access to reports
 * without also handing over the ArvanCloud API keys.
 *
 * Nothing in this plugin checks manage_options directly.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Support;

defined( 'ABSPATH' ) || exit;

final class Capabilities {

	/** Read-only: dashboard, customers, ledger, usage, settlement reports. */
	public const VIEW_REPORTS = 'arvan_view_reports';

	/** Settings, API keys, branding, pricing, limits. Most privileged. */
	public const MANAGE = 'arvan_manage';

	/** Provision, suspend and terminate resources in the ArvanCloud account. */
	public const PROVISION = 'arvan_provision_services';

	/** The role assigned to customers who register on the reseller's site. */
	public const CUSTOMER_ROLE = 'arvan_customer';

	public const ALL = [
		self::VIEW_REPORTS,
		self::MANAGE,
		self::PROVISION,
	];

	/**
	 * Role => capabilities granted on activation.
	 *
	 * @return array<string, string[]>
	 */
	private static function map(): array {
		return [
			'administrator' => self::ALL,
			'editor'        => [ self::VIEW_REPORTS ],
		];
	}

	public static function grant(): void {
		foreach ( self::map() as $role_name => $caps ) {
			$role = get_role( $role_name );

			if ( null === $role ) {
				continue;
			}

			foreach ( $caps as $cap ) {
				$role->add_cap( $cap );
			}
		}

		self::registerCustomerRole();
	}

	public static function revoke(): void {
		foreach ( array_keys( self::map() ) as $role_name ) {
			$role = get_role( $role_name );

			if ( null === $role ) {
				continue;
			}

			foreach ( self::ALL as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Requirement C5. Customers get their own role with `read` and nothing
	 * else, so a shop account can never reach wp-admin content screens.
	 */
	public static function registerCustomerRole(): void {
		if ( null !== get_role( self::CUSTOMER_ROLE ) ) {
			return;
		}

		add_role(
			self::CUSTOMER_ROLE,
			__( 'مشتری آروان', 'arvan-reseller' ),
			[ 'read' => true ]
		);
	}

	/**
	 * True when the current user may see reseller admin screens at all.
	 */
	public static function currentUserCanView(): bool {
		return current_user_can( self::VIEW_REPORTS ) || current_user_can( self::MANAGE );
	}
}
