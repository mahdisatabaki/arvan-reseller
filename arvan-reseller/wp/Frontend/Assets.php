<?php
/**
 * Enqueues the plugin's own frontend stylesheet (BACKLOG T-7.1) — only on
 * pages `RouteRegistrar` actually matched, never site-wide. Loading it on
 * every theme page would risk exactly what DESIGN.md §3E warns against
 * ("Theme styles must not break critical layouts") working in reverse:
 * this plugin's own CSS leaking into pages it has no business touching.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Frontend;

defined( 'ABSPATH' ) || exit;

final class Assets {

	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'maybeEnqueue' ] );
	}

	public function maybeEnqueue(): void {
		if ( '' === (string) get_query_var( RouteRegistrar::QUERY_VAR_ROUTE ) ) {
			return;
		}

		wp_enqueue_style(
			'arvan-reseller-foundation',
			ARVAN_RESELLER_URL . 'assets/css/foundation.css',
			[],
			ARVAN_RESELLER_VERSION
		);
	}
}
