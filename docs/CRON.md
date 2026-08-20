# CRON — Background Jobs

## 1. Constraint

WP-Cron is traffic-triggered and is not a guaranteed system scheduler.

Therefore correctness must depend on persisted timestamps/idempotency, not on Cron firing exactly on time.

## 2. Core Jobs

### A. Metering
Target schedule: hourly.

Purpose:
- process active CDN services,
- fetch unprocessed Outbound Traffic,
- calculate base cost,
- apply Markup,
- create Usage record,
- debit Wallet/Ledger,
- warn on low balance,
- immediately Suspend when balance `<= 0`.

### B. Settlement
Target schedule: daily.

Purpose:
- aggregate base/markup/customer totals,
- create reconciliation/settlement period,
- execute Mock settlement when configured.

### C. Resource Sync / Retry
Target schedule: every 6 hours or minimal equivalent.

Purpose:
- sync remote resource state,
- retry safe failed lifecycle operations,
- detect inconsistent local/remote state.

Do not add extra Cron jobs unless they solve a required behavior.

## 3. Metering Algorithm

Conceptual:

```text
acquire metering lock

for each billable active service:
    from = service.metered_through or billing start
    to   = current safe cutoff

    if no unprocessed interval:
        continue

    fetch outbound traffic for interval
    normalize provider usage
    create/bill unique usage period
    advance metered_through only after successful processing

release lock
```

Provider semantics can require bucket iteration/delta calculation. `API.md` is authoritative after the Spike.

## 4. Catch-Up

Example:
- last successful Metering: 10:00
- next WP-Cron execution: 15:10

System must account for the unprocessed interval(s).

Never assume:
“Cron fired once, therefore bill one hour.”

## 5. Idempotency

Each Usage billing period has a unique database identity.

Re-executing:
- same Cron,
- same manual trigger,
- recovery after timeout

must not create a second debit.

## 6. Locking

Only one Metering process should actively bill at a time.

Use a lock suitable for the existing implementation:
- DB advisory/lock row,
- atomic option/transient with expiry plus DB uniqueness,
- equivalent.

DB unique constraints remain the final duplicate protection.

Lock must expire/recover after crash.

## 7. Immediate Lifecycle Enforcement

Suspend is not deferred to another Cron.

Inside successful Billing:

```text
new balance <= 0
→ SuspensionEngine now
```

Lifecycle retry may later be handled by Resource Sync if remote Hold failed.

## 8. Notifications

Low-balance notification can be triggered inline after debit.

Use notification `dedupe_key` to prevent repeated email.

A separate 15-minute notification Cron is unnecessary for MVP.

## 9. Manual Demo Trigger

Admin button:

```text
Run Billing Cycle Now
```

Requirements:
- capability protected,
- nonce protected,
- invokes same Metering/Billing services,
- does not contain a second fake financial implementation,
- shows safe result summary.

Demo driver can expose controlled Outbound Traffic fixture/time advancement.

## 10. Settlement Idempotency

A settlement period is unique by start/end.

Re-running same period:
- return existing result or safely no-op,
- never create duplicate transfer/mock settlement.

## 11. Failure Handling

Per service:
- one provider failure should not corrupt other customers,
- record safe error,
- do not advance `metered_through` past unprocessed usage,
- continue/retry according to error category when safe.

Global:
- release lock on failure/finally,
- expose last successful run and recent failure in System Status.

## 12. Admin Status

Show:
- last Metering run
- last successful Metering
- number of processed services
- recent failure count
- last Settlement
- last Resource Sync
- current Demo/Real provider mode

Do not expose secrets.
