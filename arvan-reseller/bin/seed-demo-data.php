<?php
/**
 * One-click demo data seed (BACKLOG T-11.1).
 *
 * Run from the command line with WordPress's own PHP, from anywhere:
 *   php bin/seed-demo-data.php
 *
 * Creates two customers with different wallet balances, one active CDN
 * service each, and a short payment/usage history — so the admin Dashboard,
 * Customers, Services, and Finance screens have real, representative data to
 * show without needing to build it live during a recording. Every write goes
 * through the plugin's own repository classes (not hand-written SQL), so the
 * resulting ledger/wallet state is exactly what the real application would
 * produce — the only shortcut is that services are created directly at
 * `active` status with a fake resource id, skipping a real ArvanCloud API
 * call, since this is fixture data, not a provisioning test.
 *
 * Safe to re-run: customer lookup is by WordPress login, and every
 * repository write here already follows the same "return existing on
 * duplicate" idempotency the rest of the plugin relies on, so running this
 * twice does not create duplicate customers or double-charge.
 *
 * Requires the plugin to already be active and the Setup Wizard already
 * completed (business profile / markup / API key) — this script only seeds
 * customer-side data, not reseller configuration.
 *
 * @package ArvanReseller
 */

declare( strict_types = 1 );

define( 'WP_USE_THEMES', false );

// Search upward from this file for wp-load.php rather than assuming a fixed
// depth — a symlinked/junctioned plugin directory (common in local dev, e.g.
// a repo checkout linked into wp-content/plugins/) resolves __DIR__ to the
// real target path, not the virtual one, so a hardcoded "4 levels up" guess
// is not reliable across setups.
$wp_load = null;
$dir     = __DIR__;

for ( $i = 0; $i < 6; $i++ ) {
	$dir = dirname( $dir );
	if ( file_exists( $dir . '/wp-load.php' ) ) {
		$wp_load = $dir . '/wp-load.php';
		break;
	}
}

if ( null === $wp_load ) {
	fwrite( STDERR, "Could not find wp-load.php by searching upward from " . __DIR__ . " — edit \$wp_load at the top of this script to point at your WordPress install directly.\n" );
	exit( 1 );
}

require $wp_load;

use ArvanReseller\Domain\Money;
use ArvanReseller\Lifecycle\ServiceStatus;
use ArvanReseller\Pricing\ChargeBreakdown;
use ArvanReseller\Pricing\MarkupRate;
use ArvanReseller\Wp\Persistence\WpCustomerRepository;
use ArvanReseller\Wp\Persistence\WpLedgerRepository;
use ArvanReseller\Wp\Persistence\WpOrderRepository;
use ArvanReseller\Wp\Persistence\WpPaymentRepository;
use ArvanReseller\Wp\Persistence\WpServiceRepository;
use ArvanReseller\Wp\Persistence\WpUsageLogRepository;

global $wpdb;

$customers = new WpCustomerRepository( $wpdb );
$ledger    = new WpLedgerRepository( $wpdb );
$payments  = new WpPaymentRepository( $wpdb );
$services  = new WpServiceRepository( $wpdb );
$usage     = new WpUsageLogRepository( $wpdb );
$orders    = new WpOrderRepository( $wpdb );

/**
 * Finds or creates the WordPress user; registration itself already triggers
 * CustomerRegistration's `user_register` hook (customer row + zero wallet),
 * this just returns the WP user id either way.
 */
function seed_demo_user( string $login, string $email, string $displayName, string $password ): int {
	$existing = get_user_by( 'login', $login );
	if ( false !== $existing ) {
		return (int) $existing->ID;
	}

	$id = wp_insert_user(
		[
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => $password,
			'display_name' => $displayName,
			'role'         => 'arvan_customer',
		]
	);

	if ( is_wp_error( $id ) ) {
		fwrite( STDERR, "Failed creating {$login}: " . $id->get_error_message() . "\n" );
		exit( 1 );
	}

	return (int) $id;
}

