<?php
/**
 * CDN Product Page — `/arvan/cdn` (BACKLOG T-7.3, SCREEN-SPECS.md §9).
 *
 * Registered as WordPress's `template_include` for the `cdn` route
 * (RouteRegistrar/TemplateRouter, T-7.2), so this file is the entry point
 * itself — there is no controller class in front of it composing local
 * variables the way `SetupWizard::renderTemplate()` does, because
 * `template_include` only ever returns a bare file path for WordPress core
 * to `include` (see TemplateRouter.php). This file plays that composition
 * role itself: build the WP adapters it needs from `global $wpdb`, then
 * render. No domain/pricing logic lives here beyond calling `ResellerPricing`
 * with values already computed elsewhere — CLAUDE.md's WordPress Boundary
 * still holds, this is the adapter/UI layer.
 *
 * `get_header()`/`get_footer()` intentionally still run (the active theme's
 * chrome), with everything plugin-owned scoped under `.arvan-app`
 * (foundation.css's own docblock: "so this file can never leak into theme
 * content or be overridden by it") — DESIGN.md §3E "Theme styles must not
 * break critical layouts" only makes sense read this way; a fully standalone
 * document would have no theme styles to defend against in the first place.
 *
 * Only CDN appears (CLAUDE.md Frozen MVP: "Product pages: one CDN sales page
 * only") — no Cloud Server/Object Storage cards (DESIGN.md §9).
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

use ArvanReseller\Domain\Money;
use ArvanReseller\Pricing\ResellerPricing;
use ArvanReseller\Wp\Admin\ResellerSettings;
use ArvanReseller\Wp\Frontend\Controllers\OrderController;
use ArvanReseller\Wp\Frontend\CurrentCustomer;
use ArvanReseller\Wp\Persistence\WpCustomerRepository;
use ArvanReseller\Wp\Persistence\WpWalletRepository;

defined( 'ABSPATH' ) || exit;

global $wpdb;

$settings = new ResellerSettings();
$customer = ( new CurrentCustomer( new WpCustomerRepository( $wpdb ) ) )->resolve();

$wallets       = new WpWalletRepository( $wpdb );
$walletBalance = null !== $customer ? $wallets->currentBalance( (int) $customer['id'] ) : null;

$business   = $settings->getBusinessProfile();
$markupRate = $settings->getMarkupRate();
$unitPrice  = Money::fromRial( $settings->getUnitPriceRialPerGb() );
$sample     = ( new ResellerPricing( $markupRate ) )->charge( $unitPrice );

$errorMessages = [
	'invalid_domain'       => __( 'دامنه‌ی واردشده معتبر نیست. لطفاً یک دامنه‌ی صحیح مانند example.com وارد کنید.', 'arvan-reseller' ),
	'insufficient_balance' => __( 'موجودی کیف پول شما برای فعال‌سازی سرویس کافی نیست. ابتدا اعتبار خود را افزایش دهید.', 'arvan-reseller' ),
	'not_configured'       => __( 'در حال حاضر امکان فروش سرویس وجود ندارد. لطفاً بعداً دوباره تلاش کنید.', 'arvan-reseller' ),
];
$errorCode = isset( $_GET['arvan_error'] ) ? sanitize_key( wp_unslash( $_GET['arvan_error'] ) ) : '';

$isEligible = null !== $customer && null !== $walletBalance && $walletBalance->greaterThan( Money::zero() );

/**
 * DESIGN.md §8's step-5 note explains why this reads a stored value with no
 * UI ever having offered a choice yet: the Setup Wizard's layout picker was
 * removed (T-2.4) because this page — its only consumer — did not exist,
 * but a default was still saved "so T-7.3 has something to read from day
 * one." `cards` is the two-column grid already built for the pricing/CTA
 * cards; `compact` is the same two cards stacked in a single column — a
 * genuinely simpler, denser reading order, not a cosmetic-only toggle.
 */
$isCompact = ResellerSettings::LAYOUT_COMPACT === $settings->getLayout();

get_header();

$arvan_customer            = $customer;
$arvan_wallet_balance_rial = null !== $walletBalance ? $walletBalance->toRial() : null;
$arvan_business_name       = $business['name'];
require __DIR__ . '/partials/topbar.php';
?>

