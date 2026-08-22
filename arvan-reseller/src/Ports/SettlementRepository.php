<?php
/**
 * Read access to reconciliation periods (`arvan_settlements`).
 *
 * ADR-015: Settlement can be simulated — this port only covers what T-8.4's
 * Admin Finance "Settlements" tab needs to read. Creating a settlement
 * (aggregating usage/ledger into a period, T-9.1's SettlementService) is a
 * separate, not-yet-built concern; this interface deliberately has no write
 * method yet so it does not guess at that shape ahead of time.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

namespace ArvanReseller\Ports;

interface SettlementRepository {

	/**
	 * Settlement periods, newest first — SCREEN-SPECS.md §6's "Settlements"
	 * tab. Empty until T-9.1 ships the code that creates rows here; an empty
	 * result is a legitimate "no settlements yet" state, not an error.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function allRecent( int $limit = 50 ): array;
}
