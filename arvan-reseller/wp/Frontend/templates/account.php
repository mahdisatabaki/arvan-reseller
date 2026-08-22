<?php
/**
 * Customer Account Dashboard — `/arvan/account` (BACKLOG T-7.6,
 * SCREEN-SPECS.md §12, DESIGN.md §10).
 *
 * Same composition-at-the-template pattern as cdn.php/service-detail.php —
 * see cdn.php's docblock for why (`template_include` hands WordPress a bare
 * path, so there is no controller class already holding local variables).
 *
 * Read-only screen: no form submissions, no admin-post.php action, no
 * Controller class. The four tabs (Services/Transactions/Payments/Usage,
 * SCREEN-SPECS.md §12) are switched via a server-rendered `?tab=` query
 * string using `.arvan-tabs` — a full page reload per click, no JS.
 *
 * `findOwnedByCustomer()`/`*ForCustomer()` methods are the only lookups used
 * (SECURITY.md §6) — every repository call below is scoped to the resolved
 * customer id, never a bare id lookup.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

use ArvanReseller\Domain\Money;
use ArvanReseller\Lifecycle\ServiceStatus;
use ArvanReseller\Lifecycle\SuspensionEngine;
use ArvanReseller\Wp\Admin\ResellerSettings;
use ArvanReseller\Wp\Frontend\CurrentCustomer;
use ArvanReseller\Wp\Persistence\WpCustomerRepository;
use ArvanReseller\Wp\Persistence\WpLedgerRepository;
use ArvanReseller\Wp\Persistence\WpPaymentRepository;
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
				<p><?php esc_html_e( 'برای مشاهده‌ی حساب کاربری ابتدا وارد حساب کاربری خود شوید.', 'arvan-reseller' ); ?></p>
				<a class="arvan-btn arvan-btn--primary" href="<?php echo esc_url( home_url( '/arvan/auth' ) ); ?>"><?php esc_html_e( 'ورود / ثبت‌نام', 'arvan-reseller' ); ?></a>
			</div>
		</div>
	</div>
	<?php
	get_footer();
	return;
}

$customerId = (int) $customer['id'];

$walletRepository = new WpWalletRepository( $wpdb );
$walletBalance     = $walletRepository->currentBalance( $customerId );
$lowThreshold      = $walletRepository->lowBalanceThreshold( $customerId );

$serviceRepository = new WpServiceRepository( $wpdb );
$suspendedByWallet = $serviceRepository->findSuspendedByCustomer( $customerId, SuspensionEngine::REASON_WALLET );

$allowedTabs = [ 'services', 'transactions', 'payments', 'usage' ];
$activeTab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'services';
if ( ! in_array( $activeTab, $allowedTabs, true ) ) {
	$activeTab = 'services';
}

$tabLabels = [
	'services'     => __( 'سرویس‌ها', 'arvan-reseller' ),
	'transactions' => __( 'تراکنش‌ها', 'arvan-reseller' ),
	'payments'     => __( 'پرداخت‌ها', 'arvan-reseller' ),
	'usage'        => __( 'مصرف', 'arvan-reseller' ),
];

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
?>

<div class="arvan-app">
	<div class="arvan-container">

		<div class="arvan-card" style="margin-bottom: var(--arvan-space-4);">
			<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap: var(--arvan-space-3);">
				<div>
					<h1><?php esc_html_e( 'حساب کاربری', 'arvan-reseller' ); ?></h1>
					<p style="font-size: var(--arvan-font-size-lg); font-weight: 700;">
						<?php
						printf(
							/* translators: %s: wallet balance in Toman */
							esc_html__( 'موجودی کیف پول: %s تومان', 'arvan-reseller' ),
							esc_html( number_format_i18n( $walletBalance->toToman() ) )
						);
						?>
					</p>
				</div>
				<a class="arvan-btn arvan-btn--primary" href="<?php echo esc_url( home_url( '/arvan/recharge' ) ); ?>"><?php esc_html_e( 'افزایش اعتبار', 'arvan-reseller' ); ?></a>
			</div>

			<?php if ( [] !== $suspendedByWallet ) : ?>
				<div class="arvan-alert arvan-alert--warning">
					<?php esc_html_e( 'برخی سرویس‌های شما به دلیل اتمام اعتبار کیف پول معلق شده‌اند.', 'arvan-reseller' ); ?>
				</div>
			<?php elseif ( $walletBalance->lessThanOrEqual( $lowThreshold ) ) : ?>
				<div class="arvan-alert arvan-alert--info">
					<?php esc_html_e( 'موجودی کیف پول شما رو به اتمام است. برای جلوگیری از تعلیق سرویس‌ها اعتبار خود را افزایش دهید.', 'arvan-reseller' ); ?>
				</div>
			<?php endif; ?>
		</div>

		<nav class="arvan-tabs">
			<?php foreach ( $tabLabels as $tabKey => $tabLabel ) : ?>
				<a class="<?php echo esc_attr( $activeTab === $tabKey ? 'is-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( 'tab', $tabKey, home_url( '/arvan/account' ) ) ); ?>">
					<?php echo esc_html( $tabLabel ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<div class="arvan-card">

			<?php if ( 'services' === $activeTab ) : ?>

				<?php
				$allServices  = $serviceRepository->allForCustomer( $customerId );
				$usageLogRepo = new WpUsageLogRepository( $wpdb );
				?>

				<?php if ( [] === $allServices ) : ?>
					<div class="arvan-empty">
						<p><?php esc_html_e( 'هنوز هیچ سرویسی فعال نکرده‌اید.', 'arvan-reseller' ); ?></p>
						<a class="arvan-btn arvan-btn--primary" href="<?php echo esc_url( home_url( '/arvan/cdn' ) ); ?>"><?php esc_html_e( 'فعال‌سازی CDN', 'arvan-reseller' ); ?></a>
					</div>
				<?php else : ?>
					<div class="arvan-grid arvan-grid--cols-2">
						<?php foreach ( $allServices as $service ) : ?>
							<?php
							$status     = (string) $service['status'];
							$badgeClass = match ( $status ) {
								ServiceStatus::ACTIVE => 'arvan-badge--active',
								ServiceStatus::SUSPENDED => 'arvan-badge--suspended',
								ServiceStatus::PROVISIONING => 'arvan-badge--provisioning',
								ServiceStatus::TERMINATED => 'arvan-badge--terminated',
								default => 'arvan-badge--failed',
							};

							$recentUsage = $usageLogRepo->historyForCustomer( $customerId, (int) $service['id'], 1 )[0] ?? null;
							?>
							<a class="arvan-card" style="display:block; text-decoration:none; color:inherit;" href="<?php echo esc_url( home_url( '/arvan/account/services/' . (int) $service['id'] ) ); ?>">
								<div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap: var(--arvan-space-2);">
									<strong><?php echo esc_html( $service['domain'] ); ?></strong>
									<span class="arvan-badge <?php echo esc_attr( $badgeClass ); ?>"><?php echo esc_html( $statusLabels[ $status ] ?? $status ); ?></span>
								</div>
								<?php if ( null !== $recentUsage ) : ?>
									<p class="arvan-field__help">
										<?php
										printf(
											/* translators: 1: traffic in GB, 2: charge in Toman */
											esc_html__( 'ترافیک اخیر: %1$s گیگابایت — هزینه: %2$s تومان', 'arvan-reseller' ),
											esc_html( number_format_i18n( round( ( (int) $recentUsage['traffic_value'] ) / 1000000000, 3 ) ) ),
											esc_html( number_format_i18n( Money::fromRial( (int) $recentUsage['total_rial'] )->toToman() ) )
										);
										?>
									</p>
								<?php endif; ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

			<?php elseif ( 'transactions' === $activeTab ) : ?>

				<?php $ledgerRows = ( new WpLedgerRepository( $wpdb ) )->historyForCustomer( $customerId, 20 ); ?>

				<?php if ( [] === $ledgerRows ) : ?>
					<div class="arvan-empty"><?php esc_html_e( 'هنوز تراکنشی برای شما ثبت نشده است.', 'arvan-reseller' ); ?></div>
				<?php else : ?>
					<table>
						<thead>
							<tr>
								<th><?php esc_html_e( 'تاریخ', 'arvan-reseller' ); ?></th>
								<th><?php esc_html_e( 'شرح', 'arvan-reseller' ); ?></th>
								<th><?php esc_html_e( 'مبلغ (تومان)', 'arvan-reseller' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $ledgerRows as $row ) : ?>
								<?php
								$isCredit    = 'credit' === $row['direction'];
								$amountToman = Money::fromRial( (int) $row['amount_rial'] )->toToman();
								?>
								<tr>
									<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $row['created_at'] ) ); ?></td>
									<td><?php echo esc_html( ! empty( $row['description'] ) ? $row['description'] : (string) $row['category'] ); ?></td>
									<td><?php echo esc_html( ( $isCredit ? '+' : '−' ) . number_format_i18n( $amountToman ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

			<?php elseif ( 'payments' === $activeTab ) : ?>

				<?php
				$paymentRows         = ( new WpPaymentRepository( $wpdb ) )->historyForCustomer( $customerId, 20 );
				$paymentStatusLabels = [
					'pending'   => __( 'در انتظار', 'arvan-reseller' ),
					'succeeded' => __( 'موفق', 'arvan-reseller' ),
					'failed'    => __( 'ناموفق', 'arvan-reseller' ),
				];
				?>

				<?php if ( [] === $paymentRows ) : ?>
					<div class="arvan-empty"><?php esc_html_e( 'هنوز پرداختی برای شما ثبت نشده است.', 'arvan-reseller' ); ?></div>
				<?php else : ?>
					<table>
						<thead>
							<tr>
								<th><?php esc_html_e( 'تاریخ', 'arvan-reseller' ); ?></th>
								<th><?php esc_html_e( 'مبلغ (تومان)', 'arvan-reseller' ); ?></th>
								<th><?php esc_html_e( 'وضعیت', 'arvan-reseller' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $paymentRows as $row ) : ?>
								<?php
								$paymentStatus = (string) $row['status'];
								$paymentBadge  = match ( $paymentStatus ) {
									'succeeded' => 'arvan-badge--active',
									'pending' => 'arvan-badge--suspended',
									default => 'arvan-badge--failed',
								};
								?>
								<tr>
									<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $row['created_at'] ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( Money::fromRial( (int) $row['amount_rial'] )->toToman() ) ); ?></td>
									<td><span class="arvan-badge <?php echo esc_attr( $paymentBadge ); ?>"><?php echo esc_html( $paymentStatusLabels[ $paymentStatus ] ?? $paymentStatus ); ?></span></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

			<?php else : ?>

				<?php
				// Usage tab. The port has no join to domain names, so build the
				// service_id => domain map ourselves from allForCustomer().
				$domainByServiceId = [];
				foreach ( $serviceRepository->allForCustomer( $customerId ) as $svc ) {
					$domainByServiceId[ (int) $svc['id'] ] = $svc['domain'];
				}

				$usageRows = ( new WpUsageLogRepository( $wpdb ) )->historyForCustomer( $customerId, null, 20 );
				?>

				<?php if ( [] === $usageRows ) : ?>
					<div class="arvan-empty"><?php esc_html_e( 'هنوز هیچ دوره‌ی مصرفی ثبت نشده است.', 'arvan-reseller' ); ?></div>
				<?php else : ?>
					<table>
						<thead>
							<tr>
								<th><?php esc_html_e( 'دوره', 'arvan-reseller' ); ?></th>
								<th><?php esc_html_e( 'دامنه', 'arvan-reseller' ); ?></th>
								<th><?php esc_html_e( 'ترافیک خروجی', 'arvan-reseller' ); ?></th>
								<th><?php esc_html_e( 'هزینه (تومان)', 'arvan-reseller' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $usageRows as $row ) : ?>
								<tr>
									<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $row['period_start'] ) . ' – ' . mysql2date( 'Y/m/d H:i', $row['period_end'] ) ); ?></td>
									<td><?php echo esc_html( $domainByServiceId[ (int) $row['service_id'] ] ?? '' ); ?></td>
									<td><?php echo esc_html( number_format_i18n( round( ( (int) $row['traffic_value'] ) / 1000000000, 3 ) ) . ' GB' ); ?></td>
									<td><?php echo esc_html( number_format_i18n( Money::fromRial( (int) $row['total_rial'] )->toToman() ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

			<?php endif; ?>

		</div>

	</div>
</div>

<?php
get_footer();
