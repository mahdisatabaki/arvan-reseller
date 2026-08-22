<?php
/**
 * Admin Services view. Rendered by ServicesController::render() via
 * `require`, same composition convention as dashboard.php/settings.php.
 *
 * @package ArvanReseller
 *
 * @var string $activeSlug
 * @var array<int, array<string, mixed>> $allServices
 * @var array<int, string> $customerNames customer_id => display_name
 * @var array<int, array{label: string, last_four: string}> $apiKeyInfo api_key_id => credential info
 * @var string $retryAction
 * @var string $errorCode
 * @var string $retried
 */

use ArvanReseller\Lifecycle\ServiceStatus;

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/partials/admin-header.php';

$status_labels = [
	ServiceStatus::PROVISIONING        => __( 'در حال آماده‌سازی', 'arvan-reseller' ),
	ServiceStatus::ACTIVE              => __( 'فعال', 'arvan-reseller' ),
	ServiceStatus::SUSPENDED           => __( 'معلق', 'arvan-reseller' ),
	ServiceStatus::TERMINATED          => __( 'خاتمه‌یافته', 'arvan-reseller' ),
	ServiceStatus::PROVISIONING_FAILED => __( 'خطا در راه‌اندازی', 'arvan-reseller' ),
	ServiceStatus::SUSPEND_FAILED      => __( 'خطا در تعلیق', 'arvan-reseller' ),
	ServiceStatus::RESUME_FAILED       => __( 'خطا در ازسرگیری', 'arvan-reseller' ),
	ServiceStatus::TERMINATE_FAILED    => __( 'خطا در خاتمه', 'arvan-reseller' ),
];

$error_messages = [
	'service_not_found' => __( 'سرویس موردنظر یافت نشد.', 'arvan-reseller' ),
	'not_retryable'      => __( 'این سرویس در وضعیت خطای راه‌اندازی نیست؛ تلاش مجدد ممکن نیست.', 'arvan-reseller' ),
	'no_usable_key'      => __( 'کلید API فعالی برای این سرویس یافت نشد.', 'arvan-reseller' ),
];
?>

	<h1><?php esc_html_e( 'سرویس‌ها', 'arvan-reseller' ); ?></h1>

	<?php if ( 'ok' === $retried ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'تلاش مجدد با موفقیت انجام شد؛ سرویس اکنون فعال است.', 'arvan-reseller' ); ?></p></div>
	<?php elseif ( 'failed' === $retried ) : ?>
		<div class="notice notice-error"><p><?php esc_html_e( 'تلاش مجدد ناموفق بود. جزئیات خطا را در ردیف سرویس ببینید.', 'arvan-reseller' ); ?></p></div>
	<?php endif; ?>

	<?php if ( '' !== $errorCode ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $error_messages[ $errorCode ] ?? __( 'عملیات ناموفق بود.', 'arvan-reseller' ) ); ?></p></div>
	<?php endif; ?>

	<div class="arvan-admin-card">

		<?php if ( [] === $allServices ) : ?>
			<div class="arvan-admin-empty"><?php esc_html_e( 'هنوز هیچ سرویسی ثبت نشده است.', 'arvan-reseller' ); ?></div>
		<?php else : ?>
			<div style="overflow-x:auto;">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'دامنه', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'شناسه‌ی منبع', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'مالک', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'کلید API', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'اندازه‌گیری‌شده تا', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'هزینه‌ی اخیر', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'وضعیت هماهنگی با ارائه‌دهنده', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'عملیات', 'arvan-reseller' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $allServices as $service ) : ?>
						<?php
						$service_id = (int) $service['id'];
						$status     = (string) $service['status'];
						$badgeClass = match ( $status ) {
							ServiceStatus::ACTIVE => 'arvan-admin-badge--ok',
							ServiceStatus::SUSPENDED, ServiceStatus::PROVISIONING => 'arvan-admin-badge--warn',
							ServiceStatus::TERMINATED, ServiceStatus::PROVISIONING_FAILED, ServiceStatus::SUSPEND_FAILED, ServiceStatus::RESUME_FAILED, ServiceStatus::TERMINATE_FAILED => 'arvan-admin-badge--bad',
							default => 'arvan-admin-badge--muted',
						};

						$owner_id   = (int) $service['customer_id'];
						$owner_name = $customerNames[ $owner_id ] ?? sprintf( /* translators: %d: customer id */ __( 'مشتری #%d', 'arvan-reseller' ), $owner_id );

						$api_key_id = (int) ( $service['api_key_id'] ?? 0 );
						$credential = $apiKeyInfo[ $api_key_id ] ?? null;

						$attempts  = (int) ( $service['provision_attempts'] ?? 0 );
						$lastError = $service['last_error'] ?? null;
						?>
						<tr>
							<td><?php echo esc_html( (string) $service['domain'] ); ?></td>
							<td><?php echo esc_html( ! empty( $service['arvan_resource_id'] ) ? (string) $service['arvan_resource_id'] : '—' ); ?></td>
							<td><?php echo esc_html( $owner_name ); ?></td>
							<td><span class="arvan-admin-badge <?php echo esc_attr( $badgeClass ); ?>"><?php echo esc_html( $status_labels[ $status ] ?? $status ); ?></span></td>
							<td><?php echo null !== $credential ? esc_html( $credential['label'] . ' (••••' . $credential['last_four'] . ')' ) : '—'; ?></td>
							<td><?php echo esc_html( ! empty( $service['metered_through'] ) ? mysql2date( 'Y/m/d H:i', $service['metered_through'] ) : '—' ); ?></td>
							<td>—</td>
							<td>
								<?php
								printf(
									/* translators: %d: number of provisioning attempts */
									esc_html__( 'تلاش‌ها: %d', 'arvan-reseller' ),
									$attempts
								);
								?>
								<?php if ( ! empty( $lastError ) ) : ?>
									<details>
										<summary><?php esc_html_e( 'آخرین خطا', 'arvan-reseller' ); ?></summary>
										<p><?php echo esc_html( (string) $lastError ); ?></p>
									</details>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ServiceStatus::PROVISIONING_FAILED === $status ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="<?php echo esc_attr( $retryAction ); ?>" />
										<input type="hidden" name="service_id" value="<?php echo esc_attr( (string) $service_id ); ?>" />
										<?php wp_nonce_field( $retryAction ); ?>
										<button type="submit" class="button button-small"><?php esc_html_e( 'تلاش مجدد', 'arvan-reseller' ); ?></button>
									</form>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		<?php endif; ?>

	</div>

</div>
