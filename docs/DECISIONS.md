# DECISIONS — Architecture Decision Log

All decisions are Accepted unless marked otherwise.

## ADR-001 — CDN Is the Only MVP Product

**Context:** Challenge requires complete implementation of one of CDN, Object Storage, Cloud Server.

**Decision:** Implement CDN only, including one CDN sales page and full lifecycle.

**Consequences:**
- Cloud Server and Object Storage receive no page, placeholder or API work.
- All available time goes to one complete loop.

---

## ADR-002 — Markup, Not Commission

**Context:** Reseller adds profit on top of Arvan base price.

**Decision:**

```text
Customer = Base × (1 + Markup)
```

Example:
`100 + 20% = 120`.

**Consequences:**
- no Commission Mode,
- max Markup = 20%.

---

## ADR-003 — VAT Is Outside P0

**Context:** Hackathon core grading focuses on financial ledger, reseller share and lifecycle.

**Decision:** No VAT/Tax engine in MVP.

**Consequences:** Wallet debit = Base + Markup only.

---

## ADR-004 — WordPress Is Runtime/Container

**Context:** Organizer clarified “independent from WordPress core” means Business Logic isolation, not prohibition on WordPress APIs.

**Decision:** WordPress may provide:
- hooks,
- `$wpdb`,
- auth,
- UI host,
- REST/AJAX,
- Cron.

Business logic must not depend on WordPress content structures or third-party plugins/themes.

---

## ADR-005 — Custom Tables for Business/Financial Data

**Decision:** Wallet, Ledger, Payment, Order, Service, Usage, credentials, settlements, notifications and audit use dedicated tables.

**Consequence:** no `posts/postmeta` for financial/product runtime state.

---

## ADR-006 — Demo Access Tokens Are Team-Defined and Hash-Only

**Context:** Organizer explicitly permits self-defined test tokens.

**Decision:** Define tokens such as `arvan_test_123`, bundle only `password_hash(..., PASSWORD_DEFAULT)` results, verify with `password_verify()`.

**Consequences:**
- no need to wait for organizer token,
- raw token is never stored in plugin seed/DB.

---

## ADR-007 — Only CDN Outbound Traffic Is Billable in MVP

**Context:** Organizer clarified one tangible consumption parameter is sufficient.

**Decision:** Implement only Outbound Traffic end-to-end.

**Consequences:**
- no POP split,
- no request-count billing,
- no multi-metric engine for hackathon,
- API Spike focuses only on this metric.

---

## ADR-008 — Integer Rial Money

**Decision:** Money is stored as integer Rial; floats are forbidden.

**Consequences:** deterministic billing and Ledger reconciliation.

---

## ADR-009 — Ledger Is Append-Only

**Decision:** Historical financial entries are immutable.

**Consequences:** corrections use compensating entries; Wallet is a cached current balance.

---

## ADR-010 — Negative Wallet Balance Is Preserved

**Decision:** Usage can make Wallet negative and the exact value is stored.

**Consequences:** no clamp-to-zero; financial reconciliation remains exact.

---

## ADR-011 — Suspend Happens Inline After Debit

**Decision:** If Billing produces `balance <= 0`, SuspensionEngine runs in the same application flow.

**Consequences:** no separate delay before required service restriction.

---

## ADR-012 — Recharge Can Resume Wallet-Suspended CDN

**Decision:** Successful recharge above resume threshold attempts `unhold`.

**Consequences:** Suspend → Recharge → Resume is a complete customer recovery loop.

---

## ADR-013 — Real and Mock CDN Clients Share One Port

**Decision:** Application business logic depends on `CdnClient`; Real and Mock implementations are adapters.

**Consequences:** Demo can remain deterministic without implementing a second financial logic path.

---

## ADR-014 — Mock Payment Is the MVP Payment Method

**Context:** Challenge allows mock/test/manual payment.

**Decision:** No real payment gateway during hackathon.

**Consequences:** full payment state/idempotency behavior is implemented without gateway scope.

---

## ADR-015 — Settlement Can Be Simulated

**Context:** Challenge explicitly permits hypothetical/mock settlement.

**Decision:** Implement reconciliation + Mock Settlement.

**Consequences:** clearly label simulation in demo/UI.

---

## ADR-016 — Admin UI Is Consolidated

**Decision:** Use five main admin areas instead of many separate pages:
Dashboard, Customers, Services, Finance, Settings.

**Consequence:** higher UI quality within time budget.

---

## ADR-017 — Arvan API Details Must Be Verified

**Decision:** Exact endpoint/field/unit for Outbound Traffic is unresolved until API Spike.

**Consequences:** no guessed production endpoint. Mock/port permits parallel development.
