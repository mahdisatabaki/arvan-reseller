<?php
/**
 * Admin Customers — list + detail (BACKLOG T-8.2, SCREEN-SPECS.md §3-4,
 * DESIGN.md §13). Rendered by CustomersController::renderList()/
 * renderDetail() via `require` (same composition convention as
 * dashboard.php/settings.php) — every variable referenced below is set
 * there.
 *
 * The two states share this one file, branching on whether `$customer` is
 * non-null — the same single-template-two-states pattern account.php uses
 * for `?tab=`, just switched on `?customer_id=` presence instead.
 *
 * @package ArvanReseller
 *
 * @var string $activeSlug
 * @var int $customerId
 * @var array<string, mixed>|null $customer
 * @var string $adjustError
 * @var bool $adjustSuccess
 *
 * List-state vars (set when $customer is null and $customerId is 0):
 * @var array<int, array<string, mixed>> $customerRows
 * @var array<int, \ArvanReseller\Domain\Money> $balances
 * @var array<int, int> $serviceCounts
 * @var array<int, array<string, mixed>|null> $recentUsageByCustomer
 *
 * Detail-state vars (set when $customer is non-null):
 * @var \ArvanReseller\Domain\Money $walletBalance
 * @var \ArvanReseller\Domain\Money $lowThreshold
 * @var \ArvanReseller\Domain\Money $resumeThreshold
 * @var array<int, array<string, mixed>> $customerServices
 * @var array<int, array<string, mixed>> $paymentRows
 * @var array<int, array<string, mixed>> $ledgerRows
 * @var array<int, array<string, mixed>> $usageRows
 * @var string $adjustAction
 * @var string $adjustNonceAction
 */

use ArvanReseller\Domain\Money;
use ArvanReseller\Lifecycle\ServiceStatus;
use ArvanReseller\Wp\Admin\AdminMenu;

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/partials/admin-header.php';

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

$serviceBadgeClass = static function ( string $status ): string {
	return match ( $status ) {
		ServiceStatus::ACTIVE => 'arvan-admin-badge--ok',
		ServiceStatus::SUSPENDED, ServiceStatus::PROVISIONING => 'arvan-admin-badge--warn',
		ServiceStatus::TERMINATED, ServiceStatus::PROVISIONING_FAILED, ServiceStatus::SUSPEND_FAILED, ServiceStatus::RESUME_FAILED, ServiceStatus::TERMINATE_FAILED => 'arvan-admin-badge--bad',
		default => 'arvan-admin-badge--muted',
	};
};

$paymentStatusLabels = [
	'pending'   => __( 'در انتظار', 'arvan-reseller' ),
	'succeeded' => __( 'موفق', 'arvan-reseller' ),
	'failed'    => __( 'ناموفق', 'arvan-reseller' ),
];

