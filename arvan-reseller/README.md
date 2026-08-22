# Arvan Reseller

A standalone WordPress plugin that lets a reseller sell **ArvanCloud CDN** through their own WordPress site — customers register, top up a prepaid Rial wallet, order a CDN service, and get metered and billed hourly against real (or mocked) ArvanCloud outbound-traffic usage. WordPress supplies the runtime, authentication, database access, and UI host only; the plugin has **zero runtime dependency on WooCommerce, ACF, Elementor, any other plugin, or any theme**. This is a hackathon submission built to a frozen MVP scope: CDN only, one sales page, integer-Rial money, 0–20% markup, Mock Payment only.

## What this is

Arvan Reseller turns a plain WordPress install into a small CDN reselling storefront. A reseller runs the Setup Wizard once (business profile, encrypted ArvanCloud API key, markup rate, traffic unit price, lifecycle thresholds), and from then on customers can register, recharge a wallet with a mock payment, order the one CDN product, and watch their service get metered and billed automatically by WP-Cron. All financial and resource state lives in dedicated custom database tables — never in `posts`/`postmeta` — and money is always stored as an integer Rial, never a float.

## Install

Requirements: PHP 8.1+, a current stable WordPress release, and a MySQL/MariaDB database reachable through `$wpdb` (no separate database or service to stand up — it's a normal WordPress plugin). No specific minimum WordPress version was verified against during development; `docs/TECH.md` only specifies "current local stable" was used.

1. Copy this `arvan-reseller/` directory into `wp-content/plugins/` and activate it from the WordPress admin Plugins screen.
2. Activation runs `Installer::activate()` (`wp/Installation/Installer.php`), which calls `Installer::migrate()` to create/upgrade the plugin's schema (versioned via `arvan_reseller_db_version`, safe to re-run), grants the plugin's custom capabilities and the `arvan_customer` role, schedules the WP-Cron jobs, flushes rewrite rules, and sets a one-time redirect flag.
3. On the next admin page load you land in the **Setup Wizard**. Step 1 asks for an Access Token issued to the reseller by ArvanCloud. For this hackathon submission, the gate is backed by a bundled list of test-token hashes (`data/access-token-hashes.php`, checked with `password_verify()` — no raw token is stored anywhere). The documented valid demo token is `arvan_test_123` (see `docs/SECURITY.md` §3 and `docs/DEMO.md` §2); `wrong_token` is the documented invalid case for testing rejection.
4. Continue through the remaining wizard steps: add and test an ArvanCloud API key (encrypted at rest — see Security below), set the markup rate (0–20%) and traffic unit price, set lifecycle thresholds, and finish. You land on the plugin's admin Dashboard.
5. Deactivating the plugin does not drop financial tables; see `uninstall.php` for what a full uninstall removes.

No Composer install step is required — the plugin uses a small PSR-4 autoloader (`wp/Support/Autoloader.php`), not a vendor directory.

## Architecture

Hexagonal / ports-and-adapters. `src/` is the domain and application core and has **zero WordPress dependency** (no `wp_*` calls, no `$wpdb`, no `WP_Post`) — it only depends on interfaces defined in `src/Ports/`. `wp/` holds every WordPress-specific adapter: `$wpdb` repositories, admin screens, cron wiring, HTTP client, secret storage, capabilities, and the frontend route/template layer. Business logic (Pricing, Wallet, Ledger, Metering, Lifecycle, Provisioning) lives only in `src/`; WordPress code calls into it, never the reverse.

Data lives in eleven dedicated `arvan_*` tables (never `posts`/`postmeta`): `arvan_customers`, `arvan_wallets`, `arvan_ledger` (append-only), `arvan_payments`, `arvan_orders`, `arvan_services`, `arvan_usage_log`, `arvan_api_keys`, `arvan_settlements`, `arvan_notifications`, `arvan_audit_log`. Money is always a signed integer Rial column (`BIGINT`), never a float.

The ArvanCloud CDN integration sits behind a single `CdnClient` port (`src/Arvan/CdnClient.php`) with two interchangeable implementations: `ArvanCdnClient` (real HTTP calls, itself built on a `src/Ports/HttpClient.php` port rather than `wp_remote_request()` directly) and `MockCdnClient` (in-memory, deterministic, used for development and demo scenarios where a real credential isn't available).

Full architecture, the complete repository tree, and the application service breakdown are documented in `docs/TECH.md`; the table-by-table schema is in `docs/DATA-MODEL.md`.

## Security

- **Arvan API keys are encrypted at rest** with AES-256-GCM (`wp/Security/WordPressSecretStore.php`), keyed from `ARVAN_ENCRYPTION_KEY` if defined, otherwise derived from WordPress auth salts. Only the ciphertext, a fingerprint hash, and the last 4 characters are ever persisted — the plaintext key is never logged, serialized into audit metadata, or shown again in the UI.
- **The reseller Access Token gate** verifies input against bundled `password_hash()` hashes using `password_verify()`, rate-limited to 5 attempts per 15 minutes via WordPress transients. No raw token is stored in the database or the codebase.
- **Every state-changing admin-post action** (17 of them across the plugin) requires a WordPress nonce plus a custom capability check (`arvan_manage`, etc.) — nonce alone is never treated as authorization.
- **All customer-owned repository queries are ownership-scoped** (`findOwnedByCustomer()` / `*ForCustomer()` patterns) to prevent IDOR — a customer can never read or act on another customer's wallet, ledger, payment, order, or service by guessing an ID.
- **Billing and payment writes are idempotent**: the ledger enforces a unique `idempotency_key` per financial entry, ledger + wallet update inside one atomic operation, and metering billing keys off `(service_id, period_start)` specifically to survive concurrent/duplicate cron runs without double-charging.
- All dynamic SQL goes through `$wpdb->prepare()`.

A dedicated security-audit pass (Block 10 of the project) ran two independent review passes across nonce/capability coverage, IDOR, input/output handling and SQL, and secret handling — both passes reported **zero findings**. That is a real result from those two passes at that point in time, not a guarantee that no issue exists anywhere in the codebase; it should be read as "nothing in the audited surface was wrong when it was checked," not as a certification.

## Demo mode / what's real vs simulated

This section is deliberately precise — read it before demoing or judging the project.

- **Mock Payment is the only payment method.** This is by the challenge's explicit allowance (no real payment gateway integration was in scope), not a shortcut taken under time pressure. There is no real gateway integration anywhere in the codebase.
- **CDN provisioning against the real ArvanCloud API is real** when a real, valid API key is configured through the Setup Wizard or Admin Settings — `ArvanCdnClient` makes actual HTTP calls to ArvanCloud. When no real key is available, `MockCdnClient` provides a deterministic in-memory stand-in used for development and for demo scenarios that need exact, repeatable numbers.
- **Suspend/Resume change only local plugin state.** No confirmed ArvanCloud hold/unhold API was ever found in the available documentation or source (an open item since the project's first API spike). When this plugin marks a service "Suspended," it updates `arvan_services.status` and stops the customer from seeing an active service in the UI — **the underlying CDN resource on ArvanCloud keeps serving traffic.** This is the single most important thing to disclose about this project: Suspend is not a real traffic cutoff. Terminate, by contrast, is real — it calls ArvanCloud's actual delete-resource endpoint.
- **Settlement/reconciliation was not built in this submission's time window.** The Finance → Settlements tab in the admin UI is wired up and renders correctly, but it reads from a repository (`SettlementRepository`) that nothing yet writes to — it will correctly show an empty state, not broken UI, because the aggregation service itself (Block 9 of the plan) was deliberately skipped for time.
- **The Access Token gate uses bundled test-hash data appropriate for hackathon evaluation** (`data/access-token-hashes.php`), not a production reseller-identity mechanism. Treat it as a demo/evaluation gate, not an auth system to build further product on without revisiting.

## Limitations

- CDN is the only product — Cloud Server and Object Storage are explicitly out of scope for this MVP.
- One CDN sales page only; no multi-product catalog.
- No VAT/tax engine — pricing is base cost + markup only.
- No real payment gateway — Mock Payment / manual receipt only.
- Settlement/reconciliation reporting is not implemented (empty but functional UI, no aggregation service behind it).
- No formal mobile/responsive QA pass was completed — only a visual smoke test, not systematic breakpoint testing (Block 10's responsive task was intentionally skipped for time).
- Suspend is local-status-only: a "suspended" service's real ArvanCloud CDN resource is unaffected and continues serving traffic (see Demo mode above).
- A handful of ArvanCloud response-field details (exact JSON field names/units for resource and traffic-usage responses) are inferred with medium, not verified, confidence — see `docs/DECISIONS.md` and the open-items table in `docs/PROGRESS.md` for specifics; they're isolated behind single mapping methods so a correction is cheap.

## Project structure

```text
arvan-reseller/
├── arvan-reseller.php      # plugin bootstrap, autoloader registration
├── uninstall.php
├── src/                    # domain + application core — zero WordPress dependency
│   ├── Pricing/            # Money, MarkupRate, ChargeBreakdown
│   ├── Wallet/             # PaymentService, ManualAdjustmentService
│   ├── Provisioning/       # ProvisioningService, ResourceSyncService, DeliveryData
│   ├── Metering/           # MeteringService, UsagePeriod, UsagePricingAdapter
│   ├── Billing/            # BillingService
│   ├── Lifecycle/          # ServiceStatus, SuspensionEngine, TerminationEngine, ThresholdPolicy
│   ├── Arvan/               # CdnClient port, CdnResource, ArvanCdnClient, MockCdnClient
│   └── Ports/               # repository/service interfaces implemented under wp/
├── wp/                     # WordPress-specific adapters
│   ├── Plugin.php
│   ├── Installation/       # Installer, schema migrations
│   ├── Persistence/        # $wpdb repository implementations
│   ├── Http/                # WordPressHttpClient (implements src/Ports/HttpClient)
│   ├── Security/            # WordPressSecretStore, AccessTokenGate
│   ├── Cron/                 # WP-Cron handlers (metering, etc.)
│   ├── Admin/                # Admin menu, controllers + templates (Dashboard, Customers, Services, Finance, Settings)
│   ├── Arvan/                 # CdnClientResolver
│   ├── Customer/              # CustomerRegistration
│   └── Frontend/              # RouteRegistrar, TemplateRouter, Assets, customer-facing controllers/templates
├── assets/                 # plugin-owned CSS/JS (RTL, Persian UI)
├── data/                   # access-token-hashes.php (bundled demo hashes only)
└── docs/                   # full internal spec set (PRD, TECH, DATA-MODEL, BILLING, SECURITY, ...)
```

See `docs/TECH.md` for the fully annotated tree and the application-service breakdown, and `docs/BACKLOG.md` / `docs/PROGRESS.md` for exactly what was implemented and in what order.
