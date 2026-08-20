# TEST-PLAN — Functional, Financial, Security and Demo Tests

## 1. Test Levels

### Unit
No remote API:
- Money
- Markup
- Ledger rules
- Wallet
- state machines
- threshold logic
- idempotency keys
- Usage pricing adapter

### Integration
- repository + DB
- MockCdnClient
- payment → Wallet
- order → Service mapping
- Metering → Billing
- Billing → Suspend
- recharge → Resume

### Manual/E2E
- setup
- responsive
- real/mock provider
- security ownership
- demo scenario

## 2. Financial Cases

### FIN-001 — 20% Markup
Given Base = 100
When Markup = 20%
Then:
- Markup = 20
- Customer = 120

### FIN-002 — 0% Markup
Customer charge equals Base.

### FIN-003 — Above 20%
25% rejected server-side.

### FIN-004 — Payment success
One successful payment creates exactly one positive Ledger credit.

### FIN-005 — Duplicate payment
Same idempotency key does not create second credit.

### FIN-006 — Usage debit
Usage calculation creates one negative Ledger debit equal to customer charge.

### FIN-007 — Duplicate billing
Same Service/Period/Metric creates no second debit.

### FIN-008 — Negative balance
Before 5,000; debit 8,000; after must be -3,000.

### FIN-009 — Reconciliation
Latest Ledger balance equals Wallet balance.

### FIN-010 — Long sequence
1000 sequential billing operations have zero reconciliation drift.

## 3. Access Token / Setup

### SET-001
Invalid demo token rejected.

### SET-002
`arvan_test_123` (or seeded valid token) accepted.

### SET-003
Raw token not stored in DB/log.

### SET-004
Markup controls remain unavailable until token validation.

### SET-005
API key connection test success/failure displayed safely.

### SET-006
Multiple API keys stored encrypted and only last4 shown.

## 4. Provisioning

### PROV-001
Valid order creates local Order + Service before remote request.

### PROV-002
Mock create returns Resource ID and Service becomes Active.

### PROV-003
Resource is mapped to correct Customer and API credential.

### PROV-004
Provider failure produces failed local state, not false success.

### PROV-005
Repeated uncertain create does not blindly duplicate remote resource.

## 5. Metering

### MET-001
Only CDN Outbound Traffic metric is processed.

### MET-002
Verified/Mock usage normalizes unit and period.

### MET-003
Delayed Cron catches up from `metered_through`.

### MET-004
Two concurrent/repeated runs cannot double bill.

### MET-005
Failed provider usage fetch does not advance metered-through incorrectly.

## 6. Lifecycle

### LIFE-001 — Threshold
Crossing low balance creates one notification.

### LIFE-002 — Notification dedupe
Repeated run does not create/send duplicate event for same threshold episode.

### LIFE-003 — Immediate Suspend
Debit makes balance <=0; Hold is invoked in same workflow.

### LIFE-004 — Isolation
Customer A hits zero; only A's service is suspended. Customer B unchanged.

### LIFE-005 — Negative balance preserved
Suspend does not clamp Wallet.

### LIFE-006 — Resume
Successful recharge above threshold unholds wallet-suspended Service.

### LIFE-007 — Resume failure
Wallet credit remains; Service state shows resume failure/retry.

### LIFE-008 — Terminate
Grace period elapsed → delete → terminated.

## 7. Authorization / IDOR

Use two customer accounts.

### SEC-001
Customer B cannot view A Wallet.

### SEC-002
Customer B cannot view A Service.

### SEC-003
Customer B cannot view A Order.

### SEC-004
Customer B cannot view A Payment.

### SEC-005
Customer B cannot view A Ledger/Usage.

### SEC-006
Customer B cannot trigger lifecycle action on A Service.

Expected:
403 or safe not-found behavior.

## 8. CSRF / Permissions

### SEC-010
Settings change without nonce fails.

### SEC-011
Recharge/order state change without nonce fails.

### SEC-012
Admin page/action without `arvan_manage` fails.

### SEC-013
REST routes have real `permission_callback`.

## 9. Input / XSS / SQL

### SEC-020
Business name containing HTML/script is safely rendered.

### SEC-021
Domain input is validated.

### SEC-022
Query/search inputs cannot alter SQL syntax.

### SEC-023
Sort/status values use allowlists.

## 10. Secret Handling

### SEC-030
DB does not contain plaintext API key.

### SEC-031
Admin UI never reprints full key.

### SEC-032
Logs do not contain API key/raw Access Token.

### SEC-033
Provider error response is redacted.

## 11. Responsive

Test widths representative of:
- mobile
- tablet
- laptop/desktop

Critical screens:
- Setup Wizard
- CDN Product
- Recharge
- Customer Account
- Service Detail
- Admin Dashboard
- Admin Customer/Finance critical views

Acceptance:
- no page-level horizontal overflow,
- controls usable by touch,
- tables adapt,
- dialogs fit viewport.

## 12. Demo E2E

Use deterministic data.

Scenario:
1. install/activate
2. invalid Access Token
3. valid Access Token
4. add/test two API Keys
5. set 20% Markup; reject 25%
6. customer registration
7. Mock credit
8. CDN order
9. provisioning + Resource ID
10. controlled Outbound Traffic
11. billing breakdown
12. repeat billing → no duplicate
13. low balance
14. negative Wallet
15. immediate Suspend
16. Customer B unaffected
17. recharge
18. Resume
19. admin finance/settlement
20. mobile verification

## 13. Exit Criteria

P0 release candidate requires:
- all FIN tests pass,
- provisioning happy path passes in Mock and Real where credentials/API allow,
- MET duplicate test passes,
- LIFE Suspend/Isolation/Resume tests pass,
- SEC IDOR/secret tests pass,
- critical responsive screens pass,
- demo E2E rehearsed once without manual database repair.
