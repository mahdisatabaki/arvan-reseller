<?php
/**
 * Reseller configuration — business profile, markup, lifecycle policy, sales
 * layout, and the Setup Wizard's own progress.
 *
 * Stored as four grouped options, not one option per field — matching what
 * uninstall.php has anticipated since T-0.x (`arvan_reseller_branding`,
 * `arvan_reseller_pricing`, `arvan_reseller_limits`, `arvan_reseller_settings`).
 * A scattered-option design (one row per field) would multiply autoloaded
 * options for no benefit here: every field in a group is always read and
 * written together.
 *
 * This is a thin option wrapper, not a new Port: unlike the table-backed
 * repositories (Wallet, Service, ApiKey, ...), there is exactly one
 * implementation of "read/write a WordPress option," and nothing here would
 * ever be swapped for a fake — `get_option()`/`update_option()` already are
 * the seam a test would fake, the same way earlier tasks stubbed them
 * directly rather than wrapping them in another interface.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Admin;

use ArvanReseller\Pricing\MarkupRate;

defined( 'ABSPATH' ) || exit;

final class ResellerSettings {

	private const OPTION_BRANDING = 'arvan_reseller_branding';
	private const OPTION_PRICING  = 'arvan_reseller_pricing';
	private const OPTION_LIMITS   = 'arvan_reseller_limits';
	private const OPTION_SETTINGS = 'arvan_reseller_settings';

	public const LAYOUT_CARDS   = 'cards';
	public const LAYOUT_COMPACT = 'compact';

	/**
	 * @return array{name: string, logo_url: string, website: string, email: string, phone: string, about: string}
	 */
	public function getBusinessProfile(): array {
		$defaults = [
			'name'     => '',
			'logo_url' => '',
			'website'  => '',
			'email'    => '',
			'phone'    => '',
			'about'    => '',
		];

		return array_merge( $defaults, (array) get_option( self::OPTION_BRANDING, [] ) );
	}

	/**
	 * SECURITY.md §9: business name/about are untrusted stored input —
	 * sanitized here, at write time; escaped again wherever they are later
	 * rendered.
	 */
	public function setBusinessProfile(
		string $name,
		string $logoUrl,
		string $website,
		string $email,
		string $phone,
		string $about
	): void {
		update_option(
			self::OPTION_BRANDING,
			[
				'name'     => sanitize_text_field( $name ),
				'logo_url' => '' === $logoUrl ? '' : esc_url_raw( $logoUrl ),
				'website'  => '' === $website ? '' : esc_url_raw( $website ),
				'email'    => sanitize_email( $email ),
				'phone'    => sanitize_text_field( $phone ),
				'about'    => sanitize_textarea_field( $about ),
			]
		);
	}

	/**
	 * PRD/CLAUDE.md: markup is capped at 20% — enforced by `MarkupRate`
	 * itself (T-0.7), not re-validated here.
	 */
	public function getMarkupRate(): MarkupRate {
		$pricing = (array) get_option( self::OPTION_PRICING, [] );

		return MarkupRate::fromBasisPoints( (int) ( $pricing['markup_bps'] ?? 0 ) );
	}

	public function setMarkupRate( MarkupRate $rate ): void {
		update_option( self::OPTION_PRICING, [ 'markup_bps' => $rate->toBasisPoints() ] );
	}

	/**
	 * Reseller-wide defaults (SCREEN-SPECS.md §7 "Lifecycle" tab). These are
	 * the values a new wallet is created with; they are not the same as a
	 * single customer's own overridden threshold on `arvan_wallets`.
	 *
	 * @return array{notify_threshold_rial: int, resume_threshold_rial: int, terminate_grace_days: int}
	 */
	public function getLifecyclePolicy(): array {
		$defaults = [
			'notify_threshold_rial' => 0,
			'resume_threshold_rial' => 0,
			'terminate_grace_days'  => 7,
		];

		return array_merge( $defaults, (array) get_option( self::OPTION_LIMITS, [] ) );
	}

	public function setLifecyclePolicy(
		int $notifyThresholdRial,
		int $resumeThresholdRial,
		int $terminateGraceDays
	): void {
		update_option(
			self::OPTION_LIMITS,
			[
				'notify_threshold_rial' => max( 0, $notifyThresholdRial ),
				'resume_threshold_rial' => max( 0, $resumeThresholdRial ),
				'terminate_grace_days'  => max( 0, $terminateGraceDays ),
			]
		);
	}

	public function getLayout(): string {
		$settings = (array) get_option( self::OPTION_SETTINGS, [] );
		$layout   = (string) ( $settings['layout'] ?? self::LAYOUT_CARDS );

		return in_array( $layout, [ self::LAYOUT_CARDS, self::LAYOUT_COMPACT ], true ) ? $layout : self::LAYOUT_CARDS;
	}

	public function setLayout( string $layout ): void {
		$safe = in_array( $layout, [ self::LAYOUT_CARDS, self::LAYOUT_COMPACT ], true ) ? $layout : self::LAYOUT_CARDS;

		$this->mergeSettings( [ 'layout' => $safe ] );
	}

	/**
	 * DESIGN.md §8: "persist completed steps." The furthest step the
	 * reseller has reached — used both to resume after a reload and to stop
	 * the wizard being navigated ahead of where it has actually progressed.
	 */
	public function getWizardStep(): int {
		$settings = (array) get_option( self::OPTION_SETTINGS, [] );

		return max( 1, (int) ( $settings['wizard_step'] ?? 1 ) );
	}

	public function setWizardStep( int $step ): void {
		$this->mergeSettings( [ 'wizard_step' => max( 1, $step ) ] );
	}

	public function isWizardComplete(): bool {
		$settings = (array) get_option( self::OPTION_SETTINGS, [] );

		return (bool) ( $settings['wizard_complete'] ?? false );
	}

	public function setWizardComplete( bool $complete ): void {
		$this->mergeSettings( [ 'wizard_complete' => $complete ] );
	}

	/**
	 * @param array<string, mixed> $partial
	 */
	private function mergeSettings( array $partial ): void {
		$current = (array) get_option( self::OPTION_SETTINGS, [] );

		update_option( self::OPTION_SETTINGS, array_merge( $current, $partial ) );
	}
}
