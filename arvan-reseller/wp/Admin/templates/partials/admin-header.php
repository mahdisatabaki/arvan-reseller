<?php
/**
 * Shared chrome for the 5 Reseller Admin pages (DESIGN.md §6): the same
 * `.wrap` + explicit `dir="rtl" lang="fa"` convention SetupWizard's own
 * template established (CLAUDE.md's UI Language & Direction requirement
 * applies to every plugin-owned admin screen, not just the wizard), plus a
 * persistent nav row across Dashboard/Customers/Services/Finance/Settings
 * (DESIGN.md §6's "Reseller Admin" list) and a small shared component
 * vocabulary (`.arvan-admin-card`/`-grid`/`-metric`/`-badge--*`) every page
 * template below reuses instead of each inventing its own.
 *
 * `require`d with the caller's own scope — same non-controller-owned
 * composition as the frontend's `partials/topbar.php` (see that file's
 * docblock). Opens `<div class="wrap arvan-admin" ...>` but does not close
 * it: each page template is responsible for its own closing `</div>`, the
 * same split topbar.php uses with `.arvan-app`.
 *
 * @package ArvanReseller
 *
 * @var string $activeSlug One of AdminMenu::SLUG_*.
 */

use ArvanReseller\Wp\Admin\AdminMenu;

defined( 'ABSPATH' ) || exit;

$arvan_admin_nav_items = [
	AdminMenu::SLUG_DASHBOARD => __( 'داشبورد', 'arvan-reseller' ),
	AdminMenu::SLUG_CUSTOMERS => __( 'مشتریان', 'arvan-reseller' ),
	AdminMenu::SLUG_SERVICES  => __( 'سرویس‌ها', 'arvan-reseller' ),
	AdminMenu::SLUG_FINANCE   => __( 'مالی', 'arvan-reseller' ),
	AdminMenu::SLUG_SETTINGS  => __( 'تنظیمات', 'arvan-reseller' ),
];
?>
<div class="wrap arvan-admin" dir="rtl" lang="fa">
	<style>
		.arvan-admin { direction: rtl; text-align: right; }
		.arvan-admin h1 { font-size: 24px; font-weight: 700; margin: 0 0 16px; }
		.arvan-admin h2 { font-size: 18px; font-weight: 700; }
		.arvan-admin-nav { display: flex; gap: 4px; list-style: none; margin: 0 0 20px; padding: 0; border-bottom: 1px solid #dcdcde; }
		.arvan-admin-nav li { margin: 0; }
		.arvan-admin-nav a { display: inline-block; padding: 10px 14px; text-decoration: none; font-weight: 600; color: #50575e; border-bottom: 2px solid transparent; }
		.arvan-admin-nav a.is-active { color: #2271b1; border-bottom-color: #2271b1; }
		.arvan-admin-card { background: #fff; border: 1px solid #dcdcde; border-radius: 6px; padding: 16px 20px; margin-bottom: 16px; }
		.arvan-admin-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
		.arvan-admin-metric { font-size: 22px; font-weight: 700; margin: 0; }
		.arvan-admin-metric-label { color: #646970; font-size: 13px; margin: 0 0 4px; }
		.arvan-admin table.widefat td, .arvan-admin table.widefat th { text-align: right; }
		.arvan-admin-badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
		.arvan-admin-badge--ok { background: #edfaef; color: #1a7f37; }
		.arvan-admin-badge--warn { background: #fff8e5; color: #9a6700; }
		.arvan-admin-badge--bad { background: #fdeded; color: #c62828; }
		.arvan-admin-badge--muted { background: #f0f0f1; color: #50575e; }
		.arvan-admin-tabs { display: flex; gap: 4px; list-style: none; margin: 0 0 16px; padding: 0; }
		.arvan-admin-tabs a { display: inline-block; padding: 8px 12px; text-decoration: none; font-weight: 600; color: #50575e; background: #f0f0f1; border-radius: 4px; }
		.arvan-admin-tabs a.is-active { background: #2271b1; color: #fff; }
		.arvan-admin-empty { color: #646970; padding: 24px 0; text-align: center; }
	</style>

	<nav>
		<ul class="arvan-admin-nav">
			<?php foreach ( $arvan_admin_nav_items as $arvan_nav_slug => $arvan_nav_label ) : ?>
				<li>
					<a class="<?php echo esc_attr( $activeSlug === $arvan_nav_slug ? 'is-active' : '' ); ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $arvan_nav_slug ) ); ?>">
						<?php echo esc_html( $arvan_nav_label ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>
