<?php
/**
 * Shared top navigation for every plugin-owned customer screen.
 *
 * Included (not `get_template_part()`, since this lives under the plugin,
 * not a theme) after `get_header()` inside each `templates/*.php` file's own
 * `.arvan-app` wrapper. Reads `$arvan_customer` (the current customer row or
 * null — set by the including template via `CurrentCustomer::resolve()`) and
 * `$arvan_wallet_balance_rial` (int|null, only meaningful when a customer is
 * present) from the including scope, PHP's normal `require` variable-sharing
 * rather than a parameter list, matching how WordPress template parts work.
 *
 * DESIGN.md §6 "Customer" nav: CDN / Account, with Recharge/Auth as
 * contextual views reached from here rather than being their own nav items.
 *
 * @package ArvanReseller
 *
 * @var array<string, mixed>|null $arvan_customer
 * @var int|null                  $arvan_wallet_balance_rial
 * @var string|null               $arvan_business_name
 */

defined( 'ABSPATH' ) || exit;

$arvan_customer            = $arvan_customer ?? null;
$arvan_wallet_balance_rial = $arvan_wallet_balance_rial ?? null;
$arvan_business_name       = $arvan_business_name ?? '';
?>
<div class="arvan-topbar">
	<a class="arvan-topbar__brand" href="<?php echo esc_url( home_url( '/arvan/cdn' ) ); ?>">
		<?php echo esc_html( '' !== $arvan_business_name ? $arvan_business_name : __( 'آروان ریسلر', 'arvan-reseller' ) ); ?>
	</a>

	<nav class="arvan-topbar__nav">
		<a href="<?php echo esc_url( home_url( '/arvan/cdn' ) ); ?>"><?php esc_html_e( 'سرویس CDN', 'arvan-reseller' ); ?></a>

		<?php if ( null !== $arvan_customer ) : ?>
			<a href="<?php echo esc_url( home_url( '/arvan/account' ) ); ?>"><?php esc_html_e( 'حساب کاربری', 'arvan-reseller' ); ?></a>

			<span class="arvan-badge arvan-topbar__balance">
				<?php
				printf(
					/* translators: %s: formatted wallet balance in Toman */
					esc_html__( 'موجودی: %s تومان', 'arvan-reseller' ),
					esc_html( number_format_i18n( intdiv( (int) $arvan_wallet_balance_rial, 10 ) ) )
				);
				?>
			</span>

			<a href="<?php echo esc_url( wp_logout_url( home_url( '/arvan/cdn' ) ) ); ?>"><?php esc_html_e( 'خروج', 'arvan-reseller' ); ?></a>
		<?php else : ?>
			<a href="<?php echo esc_url( home_url( '/arvan/auth' ) ); ?>"><?php esc_html_e( 'ورود / ثبت‌نام', 'arvan-reseller' ); ?></a>
		<?php endif; ?>
	</nav>
</div>
