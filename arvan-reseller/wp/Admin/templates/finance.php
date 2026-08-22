<?php
/**
 * Admin Finance view. Rendered by FinanceController::render() via `require`,
 * same composition convention as dashboard.php/settings.php/services.php.
 *
 * @package ArvanReseller
 *
 * @var string $activeSlug
 * @var string $activeTab
 * @var array<int, string> $customerNames customer_id => display_name
 * @var array<int, array<string, mixed>> $paymentRows
 * @var string $paymentStatusFilter
 * @var array<int, array<string, mixed>> $ledgerRows
 * @var array<int, array<string, mixed>> $settlementRows
 */

use ArvanReseller\Domain\Money;
use ArvanReseller\Wp\Admin\AdminMenu;

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/partials/admin-header.php';

$tab_labels = [
	'payments'    => __( 'پرداخت‌ها', 'arvan-reseller' ),
	'ledger'      => __( 'دفتر کل', 'arvan-reseller' ),
	'settlements' => __( 'تسویه‌حساب‌ها', 'arvan-reseller' ),
];

$payment_status_labels = [
	'pending'   => __( 'در انتظار', 'arvan-reseller' ),
	'succeeded' => __( 'موفق', 'arvan-reseller' ),
	'failed'    => __( 'ناموفق', 'arvan-reseller' ),
];

