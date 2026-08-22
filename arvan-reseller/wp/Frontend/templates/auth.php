<?php
/**
 * Login/Register — `/arvan/auth` (BACKLOG T-7.4, SCREEN-SPECS.md §8).
 *
 * Same composition-at-the-template pattern as cdn.php/service-detail.php —
 * see cdn.php's docblock for why (`template_include` hands WordPress a bare
 * path, so there is no controller class already holding local variables).
 *
 * Two forms on one page (login + register), posting to
 * `AuthController::ACTION_LOGIN`/`ACTION_REGISTER` via `admin-post.php`,
 * each nonce-protected. No JS framework — a plain in-page anchor toggle
 * only (DESIGN.md "no decorative complexity").
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

use ArvanReseller\Wp\Admin\ResellerSettings;
use ArvanReseller\Wp\Frontend\Controllers\AuthController;
use ArvanReseller\Wp\Frontend\CurrentCustomer;
use ArvanReseller\Wp\Persistence\WpCustomerRepository;

defined( 'ABSPATH' ) || exit;

global $wpdb;

if ( is_user_logged_in() ) {
	wp_safe_redirect( home_url( '/arvan/account' ) );
	exit;
}

$errorMessages = [
	'login_failed'         => __( 'ورود ناموفق بود. نام کاربری/ایمیل یا رمز عبور را بررسی کنید.', 'arvan-reseller' ),
	'rate_limited'         => __( 'تعداد تلاش‌های ناموفق شما زیاد است. لطفاً چند دقیقه دیگر دوباره تلاش کنید.', 'arvan-reseller' ),
	'invalid_email'        => __( 'ایمیل واردشده معتبر نیست.', 'arvan-reseller' ),
	'email_taken'          => __( 'این ایمیل قبلاً ثبت‌نام کرده است. وارد حساب خود شوید.', 'arvan-reseller' ),
	'weak_password'        => __( 'رمز عبور باید حداقل ۸ کاراکتر باشد.', 'arvan-reseller' ),
	'registration_failed'  => __( 'ثبت‌نام با خطا مواجه شد. لطفاً دوباره تلاش کنید.', 'arvan-reseller' ),
];
$errorCode = isset( $_GET['arvan_error'] ) ? sanitize_key( wp_unslash( $_GET['arvan_error'] ) ) : '';

get_header();

$arvan_customer            = ( new CurrentCustomer( new WpCustomerRepository( $wpdb ) ) )->resolve();
$arvan_wallet_balance_rial = null;
$arvan_business_name       = ( new ResellerSettings() )->getBusinessProfile()['name'];
require __DIR__ . '/partials/topbar.php';
?>

<div class="arvan-app">
	<div class="arvan-container">

		<?php if ( '' !== $errorCode && isset( $errorMessages[ $errorCode ] ) ) : ?>
			<div class="arvan-alert arvan-alert--danger" role="alert" style="margin-bottom: var(--arvan-space-4);">
				<?php echo esc_html( $errorMessages[ $errorCode ] ); ?>
			</div>
		<?php endif; ?>

		<div class="arvan-grid arvan-grid--cols-2">

			<div class="arvan-card">
				<h2><?php esc_html_e( 'ورود', 'arvan-reseller' ); ?></h2>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( AuthController::ACTION_LOGIN ); ?>" />
					<?php wp_nonce_field( AuthController::ACTION_LOGIN ); ?>

					<div class="arvan-field">
						<label class="arvan-field__label" for="arvan-login"><?php esc_html_e( 'ایمیل یا نام کاربری', 'arvan-reseller' ); ?></label>
						<input class="arvan-input" type="text" id="arvan-login" name="login" required />
					</div>

					<div class="arvan-field">
						<label class="arvan-field__label" for="arvan-login-password"><?php esc_html_e( 'رمز عبور', 'arvan-reseller' ); ?></label>
						<input class="arvan-input" type="password" id="arvan-login-password" name="password" required />
					</div>

					<button type="submit" class="arvan-btn arvan-btn--primary"><?php esc_html_e( 'ورود', 'arvan-reseller' ); ?></button>
				</form>
			</div>

			<div class="arvan-card">
				<h2><?php esc_html_e( 'ثبت‌نام', 'arvan-reseller' ); ?></h2>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( AuthController::ACTION_REGISTER ); ?>" />
					<?php wp_nonce_field( AuthController::ACTION_REGISTER ); ?>

					<div class="arvan-field">
						<label class="arvan-field__label" for="arvan-register-email"><?php esc_html_e( 'ایمیل', 'arvan-reseller' ); ?></label>
						<input class="arvan-input" type="email" id="arvan-register-email" name="email" required />
					</div>

					<div class="arvan-field">
						<label class="arvan-field__label" for="arvan-register-password"><?php esc_html_e( 'رمز عبور', 'arvan-reseller' ); ?></label>
						<input class="arvan-input" type="password" id="arvan-register-password" name="password" minlength="8" required />
						<span class="arvan-field__help"><?php esc_html_e( 'حداقل ۸ کاراکتر', 'arvan-reseller' ); ?></span>
					</div>

					<div class="arvan-field">
						<label class="arvan-field__label" for="arvan-register-name"><?php esc_html_e( 'نام نمایشی', 'arvan-reseller' ); ?></label>
						<input class="arvan-input" type="text" id="arvan-register-name" name="display_name" />
					</div>

					<button type="submit" class="arvan-btn arvan-btn--primary"><?php esc_html_e( 'ثبت‌نام', 'arvan-reseller' ); ?></button>
				</form>
			</div>

		</div>
	</div>
</div>

<?php
get_footer();
