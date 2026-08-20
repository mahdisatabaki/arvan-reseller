# IMPLEMENTATION-PLAN — Build Sequence

## 1. Rule

`BACKLOG.md` tracks task status. This file defines sequencing and exit gates.

Do not start a later phase merely because UI work is easier. The critical loop is:

```text
Setup
→ Wallet
→ Provision
→ Meter
→ Bill
→ Suspend
→ Recharge
→ Resume
```

## Phase 0 — Foundation Correction

Read:
- `PRD.md`
- relevant `BACKLOG.md`
- `TECH.md`
- `BILLING.md`

Deliver:
- clean local WordPress
- plugin activation
- schema/migrations
- final Markup-only pricing abstraction
- Ports

Exit:
- 100 +20% =120 test passes
- no Commission/VAT path remains in P0.

## Phase 1 — CDN API Spike + Provider Port

Read:
- `API.md`
- `SECURITY.md`

Deliver:
- `CdnClient`
- sanitized real fixtures where available
- exact Outbound Traffic field/unit documented
- `ArvanCdnClient`
- `MockCdnClient`

Exit:
- no guessed production endpoint/field
- both drivers satisfy same contract.

If API credentials/usage discovery blocks progress:
continue business implementation through Mock; do not invent Real API behavior.

## Phase 2 — Reseller Setup and Secrets

Read:
- `SECURITY.md`
- `SCREEN-SPECS.md`

Deliver:
- hashed demo token allowlist
- Access Token gate
- encrypted Multi API Key
- Test Connection
- Setup Wizard

Exit:
- invalid/valid token behavior tested
- no plaintext API key in DB/UI.

## Phase 3 — Wallet, Ledger, Payment

Read:
- `BILLING.md`
- `DATA-MODEL.md`
- `TEST-PLAN.md`

Deliver:
- Customer/Wallet repositories
- LedgerService
- Mock Payment
- atomic mutation
- idempotency

Exit:
- payment duplicate protection
- Ledger/Wallet reconciliation
- negative balance test.

## Phase 4 — CDN Provisioning

Read:
- `API.md`
- `SERVICE-LIFECYCLE.md`

Deliver:
- Order state
- local Service before provider call
- Resource mapping
- retry/failure state

Exit:
- customer order → Resource ID → correct ownership.

## Phase 5 — Metering and Billing

Read:
- `BILLING.md`
- `CRON.md`
- `API.md`

Deliver:
- Outbound Traffic only
- metered-through catch-up
- usage period persistence
- Base/Markup/Total
- atomic debit
- Billing manual trigger

Exit:
- repeated period does not double debit.

## Phase 6 — Limits and Lifecycle

Read:
- `SERVICE-LIFECYCLE.md`
- `BILLING.md`
- `SECURITY.md`

Deliver:
- threshold
- deduped warning
- inline Suspend
- Resume after recharge
- terminate grace path
- isolation tests

Exit:
- Customer A zero → A suspended
- Customer B unchanged
- recharge A → A active.

## Phase 7 — Customer UI

Read:
- `USER-FLOWS.md`
- `DESIGN.md`
- `SCREEN-SPECS.md`

Deliver:
- CDN page
- Auth
- Recharge
- Provisioning result
- Account
- Service detail

Exit:
- full customer loop works Desktop/Mobile.

## Phase 8 — Reseller Admin

Deliver consolidated:
- Dashboard
- Customers
- Services
- Finance
- Settings

Avoid creating 12 separate pages.

Exit:
- all reseller required operations possible without DB editing.

## Phase 9 — Settlement/System Status

Deliver:
- reconciliation
- Mock settlement
- provider/Cron status
- manual billing control

Exit:
- settlement totals reconcile.

## Phase 10 — Security and QA

Read:
- `SECURITY.md`
- `TEST-PLAN.md`
- `ACCEPTANCE.md`

Run:
- nonce/capability audit
- IDOR two-customer test
- SQL/output audit
- secret scan
- responsive checks
- regression financial tests

Freeze features at start of this phase except demo-blocking corrections.

## Phase 11 — Demo/Delivery

Read:
- `DEMO.md`
- `ACCEPTANCE.md`

Deliver:
- deterministic seed
- README
- rehearsal
- desktop video
- mobile video
- final repository

No new features.

## Stop/Scope Rules

When behind schedule, remove in order:
1. second CDN layout
2. advanced filters
3. noncritical admin polish
4. settlement detail polish
5. audit-log UI
6. charts
7. secondary localization

Never remove:
- Wallet/Ledger
- Markup correctness
- provisioning/mapping
- Outbound Traffic billing
- idempotency
- customer isolation
- immediate Suspend
- recharge/Resume
- secret protection
- responsive demo
- video.
