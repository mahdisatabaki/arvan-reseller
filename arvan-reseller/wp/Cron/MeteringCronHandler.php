<?php
/**
 * Drives `MeteringService` + `BillingService` across every service due for
 * metering — the actual listener behind `Scheduler::HOOK_METER` (T-0.5
 * scheduled the cron event; nothing consumed it until now), and the same
 * code path a manual "Run Billing Cycle Now" trigger uses (BACKLOG T-5.4:
 * "invokes same application service" — there is exactly one implementation
 * of a billing run, not a duplicated admin-triggered copy).
 *
 * Per-service `api_key_id` (DATA-MODEL.md §8) means a single shared
 * `CdnClient` cannot cover every due service — one is resolved per service
 * here via `CdnClientResolver` (T-7.3 extracted this from what used to be
 * this class's own inline decrypt-and-construct logic, once the CDN order
 * flow needed the same step starting from a different lookup).
 *
 * The transient lock is T-5.2's "cron/process lock": WP-Cron can fire the
 * same hook from two overlapping requests (it is traffic-triggered, not a
 * real scheduler), and a second run starting mid-batch would otherwise
 * duplicate the whole per-service loop. It is deliberately coarse (one
 * lock for the entire batch, not per-service) — `BillingService`'s own
 * idempotency key is what actually makes a double-run financially safe;
 * this lock exists to avoid wasted duplicate provider API calls, not as
 * the safety mechanism itself.
 *
 * After a successful debit, this is also where `SuspensionEngine` (T-6.3)
 * and `LowBalanceNotifier` (T-6.2) get their one call each — both read the
 * post-debit balance `BillingService::bill()` already returned, so neither
 * needs its own extra wallet read.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Cron;

use ArvanReseller\Arvan\CdnClient;
use ArvanReseller\Arvan\CdnProviderException;
use ArvanReseller\Billing\BillingService;
use ArvanReseller\Domain\Money;
use ArvanReseller\Lifecycle\SuspensionEngine;
use ArvanReseller\Lifecycle\ThresholdPolicyResolver;
use ArvanReseller\Metering\MeteringService;
use ArvanReseller\Ports\Clock;
use ArvanReseller\Ports\CustomerRepository;
use ArvanReseller\Ports\SecretStore;
use ArvanReseller\Ports\ServiceRepository;
use ArvanReseller\Ports\WalletRepository;
use ArvanReseller\Pricing\MarkupRate;
use ArvanReseller\Wallet\LowBalanceNotifier;
use ArvanReseller\Wp\Admin\ResellerSettings;
use ArvanReseller\Wp\Arvan\CdnClientResolver;
use ArvanReseller\Wp\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

final class MeteringCronHandler {

	private const LOCK_TRANSIENT = 'arvan_metering_lock';
	private const LOCK_TTL       = 5 * MINUTE_IN_SECONDS;

	/**
	 * Public: the Admin Dashboard's "Run Billing Cycle Now" button
	 * (BACKLOG T-8.1, SCREEN-SPECS.md §2) needs this to build its
	 * admin-post.php link and matching `wp_nonce_field()`.
	 */
	public const MANUAL_ACTION = 'arvan_run_billing_cycle';

	public function __construct(
		private readonly ServiceRepository $services,
		private readonly CdnClientResolver $cdnClients,
		private readonly MeteringService $metering,
		private readonly BillingService $billing,
		private readonly ResellerSettings $settings,
		private readonly Clock $clock,
		private readonly WalletRepository $wallets,
		private readonly CustomerRepository $customers,
		private readonly SuspensionEngine $suspension,
		private readonly ThresholdPolicyResolver $thresholds,
		private readonly LowBalanceNotifier $notifier
	) {}

	public function register(): void {
		add_action( Scheduler::HOOK_METER, [ $this, 'run' ] );
		add_action( 'admin_post_' . self::MANUAL_ACTION, [ $this, 'handleManualTrigger' ] );
	}

	/**
	 * "Run Billing Cycle Now" — nonce + capability protected (CLAUDE.md:
	 * "every state-changing request uses authorization + CSRF protection").
	 * No dedicated admin page links to this yet (T-9.2, not built); it is
	 * reachable at `admin-post.php?action=arvan_run_billing_cycle` with a
	 * matching nonce, ready for that page to point at once it exists.
	 */
	public function handleManualTrigger(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'شما اجازه‌ی دسترسی به این عملیات را ندارید.', 'arvan-reseller' ) );
		}

		check_admin_referer( self::MANUAL_ACTION );

		$this->run( get_current_user_id() ?: null );

		wp_safe_redirect( wp_get_referer() ?: admin_url( 'index.php' ) );
		exit;
	}

	/**
	 * @return array{ok: bool, processed: int, results: array<int, array<string, mixed>>}
	 */
	public function run( ?int $actorWpUserId = null ): array {
		if ( false !== get_transient( self::LOCK_TRANSIENT ) ) {
			return [ 'ok' => false, 'processed' => 0, 'results' => [] ];
		}

		set_transient( self::LOCK_TRANSIENT, 1, self::LOCK_TTL );

		try {
			return $this->processDue( $actorWpUserId );
		} finally {
			delete_transient( self::LOCK_TRANSIENT );
		}
	}

	/**
	 * @return array{ok: bool, processed: int, results: array<int, array<string, mixed>>}
	 */
	private function processDue( ?int $actorWpUserId ): array {
		$due        = $this->services->dueForMetering( $this->clock->now() );
		$markupRate = $this->settings->getMarkupRate();
		$unitPrice  = Money::fromRial( $this->settings->getUnitPriceRialPerGb() );
		$results    = [];

		foreach ( $due as $service ) {
			$results[] = $this->processOne( $service, $markupRate, $unitPrice, $actorWpUserId );
		}

		return [
			'ok'        => true,
			'processed' => count( $results ),
			'results'   => $results,
		];
	}

	/**
	 * @param array<string, mixed> $service
	 * @return array<string, mixed>
	 */
	private function processOne( array $service, MarkupRate $markupRate, Money $unitPrice, ?int $actorWpUserId ): array {
		$serviceId  = (int) $service['id'];
		$customerId = (int) $service['customer_id'];
		$client     = $this->resolveClient( (int) ( $service['api_key_id'] ?? 0 ) );

		if ( null === $client ) {
			return [
				'service_id' => $serviceId,
				'ok'         => false,
				'message'    => 'No usable API key for this service.',
			];
		}

		try {
			$usage = $this->metering->measure( $service, $client );
		} catch ( CdnProviderException $e ) {
			return [
				'service_id' => $serviceId,
				'ok'         => false,
				'message'    => $e->getMessage(),
			];
		}

		$previousBalance = $this->wallets->currentBalance( $customerId );
		$result          = $this->billing->bill( $usage, $markupRate, $unitPrice );

		if ( $result['ok'] && null !== $result['balance'] ) {
			$this->applyLifecycleEffects( $serviceId, $customerId, $previousBalance, $result['balance'], $result['usage_log_id'], $actorWpUserId );
		}

		return [ 'service_id' => $serviceId ] + $result;
	}

	/**
	 * Suspend-on-zero-balance (T-6.3) and the low-balance notice (T-6.2) both
	 * hang off the same post-debit balance, in the same request, per
	 * BILLING.md §14's "invoke SuspensionEngine in the same billing workflow"
	 * — neither waits for a separate cron pass.
	 */
	private function applyLifecycleEffects(
		int $serviceId,
		int $customerId,
		Money $previousBalance,
		Money $newBalance,
		?int $usageLogId,
		?int $actorWpUserId
	): void {
		$this->suspension->suspendIfNeeded( $serviceId, $customerId, $newBalance, $actorWpUserId );

		$customer = $this->customers->find( $customerId );

		if ( null === $customer || '' === (string) ( $customer['email'] ?? '' ) ) {
			return;
		}

		$policy = $this->thresholds->resolve( $customerId, $this->settings->getLifecyclePolicy()['terminate_grace_days'] );

		$this->notifier->notifyIfCrossed(
			$customerId,
			(string) $customer['email'],
			$previousBalance,
			$newBalance,
			$policy->lowBalanceThreshold,
			'usage-log-' . $usageLogId
		);
	}

	private function resolveClient( int $api_key_id ): ?CdnClient {
		$resolved = $this->cdnClients->resolveById( $api_key_id );

		return null === $resolved ? null : $resolved['client'];
	}
}
