<?php
/**
 * Customer Service Detail — `/arvan/account/services/{id}` (BACKLOG T-7.5,
 * T-13/SCREEN-SPECS.md §13). Also the page a successful or failed CDN order
 * (T-7.3's OrderController) redirects straight to, which is what
 * SCREEN-SPECS.md §11 "Provisioning Result" turns out to be: since
 * `ProvisioningService::provision()` calls the CDN provider synchronously
 * (BACKLOG T-4.2 — there is no async job queue in this MVP), the service's
 * status is already `active` or `provisioning_failed` by the time this page
 * renders, so "Loading" is not a fake instant state here — it is a real one,
 * shown only if a service is ever actually left in `provisioning` (e.g. a
 * future retry path, T-4.4's `ResourceSyncService`, revisits it before this
 * page is reloaded). One template covers both specs rather than two, since
 * they describe the same status-driven view of the same row.
 *
 * Same composition-at-the-template pattern as cdn.php — see that file's
 * docblock for why (`template_include` hands WordPress a bare path, so there
 * is no controller class already holding local variables the way
 * SetupWizard's view has).
 *
 * `findOwnedByCustomer()` is the only lookup used (SECURITY.md §6): a
 * service id for another customer renders the same "not found" state as one
 * that does not exist at all, never a distinguishing error.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

use ArvanReseller\Domain\Money;
use ArvanReseller\Lifecycle\ServiceStatus;
use ArvanReseller\Wp\Admin\ResellerSettings;
use ArvanReseller\Wp\Frontend\CurrentCustomer;
use ArvanReseller\Wp\Frontend\RouteRegistrar;
use ArvanReseller\Wp\Persistence\WpCustomerRepository;
use ArvanReseller\Wp\Persistence\WpServiceRepository;
use ArvanReseller\Wp\Persistence\WpUsageLogRepository;
use ArvanReseller\Wp\Persistence\WpWalletRepository;

defined( 'ABSPATH' ) || exit;

global $wpdb;

$customer = ( new CurrentCustomer( new WpCustomerRepository( $wpdb ) ) )->resolve();

get_header();

$arvan_customer            = $customer;
$arvan_wallet_balance_rial = null !== $customer ? ( new WpWalletRepository( $wpdb ) )->currentBalance( (int) $customer['id'] )->toRial() : null;
$arvan_business_name       = ( new ResellerSettings() )->getBusinessProfile()['name'];
require __DIR__ . '/partials/topbar.php';

if ( null === $customer ) {
	?>
	<div class="arvan-app">
		<div class="arvan-container">
			<div class="arvan-empty">
				<p><?php esc_html_e( 'برای مشاهده‌ی این صفحه ابتدا وارد حساب کاربری خود شوید.', 'arvan-reseller' ); ?></p>
				<a class="arvan-btn arvan-btn--primary" href="<?php echo esc_url( home_url( '/arvan/auth' ) ); ?>"><?php esc_html_e( 'ورود / ثبت‌نام', 'arvan-reseller' ); ?></a>
			</div>
		</div>
	</div>
	<?php
	get_footer();
	return;
}

$serviceId = (int) get_query_var( RouteRegistrar::QUERY_VAR_SERVICE_ID );
$service   = ( new WpServiceRepository( $wpdb ) )->findOwnedByCustomer( $serviceId, (int) $customer['id'] );

if ( null === $service ) {
	?>
	<div class="arvan-app">
		<div class="arvan-container">
			<div class="arvan-empty">
				<p><?php esc_html_e( 'سرویسی با این مشخصات یافت نشد.', 'arvan-reseller' ); ?></p>
				<a class="arvan-btn arvan-btn--secondary" href="<?php echo esc_url( home_url( '/arvan/account' ) ); ?>"><?php esc_html_e( 'بازگشت به حساب کاربری', 'arvan-reseller' ); ?></a>
			</div>
		</div>
	</div>
	<?php
	get_footer();
	return;
}

$status = (string) $service['status'];

$statusLabels = [
	ServiceStatus::PROVISIONING        => __( 'در حال آماده‌سازی', 'arvan-reseller' ),
	ServiceStatus::ACTIVE              => __( 'فعال', 'arvan-reseller' ),
	ServiceStatus::SUSPENDED           => __( 'معلق', 'arvan-reseller' ),
	ServiceStatus::TERMINATED          => __( 'خاتمه‌یافته', 'arvan-reseller' ),
	ServiceStatus::PROVISIONING_FAILED => __( 'خطا در راه‌اندازی', 'arvan-reseller' ),
	ServiceStatus::SUSPEND_FAILED      => __( 'خطا در تعلیق', 'arvan-reseller' ),
	ServiceStatus::RESUME_FAILED       => __( 'خطا در ازسرگیری', 'arvan-reseller' ),
	ServiceStatus::TERMINATE_FAILED    => __( 'خطا در خاتمه', 'arvan-reseller' ),
];

$badgeClass = match ( $status ) {
	ServiceStatus::ACTIVE => 'arvan-badge--active',
	ServiceStatus::SUSPENDED => 'arvan-badge--suspended',
	ServiceStatus::PROVISIONING => 'arvan-badge--provisioning',
	ServiceStatus::TERMINATED => 'arvan-badge--terminated',
	default => 'arvan-badge--failed',
};

$usageHistory = ( new WpUsageLogRepository( $wpdb ) )->historyForCustomer( (int) $customer['id'], (int) $service['id'], 10 );
?>

<div class="arvan-app">
	<div class="arvan-container">

		<div class="arvan-card" style="margin-bottom: var(--arvan-space-4);">
			<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap: var(--arvan-space-2);">
				<h1><?php echo esc_html( $service['domain'] ); ?></h1>
				<span class="arvan-badge <?php echo esc_attr( $badgeClass ); ?>"><?php echo esc_html( $statusLabels[ $status ] ?? $status ); ?></span>
			</div>

			<p class="arvan-field__help">
				<?php
				if ( ! empty( $service['arvan_resource_id'] ) ) {
					printf(
						/* translators: %s: provider resource identifier */
						esc_html__( 'شناسه‌ی سرویس: %s', 'arvan-reseller' ),
						esc_html( $service['arvan_resource_id'] )
					);
				}
				?>
			</p>
			<p class="arvan-field__help">
				<?php
				printf(
					/* translators: %s: creation date */
					esc_html__( 'تاریخ ایجاد: %s', 'arvan-reseller' ),
					esc_html( mysql2date( 'Y/m/d H:i', $service['created_at'] ) )
				);
				?>
			</p>

			<?php if ( ServiceStatus::PROVISIONING === $status ) : ?>
				<div class="arvan-alert arvan-alert--info">
					<?php esc_html_e( 'در حال ایجاد سرویس CDN شما هستیم…', 'arvan-reseller' ); ?>
				</div>
			<?php elseif ( ServiceStatus::PROVISIONING_FAILED === $status ) : ?>
				<div class="arvan-alert arvan-alert--danger">
					<?php esc_html_e( 'راه‌اندازی این سرویس با خطا مواجه شد. لطفاً دوباره از صفحه‌ی CDN تلاش کنید یا با پشتیبانی تماس بگیرید.', 'arvan-reseller' ); ?>
				</div>
				<a class="arvan-btn arvan-btn--primary" href="<?php echo esc_url( home_url( '/arvan/cdn' ) ); ?>"><?php esc_html_e( 'تلاش مجدد', 'arvan-reseller' ); ?></a>
			<?php elseif ( ServiceStatus::SUSPENDED === $status ) : ?>
				<div class="arvan-alert arvan-alert--warning">
					<p>
						<?php
						echo 'wallet' === ( $service['suspend_reason'] ?? '' )
							? esc_html__( 'این سرویس به دلیل اتمام اعتبار کیف پول معلق شده است.', 'arvan-reseller' )
							: esc_html__( 'این سرویس در حال حاضر معلق است.', 'arvan-reseller' );
						?>
					</p>
					<p><?php esc_html_e( 'با افزایش اعتبار و مثبت شدن موجودی کیف پول، این سرویس به‌طور خودکار از سر گرفته می‌شود.', 'arvan-reseller' ); ?></p>
				</div>
				<a class="arvan-btn arvan-btn--primary" href="<?php echo esc_url( home_url( '/arvan/recharge' ) ); ?>"><?php esc_html_e( 'افزایش اعتبار', 'arvan-reseller' ); ?></a>
			<?php elseif ( ServiceStatus::TERMINATED === $status ) : ?>
				<div class="arvan-alert arvan-alert--info">
					<?php esc_html_e( 'این سرویس به‌طور دائم خاتمه یافته و غیرقابل بازگشت است.', 'arvan-reseller' ); ?>
				</div>
			<?php elseif ( in_array( $status, [ ServiceStatus::SUSPEND_FAILED, ServiceStatus::RESUME_FAILED, ServiceStatus::TERMINATE_FAILED ], true ) ) : ?>
				<div class="arvan-alert arvan-alert--danger">
					<?php esc_html_e( 'در به‌روزرسانی وضعیت این سرویس نزد ارائه‌دهنده خطایی رخ داده است. تیم پشتیبانی در جریان است.', 'arvan-reseller' ); ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="arvan-card">
			<h2><?php esc_html_e( 'ترافیک و هزینه‌های اخیر', 'arvan-reseller' ); ?></h2>

			<?php if ( [] === $usageHistory ) : ?>
				<div class="arvan-empty"><?php esc_html_e( 'هنوز هیچ دوره‌ی مصرفی برای این سرویس ثبت نشده است.', 'arvan-reseller' ); ?></div>
			<?php else : ?>
				<table>
					<thead>
						<tr>
							<th><?php esc_html_e( 'دوره', 'arvan-reseller' ); ?></th>
							<th><?php esc_html_e( 'ترافیک خروجی', 'arvan-reseller' ); ?></th>
							<th><?php esc_html_e( 'هزینه (تومان)', 'arvan-reseller' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $usageHistory as $row ) : ?>
							<tr>
								<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $row['period_start'] ) . ' – ' . mysql2date( 'Y/m/d H:i', $row['period_end'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( round( ( (int) $row['traffic_value'] ) / 1000000000, 3 ) ) . ' GB' ); ?></td>
								<td><?php echo esc_html( number_format_i18n( Money::fromRial( (int) $row['total_rial'] )->toToman() ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

	</div>
</div>

<?php
get_footer();
