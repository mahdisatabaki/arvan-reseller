<?php
/**
 * Source of "now" for the domain and application layers.
 *
 * TECH.md §9 is explicit that WP-Cron is traffic-triggered, so Metering must
 * work from `metered_through`, not "one execution = one hour" — that only
 * holds if the code asking "what time is it" can be swapped for a fake one in
 * tests (TECH.md §14: unit-test threshold behavior and idempotency without a
 * live clock). No domain or application code may call `time()` or `new
 * DateTimeImmutable('now')` directly; everything goes through this port.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Ports;

use DateTimeImmutable;

interface Clock {

	/**
	 * The current instant, in UTC (DATA-MODEL.md §1: "UTC timestamps are
	 * recommended internally; local formatting happens in UI").
	 */
	public function now(): DateTimeImmutable;
}