$settlement_status_labels = [
	'draft'       => __( 'پیش‌نویس', 'arvan-reseller' ),
	'transmitted' => __( 'ارسال‌شده', 'arvan-reseller' ),
];
?>

	<h1><?php esc_html_e( 'مالی', 'arvan-reseller' ); ?></h1>

	<ul class="arvan-admin-tabs">
		<?php foreach ( $tab_labels as $tab_key => $tab_label ) : ?>
			<li>
				<a class="<?php echo esc_attr( $activeTab === $tab_key ? 'is-active' : '' ); ?>" href="<?php echo esc_url( add_query_arg( [ 'page' => AdminMenu::SLUG_FINANCE, 'tab' => $tab_key ], admin_url( 'admin.php' ) ) ); ?>">
					<?php echo esc_html( $tab_label ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>

	<div class="arvan-admin-card">

	<?php if ( 'payments' === $activeTab ) : ?>

		<p>
			<?php
			$status_filter_links = [ '' => __( 'همه', 'arvan-reseller' ) ] + $payment_status_labels;
			foreach ( $status_filter_links as $status_key => $status_label ) :
				$is_current = $paymentStatusFilter === $status_key;
				$link_args  = [ 'page' => AdminMenu::SLUG_FINANCE, 'tab' => 'payments' ];
				if ( '' !== $status_key ) {
					$link_args['status'] = $status_key;
				}
				?>
				<a class="<?php echo esc_attr( 'button button-small ' . ( $is_current ? 'button-primary' : '' ) ); ?>" href="<?php echo esc_url( add_query_arg( $link_args, admin_url( 'admin.php' ) ) ); ?>">
					<?php echo esc_html( $status_label ); ?>
				</a>
			<?php endforeach; ?>
		</p>

		<?php if ( [] === $paymentRows ) : ?>
			<div class="arvan-admin-empty"><?php esc_html_e( 'هیچ پرداختی با این مشخصات یافت نشد.', 'arvan-reseller' ); ?></div>
		<?php else : ?>
			<div style="overflow-x:auto;">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'زمان', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'مشتری', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'مبلغ (تومان)', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'روش', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'مرجع', 'arvan-reseller' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $paymentRows as $row ) : ?>
						<?php
						$row_status = (string) $row['status'];
						$row_badge  = match ( $row_status ) {
							'succeeded' => 'arvan-admin-badge--ok',
							'pending' => 'arvan-admin-badge--warn',
							default => 'arvan-admin-badge--bad',
						};
						$row_customer_id = (int) $row['customer_id'];
						?>
						<tr>
							<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $row['created_at'] ) ); ?></td>
							<td><?php echo esc_html( $customerNames[ $row_customer_id ] ?? sprintf( /* translators: %d: customer id */ __( 'مشتری #%d', 'arvan-reseller' ), $row_customer_id ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( Money::fromRial( (int) $row['amount_rial'] )->toToman() ) ); ?></td>
							<td><?php echo esc_html( (string) $row['gateway'] ); ?></td>
							<td><span class="arvan-admin-badge <?php echo esc_attr( $row_badge ); ?>"><?php echo esc_html( $payment_status_labels[ $row_status ] ?? $row_status ); ?></span></td>
							<td><?php echo esc_html( ! empty( $row['reference'] ) ? (string) $row['reference'] : '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		<?php endif; ?>

	<?php elseif ( 'ledger' === $activeTab ) : ?>

		<p class="description"><?php esc_html_e( 'دفتر کل فقط قابل مشاهده است؛ هیچ ردیفی از این صفحه ویرایش یا حذف نمی‌شود.', 'arvan-reseller' ); ?></p>

		<?php if ( [] === $ledgerRows ) : ?>
			<div class="arvan-admin-empty"><?php esc_html_e( 'هنوز هیچ تراکنشی ثبت نشده است.', 'arvan-reseller' ); ?></div>
		<?php else : ?>
			<div style="overflow-x:auto;">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'زمان', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'مشتری', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'نوع', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'مبلغ (تومان)', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'موجودی پس از تراکنش (تومان)', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'مرجع', 'arvan-reseller' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $ledgerRows as $row ) : ?>
						<?php
						$is_credit         = 'credit' === $row['direction'];
						$row_customer_id   = (int) $row['customer_id'];
						$amount_toman      = Money::fromRial( (int) $row['amount_rial'] )->toToman();
						$balance_after_toman = Money::fromRial( (int) $row['balance_after_rial'] )->toToman();
						$reference          = ! empty( $row['reference_type'] )
							? $row['reference_type'] . ( ! empty( $row['reference_id'] ) ? ' #' . (int) $row['reference_id'] : '' )
							: '—';
						?>
						<tr>
							<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $row['created_at'] ) ); ?></td>
							<td><?php echo esc_html( $customerNames[ $row_customer_id ] ?? sprintf( /* translators: %d: customer id */ __( 'مشتری #%d', 'arvan-reseller' ), $row_customer_id ) ); ?></td>
							<td><?php echo esc_html( (string) $row['category'] ); ?></td>
							<td><?php echo esc_html( ( $is_credit ? '+' : '−' ) . number_format_i18n( $amount_toman ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $balance_after_toman ) ); ?></td>
							<td><?php echo esc_html( $reference ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		<?php endif; ?>

	<?php else : ?>

		<?php if ( [] === $settlementRows ) : ?>
			<div class="arvan-admin-empty"><?php esc_html_e( 'هنوز هیچ تسویه‌حسابی ثبت نشده است.', 'arvan-reseller' ); ?></div>
		<?php else : ?>
			<div style="overflow-x:auto;">
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'دوره', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'پایه (تومان)', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'سود (تومان)', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'مجموع مشتری (تومان)', 'arvan-reseller' ); ?></th>
						<th><?php esc_html_e( 'وضعیت', 'arvan-reseller' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $settlementRows as $row ) : ?>
						<?php $settlement_status = (string) $row['status']; ?>
						<tr>
							<td><?php echo esc_html( mysql2date( 'Y/m/d', $row['period_start'] ) . ' – ' . mysql2date( 'Y/m/d', $row['period_end'] ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( Money::fromRial( (int) $row['base_rial'] )->toToman() ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( Money::fromRial( (int) $row['markup_rial'] )->toToman() ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( Money::fromRial( (int) $row['gross_rial'] )->toToman() ) ); ?></td>
							<td><span class="arvan-admin-badge <?php echo esc_attr( 'transmitted' === $settlement_status ? 'arvan-admin-badge--ok' : 'arvan-admin-badge--muted' ); ?>"><?php echo esc_html( $settlement_status_labels[ $settlement_status ] ?? $settlement_status ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		<?php endif; ?>

	<?php endif; ?>

	</div>

</div>
