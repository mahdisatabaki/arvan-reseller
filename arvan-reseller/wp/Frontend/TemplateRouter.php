<?php
/**
 * Serves a plugin-owned template for `arvan_route` requests.
 *
 * Hooks `template_include` so a matched route (see RouteRegistrar) is
 * rendered from `wp/Frontend/templates/{route}.php` instead of falling
 * through to the active theme's template hierarchy. When the route's
 * template file does not exist yet (T-7.3/7.4/7.5/7.6 land later), this
 * intentionally falls back to WordPress's normal `$template` resolution.
 *
 * `fixMainQuery()` exists because a custom query var alone does not stop
 * `WP_Query` from defaulting `is_home` to true when nothing else identifies
 * the request (no `p`/`page_id`/`name`/post type in the query vars) —
 * confirmed live, not assumed: visiting `/arvan/cdn` without this rendered
 * the theme's actual blog listing (`body class="home blog"`), not a blank
 * or 404 page, because WordPress silently treated the unrecognized request
 * as the front page. `parse_query` is the documented hook point for a
 * "virtual page" plugin to correct this before template selection runs.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Frontend;

use WP_Query;

defined( 'ABSPATH' ) || exit;

final class TemplateRouter {

	public function register(): void {
		add_action( 'parse_query', [ $this, 'fixMainQuery' ] );
		add_filter( 'template_include', [ $this, 'maybeServeArvanTemplate' ] );
	}

	public function fixMainQuery( WP_Query $query ): void {
		if ( ! $query->is_main_query() || '' === (string) get_query_var( RouteRegistrar::QUERY_VAR_ROUTE ) ) {
			return;
		}

		$query->is_home = false;
		$query->is_404  = false;
	}

	public function maybeServeArvanTemplate( string $template ): string {
		$route = get_query_var( RouteRegistrar::QUERY_VAR_ROUTE );

		if ( '' === $route ) {
			return $template;
		}

		$file = ARVAN_RESELLER_DIR . 'wp/Frontend/templates/' . $route . '.php';

		return file_exists( $file ) ? $file : $template;
	}
}