$adjustErrorMessages = [
	'customer_not_found' => __( 'مشتری موردنظر یافت نشد.', 'arvan-reseller' ),
	'invalid_direction'  => __( 'جهت تراکنش نامعتبر است.', 'arvan-reseller' ),
	'invalid_amount'     => __( 'مبلغ باید بزرگ‌تر از صفر باشد.', 'arvan-reseller' ),
	'reason_required'    => __( 'وارد کردن دلیل الزامی است.', 'arvan-reseller' ),
	'not_confirmed'      => __( 'برای اعمال تغییر، لطفاً تأیید را علامت بزنید.', 'arvan-reseller' ),
	'adjustment_failed'  => __( 'اعمال تغییر دستی ناموفق بود. مقادیر واردشده را بررسی کنید.', 'arvan-reseller' ),
];
?>

	<h1><?php esc_html_e( 'مشتریان', 'arvan-reseller' ); ?></h1>

	<?php if ( $adjustSuccess ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'تغییر دستی موجودی با موفقیت ثبت شد.', 'arvan-reseller' ); ?></p></div>
	<?php endif; ?>

	<?php if ( '' !== $adjustError && isset( $adjustErrorMessages[ $adjustError ] ) ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $adjustErrorMessages[ $adjustError ] ); ?></p></div>
	<?php endif; ?>

	<?php if ( null === $customer ) : ?>

		<?php if ( $customerId > 0 ) : ?>

			<div class="arvan-admin-card">
				<div class="arvan-admin-empty"><?php esc_html_e( 'مشتری موردنظر یافت نشد.', 'arvan-reseller' ); ?></div>
				<p>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_CUSTOMERS ) ); ?>">
						<?php esc_html_e( 'بازگشت به فهرست مشتریان', 'arvan-reseller' ); ?>
					</a>
				</p>
			</div>

		<?php else : ?>

			<div class="arvan-admin-card">
				<?php if ( [] === $customerRows ) : ?>
					<div class="arvan-admin-empty"><?php esc_html_e( 'هنوز هیچ مشتری‌ای ثبت‌نام نکرده است.', 'arvan-reseller' ); ?></div>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'مشتری', 'arvan-reseller' ); ?></th>
								<th><?php esc_html_e( 'موجودی کیف پول (تومان)', 'arvan-reseller' ); ?></th>
								<th><?php esc_html_e( 'تعداد سرویس‌ها', 'arvan-reseller' ); ?></th>
								<th><?php esc_html_e( 'وضعیت', 'arvan-reseller' ); ?></th>
								<th><?php esc_html_e( 'مصرف/هزینه‌ی اخیر', 'arvan-reseller' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $customerRows as $row ) : ?>
								<?php
								$rowId          = (int) $row['id'];
								$rowBalance     = $balances[ $rowId ] ?? Money::zero();
								$rowServiceCnt  = $serviceCounts[ $rowId ] ?? 0;
								$rowStatus      = '' !== (string) ( $row['status'] ?? '' ) ? (string) $row['status'] : 'active';
								$rowRecentUsage = $recentUsageByCustomer[ $rowId ] ?? null;
								$rowDisplayName = '' !== (string) ( $row['display_name'] ?? '' ) ? $row['display_name'] : $row['email'];
								$rowUrl         = add_query_arg(
									[
										'page'        => AdminMenu::SLUG_CUSTOMERS,
										'customer_id' => $rowId,
									],
									admin_url( 'admin.php' )
								);
								?>
								<tr>
									<td>
										<a href="<?php echo esc_url( $rowUrl ); ?>"><strong><?php echo esc_html( $rowDisplayName ); ?></strong></a><br />
										<span style="color:#646970;"><?php echo esc_html( $row['email'] ); ?></span>
									</td>
									<td><?php echo esc_html( number_format_i18n( $rowBalance->toToman() ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $rowServiceCnt ) ); ?></td>
									<td>
										<span class="arvan-admin-badge <?php echo esc_attr( 'active' === $rowStatus ? 'arvan-admin-badge--ok' : 'arvan-admin-badge--muted' ); ?>">
											<?php echo esc_html( 'active' === $rowStatus ? __( 'فعال', 'arvan-reseller' ) : $rowStatus ); ?>
										</span>
									</td>
									<td>
										<?php if ( null === $rowRecentUsage ) : ?>
											&mdash;
										<?php else : ?>
											<?php
											printf(
												/* translators: 1: traffic in GB, 2: charge in Toman */
												esc_html__( '%1$s گیگابایت — %2$s تومان', 'arvan-reseller' ),
												esc_html( number_format_i18n( round( ( (int) $rowRecentUsage['traffic_value'] ) / 1000000000, 3 ) ) ),
												esc_html( number_format_i18n( Money::fromRial( (int) $rowRecentUsage['total_rial'] )->toToman() ) )
											);
											?>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

		<?php endif; ?>

	<?php else : ?>

		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_CUSTOMERS ) ); ?>">
				&rarr; <?php esc_html_e( 'بازگشت به فهرست مشتریان', 'arvan-reseller' ); ?>
			</a>
		</p>

		<div class="arvan-admin-card">
			<h2><?php echo esc_html( '' !== (string) ( $customer['display_name'] ?? '' ) ? $customer['display_name'] : $customer['email'] ); ?></h2>
			<p>
				<?php echo esc_html( $customer['email'] ); ?>
				<?php if ( ! empty( $customer['phone'] ) ) : ?>
					&middot; <?php echo esc_html( $customer['phone'] ); ?>
				<?php endif; ?>
			</p>
			<p style="color:#646970;">
				<?php
				printf(
					/* translators: %s: registration date */
					esc_html__( 'تاریخ عضویت: %s', 'arvan-reseller' ),
					esc_html( mysql2date( 'Y/m/d H:i', $customer['created_at'] ) )
				);
				?>
			</p>
		</div>

		<div class="arvan-admin-grid">
			<div class="arvan-admin-card">
				<p class="arvan-admin-metric-label"><?php esc_html_e( 'موجودی کیف پول (تومان)', 'arvan-reseller' ); ?></p>
				<p class="arvan-admin-metric"><?php echo esc_html( number_format_i18n( $walletBalance->toToman() ) ); ?></p>
			</div>
			<div class="arvan-admin-card">
				<p class="arvan-admin-metric-label"><?php esc_html_e( 'آستانه‌ی اعلان کمبود اعتبار (تومان)', 'arvan-reseller' ); ?></p>
				<p class="arvan-admin-metric"><?php echo esc_html( number_format_i18n( $lowThreshold->toToman() ) ); ?></p>
			</div>
			<div class="arvan-admin-card">
				<p class="arvan-admin-metric-label"><?php esc_html_e( 'آستانه‌ی ازسرگیری سرویس (تومان)', 'arvan-reseller' ); ?></p>
				<p class="arvan-admin-metric"><?php echo esc_html( number_format_i18n( $resumeThreshold->toToman() ) ); ?></p>
			</div>
		</div>

		<div class="arvan-admin-card">
			<h2><?php esc_html_e( 'سرویس‌ها', 'arvan-reseller' ); ?></h2>
			<?php if ( [] === $customerServices ) : ?>
				<div class="arvan-admin-empty"><?php esc_html_e( 'این مشتری هنوز هیچ سرویسی ندارد.', 'arvan-reseller' ); ?></div>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'دامنه', 'arvan-reseller' ); ?></th>
							<th><?php esc_html_e( 'شناسه‌ی منبع آروان', 'arvan-reseller' ); ?></th>
							<th><?php esc_html_e( 'وضعیت', 'arvan-reseller' ); ?></th>
							<th><?php esc_html_e( 'تاریخ ایجاد', 'arvan-reseller' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $customerServices as $svc ) : ?>
							<?php $svcStatus = (string) $svc['status']; ?>
							<tr>
								<td><?php echo esc_html( $svc['domain'] ); ?></td>
								<td><?php echo esc_html( ! empty( $svc['arvan_resource_id'] ) ? $svc['arvan_resource_id'] : '—' ); ?></td>
								<td>
									<span class="arvan-admin-badge <?php echo esc_attr( $serviceBadgeClass( $svcStatus ) ); ?>">
										<?php echo esc_html( $statusLabels[ $svcStatus ] ?? $svcStatus ); ?>
									</span>
								</td>
								<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $svc['created_at'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="arvan-admin-card">
			<h2><?php esc_html_e( 'پرداخت‌ها', 'arvan-reseller' ); ?></h2>
			<?php if ( [] === $paymentRows ) : ?>
				<div class="arvan-admin-empty"><?php esc_html_e( 'هنوز پرداختی برای این مشتری ثبت نشده است.', 'arvan-reseller' ); ?></div>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'تاریخ', 'arvan-reseller' ); ?></th>
							<th><?php esc_html_e( 'مبلغ (تومان)', 'arvan-reseller' ); ?></th>
							<th><?php esc_html_e( 'روش', 'arvan-reseller' ); ?></th>
							<th><?php esc_html_e( 'وضعیت', 'arvan-reseller' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $paymentRows as $pay ) : ?>
							<?php
							$payStatus = (string) $pay['status'];
							$payBadge  = match ( $payStatus ) {
								'succeeded' => 'arvan-admin-badge--ok',
								'pending' => 'arvan-admin-badge--warn',
								default => 'arvan-admin-badge--bad',
							};
							?>
							<tr>
								<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $pay['created_at'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( Money::fromRial( (int) $pay['amount_rial'] )->toToman() ) ); ?></td>
								<td><?php echo esc_html( (string) $pay['gateway'] ); ?></td>
								<td>
									<span class="arvan-admin-badge <?php echo esc_attr( $payBadge ); ?>">
										<?php echo esc_html( $paymentStatusLabels[ $payStatus ] ?? $payStatus ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="arvan-admin-card">
			<h2><?php esc_html_e( 'دفتر کل (فقط خواندنی)', 'arvan-reseller' ); ?></h2>
			<?php if ( [] === $ledgerRows ) : ?>
				<div class="arvan-admin-empty"><?php esc_html_e( 'هنوز تراکنشی در دفتر کل این مشتری ثبت نشده است.', 'arvan-reseller' ); ?></div>
			<?php else : ?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'تاریخ', 'arvan-reseller' ); ?></th>
							<th><?php esc_html_e( 'نوع', 'arvan-reseller' ); ?></th>
							<th><?php esc_html_e( 'شرح', 'arvan-reseller' ); ?></th>
							<th><?php esc_html_e( 'مبلغ (تومان)', 'arvan-reseller' ); ?></th>
							<th><?php esc_html_e( 'موجودی پس از تراکنش (تومان)', 'arvan-reseller' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $ledgerRows as $entry ) : ?>
							<?php
							$isCredit    = 'credit' === $entry['direction'];
							$amountToman = Money::fromRial( (int) $entry['amount_rial'] )->toToman();
							?>
							<tr>
								<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $entry['created_at'] ) ); ?></td>
								<td><?php echo esc_html( (string) $entry['category'] ); ?></td>
								<td><?php echo esc_html( ! empty( $entry['description'] ) ? $entry['description'] : '—' ); ?></td>
								<td><?php echo esc_html( ( $isCredit ? '+' : '−' ) . number_format_i18n( $amountToman ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( Money::fromRial( (int) $entry['balance_after_rial'] )->toToman() ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="arvan-admin-card">
			<h2><?php esc_html_e( 'مصرف', 'arvan-reseller' ); ?></h2>
			<?php if ( [] === $usageRows ) : ?>
				<div class="arvan-admin-empty"><?php esc_html_e( 'هنوز هیچ دوره‌ی مصرفی برای این مشتری ثبت نشده است.', 'arvan-reseller' ); ?></div>
			<?php else : ?>
				<?php
				$domainByServiceId = [];
				foreach ( $customerServices as $svc ) {
					$domainByServiceId[ (int) $svc['id'] ] = $svc['domain'];
				}
				?>
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'دوره', 'arvan-reseller' ); ?></th>
							<th><?php esc_html_e( 'دامنه', 'arvan-reseller' ); ?></th>
							<th><?php esc_html_e( 'ترافیک خروجی', 'arvan-reseller' ); ?></th>
							<th><?php esc_html_e( 'هزینه (تومان)', 'arvan-reseller' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $usageRows as $usage ) : ?>
							<tr>
								<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $usage['period_start'] ) . ' – ' . mysql2date( 'Y/m/d H:i', $usage['period_end'] ) ); ?></td>
								<td><?php echo esc_html( $domainByServiceId[ (int) $usage['service_id'] ] ?? '' ); ?></td>
								<td><?php echo esc_html( number_format_i18n( round( ( (int) $usage['traffic_value'] ) / 1000000000, 3 ) ) . ' GB' ); ?></td>
								<td><?php echo esc_html( number_format_i18n( Money::fromRial( (int) $usage['total_rial'] )->toToman() ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>

		<div class="arvan-admin-card">
			<h2><?php esc_html_e( 'تغییر دستی موجودی کیف پول', 'arvan-reseller' ); ?></h2>
			<form
				method="post"
				action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
				onsubmit="return confirm('<?php echo esc_js( __( 'آیا از اعمال این تغییر دستی روی موجودی کیف پول مشتری مطمئن هستید؟ این عملیات در دفتر کل ثبت می‌شود.', 'arvan-reseller' ) ); ?>');"
			>
				<input type="hidden" name="action" value="<?php echo esc_attr( $adjustAction ); ?>" />
				<input type="hidden" name="customer_id" value="<?php echo esc_attr( (string) $customerId ); ?>" />
				<?php wp_nonce_field( $adjustNonceAction ); ?>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'جهت', 'arvan-reseller' ); ?></th>
						<td>
							<label><input type="radio" name="direction" value="credit" checked="checked" /> <?php esc_html_e( 'افزایش (بستانکار کردن)', 'arvan-reseller' ); ?></label>
							&nbsp;&nbsp;
							<label><input type="radio" name="direction" value="debit" /> <?php esc_html_e( 'کاهش (بدهکار کردن)', 'arvan-reseller' ); ?></label>
						</td>
					</tr>
					<tr>
						<th><label for="arvan-adjust-amount"><?php esc_html_e( 'مبلغ (تومان)', 'arvan-reseller' ); ?></label></th>
						<td><input type="number" min="1" step="1" id="arvan-adjust-amount" name="amount_toman" required="required" /></td>
					</tr>
					<tr>
						<th><label for="arvan-adjust-reason"><?php esc_html_e( 'دلیل (الزامی)', 'arvan-reseller' ); ?></label></th>
						<td><textarea class="large-text" id="arvan-adjust-reason" name="reason" rows="3" required="required"></textarea></td>
					</tr>
					<tr>
						<th></th>
						<td>
							<label>
								<input type="checkbox" name="confirm_adjustment" value="1" required="required" />
								<?php esc_html_e( 'تأیید می‌کنم که این تغییر دستی را با آگاهی کامل اعمال می‌کنم.', 'arvan-reseller' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'اعمال تغییر', 'arvan-reseller' ); ?></button>
			</form>
		</div>

	<?php endif; ?>

</div>
