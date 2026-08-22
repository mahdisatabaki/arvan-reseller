<?php
/**
 * Handles CDN order submission from the sales page (BACKLOG T-7.3).
 *
 * `admin-post.php?action=arvan_place_order`, nonce + logged-in-customer
 * checked, same pattern `MeteringCronHandler::handleManualTrigger()` already
 * established for a state-changing plugin-owned action (CLAUDE.md: "every
 * state-changing request uses authorization + CSRF protection").
 *
 * The order is intentionally not attempted at all — not even a local
 * `provisioning` row — when the wallet balance is already `<= 0` (DESIGN.md
 * §9's "wallet context if logged in" / SCREEN-SPECS.md §9's "insufficient/no
 * credit → Add Credit" CTA): a customer with nothing to bill should never
 * reach `ProvisioningService::provision()` in the first place, since a
 * successful create there would immediately be metered against a wallet that
 * cannot pay for it.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Frontend\Controllers;

use ArvanReseller\Domain\Money;
use ArvanReseller\Ports\WalletRepository;
use ArvanReseller\Provisioning\ProvisioningService;
use ArvanReseller\Wp\Admin\ResellerSettings;
use ArvanReseller\Wp\Arvan\CdnClientResolver;
use ArvanReseller\Wp\Frontend\CurrentCustomer;

defined( 'ABSPATH' ) || exit;

final class OrderController {

	public const ACTION = 'arvan_place_order';

	public function __construct(
		private readonly CurrentCustomer $currentCustomer,
		private readonly WalletRepository $wallets,
		private readonly CdnClientResolver $cdnClients,
		private readonly ProvisioningService $provisioning,
		private readonly ResellerSettings $settings
	) {}

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
	}

	public function handle(): void {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( home_url( '/arvan/auth' ) );
			exit;
		}

		check_admin_referer( self::ACTION );

		$customer = $this->currentCustomer->resolve();

		if ( null === $customer ) {
			wp_die( esc_html__( 'حساب مشتری شما یافت نشد.', 'arvan-reseller' ) );
		}

		$customerId = (int) $customer['id'];
		$domain     = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';

		if ( ! self::isValidDomain( $domain ) ) {
			$this->redirectWithError( 'invalid_domain' );
		}

		if ( $this->wallets->currentBalance( $customerId )->lessThanOrEqual( Money::zero() ) ) {
			$this->redirectWithError( 'insufficient_balance' );
		}

		$resolved = $this->cdnClients->resolveDefault( 'cdn' );

		if ( null === $resolved ) {
			$this->redirectWithError( 'not_configured' );
		}

		$result = $this->provisioning->provision(
			$customerId,
			$domain,
			$resolved['api_key_id'],
			$this->settings->getMarkupRate()->toBasisPoints(),
			$resolved['client']
		);

		wp_safe_redirect( home_url( '/arvan/account/services/' . $result['service_id'] ) );
		exit;
	}

	/**
	 * A permissive-but-sane hostname check: this is order-time input
	 * validation, not a DNS/ownership proof (there is none to make here) —
	 * ArvanCloud's own API is the source of truth for whether the domain is
	 * actually usable, surfaced back to the customer as a provisioning
	 * failure if not.
	 */
	private static function isValidDomain( string $domain ): bool {
		if ( '' === $domain || strlen( $domain ) > 253 ) {
			return false;
		}

		return 1 === preg_match(
			'/^(?=.{1,253}$)(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/',
			$domain
		);
	}

	private function redirectWithError( string $code ): never {
		wp_safe_redirect( add_query_arg( 'arvan_error', $code, home_url( '/arvan/cdn' ) ) );
		exit;
	}
}
