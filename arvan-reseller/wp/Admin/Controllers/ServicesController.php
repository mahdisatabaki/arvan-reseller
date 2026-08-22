<?php
/**
 * Admin Services — BACKLOG T-8.3, SCREEN-SPECS.md §5, DESIGN.md §14.
 *
 * One unscoped list across every customer (`ServiceRepository::allForAdmin()`),
 * the admin-only counterpart to the customer-scoped `allForCustomer()` the
 * frontend account dashboard uses (T-7.6). Owner and credential columns are
 * resolved from two maps built once from `CustomerRepository::all()` /
 * `ApiKeyRepository::all()` — never a per-row lookup — the same N+1 avoidance
 * TECH.md §13 calls out.
 *
 * The only write path here is "retry", for services stuck in
 * `provisioning_failed`. It never duplicates `ProvisioningService::retry()`'s
 * own status-transition/attempt-recording logic (built this session
 * specifically for this button) — this controller only resolves a real
 * `CdnClient` from the service's own stored `api_key_id`
 * (`CdnClientResolver::resolveById()`, DATA-MODEL.md §8's "lifecycle calls
 * always use this row's api_key_id" rule) and hands off.
 *
 * There is no per-service "recent charge" column wired up: that would need
 * `UsageLogRepository`, which is deliberately not part of this controller's
 * constructor (it has no admin-wide, non-N+1 read of "latest charge per
 * service" today) — the template renders an honest "—" for that cell instead
 * of guessing at a number.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Admin\Controllers;

use ArvanReseller\Lifecycle\ServiceStatus;
use ArvanReseller\Ports\ApiKeyRepository;
use ArvanReseller\Ports\CustomerRepository;
use ArvanReseller\Ports\ServiceRepository;
use ArvanReseller\Provisioning\ProvisioningService;
use ArvanReseller\Wp\Admin\AdminMenu;
use ArvanReseller\Wp\Arvan\CdnClientResolver;
use ArvanReseller\Wp\Support\Capabilities;

defined( 'ABSPATH' ) || exit;

final class ServicesController {

	private const ACTION_RETRY = 'arvan_services_retry';

	public function __construct(
		private readonly ServiceRepository $services,
		private readonly CustomerRepository $customers,
		private readonly ApiKeyRepository $apiKeys,
		private readonly CdnClientResolver $cdnClients,
		private readonly ProvisioningService $provisioning
	) {}

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION_RETRY, [ $this, 'handleRetry' ] );
	}

	public function render(): void {
		if ( ! Capabilities::currentUserCanView() ) {
			wp_die( esc_html__( 'شما اجازه‌ی دسترسی به این صفحه را ندارید.', 'arvan-reseller' ) );
		}

		$activeSlug = AdminMenu::SLUG_SERVICES;

		$allServices = $this->services->allForAdmin();

		$customerNames = [];
		foreach ( $this->customers->all() as $customer ) {
			$customerNames[ (int) $customer['id'] ] = (string) $customer['display_name'];
		}

		$apiKeyInfo = [];
		foreach ( $this->apiKeys->all() as $key ) {
			$apiKeyInfo[ (int) $key['id'] ] = [
				'label'     => (string) $key['label'],
				'last_four' => (string) $key['last_four'],
			];
		}

		$retryAction = self::ACTION_RETRY;

		$errorCode = isset( $_GET['arvan_error'] ) ? sanitize_key( wp_unslash( $_GET['arvan_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$retried   = isset( $_GET['arvan_retried'] ) ? sanitize_key( wp_unslash( $_GET['arvan_retried'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		require __DIR__ . '/../templates/services.php';
	}

	public function handleRetry(): void {
		if ( ! current_user_can( Capabilities::PROVISION ) ) {
			wp_die( esc_html__( 'شما اجازه‌ی انجام این عملیات را ندارید.', 'arvan-reseller' ) );
		}

		check_admin_referer( self::ACTION_RETRY );

		$serviceId = isset( $_POST['service_id'] ) ? (int) $_POST['service_id'] : 0;
		$service   = $this->services->find( $serviceId );

		if ( null === $service ) {
			$this->redirect( [ 'arvan_error' => 'service_not_found' ] );
		}

		if ( ServiceStatus::PROVISIONING_FAILED !== $service['status'] ) {
			$this->redirect( [ 'arvan_error' => 'not_retryable' ] );
		}

		$resolved = $this->cdnClients->resolveById( (int) $service['api_key_id'] );

		if ( null === $resolved ) {
			$this->redirect( [ 'arvan_error' => 'no_usable_key' ] );
		}

		$result = $this->provisioning->retry( $service, $resolved['client'] );

		$this->redirect( $result['ok'] ? [ 'arvan_retried' => 'ok' ] : [ 'arvan_retried' => 'failed' ] );
	}

	/**
	 * @param array<string, int|string> $extraArgs
	 */
	private function redirect( array $extraArgs = [] ): never {
		$url = add_query_arg(
			array_merge( [ 'page' => AdminMenu::SLUG_SERVICES ], $extraArgs ),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
