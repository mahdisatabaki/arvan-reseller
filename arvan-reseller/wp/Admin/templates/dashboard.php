<?php
/**
 * Admin Dashboard view. Rendered by DashboardController::render() via
 * `require`, which runs in that method's own scope — every variable
 * referenced below is set there (same convention as
 * SetupWizard::renderTemplate()).
 *
 * @package ArvanReseller
 *
 * @var string $activeSlug
 * @var array<int, array<string, mixed>> $allServices
 * @var array<string, int> $statusCounts
 * @var int $totalCustomers
 * @var \ArvanReseller\Domain\Money $totalBalance
 * @var int $lowBalanceWarnings
 * @var array{traffic_value:int, base_rial:int, markup_rial:int, total_rial:int} $todayTotals
 * @var array{traffic_value:int, base_rial:int, markup_rial:int, total_rial:int} $allTimeTotals
 * @var int $activeServices
 * @var int $suspendedServices
 * @var string $runBillingAction
 */

use ArvanReseller\Domain\Money;
use ArvanReseller\Wp\Admin\AdminMenu;

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/partials/admin-header.php';
?>

	<h1><?php esc_html_e( 'داشبورد', 'arvan-reseller' ); ?></h1>

	<?php if ( 0 === $totalCustomers ) : ?>
		<div class="arvan-admin-card">
			<p><?php esc_html_e( 'هنوز هیچ مشتری‌ای ثبت‌نام نکرده است. پس از فروش اولین سرویس، آمار اینجا نمایش داده می‌شود.', 'arvan-reseller' ); ?></p>
		</div>
	<?php endif; ?>

	<div class="arvan-admin-grid">
		<div class="arvan-admin-card">
			<p class="arvan-admin-metric-label"><?php esc_html_e( 'مشتریان', 'arvan-reseller' ); ?></p>
			<p class="arvan-admin-metric"><?php echo esc_html( number_format_i18n( $totalCustomers ) ); ?></p>
		</div>
		<div class="arvan-admin-card">
			<p class="arvan-admin-metric-label"><?php esc_html_e( 'سرویس‌های فعال', 'arvan-reseller' ); ?></p>
			<p class="arvan-admin-metric"><?php echo esc_html( number_format_i18n( $activeServices ) ); ?></p>
		</div>
		<div class="arvan-admin-card">
			<p class="arvan-admin-metric-label"><?php esc_html_e( 'سرویس‌های معلق', 'arvan-reseller' ); ?></p>
			<p class="arvan-admin-metric"><?php echo esc_html( number_format_i18n( $suspendedServices ) ); ?></p>
		</div>
		<div class="arvan-admin-card">
			<p class="arvan-admin-metric-label"><?php esc_html_e( 'مجموع موجودی کیف پول‌ها (تومان)', 'arvan-reseller' ); ?></p>
			<p class="arvan-admin-metric"><?php echo esc_html( number_format_i18n( $totalBalance->toToman() ) ); ?></p>
		</div>
		<div class="arvan-admin-card">
			<p class="arvan-admin-metric-label"><?php esc_html_e( 'هزینه‌ی امروز (تومان)', 'arvan-reseller' ); ?></p>
			<p class="arvan-admin-metric"><?php echo esc_html( number_format_i18n( Money::fromRial( $todayTotals['total_rial'] )->toToman() ) ); ?></p>
		</div>
		<div class="arvan-admin-card">
			<p class="arvan-admin-metric-label"><?php esc_html_e( 'مجموع سود ریسلر (تومان)', 'arvan-reseller' ); ?></p>
			<p class="arvan-admin-metric"><?php echo esc_html( number_format_i18n( Money::fromRial( $allTimeTotals['markup_rial'] )->toToman() ) ); ?></p>
		</div>
	</div>

	<?php if ( $lowBalanceWarnings > 0 ) : ?>
		<div class="arvan-admin-card" style="border-color:#f0c33c;">
			<span class="arvan-admin-badge arvan-admin-badge--warn">
				<?php
				printf(
					/* translators: %s: number of customers */
					esc_html__( '%s مشتری در آستانه‌ی اتمام اعتبار کیف پول هستند', 'arvan-reseller' ),
					esc_html( number_format_i18n( $lowBalanceWarnings ) )
				);
				?>
			</span>
		</div>
	<?php endif; ?>

	<div class="arvan-admin-card">
		<h2><?php esc_html_e( 'اقدامات', 'arvan-reseller' ); ?></h2>
		<p>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_CUSTOMERS ) ); ?>"><?php esc_html_e( 'مشتریان', 'arvan-reseller' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_SERVICES ) ); ?>"><?php esc_html_e( 'سرویس‌ها', 'arvan-reseller' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETTINGS ) ); ?>"><?php esc_html_e( 'تنظیمات', 'arvan-reseller' ); ?></a>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $runBillingAction ); ?>" />
			<?php wp_nonce_field( $runBillingAction ); ?>
			<button type="submit" class="button button-primary"><?php esc_html_e( 'اجرای فوری چرخه‌ی صورتحساب', 'arvan-reseller' ); ?></button>
		</form>
	</div>

</div>
