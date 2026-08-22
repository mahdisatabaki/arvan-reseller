<?php
/**
 * Wallet Recharge (Mock Payment) — `/arvan/recharge` (BACKLOG T-7.4,
 * SCREEN-SPECS.md §10, USER-FLOWS.md §5, ADR-014: Mock Payment is the whole
 * MVP payment method, no real gateway).
 *
 * Same composition-at-the-template pattern as cdn.php/service-detail.php —
 * see cdn.php's docblock for why. Two-step mock flow entirely driven by
 * `RechargeController`:
 *   1. amount form → `arvan_recharge_initiate` → redirects back here with
 *      `?payment_id=`.
 *   2. this page, given a `payment_id` that resolves (IDOR-safe, via
 *      `findOwnedByCustomer()`) to a still-`pending` payment, renders the
 *      "mock gateway" block with succeed/fail buttons.
 *
 * `findOwnedByCustomer()` is read directly here (not through the
 * controller) purely for *display* — the controller re-resolves it again,
 * independently, before ever acting on a POST (SECURITY.md §6): a payment
 * id in the query string is never trusted for a write.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

use ArvanReseller\Domain\Money;
use ArvanReseller\Wp\Admin\ResellerSettings;
use ArvanReseller\Wp\Frontend\Controllers\RechargeController;
use ArvanReseller\Wp\Frontend\CurrentCustomer;
use ArvanReseller\Wp\Persistence\WpCustomerRepository;
use ArvanReseller\Wp\Persistence\WpPaymentRepository;
use ArvanReseller\Wp\Persistence\WpWalletRepository;

defined( 'ABSPATH' ) || exit;

global $wpdb;

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( home_url( '/arvan/auth' ) );
	exit;
}

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

$customerId = (int) $customer['id'];
$payments   = new WpPaymentRepository( $wpdb );
$balance    = Money::fromRial( (int) $arvan_wallet_balance_rial );

$errorMessages = [
	'invalid_amount' => __( 'مبلغ واردشده معتبر نیست.', 'arvan-reseller' ),
	'payment_failed' => __( 'پرداخت ناموفق بود.', 'arvan-reseller' ),
];
$errorCode   = isset( $_GET['arvan_error'] ) ? sanitize_key( wp_unslash( $_GET['arvan_error'] ) ) : '';
$showSuccess = isset( $_GET['arvan_success'] );

$pendingPayment = null;
$paymentIdParam = isset( $_GET['payment_id'] ) ? absint( wp_unslash( $_GET['payment_id'] ) ) : 0;

if ( $paymentIdParam > 0 ) {
	$found = $payments->findOwnedByCustomer( $paymentIdParam, $customerId );

	if ( null !== $found && 'pending' === $found['status'] ) {
		$pendingPayment = $found;
	}
}

$statusLabels = [
	'succeeded' => __( 'موفق', 'arvan-reseller' ),
	'pending'   => __( 'در انتظار', 'arvan-reseller' ),
	'failed'    => __( 'ناموفق', 'arvan-reseller' ),
];
$statusBadgeClass = [
	'succeeded' => 'arvan-badge--active',
	'pending'   => 'arvan-badge--suspended',
	'failed'    => 'arvan-badge--failed',
];

$history = $payments->historyForCustomer( $customerId, 10 );
?>

<div class="arvan-app">
	<div class="arvan-container">

		<?php if ( $showSuccess ) : ?>
			<div class="arvan-alert arvan-alert--success" role="status" style="margin-bottom: var(--arvan-space-4);">
				<?php esc_html_e( 'کیف پول شما با موفقیت شارژ شد.', 'arvan-reseller' ); ?>
			</div>
		<?php elseif ( '' !== $errorCode && isset( $errorMessages[ $errorCode ] ) ) : ?>
			<div class="arvan-alert arvan-alert--danger" role="alert" style="margin-bottom: var(--arvan-space-4);">
				<?php echo esc_html( $errorMessages[ $errorCode ] ); ?>
			</div>
		<?php endif; ?>

		<div class="arvan-card" style="margin-bottom: var(--arvan-space-4);">
			<h1><?php esc_html_e( 'افزایش اعتبار کیف پول', 'arvan-reseller' ); ?></h1>
			<p style="font-size: var(--arvan-font-size-lg); font-weight: 700;">
				<?php
				printf(
					/* translators: %s: wallet balance in Toman */
					esc_html__( 'موجودی فعلی: %s تومان', 'arvan-reseller' ),
					esc_html( number_format_i18n( $balance->toToman() ) )
				);
				?>
			</p>
		</div>

		<?php if ( null !== $pendingPayment ) : ?>
			<div class="arvan-card" style="margin-bottom: var(--arvan-space-4);">
				<h2><?php esc_html_e( 'شبیه‌سازی درگاه پرداخت', 'arvan-reseller' ); ?></h2>
				<p>
					<?php
					printf(
						/* translators: %s: payment amount in Toman */
						esc_html__( 'مبلغ %s تومان در انتظار تأیید است.', 'arvan-reseller' ),
						esc_html( number_format_i18n( Money::fromRial( (int) $pendingPayment['amount_rial'] )->toToman() ) )
					);
					?>
				</p>

				<div style="display:flex; gap: var(--arvan-space-2); flex-wrap:wrap;">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( RechargeController::ACTION_CONFIRM ); ?>" />
						<input type="hidden" name="payment_id" value="<?php echo esc_attr( (string) $pendingPayment['id'] ); ?>" />
						<?php wp_nonce_field( RechargeController::ACTION_CONFIRM ); ?>
						<button type="submit" class="arvan-btn arvan-btn--primary"><?php esc_html_e( 'پرداخت موفق', 'arvan-reseller' ); ?></button>
					</form>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( RechargeController::ACTION_FAIL ); ?>" />
						<input type="hidden" name="payment_id" value="<?php echo esc_attr( (string) $pendingPayment['id'] ); ?>" />
						<?php wp_nonce_field( RechargeController::ACTION_FAIL ); ?>
						<button type="submit" class="arvan-btn arvan-btn--secondary"><?php esc_html_e( 'پرداخت ناموفق', 'arvan-reseller' ); ?></button>
					</form>
				</div>
			</div>
		<?php else : ?>
			<div class="arvan-card" style="margin-bottom: var(--arvan-space-4);">
				<h2><?php esc_html_e( 'مبلغ شارژ', 'arvan-reseller' ); ?></h2>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( RechargeController::ACTION_INITIATE ); ?>" />
					<?php wp_nonce_field( RechargeController::ACTION_INITIATE ); ?>

					<div class="arvan-field">
						<label class="arvan-field__label" for="arvan-recharge-amount"><?php esc_html_e( 'مبلغ (تومان)', 'arvan-reseller' ); ?></label>
						<input class="arvan-input" type="number" min="1" step="1" id="arvan-recharge-amount" name="amount_toman" required />
						<span class="arvan-field__help"><?php esc_html_e( 'روش پرداخت: شبیه‌سازی درگاه (Mock Payment)', 'arvan-reseller' ); ?></span>
					</div>

					<button type="submit" class="arvan-btn arvan-btn--primary"><?php esc_html_e( 'ادامه', 'arvan-reseller' ); ?></button>
				</form>
			</div>
		<?php endif; ?>

		<div class="arvan-card">
			<h2><?php esc_html_e( 'پرداخت‌های اخیر', 'arvan-reseller' ); ?></h2>

			<?php if ( [] === $history ) : ?>
				<div class="arvan-empty"><?php esc_html_e( 'هنوز هیچ پرداختی ثبت نشده است.', 'arvan-reseller' ); ?></div>
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
						<?php foreach ( $history as $row ) : ?>
							<?php $status = (string) $row['status']; ?>
							<tr>
								<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $row['created_at'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( Money::fromRial( (int) $row['amount_rial'] )->toToman() ) ); ?></td>
								<td>
									<span class="arvan-badge <?php echo esc_attr( $statusBadgeClass[ $status ] ?? 'arvan-badge--failed' ); ?>">
										<?php echo esc_html( $statusLabels[ $status ] ?? $status ); ?>
									</span>
								</td>
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
