# SECURITY — Plugin Security Specification

## 1. Security Goals

Protect:
- reseller Arvan API credentials,
- customer Wallet and financial history,
- resource ownership,
- payment state,
- lifecycle actions,
- admin configuration.

Primary risks:
- IDOR/cross-customer access,
- CSRF,
- XSS,
- SQL injection,
- leaked API secrets,
- duplicate financial mutations,
- unauthorized lifecycle operations,
- unsafe provider error/log handling.

## 2. Trust Boundaries

Untrusted:
- all browser input,
- query/path IDs,
- payment callback/mock action input,
- provider responses until validated,
- uploaded/manual receipt metadata,
- WordPress user display/profile fields.

Trusted only after verification:
- authenticated current user identity,
- capability checks,
- ownership loaded server-side,
- validated/sanitized input,
- decrypted secret inside server-side request scope.

## 3. Demo Access Token

Hackathon demo tokens are team-defined.

Example raw values for demo operation:
- `arvan_test_123`
- `arvan_test_456`

Bundled plugin data contains **hashes only**.

Generate:

```php
password_hash($token, PASSWORD_DEFAULT);
```

Verify:

```php
password_verify($input, $storedHash);
```

Rules:
- raw token not stored in DB,
- raw token not included in hash allowlist,
- never log attempted token,
- rate-limit repeated failures,
- successful verification unlocks reseller setup/sales configuration.

A SHA-256 implementation is not needed because `password_hash/password_verify` is the preferred approach.

## 4. Arvan API Keys

At rest:
- encrypt, do not hash, because key must be recovered for API calls,
- use authenticated encryption such as AES-256-GCM where available,
- encryption key comes from secure WordPress config/salts or explicit environment/config secret,
- unique nonce/IV per encryption.

UI:
- show only last 4 characters,
- never refill plaintext secret into form,
- replacing a key is explicit.

Logs/errors:
- redact Authorization header,
- redact token-like strings,
- never serialize decrypted key into audit metadata.

## 5. Authorization

### Admin
Use custom capabilities, e.g.:
- `arvan_manage`
- `arvan_view_reports`
- `arvan_provision_services`

Do not rely only on “user is logged in”.

### Customer
Resolve:

```text
current WordPress user
→ arvan_customer
```

Then every customer-owned action is restricted by that customer ID.

Never trust a `customer_id` supplied by browser.

## 6. IDOR Prevention

Must test at minimum:
- Wallet
- Payment
- Ledger history
- Order
- Service
- Usage

Customer A must receive 403/not-found-safe behavior for Customer B resources.

Service action pattern:

```text
requested service_id
+
current customer_id
→ owned service query
```

Not:

```text
requested service_id → service
```

## 7. CSRF

Every state-changing browser action requires:
- WordPress nonce verification,
- authorization/capability/ownership check.

Nonce is not authorization.

Applies to:
- save settings,
- add/remove API key,
- payment/recharge action,
- order/provision,
- manual adjustment,
- suspend/resume/terminate,
- manual Cron/demo trigger.

REST routes require real `permission_callback`.

## 8. SQL Injection

Use `$wpdb->prepare()` for dynamic values.

Allowlist:
- sort columns,
- status values,
- enum-like product/state fields.

Never concatenate user-controlled identifiers into SQL.

## 9. XSS and Output Encoding

Input:
- validate first,
- sanitize according to semantic type.

Output:
- escape at render point:
  - text,
  - attribute,
  - URL,
  - HTML allowlist where intentionally supported.

Business name/about fields are untrusted stored input.

## 10. Remote API Security

- HTTPS only
- TLS verification enabled
- fixed/allowlisted provider host
- explicit timeout
- no arbitrary URL from customer input
- validate provider response before persistence
- safe error normalization
- bounded retries

## 11. Financial Security

- integer Rial
- append-only Ledger
- unique idempotency key
- atomic Ledger + Wallet update
- payment success credited once
- usage period billed once
- concurrency lock for Metering
- manual adjustment requires reason + audit

## 12. Resource Lifecycle Security

Before lifecycle operation:
- resolve Service,
- verify actor permission/ownership,
- ensure transition is valid,
- use Service's stored `api_key_id`,
- audit result.

Destructive Terminate should require explicit confirmation for manual admin/customer action.

## 13. Rate Limiting

Minimum targets:
- Access Token attempts
- register/login adjunct endpoints if custom REST used
- payment/mock action
- Test Connection
- provisioning request

Implementation can use transients/custom store suitable for hackathon scale.

## 14. Audit Log

Record:
- API key added/replaced/disabled (never secret),
- Access Token verification success/failure count event if useful, without raw token,
- manual Wallet adjustment,
- provisioning,
- suspend/resume/terminate,
- settlement/manual run,
- critical admin setting changes.

Audit data must itself be escaped/redacted.

## 15. Uninstall

At minimum:
- remove/deactivate active schedules,
- remove stored secrets when full uninstall policy says data removal,
- avoid accidental financial data loss on normal deactivate/reactivate.

For hackathon, document behavior clearly.

## 16. Security Acceptance Checklist

- [ ] no plaintext API key in DB
- [ ] no secret in logs
- [ ] demo access token allowlist contains hashes only
- [ ] failed token attempts rate-limited
- [ ] all state-changing actions nonce-protected
- [ ] custom capability checks present
- [ ] customer ownership checks present
- [ ] IDOR test passes
- [ ] dynamic SQL prepared
- [ ] output escaped
- [ ] provider TLS verify enabled
- [ ] payment idempotency tested
- [ ] billing idempotency tested
- [ ] lifecycle uses correct service credential
- [ ] no WooCommerce/third-party plugin runtime dependency
