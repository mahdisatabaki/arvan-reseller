<?php
/**
 * The consolidated Reseller Admin menu (ADR-016, DESIGN.md §6): five pages
 * under one top-level "آروان ریسلر" item — Dashboard, Customers, Services,
 * Finance, Settings — instead of "12 independent pages" (BACKLOG Block 8's
 * own header note).
 *
 * This class owns page/menu *structure* only. Each controller still hooks
 * its own `admin_post_{action}` handlers via its own `register()` (called
 * separately in Plugin::bootAdmin()) — the same separation OrderController/
 * AuthController/RechargeController already established for the frontend:
 * "where are the pages" is not the same concern as "what happens on submit".
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Admin;

use ArvanReseller\Wp\Admin\Controllers\CustomersController;
use ArvanReseller\Wp\Admin\Controllers\DashboardController;
use ArvanReseller\Wp\Admin\Controllers\FinanceController;
use ArvanReseller\Wp\Admin\Controllers\ServicesController;
use ArvanReseller\Wp\Admin\Controllers\SettingsController;
use ArvanReseller\Wp\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

final class AdminMenu {

	public const SLUG_DASHBOARD = 'arvan-reseller-dashboard';
	public const SLUG_CUSTOMERS = 'arvan-reseller-customers';
	public const SLUG_SERVICES  = 'arvan-reseller-services';
	public const SLUG_FINANCE   = 'arvan-reseller-finance';
	public const SLUG_SETTINGS  = 'arvan-reseller-settings';

	public function __construct(
		private readonly DashboardController $dashboard,
		private readonly CustomersController $customers,
		private readonly ServicesController $services,
		private readonly FinanceController $finance,
		private readonly SettingsController $settings
	) {}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'registerPages' ] );
	}

	/**
	 * `add_menu_page()`'s own callback is intentionally left pointing at the
	 * Dashboard controller too (WordPress auto-creates a first submenu item
	 * with the top-level label otherwise); the explicit `add_submenu_page()`
	 * call right after it just relabels that first entry to "داشبورد"
	 * instead of repeating "آروان ریسلر" in the sidebar.
	 */
	public function registerPages(): void {
		add_menu_page(
			__( 'آروان ریسلر', 'arvan-reseller' ),
			__( 'آروان ریسلر', 'arvan-reseller' ),
			Capabilities::VIEW_REPORTS,
			self::SLUG_DASHBOARD,
			[ $this->dashboard, 'render' ],
			'dashicons-cloud',
			56
		);

		add_submenu_page(
			self::SLUG_DASHBOARD,
			__( 'داشبورد', 'arvan-reseller' ),
			__( 'داشبورد', 'arvan-reseller' ),
			Capabilities::VIEW_REPORTS,
			self::SLUG_DASHBOARD,
			[ $this->dashboard, 'render' ]
		);

		add_submenu_page(
			self::SLUG_DASHBOARD,
			__( 'مشتریان', 'arvan-reseller' ),
			__( 'مشتریان', 'arvan-reseller' ),
			Capabilities::VIEW_REPORTS,
			self::SLUG_CUSTOMERS,
			[ $this->customers, 'render' ]
		);

		add_submenu_page(
			self::SLUG_DASHBOARD,
			__( 'سرویس‌ها', 'arvan-reseller' ),
			__( 'سرویس‌ها', 'arvan-reseller' ),
			Capabilities::VIEW_REPORTS,
			self::SLUG_SERVICES,
			[ $this->services, 'render' ]
		);

		add_submenu_page(
			self::SLUG_DASHBOARD,
			__( 'مالی', 'arvan-reseller' ),
			__( 'مالی', 'arvan-reseller' ),
			Capabilities::VIEW_REPORTS,
			self::SLUG_FINANCE,
			[ $this->finance, 'render' ]
		);

		add_submenu_page(
			self::SLUG_DASHBOARD,
			__( 'تنظیمات', 'arvan-reseller' ),
			__( 'تنظیمات', 'arvan-reseller' ),
			Capabilities::MANAGE,
			self::SLUG_SETTINGS,
			[ $this->settings, 'render' ]
		);
	}
}
