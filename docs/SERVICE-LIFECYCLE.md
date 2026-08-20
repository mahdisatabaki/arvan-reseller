# SERVICE-LIFECYCLE — CDN Order and Resource States

## 1. Principles

- Local ownership record exists before remote provisioning.
- Every Service belongs to one Customer.
- Every Service stores the API credential used to create it.
- Lifecycle operations use that same credential unless an explicit migration exists.
- Invalid transitions fail closed.
- Provider failure is represented explicitly; it is not hidden.

## 2. Order States

```text
draft
  ↓
pending
  ↓
provisioning
  ├──→ completed
  └──→ failed
```

### `draft`
Customer configuration not submitted.

### `pending`
Order accepted locally and ready to provision.

### `provisioning`
Remote API request is being processed.

### `completed`
Resource mapping succeeded.

### `failed`
Provisioning failed or response could not be safely mapped.

## 3. Service States

Primary lifecycle:

```text
provisioning
    ↓
active
    ↓
suspended
    ↓
active
    ↓
terminated
```

Failure states:
- `provisioning_failed`
- `suspend_failed`
- `resume_failed`
- `terminate_failed`

## 4. Provisioning

Trigger:
Customer submits a valid CDN order and business rules allow provisioning.

Sequence:

```text
create order
create local service(status=provisioning)
persist customer_id + api_key_id
call provider createResource
receive remote identifier
persist remote_resource_id
service → active
order → completed
```

If provider call fails:

```text
service → provisioning_failed
order → failed
audit error
```

If remote success is uncertain because response was lost:
- do not blindly create a second resource,
- attempt provider lookup/reconciliation first.

## 5. Active

Allowed:
- Metering
- Usage display
- Suspend
- Terminate
- Provider status sync

No Metering after confirmed termination.

## 6. Low Balance

Low Balance is a Wallet condition, not a Service state.

```text
active service remains active
+
notification is generated
```

until balance reaches `<= 0`.

## 7. Suspend

Primary MVP reason:

```text
wallet
```

Trigger:

```text
Billing completed
AND wallet.balance <= 0
```

Sequence:

```text
service active
→ call holdResource
→ suspended(reason=wallet)
→ set terminate_after if grace period configured
→ audit
```

If provider call fails:

```text
service → suspend_failed
```

The system records retry intent. It must not suspend another customer's service.

## 8. Resume

Trigger:

```text
payment succeeded
AND wallet.balance > resume_threshold
AND service is suspended because of wallet
```

Sequence:

```text
call unholdResource
→ service active
→ clear wallet suspension metadata/terminate_after
→ audit
```

If provider call fails:

```text
service → resume_failed
```

Wallet credit is not rolled back.

Admin can retry.

## 9. Terminate

Trigger:
- grace period elapsed after wallet suspension, or
- explicit authorized destructive action if UI supports it.

Sequence:

```text
call deleteResource
→ service terminated
→ audit
```

If failure:

```text
terminate_failed
```

Retry is permitted only after provider state is checked.

## 10. State Transition Matrix

| Current | Event | Next | Allowed |
|---|---|---|---|
| provisioning | provision success | active | yes |
| provisioning | provider failure | provisioning_failed | yes |
| provisioning_failed | retry | provisioning | yes |
| active | wallet <= 0 + hold success | suspended | yes |
| active | hold failure | suspend_failed | yes |
| suspend_failed | retry hold | suspended | yes |
| suspended(wallet) | eligible recharge + unhold success | active | yes |
| suspended(wallet) | unhold failure | resume_failed | yes |
| resume_failed | retry unhold | active | yes |
| suspended | grace elapsed + delete success | terminated | yes |
| terminated | resume | — | no |
| terminated | meter | — | no |
| active | direct active→terminated | only authorized policy/action | conditional |

## 11. Customer Isolation

Every lifecycle selection starts with customer ownership.

Forbidden pattern:

```text
UPDATE/act on service by arbitrary service_id from client input
```

Required pattern:

```text
resolve authenticated customer
load service where:
  service.id = requested_id
  AND service.customer_id = current_customer_id
```

Admin operations require capability checks instead.

## 12. Retry Rules

Retryable:
- provider timeout,
- transient 5xx,
- rate-limit after backoff,
- uncertain sync after status check.

Not blindly retryable:
- validation failure,
- invalid credentials,
- forbidden resource,
- delete/create request where duplication could create another billable resource without idempotency/reconciliation.

## 13. UI Mapping

Customer-visible:
- Provisioning
- Active
- Suspended — low wallet
- Failed — actionable generic message
- Terminated

Technical failure state details remain in admin/system logs after redaction.