/**
 * @param array<int, array{0:int,1:int,2:int,3:float}> $usagePeriods Each row
 *        is [baseToman, markupToman, totalToman, trafficGb] for one billed
 *        hour of usage history.
 */
function seed_demo_customer(
	string $login, string $email, string $displayName, string $password,
	int $topupToman, string $resourceIdSuffix, string $domain,
	array $usagePeriods,
	WpCustomerRepository $customers, WpLedgerRepository $ledger, WpPaymentRepository $payments,
	WpServiceRepository $services, WpUsageLogRepository $usage, WpOrderRepository $orders
): void {
	$wpUserId   = seed_demo_user( $login, $email, $displayName, $password );
	$customerId = $customers->create( $wpUserId, $displayName, $email );

	$paymentResult = $payments->createPending( $customerId, Money::fromToman( $topupToman ), 'mock', 'seed-payment-' . $customerId );
	if ( $paymentResult['created'] ) {
		$payments->markSucceeded( $paymentResult['id'] );
	}

	$ledger->append(
		$customerId,
		'wallet_credit',
		Money::fromToman( $topupToman ),
		'seed-topup-' . $customerId,
		'payment',
		$paymentResult['id'],
		'Wallet top-up (seed data)'
	);

	$orderId   = $orders->create( $customerId, 'cdn', $domain, 1500 );
	$serviceId = $services->createProvisioning( $customerId, $orderId, 0, $domain );
	$services->recordProvisioned( $serviceId, 'seed-resource-' . $resourceIdSuffix, new DateTimeImmutable( '-3 days' ) );
	$services->updateStatus( $serviceId, ServiceStatus::ACTIVE );
	$orders->markCompleted( $orderId );

	$periodStart = new DateTimeImmutable( '-2 days' );
	foreach ( $usagePeriods as $i => [ $baseToman, $markupToman, $totalToman, $trafficGb ] ) {
		$pStart = $periodStart->modify( "+{$i} hours" );
		$pEnd   = $pStart->modify( '+1 hour' );

		$charge = new ChargeBreakdown(
			Money::fromToman( $baseToman ),
			Money::fromToman( $markupToman ),
			Money::fromToman( $totalToman ),
			MarkupRate::fromPercent( 15.0 )
		);

		$usageResult = $usage->record(
			$serviceId,
			$customerId,
			$pStart,
			$pEnd,
			(int) round( $trafficGb * 1000000000 ),
			'byte',
			Money::fromToman( 1500 ),
			$charge
		);

		if ( $usageResult['created'] ) {
			$ledger->append(
				$customerId,
				'usage_debit',
				Money::fromToman( $totalToman )->negated(),
				'seed-usage-' . $usageResult['id'],
				'usage_log',
				$usageResult['id'],
				'CDN outbound traffic (seed data)'
			);
		}
	}

	$services->markMeteredThrough( $serviceId, $periodStart->modify( '+' . count( $usagePeriods ) . ' hours' ) );

	echo "seeded {$login} (customer #{$customerId}, service #{$serviceId})\n";
}

seed_demo_customer(
	'demo.customera', 'customer.a@example.com', 'مشتری اول (شرکت آلفا)', 'DemoPass123!',
	100000, '1', 'shop-alpha.example.com',
	[ [ 8000, 1200, 9200, 6.1 ], [ 5500, 825, 6325, 4.2 ] ],
	$customers, $ledger, $payments, $services, $usage, $orders
);

seed_demo_customer(
	'demo.customerb', 'customer.b@example.com', 'مشتری دوم (شرکت بتا)', 'DemoPass123!',
	15000, '2', 'blog-beta.example.com',
	[ [ 6000, 900, 6900, 4.6 ] ],
	$customers, $ledger, $payments, $services, $usage, $orders
);

echo "done\n";