<div class="arvan-app">
	<div class="arvan-container">

		<div class="arvan-card" style="margin-bottom: var(--arvan-space-4);">
			<h1><?php echo esc_html( '' !== $business['name'] ? $business['name'] : __( 'CDN آروان', 'arvan-reseller' ) ); ?></h1>

			<?php if ( '' !== $business['about'] ) : ?>
				<p><?php echo esc_html( $business['about'] ); ?></p>
			<?php endif; ?>

			<ul>
				<li><?php esc_html_e( 'تحویل سریع محتوا از نزدیک‌ترین سرور به کاربران شما', 'arvan-reseller' ); ?></li>
				<li><?php esc_html_e( 'کاهش بار سرور اصلی و افزایش پایداری وب‌سایت', 'arvan-reseller' ); ?></li>
				<li><?php esc_html_e( 'پرداخت فقط بر اساس ترافیک خروجی مصرفی', 'arvan-reseller' ); ?></li>
			</ul>
		</div>

		<div class="arvan-grid<?php echo $isCompact ? '' : ' arvan-grid--cols-2'; ?>">

			<div class="arvan-card">
				<h2><?php esc_html_e( 'قیمت‌گذاری', 'arvan-reseller' ); ?></h2>
				<p class="arvan-field__help">
					<?php esc_html_e( 'قیمت زیر، قیمت نهایی مشتری برای هر گیگابایت ترافیک خروجی است (شامل نرخ سود ریسلر).', 'arvan-reseller' ); ?>
				</p>
				<p style="font-size: var(--arvan-font-size-lg); font-weight: 700;">
					<?php
					printf(
						/* translators: %s: price in Toman */
						esc_html__( '%s تومان به ازای هر گیگابایت', 'arvan-reseller' ),
						esc_html( number_format_i18n( $sample->total->toToman() ) )
					);
					?>
				</p>

				<?php if ( null !== $walletBalance ) : ?>
					<div class="arvan-alert arvan-alert--info">
						<?php
						printf(
							/* translators: %s: wallet balance in Toman */
							esc_html__( 'موجودی فعلی کیف پول شما: %s تومان', 'arvan-reseller' ),
							esc_html( number_format_i18n( $walletBalance->toToman() ) )
						);
						?>
					</div>
				<?php endif; ?>
			</div>

			<div class="arvan-card">
				<h2><?php esc_html_e( 'فعال‌سازی CDN', 'arvan-reseller' ); ?></h2>

				<?php if ( '' !== $errorCode && isset( $errorMessages[ $errorCode ] ) ) : ?>
					<div class="arvan-alert arvan-alert--danger" role="alert">
						<?php echo esc_html( $errorMessages[ $errorCode ] ); ?>
					</div>
				<?php endif; ?>

				<?php if ( null === $customer ) : ?>
					<p><?php esc_html_e( 'برای فعال‌سازی سرویس CDN ابتدا وارد حساب کاربری خود شوید یا ثبت‌نام کنید.', 'arvan-reseller' ); ?></p>
					<a class="arvan-btn arvan-btn--primary" href="<?php echo esc_url( home_url( '/arvan/auth' ) ); ?>">
						<?php esc_html_e( 'ورود / ثبت‌نام', 'arvan-reseller' ); ?>
					</a>
				<?php elseif ( ! $isEligible ) : ?>
					<div class="arvan-alert arvan-alert--warning">
						<?php esc_html_e( 'برای فعال‌سازی سرویس جدید، ابتدا باید اعتبار کیف پول خود را افزایش دهید.', 'arvan-reseller' ); ?>
					</div>
					<a class="arvan-btn arvan-btn--primary" href="<?php echo esc_url( home_url( '/arvan/recharge' ) ); ?>">
						<?php esc_html_e( 'افزایش اعتبار', 'arvan-reseller' ); ?>
					</a>
				<?php else : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="<?php echo esc_attr( OrderController::ACTION ); ?>" />
						<?php wp_nonce_field( OrderController::ACTION ); ?>

						<div class="arvan-field">
							<label class="arvan-field__label" for="arvan-domain"><?php esc_html_e( 'دامنه', 'arvan-reseller' ); ?></label>
							<input class="arvan-input" type="text" id="arvan-domain" name="domain" placeholder="example.com" required />
							<span class="arvan-field__help"><?php esc_html_e( 'دامنه‌ای که می‌خواهید از طریق CDN سرویس‌دهی شود.', 'arvan-reseller' ); ?></span>
						</div>

						<button type="submit" class="arvan-btn arvan-btn--primary"><?php esc_html_e( 'فعال‌سازی CDN', 'arvan-reseller' ); ?></button>
					</form>
				<?php endif; ?>
			</div>

		</div>
	</div>
</div>

<?php
get_footer();
