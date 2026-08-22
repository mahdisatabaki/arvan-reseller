<?php
/**
 * Rewrite rules and query vars for the plugin's public-facing routes.
 *
 * Lets `/arvan/cdn`, `/arvan/account`, etc. resolve without a WordPress Page
 * being created for each one, and without any theme template-hierarchy
 * involvement — WordPress rewrite/query-var hooks only, per CLAUDE.md's
 * WordPress Boundary rules. Pairs with TemplateRouter, which serves the
 * plugin-owned template once `arvan_route` is set.
 *
 * A rewrite flush is not triggered here — that already happens on plugin
 * activation/deactivation (see Installer::activate()/deactivate()), and
 * flushing on every request would be a real performance bug.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Wp\Frontend;

defined( 'ABSPATH' ) || exit;

final class RouteRegistrar {

	public const QUERY_VAR_ROUTE      = 'arvan_route';
	public const QUERY_VAR_SERVICE_ID = 'arvan_service_id';

	public function register(): void {
		add_action( 'init', [ $this, 'addRewriteRules' ] );
		add_filter( 'query_vars', [ $this, 'addQueryVars' ] );
	}

	public function addRewriteRules(): void {
		add_rewrite_rule( '^arvan/cdn/?$', 'index.php?' . self::QUERY_VAR_ROUTE . '=cdn', 'top' );

		add_rewrite_rule( '^arvan/account/?$', 'index.php?' . self::QUERY_VAR_ROUTE . '=account', 'top' );

		add_rewrite_rule(
			'^arvan/account/services/([0-9]+)/?$',
			'index.php?' . self::QUERY_VAR_ROUTE . '=service-detail&' . self::QUERY_VAR_SERVICE_ID . '=$matches[1]',
			'top'
		);

		add_rewrite_rule( '^arvan/auth/?$', 'index.php?' . self::QUERY_VAR_ROUTE . '=auth', 'top' );

		add_rewrite_rule( '^arvan/recharge/?$', 'index.php?' . self::QUERY_VAR_ROUTE . '=recharge', 'top' );
	}

	/**
	 * @param string[] $vars
	 * @return string[]
	 */
	public function addQueryVars( array $vars ): array {
		$vars[] = self::QUERY_VAR_ROUTE;
		$vars[] = self::QUERY_VAR_SERVICE_ID;

		return $vars;
	}
}
