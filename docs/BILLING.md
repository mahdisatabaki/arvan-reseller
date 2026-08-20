# BILLING — Financial Invariants and Calculations

## 1. Purpose

This document is the authoritative financial specification for the MVP.

If code, UI copy, or another document conflicts with these rules, this document wins unless `DECISIONS.md` explicitly supersedes it.

## 2. Revenue Model

Only one model exists:

**Markup on Arvan base cost.**

```text
base_cost = 100
markup_rate = 20%
markup_amount = 20
customer_charge = 120

Arvan share = 100
Reseller profit = 20
```

Formula:

```text
markup_amount = round_money(base_cost × markup_rate)
customer_charge = base_cost + markup_amount
```

No Commission Mode.

## 3. Markup Range

```text
0% <= markup <= 20%
```

Persist recommended as basis points:

```text
0%  = 0 bps
15% = 1500 bps
20% = 2000 bps
```

Any attempt above `2000 bps` is rejected server-side.

## 4. VAT

VAT/Tax is **out of scope for MVP**.

Do not add VAT to Wallet debit, Settlement, pricing UI, or Ledger.

## 5. Money Representation

- storage unit: Rial
- type: integer
- float: forbidden for money
- UI can display Toman
- conversion Rial → Toman is presentation only

Rounding policy must exist in one Money helper/service and be reused everywhere.

## 6. Billable Metric

MVP supports only:

```text
CDN Outbound Traffic
```

No:
- internal/external POP split,
- request count,
- cache hit/miss,
- multiple traffic classes,
- multi-dimensional billing.

The provider adapter normalizes the verified API response into:

```text
period_start
period_end
usage_value
usage_unit
unit_price_rial
base_cost_rial
```

If Arvan does not return monetary cost directly:

```text
base_cost = normalized_usage × configured_unit_price
```

The exact provider field, unit and price source must be documented after the API Spike. Do not guess them.

## 7. Usage Period

Metering tracks:

```text
service.metered_through
```

A WP-Cron delay must not lose elapsed usage.

Example:

```text
metered_through = 10:00
current time    = 15:00
```

The service must process the missing interval(s), not charge a single generic hour.

Provider semantics determine whether catch-up uses:
- time buckets, or
- cumulative delta.

## 8. Billing Breakdown

Every billed Usage row stores:

```text
usage
unit
unit_price
base_cost
markup_bps
markup_amount
customer_charge
period
```

Example:

```text
base_cost       80,000 Rial
markup          20%
markup_amount   16,000 Rial
customer_charge 96,000 Rial
```

Ledger debit:

```text
-96,000 Rial
```

## 9. Wallet and Ledger

Ledger is the financial source of truth.

Wallet balance is a cached current balance.

Every Wallet mutation creates a Ledger row.

Credit:

```text
payment succeeded
→ +amount
```

Usage:

```text
billing succeeded
→ -customer_charge
```

Corrections use compensating Ledger entries, not editing historical rows.

## 10. Atomicity

Ledger write and Wallet update occur in one DB transaction.

Required logical sequence:

```text
BEGIN
check idempotency
lock/read wallet
compute new balance
insert ledger
update wallet
link usage to ledger
COMMIT
```

Any failure:

```text
ROLLBACK
```

The system must never have:
- Wallet changed without Ledger,
- Ledger changed without Wallet.

## 11. Idempotency

### Payment

A successful payment has a unique idempotency key.

Duplicate callback/action:

```text
same key
→ no second credit
```

### Usage Billing

A service/period/metric can be billed once.

Recommended identity:

```text
service_id + period_start + period_end + metric
```

DB unique constraint is mandatory.

Repeated Cron:

```text
already processed
→ skip
```

## 12. Negative Balance

Do not clamp balance to zero.

Example:

```text
before = 5,000
debit  = 8,000
after  = -3,000
```

Persist `-3,000`.

Reason:
- auditability,
- exact reconciliation,
- visible amount owed/overconsumed.

## 13. Low Balance Threshold

After successful debit:

```text
if previous_balance > threshold
and new_balance <= threshold
→ create one low-balance notification event
```

Deduplicate notifications so recurring Cron executions do not spam.

## 14. Immediate Suspend

After debit:

```text
if new_balance <= 0
→ invoke SuspensionEngine in the same billing workflow
```

Do not wait for a separate 15-minute Cron.

Only services belonging to the affected customer are eligible.

If hold fails:
- preserve financial result,
- set service failure state,
- audit,
- queue/retry lifecycle action.

Do not reverse valid measured usage merely because Suspend API failed.

## 15. Resume After Recharge

A service can auto/reseller-trigger resume only when:
- status is suspended,
- suspension reason is `wallet`,
- successful payment has credited the wallet,
- `balance > resume_threshold`.

MVP default:

```text
resume_threshold = 0
```

Then:

```text
unhold remote resource
→ active
```

If unhold fails, Wallet credit remains valid and service enters `resume_failed`.

## 16. Termination

Reseller configures grace period.

After a wallet-related suspension:

```text
terminate_after = suspended_at + grace_period
```

When due:
- final notification if implemented,
- delete remote CDN resource,
- state → terminated,
- audit.

Termination is irreversible in MVP.

## 17. Settlement / Reconciliation

Daily or manual period aggregation:

```text
base_total     = sum(base_cost)
markup_total   = sum(markup_amount)
customer_total = sum(customer_charge)
```

Invariant:

```text
base_total + markup_total = customer_total
```

Mock Settlement is permitted.

No VAT columns/calculation in MVP settlement.

## 18. Required Financial Tests

Must pass:
- 0% Markup
- 20% Markup
- >20% rejected
- integer rounding
- successful payment exactly once
- duplicate payment no second credit
- duplicate billing no second debit
- negative balance preserved
- Ledger latest balance equals Wallet
- 1000 sequential billing operations reconcile
- Customer A operations do not mutate Customer B
- settlement totals reconcile to usage rows
