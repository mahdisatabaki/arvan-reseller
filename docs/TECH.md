# TECH — Technical Architecture

## 1. Objective

Implement Arvan Reseller as a standalone WordPress plugin whose core business logic is isolated from WordPress content structures and all third-party plugins/themes.

The architecture must optimize for:
- correctness of money and ownership,
- fast implementation within a 36-hour hackathon,
- testability,
- replaceable real/mock Arvan adapters,
- predictable demo behavior.

## 2. Runtime and Stack

- PHP: 8.1+
- WordPress: current local stable version used for demo
- Database: MySQL/MariaDB through `$wpdb`
- Frontend: server-rendered PHP templates + minimal vanilla JS where needed
- Styling: plugin-owned CSS, RTL, Sorkhab-inspired
- Background jobs: WP-Cron + manual admin triggers for demo/recovery
- Authentication: WordPress users/session
- External plugin dependency: none
- Runtime Composer dependency: avoid unless demonstrably necessary

## 3. Architectural Layers

```text
WordPress Runtime
├── Hooks / Activation / Cron
├── Auth / wp_users
├── REST or AJAX endpoints
├── Admin UI / Frontend templates
├── $wpdb persistence adapters
└── HTTP adapter for Arvan
           ↓
Application Services
├── Setup
├── Payments
├── Provisioning
├── Billing
├── Lifecycle
└── Settlement
           ↓
Domain
├── Money
├── MarkupRate
├── Wallet/Ledger rules
├── UsagePeriod
├── Service state machine
└── Threshold policy
           ↓
Ports
├── Repositories
├── CdnClient
├── SecretStore
├── Mailer
├── Clock
└── AuditLogger
```

### Rule

Domain/Application decisions must not depend on `WP_Post`, `postmeta`, Theme APIs, WooCommerce, or another plugin.

Use WordPress in adapters where it is the correct runtime tool.

## 4. Recommended Repository Structure

```text
arvan-reseller/
├── arvan-reseller.php
├── uninstall.php
├── CLAUDE.md
├── src/
│   ├── Domain/
│   ├── Pricing/
│   ├── Ledger/
│   ├── Metering/
│   ├── Lifecycle/
│   ├── Arvan/
│   └── Ports/
├── wp/
│   ├── Plugin.php
│   ├── Installation/
│   ├── Persistence/
│   ├── Arvan/
│   │   ├── ArvanCdnClient.php
│   │   └── MockCdnClient.php
│   ├── Security/
│   ├── Cron/
│   ├── Admin/
│   ├── Frontend/
│   └── Rest/
├── templates/
├── assets/
│   ├── css/
│   └── js/
├── languages/
├── data/
│   └── access-token-hashes.php
├── tests/
│   ├── Unit/
│   └── Integration/
└── docs/
```

Existing project structure wins if already implemented and equivalent. Do not refactor merely to match this tree.

## 5. Main Application Services

### SetupService
- verify reseller demo Access Token,
- save business profile,
- create/manage encrypted Arvan credentials,
- set Markup and lifecycle policy.

### PaymentService
- create payment attempt,
- transition payment status,
- create exactly one Wallet credit for a successful payment.

### ProvisioningService
- create local Order/Service first,
- select API credential,
- request CDN resource,
- map remote resource identifier,
- persist success/failure state.

### MeteringService
- determine unprocessed usage interval,
- fetch **Outbound Traffic only**,
- normalize usage,
- calculate base cost,
- invoke BillingService.

### BillingService
- apply Markup,
- create Usage record,
- atomically debit Ledger + Wallet,
- evaluate threshold,
- trigger immediate Suspend when balance `<= 0`.

### LifecycleService
- hold/suspend,
- unhold/resume,
- terminate,
- record failure/retry/audit.

### SettlementService
- aggregate base cost, Markup and customer charge,
- create reconciliation/settlement period,
- use Mock settlement when no real endpoint exists.

## 6. Provider Abstraction

Business logic talks to:

```text
CdnClient
```

not directly to `wp_remote_request()`.

Required operations:
- `ping`
- `createResource`
- `getResource`
- `getOutboundTrafficUsage`
- `holdResource`
- `unholdResource`
- `deleteResource`

Implementations:
- `ArvanCdnClient`
- `MockCdnClient`

The mock and real clients return the same domain DTOs.

## 7. Database Strategy

Use dedicated Custom Tables through `$wpdb`.

Do not persist:
- Wallet,
- Ledger,
- Payment,
- Order,
- Service,
- Usage,
- API credentials,
- Settlement,
- Notifications,
- Audit

in `posts/postmeta`.

Table details are authoritative in `DATA-MODEL.md`.

## 8. Transactions and Concurrency

Financial operation:

```text
BEGIN
  verify idempotency key
  lock/read wallet
  insert ledger entry
  update wallet cached balance
  insert/update usage link
COMMIT
```

Rollback on failure.

A process/DB lock prevents concurrent Metering runs from charging the same period.

Unique DB constraints are the final defense against duplicate billing/payment credit.

## 9. Cron Strategy

Core jobs:
- Metering: hourly
- Settlement: daily
- Resource sync/retry: every 6 hours or minimal equivalent

WP-Cron is traffic-triggered, so Metering works from `metered_through`, not “one execution = one hour”.

Manual `Run Billing Cycle Now` invokes the same application code and exists for demo/operations.

## 10. Routing and UI

The plugin owns its templates and CSS.

Recommended public routes:
- `/arvan/cdn`
- `/arvan/account`
- service detail route
- login/register/recharge view as plugin-owned UI

Admin is consolidated:
- Dashboard
- Customers
- Services
- Finance
- Settings

Use WordPress auth/session; do not build authentication from scratch.

## 11. Error Model

Normalize errors into categories:
- validation
- authorization
- provider authentication
- provider rate-limit
- provider temporary failure
- provider permanent failure
- financial conflict/idempotency
- resource state conflict

Do not show raw provider response or secrets to customers.

Persist actionable technical details only after redaction.

## 12. API Uncertainty Rule

The exact Arvan endpoint/schema/unit for CDN Outbound Traffic is **not to be guessed**.

The first API Spike must establish:
- endpoint,
- auth header,
- resource identifier,
- traffic field,
- unit,
- cumulative vs bucketed semantics,
- date/time boundaries.

Until verified, production integration remains behind `CdnClient`; the mock can continue development.

## 13. Performance Targets

Hackathon targets:
- no obvious N+1 on admin lists,
- customer queries indexed by owner/status,
- Metering paginates/batches services if needed,
- UI has no blocking remote API calls except explicit actions such as Test Connection/Provisioning.

Correctness has priority over micro-optimization.

## 14. Testing Strategy

Unit-test without remote API:
- Money/Markup
- Ledger invariants
- negative balance
- state transitions
- threshold behavior
- idempotency

Integration-test:
- repositories,
- mock provider,
- payment → wallet,
- order → resource mapping,
- billing → suspend,
- recharge → resume.

Security and end-to-end scenarios are in `TEST-PLAN.md`.
