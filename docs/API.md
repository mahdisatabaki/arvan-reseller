# API — Arvan CDN Integration Contract

## 1. Status

This document defines the plugin-side contract.

The exact Arvan endpoint, request schema and response field names for the selected usage metric must be verified during the API Spike. Do not invent them here or in production code.

## 2. Authentication Concepts

Two credentials are distinct:

### Demo Reseller Access Token
Purpose: unlock reseller functionality in the hackathon plugin.

- tokens are defined by the team,
- examples: `arvan_test_123`, `arvan_test_456`,
- only their hashes are bundled,
- validation is local,
- not used for Arvan API calls.

### Arvan API Key / Machine User Credential
Purpose: authenticate requests to ArvanCloud.

- provided/entered by reseller,
- encrypted at rest,
- multiple credentials supported,
- one default credential for CDN can be selected,
- each created Service stores the credential ID used.

## 3. `CdnClient` Port

Application code depends on a provider-neutral contract.

**Status: finalized in T-1.2** (`src/Arvan/CdnClient.php`). Actual methods:

```text
createResource(domain: string): CdnResource

getResource(domain: string): ?CdnResource

getOutboundTrafficUsage(
  domain: string,
  since: DateTimeImmutable,
  until: DateTimeImmutable
): OutboundTrafficUsage

deleteResource(domain: string): void
```

Two divergences from the original conceptual sketch, both decided during T-1.2:

- **No `ping`, `holdResource`, `unholdResource`.** The T-1.1 spike found no
  confirmed ArvanCloud endpoint for a health check or a non-destructive
  suspend/resume operation (§14 below). Per CLAUDE.md's Work Protocol, an
  unverified endpoint is not implemented; the item stays open until a real
  mechanism is confirmed (live-key check, or a different provider primitive
  such as a firewall deny-all rule).
- **No `Credential` parameter on any method.** Each `CdnClient` instance is
  constructed already bound to one resolved API credential, rather than
  receiving it per call. The caller (future `ProvisioningService`/
  `LifecycleService`) resolves the service's `api_key_id` and obtains a client
  bound to that key *before* touching the port. This still satisfies
  DATA-MODEL.md §8's rule that lifecycle calls always use the service's
  creating credential — the binding just happens once, at construction, not
  on every call.

`LifecycleResult` (§4) remains reserved for when `holdResource`/
`unholdResource` are added; `deleteResource` in the finalized interface
signals failure by throwing, not by returning it.

## 4. Domain DTOs

### `CdnResource`

Required normalized fields:
- remote resource identifier
- domain
- normalized status
- created timestamp if available
- provider metadata only if safe/useful

### `OutboundTrafficUsage`

Required:
- `period_start`
- `period_end`
- `usage_value`
- `usage_unit`
- source semantics (`bucketed` or `cumulative`) if needed

Do not pass raw provider JSON deep into Billing.

### `LifecycleResult`

Required:
- success/failure
- normalized status
- retryability
- safe error code/message

## 5. API Spike — Mandatory Discovery

Before implementing `ArvanCdnClient` Metering, verify:

1. Base URL used by current official API.
2. Authentication header format.
3. CDN resource creation endpoint.
4. Returned resource/domain identifier.
5. Resource status/read endpoint.
6. Outbound Traffic endpoint/report.
7. Exact field carrying selected traffic metric.
8. Unit: bytes/KB/MB/GB/etc.
9. Is the metric cumulative or period bucketed?
10. Supported `from/to` or equivalent query semantics.
11. Hold/Suspend operation.
12. Unhold/Resume operation.
13. Delete/Terminate operation.
14. Error formats and important status codes.
15. Rate-limit behavior if documented/observed.

Save **sanitized** fixtures for Mock/Integration tests.

## 6. Selected Usage Scope

Only one billable signal:

```text
CDN Outbound Traffic
```

No other CDN usage dimension is required for the hackathon demo.

Provider adapter responsibility:

```text
raw response
→ extract verified outbound traffic
→ normalize unit/time interval
→ return OutboundTrafficUsage
```

Pricing adapter responsibility:

```text
normalized usage
× configured/verified unit price
→ base_cost_rial
```

Billing responsibility:

```text
base cost
+ reseller markup
→ customer charge
```

## 7. Unit Price

Do not hardcode an unverified price as factual Arvan pricing.

The MVP can support a reseller/admin/system-configured `unit_price_rial` for the selected metric if necessary for deterministic demo billing.

If official pricing is fetched/derived, document source and date in code/docs.

Demo fixtures must clearly be marked demo/test data.

## 8. HTTP Client Rules

Using WordPress HTTP APIs is allowed.

Mandatory:
- HTTPS endpoint only
- TLS verification enabled
- explicit timeout
- bounded retry
- secret redaction
- response shape validation
- no arbitrary customer-supplied base URL
- no raw provider response sent to frontend

## 9. Retry Policy

Safe/retryable examples:
- connection timeout on GET,
- transient 5xx,
- rate limit according to backoff.

Create/Delete/Hold/Unhold must consider duplicate/unknown-result risk.

For create:
- local Service exists first,
- if result is uncertain, query/reconcile before issuing another create.

## 10. Error Normalization

Map provider errors to:
- `AUTHENTICATION_FAILED`
- `FORBIDDEN`
- `INVALID_REQUEST`
- `RESOURCE_NOT_FOUND`
- `RATE_LIMITED`
- `TEMPORARY_PROVIDER_FAILURE`
- `PROVIDER_CONFLICT`
- `UNKNOWN_PROVIDER_ERROR`

Customer sees safe copy.
Admin/audit receives safe technical code and redacted context.

## 11. Multi API Key

Each API credential:
- label,
- purpose,
- active state,
- default state,
- encrypted secret,
- last4,
- last validation timestamp.

Provisioning chooses a concrete credential and persists its ID on Service.

All future operations for that Service use the persisted credential.

## 12. `MockCdnClient`

Must implement exactly the same port.

Capabilities (matches the finalized four-method port, §3):
- deterministic create,
- resource read,
- configurable outbound traffic,
- delete,
- injectable failure states.

Mock data must be obviously test/demo data in code.

The application layer must not branch on “mock vs real” except configuration/driver selection.

## 13. Logging

Allowed:
- request correlation ID,
- operation name,
- safe resource ID/domain,
- HTTP status,
- normalized error code,
- duration.

Forbidden:
- full API key,
- demo raw Access Token,
- Authorization header,
- encrypted secret payload,
- sensitive raw provider body.

## 14. Open API Questions

Until the spike resolves them, keep these explicitly open:
- exact outbound traffic endpoint,
- exact response field,
- unit,
- cumulative vs bucketed semantics,
- remote hold/unhold semantics.

Resolution must be written into this file or `DECISIONS.md` before final submission.
