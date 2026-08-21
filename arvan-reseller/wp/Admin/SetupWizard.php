<?php
/**
 * Five-step Setup Wizard controller (SCREEN-SPECS.md §1, DESIGN.md §8).
 *
 * A classic WordPress admin page: GET renders the current step, POST
 * processes it with a nonce + capability check, then redirects (POST →
 * redirect → GET) so a reload never resubmits a form. No REST/AJAX layer —
 * TECH.md §2 calls for server-rendered templates with minimal JS, and a
 * 5-step onboarding flow has no need for anything richer.
 *
 * Every dependency is constructor-injected and pre-built by Plugin::boot();
 * this class does not `new` any of the five backend pieces it orchestrates
 * (AccessTokenGate, SecretStore, ApiKeyRepository, ApiKeyConnectionTester,
 * ResellerSettings). The one exception is `ArvanCdnClient` for step 2's
 * connection test: it needs the plaintext key the admin just submitted, so
 * it cannot exist before this request and must not survive past it.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Admin;

use ArvanReseller\Arvan\ApiKeyConnectionTester;
use ArvanReseller\Arvan\ArvanCdnClient;
use ArvanReseller\Domain\Money;
use ArvanReseller\Ports\ApiKeyRepository;
use ArvanReseller\Ports\SecretStore;
use ArvanReseller\Pricing\MarkupRate;
use ArvanReseller\Wp\Http\WordPressHttpClient;
use ArvanReseller\Wp\Installation\Installer;
use ArvanReseller\Wp\Security\AccessTokenGate;
use ArvanReseller\Wp\Support\Capabilities;
use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

final class SetupWizard {

	private const PAGE_SLUG = 'arvan-reseller-setup';
	private const LAST_STEP = 5;

	/**
	 * Set by handleRequest() when a POST fails validation, read back by
	 * render(). Both run in the same request/instance — see the class
	 * docblock note on why these cannot be merged into one method.
	 */
	private ?string $pendingError = null;

	public function __construct(
		private readonly AccessTokenGate $tokenGate,
		private readonly SecretStore $secretStore,
		private readonly ApiKeyRepository $apiKeys,
		private readonly ApiKeyConnectionTester $connectionTester,
		private readonly ResellerSettings $settings
	) {}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'registerPage' ] );
		add_action( 'admin_init', [ $this, 'maybeRedirectAfterActivation' ] );
	}

	/**
	 * A hidden page (no menu parent) — reachable by URL, not added to the
	 * nav. DESIGN.md §6's persistent admin menu (Dashboard/Customers/...)
	 * does not include the wizard; it is a one-time onboarding flow.
	 *
	 * POST handling is hooked to `load-{$hook}`, not called from render().
	 * WordPress has already flushed the page header (and therefore HTTP
	 * headers) by the time a submenu page's own render callback runs, so a
	 * `wp_safe_redirect()` from inside render() fails with "headers already
	 * sent" — confirmed by testing this against the real local WordPress
	 * install, not a guess. `load-{$hook}` fires during admin bootstrap,
	 * before any output, which is where WordPress admin pages are always
	 * supposed to process a submission and redirect.
	 */
	public function registerPage(): void {
		$hook = add_submenu_page(
			'', // Hidden page: no parent menu item links to it (WordPress's documented way to do this — not null).
			__( 'Arvan Reseller Setup', 'arvan-reseller' ),
			__( 'Arvan Reseller Setup', 'arvan-reseller' ),
			Capabilities::MANAGE,
			self::PAGE_SLUG,
			[ $this, 'render' ]
		);

		if ( false !== $hook ) {
			add_action( 'load-' . $hook, [ $this, 'handleRequest' ] );
		}
	}

	/**
	 * Runs before any HTML output. Processes a POST and redirects on
	 * success (ending the request here); on validation failure it stashes
	 * the message for render() and returns normally, so the same page still
	 * renders afterward with the error shown.
	 */
	public function handleRequest(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'arvan-reseller' ) );
		}

		// A finished wizard has nothing left to show here — send the admin
		// straight to the WordPress Dashboard rather than re-rendering a
		// step. This also covers the moment handleLayoutAndFinish() itself
		// just set the flag: that method redirects on its own immediately
		// after, so this check never fires twice for the same completion.
		if ( $this->settings->isWizardComplete() ) {
			wp_safe_redirect( admin_url( 'index.php' ) );
			exit;
		}

		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}

		$step = $this->currentStepFromRequest();

		$this->pendingError = $this->handleSubmit( $step );
	}

	/**
	 * Consumes the flag Installer::activate() sets. Fires once: the option
	 * is deleted immediately, so re-activating without deactivating first,
	 * or any later admin page load, does not redirect again.
	 */
	public function maybeRedirectAfterActivation(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			return;
		}

		if ( '1' !== get_option( Installer::ACTIVATION_REDIRECT_OPTION ) ) {
			return;
		}

		delete_option( Installer::ACTIVATION_REDIRECT_OPTION );

		if ( wp_doing_ajax() || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	/**
	 * By the time this runs, handleRequest() has already redirected away if
	 * the wizard was complete (see that method) — so this only ever renders
	 * an in-progress step.
	 */
	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'arvan-reseller' ) );
		}

		$this->renderTemplate( $this->currentStepFromRequest(), $this->pendingError );
	}

	/**
	 * Never lets a URL jump ahead of what has actually been completed
	 * (DESIGN.md §8: "persist completed steps").
	 */
	private function currentStepFromRequest(): int {
		$requestedStep = isset( $_GET['step'] ) ? (int) $_GET['step'] : $this->settings->getWizardStep(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$requestedStep = max( 1, min( self::LAST_STEP, $requestedStep ) );

		return min( $requestedStep, $this->settings->getWizardStep() );
	}

	private function handleSubmit( int $step ): ?string {
		check_admin_referer( $this->nonceAction( $step ) );

		return match ( $step ) {
			1 => $this->handleAccessToken(),
			2 => $this->handleApiKey(),
			3 => $this->handleBusinessProfile(),
			4 => $this->handleMarkupAndLifecycle(),
			5 => $this->handleLayoutAndFinish(),
			default => __( 'Invalid step.', 'arvan-reseller' ),
		};
	}

	private function handleAccessToken(): ?string {
		$token = isset( $_POST['access_token'] ) ? (string) wp_unslash( $_POST['access_token'] ) : '';

		if ( '' === $token ) {
			return __( 'Enter the access token provided by the hackathon team.', 'arvan-reseller' );
		}

		if ( $this->tokenGate->isRateLimited() ) {
			return __( 'Too many failed attempts. Try again in a few minutes.', 'arvan-reseller' );
		}

		if ( ! $this->tokenGate->verify( $token ) ) {
			return __( 'That access token is not valid.', 'arvan-reseller' );
		}

		$this->advanceTo( 2 );
	}

	/**
	 * Test-then-persist (not persist-then-test): the submitted plaintext
	 * key is used directly to build a throwaway `ArvanCdnClient` and tested
	 * before anything is written. A key that fails the test is never
	 * encrypted, never stored, and never echoed back into the form.
	 */
	private function handleApiKey(): ?string {
		$label     = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$purpose   = isset( $_POST['purpose'] ) ? sanitize_key( wp_unslash( $_POST['purpose'] ) ) : 'cdn';
		$plaintext = isset( $_POST['api_key'] ) ? (string) wp_unslash( $_POST['api_key'] ) : '';

		if ( '' === $label || '' === $plaintext ) {
			return __( 'Enter a label and the API key.', 'arvan-reseller' );
		}

		$client = new ArvanCdnClient( new WordPressHttpClient(), $plaintext );
		$result = $this->connectionTester->test( $client );

		if ( ! $result['ok'] ) {
			return $result['message'];
		}

		$ciphertext  = $this->secretStore->encrypt( $plaintext );
		$fingerprint = hash( 'sha256', $plaintext );
		$lastFour    = substr( $plaintext, -4 );
		// $plaintext is not referenced again after this point.

		$created = $this->apiKeys->create( $label, $purpose, $ciphertext, $fingerprint, $lastFour );
		$this->apiKeys->recordCheckResult( $created['id'], true, $result['message'] );

		if ( $created['created'] ) {
			// The wizard's own key is the reseller's first for this purpose —
			// make it the default so provisioning has something to use
			// without a second trip to Settings.
			$this->apiKeys->setDefault( $created['id'] );
		}

		$this->advanceTo( 3 );
	}

	private function handleBusinessProfile(): ?string {
		$name = isset( $_POST['name'] ) ? (string) wp_unslash( $_POST['name'] ) : '';

		if ( '' === trim( $name ) ) {
			return __( 'Business name is required.', 'arvan-reseller' );
		}

		$this->settings->setBusinessProfile(
			$name,
			isset( $_POST['logo_url'] ) ? (string) wp_unslash( $_POST['logo_url'] ) : '',
			isset( $_POST['website'] ) ? (string) wp_unslash( $_POST['website'] ) : '',
			isset( $_POST['email'] ) ? (string) wp_unslash( $_POST['email'] ) : '',
			isset( $_POST['phone'] ) ? (string) wp_unslash( $_POST['phone'] ) : '',
			isset( $_POST['about'] ) ? (string) wp_unslash( $_POST['about'] ) : ''
		);

		$this->advanceTo( 4 );
	}

	/**
	 * Validation is not reimplemented here — `MarkupRate::fromPercent()`
	 * (T-0.7) is the single place the 20% ceiling is enforced, and this
	 * method just catches what it throws.
	 */
	private function handleMarkupAndLifecycle(): ?string {
		$percent = isset( $_POST['markup_percent'] ) ? (float) $_POST['markup_percent'] : 0.0;

		try {
			$rate = MarkupRate::fromPercent( $percent );
		} catch ( InvalidArgumentException ) {
			return __( 'Markup must be between 0% and 20%.', 'arvan-reseller' );
		}

		$this->settings->setMarkupRate( $rate );

		$notifyToman = isset( $_POST['notify_threshold_toman'] ) ? (int) $_POST['notify_threshold_toman'] : 0;
		$resumeToman = isset( $_POST['resume_threshold_toman'] ) ? (int) $_POST['resume_threshold_toman'] : 0;
		$graceDays   = isset( $_POST['terminate_grace_days'] ) ? (int) $_POST['terminate_grace_days'] : 7;

		$this->settings->setLifecyclePolicy(
			Money::fromToman( $notifyToman )->toRial(),
			Money::fromToman( $resumeToman )->toRial(),
			$graceDays
		);

		$this->advanceTo( 5 );
	}

	/**
	 * No layout choice here — the public CDN sales page that would actually
	 * use it is T-7.3, not this task, so offering a selector with no visible
	 * effect would be dishonest UX. The default is stored anyway (not
	 * user-facing) purely so T-7.3 has a value to read on day one instead of
	 * needing its own migration.
	 */
	private function handleLayoutAndFinish(): ?string {
		$this->settings->setLayout( ResellerSettings::LAYOUT_CARDS );
		$this->settings->setWizardComplete( true );

		wp_safe_redirect( admin_url( 'index.php' ) );
		exit;
	}

	private function advanceTo( int $step ): void {
		$this->settings->setWizardStep( max( $this->settings->getWizardStep(), $step ) );

		wp_safe_redirect( add_query_arg( 'step', $step, admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ) );
		exit;
	}

	private function nonceAction( int $step ): string {
		return 'arvan_setup_wizard_step_' . $step;
	}

	private function renderTemplate( int $step, ?string $error ): void {
		$furthestStep    = $this->settings->getWizardStep();
		$businessProfile = $this->settings->getBusinessProfile();
		$lifecyclePolicy = $this->settings->getLifecyclePolicy();
		$markupPercent   = $this->settings->getMarkupRate()->toPercent();
		$layout          = $this->settings->getLayout();
		$rateLimited     = $this->tokenGate->isRateLimited();
		$nonceAction     = $this->nonceAction( $step );
		$pageSlug        = self::PAGE_SLUG;
		$lastStep        = self::LAST_STEP;

		require __DIR__ . '/templates/setup-wizard.php';
	}
}
