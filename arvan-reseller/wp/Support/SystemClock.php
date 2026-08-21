<?php
/**
 * The real wall clock, in UTC (DATA-MODEL.md §1).
 *
 * The one and only place `new DateTimeImmutable('now')` is allowed to
 * appear in this plugin — every domain/application class gets "now"
 * through the `Clock` port instead (see that interface's docblock).
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Support;

use ArvanReseller\Ports\Clock;
use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

final class SystemClock implements Clock {

	public function now(): DateTimeImmutable {
		return new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
	}
}
