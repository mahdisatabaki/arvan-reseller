<?php
/**
 * Cron wiring — the five background jobs named in the requirements spec.
 *
 * WP-Cron only fires on traffic, which is fine for a demo but not for billing.
 * Every job here is therefore written to be catch-up safe: metering bills by
 * elapsed time since `metered_through` rather than "one hour per run", so a site
 * that sat idle overnight still produces a correct ledger on the next request.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Cron;

defined( 'ABSPATH' ) || exit;

final class Scheduler {

	/** E4, E5, E6 — read consumption, price it, debit the wallet. */
	public const HOOK_METER = 'arvan_meter_usage';

	/** F1–F4 — threshold e-mails, suspend at zero, terminate after grace. */
	public const HOOK_LIMITS = 'arvan_enforce_limits';

	/** E8 — roll usage up into a settlement period. */
	public const HOOK_SETTLEMENT = 'arvan_settlement';

	/** D3 — reconcile local service records against ArvanCloud reality. */
	public const HOOK_SYNC = 'arvan_sync_resources';

	/** A4, G3 — key validity, cron liveness, error surfacing. */
	public const HOOK_HEALTH = 'arvan_health_check';

	public const INTERVAL_FIFTEEN_MINUTES = 'arvan_fifteen_minutes';
	public const INTERVAL_SIX_HOURS       = 'arvan_six_hours';

	/**
	 * hook => [recurrence, offset from now for the first run]
	 *
	 * @return array<string, array{0:string, 1:int}>
	 */
	private static function jobs(): array {
		return [
			self::HOOK_METER      => [ 'hourly', HOUR_IN_SECONDS ],
			self::HOOK_LIMITS     => [ self::INTERVAL_FIFTEEN_MINUTES, 5 * MINUTE_IN_SECONDS ],
			self::HOOK_SETTLEMENT => [ 'daily', DAY_IN_SECONDS ],
			self::HOOK_SYNC       => [ self::INTERVAL_SIX_HOURS, 10 * MINUTE_IN_SECONDS ],
			self::HOOK_HEALTH     => [ 'hourly', MINUTE_IN_SECONDS ],
		];
	}

	/**
	 * Register the two intervals WordPress does not ship with.
	 *
	 * @param array<string, array{interval:int, display:string}> $schedules
	 * @return array<string, array{interval:int, display:string}>
	 */
	public static function addIntervals( array $schedules ): array {
		$schedules[ self::INTERVAL_FIFTEEN_MINUTES ] = [
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every fifteen minutes (Arvan Reseller)', 'arvan-reseller' ),
		];

		$schedules[ self::INTERVAL_SIX_HOURS ] = [
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => __( 'Every six hours (Arvan Reseller)', 'arvan-reseller' ),
		];

		return $schedules;
	}

	public static function schedule(): void {
		// Activation can run before `plugins_loaded` fires for this plugin
		// (the plugin file is only included when the activation hook runs,
		// after WordPress's own `plugins_loaded` pass already completed), so
		// Plugin::boot()'s cron_schedules filter may not be attached yet.
		// Register it here too — add_filter() is idempotent for the same
		// callback — so the custom recurrences below always resolve.
		add_filter( 'cron_schedules', [ self::class, 'addIntervals' ] );

		foreach ( self::jobs() as $hook => [ $recurrence, $offset ] ) {
			if ( ! wp_next_scheduled( $hook ) ) {
				wp_schedule_event( time() + $offset, $recurrence, $hook );
			}
		}
	}

	public static function unschedule(): void {
		foreach ( array_keys( self::jobs() ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Any job missing from the schedule, for the System Status screen.
	 * Silently dead cron means silently unbilled usage, so this is surfaced
	 * rather than logged.
	 *
	 * @return string[]
	 */
	public static function missingJobs(): array {
		$missing = [];

		foreach ( array_keys( self::jobs() ) as $hook ) {
			if ( ! wp_next_scheduled( $hook ) ) {
				$missing[] = $hook;
			}
		}

		return $missing;
	}

	public static function isHealthy(): bool {
		return [] === self::missingJobs();
	}

	/**
	 * Next run time per hook, for the System Status screen.
	 *
	 * @return array<string, int|null>
	 */
	public static function nextRuns(): array {
		$runs = [];

		foreach ( array_keys( self::jobs() ) as $hook ) {
			$next          = wp_next_scheduled( $hook );
			$runs[ $hook ] = false === $next ? null : $next;
		}

		return $runs;
	}
}
