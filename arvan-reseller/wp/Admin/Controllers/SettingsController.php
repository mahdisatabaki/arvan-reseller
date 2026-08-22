<?php
/**
 * Admin Settings — BACKLOG T-8.5, SCREEN-SPECS.md §7, DESIGN.md §6/§15.
 *
 * Five tabs (Business/API Keys/Pricing/Lifecycle/Layout) on one page,
 * server-rendered `?tab=` switching like the frontend account.php (T-7.6).
 * Every tab's write path reuses a method `SetupWizard` already built and
 * proved out — `ResellerSettings::setBusinessProfile()/setPricing()/
 * setLifecyclePolicy()/setLayout()`, `ApiKeyRepository::create()/
 * setDefault()/setStatus()/recordCheckResult()` — this controller is a
 * post-setup editing surface for the same settings, not a second
 * implementation of them. The API Key "Add"/"Test" actions copy
 * SetupWizard::handleApiKey()'s test-then-persist order exactly: a
 * submitted key is never encrypted or stored until `ApiKeyConnectionTester`
 * confirms it works, and the plaintext is never referenced again afterward.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Admin\Controllers;

use ArvanReseller\Arvan\ApiKeyConnectionTester;
use ArvanReseller\Arvan\ArvanCdnClient;
use ArvanReseller\Domain\Money;
use ArvanReseller\Ports\ApiKeyRepository;
use ArvanReseller\Ports\SecretStore;
use ArvanReseller\Pricing\MarkupRate;
use ArvanReseller\Wp\Admin\AdminMenu;
use ArvanReseller\Wp\Admin\ResellerSettings;
use ArvanReseller\Wp\Http\WordPressHttpClient;
use ArvanReseller\Wp\Support\Capabilities;
use InvalidArgumentException;

defined( 'ABSPATH' ) || exit;

final class SettingsController {

	private const ACTION_BUSINESS  = 'arvan_settings_save_business';
	private const ACTION_PRICING   = 'arvan_settings_save_pricing';
	private const ACTION_LIFECYCLE = 'arvan_settings_save_lifecycle';
	private const ACTION_LAYOUT    = 'arvan_settings_save_layout';
	private const ACTION_KEY_ADD   = 'arvan_settings_add_api_key';
	private const ACTION_KEY_TEST  = 'arvan_settings_test_api_key';
	private const ACTION_KEY_DEFAULT = 'arvan_settings_set_default_api_key';
	private const ACTION_KEY_STATUS  = 'arvan_settings_toggle_api_key_status';

	public function __construct(
		private readonly SecretStore $secretStore,
		private readonly ApiKeyRepository $apiKeys,
		private readonly ApiKeyConnectionTester $connectionTester,
		private readonly ResellerSettings $settings
	) {}

	public function register(): void {
		add_action( 'admin_post_' . self::ACTION_BUSINESS, [ $this, 'handleBusiness' ] );
		add_action( 'admin_post_' . self::ACTION_PRICING, [ $this, 'handlePricing' ] );
		add_action( 'admin_post_' . self::ACTION_LIFECYCLE, [ $this, 'handleLifecycle' ] );
		add_action( 'admin_post_' . self::ACTION_LAYOUT, [ $this, 'handleLayout' ] );
		add_action( 'admin_post_' . self::ACTION_KEY_ADD, [ $this, 'handleAddKey' ] );
		add_action( 'admin_post_' . self::ACTION_KEY_TEST, [ $this, 'handleTestKey' ] );
		add_action( 'admin_post_' . self::ACTION_KEY_DEFAULT, [ $this, 'handleSetDefaultKey' ] );
		add_action( 'admin_post_' . self::ACTION_KEY_STATUS, [ $this, 'handleToggleKeyStatus' ] );
	}

	public function render(): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'شما اجازه‌ی دسترسی به این صفحه را ندارید.', 'arvan-reseller' ) );
		}

		$activeSlug  = AdminMenu::SLUG_SETTINGS;
		$allowedTabs = [ 'business', 'api-keys', 'pricing', 'lifecycle', 'layout' ];
		$activeTab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'business'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $activeTab, $allowedTabs, true ) ) {
			$activeTab = 'business';
		}

		$businessProfile = $this->settings->getBusinessProfile();
		$apiKeyRows      = $this->apiKeys->all();
		$markupPercent   = $this->settings->getMarkupRate()->toPercent();
		$unitPriceToman  = Money::fromRial( $this->settings->getUnitPriceRialPerGb() )->toToman();
		$lifecyclePolicy = $this->settings->getLifecyclePolicy();
		$layout          = $this->settings->getLayout();

		$errorCode = isset( $_GET['arvan_error'] ) ? sanitize_key( wp_unslash( $_GET['arvan_error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$saved     = isset( $_GET['arvan_saved'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$actions = [
			'business'  => self::ACTION_BUSINESS,
			'pricing'   => self::ACTION_PRICING,
			'lifecycle' => self::ACTION_LIFECYCLE,
			'layout'    => self::ACTION_LAYOUT,
			'key_add'   => self::ACTION_KEY_ADD,
			'key_test'  => self::ACTION_KEY_TEST,
			'key_default' => self::ACTION_KEY_DEFAULT,
			'key_status'  => self::ACTION_KEY_STATUS,
		];

		require __DIR__ . '/../templates/settings.php';
	}

	public function handleBusiness(): void {
		$this->guard( self::ACTION_BUSINESS );

		$this->settings->setBusinessProfile(
			isset( $_POST['name'] ) ? (string) wp_unslash( $_POST['name'] ) : '',
			isset( $_POST['logo_url'] ) ? (string) wp_unslash( $_POST['logo_url'] ) : '',
			isset( $_POST['website'] ) ? (string) wp_unslash( $_POST['website'] ) : '',
			isset( $_POST['email'] ) ? (string) wp_unslash( $_POST['email'] ) : '',
			isset( $_POST['phone'] ) ? (string) wp_unslash( $_POST['phone'] ) : '',
			isset( $_POST['about'] ) ? (string) wp_unslash( $_POST['about'] ) : ''
		);

		$this->redirect( 'business', [ 'arvan_saved' => 1 ] );
	}

	public function handlePricing(): void {
		$this->guard( self::ACTION_PRICING );

		$percent = isset( $_POST['markup_percent'] ) ? (float) $_POST['markup_percent'] : 0.0;

		try {
			$rate = MarkupRate::fromPercent( $percent );
		} catch ( InvalidArgumentException ) {
			$this->redirect( 'pricing', [ 'arvan_error' => 'invalid_markup' ] );
		}

		$unitPriceToman = isset( $_POST['unit_price_toman_per_gb'] ) ? (int) $_POST['unit_price_toman_per_gb'] : 0;

		if ( $unitPriceToman < 0 ) {
			$this->redirect( 'pricing', [ 'arvan_error' => 'invalid_unit_price' ] );
		}

		$this->settings->setPricing( $rate, Money::fromToman( $unitPriceToman )->toRial() );

		$this->redirect( 'pricing', [ 'arvan_saved' => 1 ] );
	}

	public function handleLifecycle(): void {
		$this->guard( self::ACTION_LIFECYCLE );

		$notifyToman = isset( $_POST['notify_threshold_toman'] ) ? (int) $_POST['notify_threshold_toman'] : 0;
		$resumeToman = isset( $_POST['resume_threshold_toman'] ) ? (int) $_POST['resume_threshold_toman'] : 0;
		$graceDays   = isset( $_POST['terminate_grace_days'] ) ? (int) $_POST['terminate_grace_days'] : 7;

		$this->settings->setLifecyclePolicy(
			Money::fromToman( $notifyToman )->toRial(),
			Money::fromToman( $resumeToman )->toRial(),
			$graceDays
		);

		$this->redirect( 'lifecycle', [ 'arvan_saved' => 1 ] );
	}

	public function handleLayout(): void {
		$this->guard( self::ACTION_LAYOUT );

		$layout = isset( $_POST['layout'] ) ? sanitize_key( wp_unslash( $_POST['layout'] ) ) : ResellerSettings::LAYOUT_CARDS;

		$this->settings->setLayout( $layout );

		$this->redirect( 'layout', [ 'arvan_saved' => 1 ] );
	}

	public function handleAddKey(): void {
		$this->guard( self::ACTION_KEY_ADD );

		$label     = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$purpose   = isset( $_POST['purpose'] ) ? sanitize_key( wp_unslash( $_POST['purpose'] ) ) : 'cdn';
		$plaintext = isset( $_POST['api_key'] ) ? (string) wp_unslash( $_POST['api_key'] ) : '';

		if ( '' === $label || '' === $plaintext ) {
			$this->redirect( 'api-keys', [ 'arvan_error' => 'missing_key_fields' ] );
		}

		$client = new ArvanCdnClient( new WordPressHttpClient(), $plaintext );
		$result = $this->connectionTester->test( $client );

		if ( ! $result['ok'] ) {
			$this->redirect( 'api-keys', [ 'arvan_error' => 'key_test_failed' ] );
		}

		$ciphertext  = $this->secretStore->encrypt( $plaintext );
		$fingerprint = hash( 'sha256', $plaintext );
		$lastFour    = substr( $plaintext, -4 );
		// $plaintext is not referenced again after this point.

		$created = $this->apiKeys->create( $label, $purpose, $ciphertext, $fingerprint, $lastFour );
		$this->apiKeys->recordCheckResult( $created['id'], true, $result['message'] );

		if ( null === $this->apiKeys->findDefault( $purpose ) ) {
			$this->apiKeys->setDefault( $created['id'] );
		}

		$this->redirect( 'api-keys', [ 'arvan_saved' => 1 ] );
	}

	public function handleTestKey(): void {
		$this->guard( self::ACTION_KEY_TEST );

		$id  = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$key = $this->apiKeys->find( $id );

		if ( null === $key ) {
			$this->redirect( 'api-keys', [ 'arvan_error' => 'key_not_found' ] );
		}

		$plaintext = $this->secretStore->decrypt( (string) $key['ciphertext'] );
		$client    = new ArvanCdnClient( new WordPressHttpClient(), $plaintext );
		$result    = $this->connectionTester->test( $client );
		// $plaintext is not referenced again after this point.

		$this->apiKeys->recordCheckResult( $id, $result['ok'], $result['message'] );

		$this->redirect( 'api-keys', $result['ok'] ? [ 'arvan_saved' => 1 ] : [ 'arvan_error' => 'key_test_failed' ] );
	}

	public function handleSetDefaultKey(): void {
		$this->guard( self::ACTION_KEY_DEFAULT );

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		if ( null !== $this->apiKeys->find( $id ) ) {
			$this->apiKeys->setDefault( $id );
		}

		$this->redirect( 'api-keys', [ 'arvan_saved' => 1 ] );
	}

	public function handleToggleKeyStatus(): void {
		$this->guard( self::ACTION_KEY_STATUS );

		$id  = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$key = $this->apiKeys->find( $id );

		if ( null !== $key ) {
			$next = 'active' === $key['status'] ? 'disabled' : 'active';
			$this->apiKeys->setStatus( $id, $next );
		}

		$this->redirect( 'api-keys', [ 'arvan_saved' => 1 ] );
	}

	private function guard( string $nonceAction ): void {
		if ( ! current_user_can( Capabilities::MANAGE ) ) {
			wp_die( esc_html__( 'شما اجازه‌ی دسترسی به این عملیات را ندارید.', 'arvan-reseller' ) );
		}

		check_admin_referer( $nonceAction );
	}

	/**
	 * @param array<string, int|string> $extraArgs
	 */
	private function redirect( string $tab, array $extraArgs = [] ): never {
		$url = add_query_arg(
			array_merge( [ 'page' => AdminMenu::SLUG_SETTINGS, 'tab' => $tab ], $extraArgs ),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
