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
 * `CdnClient` cannot cover every due service — one is built per service
 * here, in this WP-layer class, since only it may touch `SecretStore` and
 * `WordPressHttpClient` (the same boundary `ProvisioningService`/
 * `MeteringService` themselves stay on the far side of).
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
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Cron;

use ArvanReseller\Arvan\ArvanCdnClient;
use ArvanReseller\Arvan\CdnClient;
use ArvanReseller\Arvan\CdnProviderException;
use ArvanReseller\Billing\BillingService;
use ArvanReseller\Domain\Money;
use ArvanReseller\Metering\MeteringService;
use ArvanReseller\Ports\ApiKeyRepository;
use ArvanReseller\Ports\Clock;
use ArvanReseller\Ports\SecretStore;
use ArvanReseller\Ports\ServiceRepository;
use ArvanReseller\Pricing\MarkupRate;
use ArvanReseller\Wp\Admin\ResellerSettings;
use ArvanReseller\Wp\Http\WordPressHttpClient;
use ArvanReseller\Wp\Support\Capabilities;
use RuntimeException;

defined( 'ABSPATH' ) || exit;

final class MeteringCronHandler {

	private const LOCK_TRANSIENT = 'arvan_metering_lock';
	private const LOCK_TTL       = 5 * MINUTE_IN_SECONDS;
	private const MANUAL_ACTION  = 'arvan_run_billing_cycle';

	public function __construct(
		private readonly ServiceRepository $services,
		private readonly ApiKeyRepository $apiKeys,
		private readonly SecretStore $secretStore,
		private readonly MeteringService $metering,
		private readonly BillingService $billing,
		private readonly ResellerSettings $settings,
		private readonly Clock $clock
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

		$this->run();

		wp_safe_redirect( wp_get_referer() ?: admin_url( 'index.php' ) );
		exit;
	}

	/**
	 * @return array{ok: bool, processed: int, results: array<int, array<string, mixed>>}
	 */
	public function run(): array {
		if ( false !== get_transient( self::LOCK_TRANSIENT ) ) {
			return [ 'ok' => false, 'processed' => 0, 'results' => [] ];
		}

		set_transient( self::LOCK_TRANSIENT, 1, self::LOCK_TTL );

		try {
			return $this->processDue();
		} finally {
			delete_transient( self::LOCK_TRANSIENT );
		}
	}

	/**
	 * @return array{ok: bool, processed: int, results: array<int, array<string, mixed>>}
	 */
	private function processDue(): array {
		$due        = $this->services->dueForMetering( $this->clock->now() );
		$markupRate = $this->settings->getMarkupRate();
		$unitPrice  = Money::fromRial( $this->settings->getUnitPriceRialPerGb() );
		$results    = [];

		foreach ( $due as $service ) {
			$results[] = $this->processOne( $service, $markupRate, $unitPrice );
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
	private function processOne( array $service, MarkupRate $markupRate, Money $unitPrice ): array {
		$serviceId = (int) $service['id'];
		$client    = $this->resolveClient( (int) ( $service['api_key_id'] ?? 0 ) );

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

		return [ 'service_id' => $serviceId ] + $this->billing->bill( $usage, $markupRate, $unitPrice );
	}

	private function resolveClient( int $api_key_id ): ?CdnClient {
		if ( 0 === $api_key_id ) {
			return null;
		}

		$key = $this->apiKeys->find( $api_key_id );

		if ( null === $key || 'active' !== $key['status'] ) {
			return null;
		}

		try {
			$plaintext = $this->secretStore->decrypt( $key['ciphertext'] );
		} catch ( RuntimeException ) {
			return null;
		}

		return new ArvanCdnClient( new WordPressHttpClient(), $plaintext );
	}
}
