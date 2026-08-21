# CLAUDE.md — Arvan Reseller

## Mission

Build a standalone WordPress plugin that lets a reseller sell **ArvanCloud CDN** through their own WordPress site.

WordPress is the runtime/container, authentication layer, database access layer, and UI host. The plugin must have **zero runtime dependency on WooCommerce, ACF, Elementor, any other plugin, or any theme**.

## Frozen MVP

- Product: **CDN only**
- Product pages: **one CDN sales page only**
- Billing metric: **CDN Outbound Traffic only**
- Revenue model: **Markup on Arvan base cost**
- Markup range: `0%..20%`
- Example: Base `100` + Markup `20%` = Customer Charge `120`
- Money storage: integer Rial; never float
- Payment: Mock Payment / manual receipt only
- VAT/Tax engine: out of scope
- Cloud Server: out of scope
- Object Storage: out of scope
- WooCommerce: forbidden
- Financial/content data must not use `posts/postmeta`
- Wallet may become negative; do not clamp to zero
- `balance <= 0` after billing must trigger Suspend in the same billing flow
- Successful recharge may Resume a wallet-suspended service
- Ledger is append-only and billing is idempotent
- Customer financial/resource data must be strictly isolated

## WordPress Boundary

Allowed:
- WordPress hooks
- `$wpdb`
- `wp_users`
- WordPress Login/Register
- REST/AJAX/Admin APIs
- WP-Cron
- WordPress nonce/capability APIs

Business logic must not depend on:
- `WP_Post` / `posts/postmeta`
- theme state
- WooCommerce or any third-party plugin
- page-builder APIs

Keep Pricing, Wallet, Ledger, Metering, Lifecycle, and Settlement logic in domain/application services. WordPress-specific code belongs in adapters/infrastructure.

## Source of Truth

Do not read every document for every task.

Read only the files relevant to the current task:

- Scope and product behavior: `docs/PRD.md`
- Current implementation order/status: `docs/BACKLOG.md`
- Architecture: `docs/TECH.md`
- Database: `docs/DATA-MODEL.md`
- Financial invariants: `docs/BILLING.md`
- CDN/API contract: `docs/API.md`
- Service states: `docs/SERVICE-LIFECYCLE.md`
- Security: `docs/SECURITY.md`
- Background jobs: `docs/CRON.md`
- UX flows: `docs/USER-FLOWS.md`
- UI rules: `docs/DESIGN.md`
- Screen details: `docs/SCREEN-SPECS.md`
- Test cases: `docs/TEST-PLAN.md`
- Final requirements: `docs/ACCEPTANCE.md`
- Phase order: `docs/IMPLEMENTATION-PLAN.md`
- Demo scenario: `docs/DEMO.md`
- Frozen architectural decisions: `docs/DECISIONS.md`
- Task-completion log: `docs/PROGRESS.md`

`PRD.md`, `BACKLOG.md`, `BILLING.md`, `SECURITY.md`, and `DECISIONS.md` are authoritative when implementation choices conflict.

## Work Protocol

For each task:

1. Read the relevant `BACKLOG.md` block.
2. Read only the domain documents needed for that task.
3. Inspect the existing code before creating new abstractions.
4. Implement the smallest complete change.
5. Run relevant tests/checks.
6. Do not expand scope.
7. Do not invent Arvan API endpoints, response fields, units, or prices. If not verified, stop that integration point behind an interface/mock and record the unresolved item.
8. Preserve backward compatibility with completed tasks unless the Backlog/Decision Log explicitly requires a migration.
9. When the task is done: check it off in `docs/BACKLOG.md` (with its acceptance criteria, if any changed during implementation), append one entry to `docs/PROGRESS.md`, then re-scan the Source of Truth list above for any other document the change makes stale (a port signature that changed, a decision that superseded an older note, a status field that's now resolved) and update those too. Do not leave two documents disagreeing about the same fact.

## Critical Engineering Invariants

- No plaintext API secrets in DB, logs, HTML, JS, or error responses.
- Demo Access Tokens are verified against bundled hashes using `password_verify()`.
- All customer-owned queries/actions enforce ownership server-side.
- Every state-changing request uses authorization + CSRF protection.
- All dynamic SQL is parameterized.
- Remote resource lifecycle actions use the API credential associated with that service.
- Financial writes use idempotency keys.
- Ledger entry + wallet balance update are atomic.
- A failed/retried Cron must not double-charge.
- A failed provisioning call must not create an unowned remote resource without a recoverable local record.

## Definition of “Done”

A task is done only when:
- implementation matches its acceptance criteria,
- relevant tests pass,
- security/ownership rules are enforced,
- failure states are handled,
- no unrelated scope was added.

Do not mark work complete based only on UI appearance.
