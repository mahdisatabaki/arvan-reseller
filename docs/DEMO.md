# DEMO — Final Presentation Script

## 1. Goal

Demonstrate one coherent end-to-end story, not a feature checklist.

Target length: approximately 7–9 minutes. Minimum requirement remains 5 minutes.

## 2. Deterministic Demo Data

Recommended:
- Reseller Markup: 20%
- Valid demo token: `arvan_test_123`
- Invalid demo token: `wrong_token`
- Customer A: main demo customer
- Customer B: isolation proof customer
- Use MockCdnClient if real API credentials/traffic cannot safely create the exact scenario
- Configure deterministic Outbound Traffic and unit price so charge math is visually simple

Example billing demonstration:

```text
Base Cost      100,000 Rial
Markup 20%      20,000 Rial
Customer Charge 120,000 Rial
```

Choose Wallet values so one controlled cycle:
- crosses Low Balance,
- then produces a small negative balance,
- makes Suspend obvious.

## 3. Script

### 00:00–00:40 — Problem
Explain:
- Arvan sees reseller master account.
- Plugin maintains per-customer Wallet, services, usage and lifecycle.
- Only CDN is fully implemented per challenge scope.

### 00:40–01:40 — Install and Reseller Setup
Show:
- clean WordPress
- install/activate
- Setup Wizard
- invalid Access Token rejected
- `arvan_test_123` accepted
- explain hashes are bundled, not raw tokens

### 01:40–02:20 — API Credentials and Markup
Show:
- add two API keys/credentials
- masked display
- Test Connection
- 20% Markup accepted
- 25% rejected
- business identity/settings

Do not expose real secret on video.

### 02:20–03:05 — CDN Sales Page
Switch to customer view/mobile-ready frontend.

Show:
- only CDN product
- domain/configuration
- pricing
- reseller branding
- Wallet context

### 03:05–03:45 — Register and Recharge
Show:
- customer registration
- Wallet starts at zero
- Mock recharge
- successful Payment
- Wallet + Ledger credit

### 03:45–04:35 — Provision CDN
Show:
- submit order
- provisioning state
- Resource ID returned
- service appears in account

If Real API is available, optionally show corresponding Arvan resource. If Mock, label it clearly.

### 04:35–05:30 — Outbound Traffic → Billing
Run controlled Billing cycle.

Show:
- selected metric: CDN Outbound Traffic only
- Base Cost
- Markup
- Customer Charge
- Ledger debit
- Wallet change

Then run same period again:
- no duplicate debit.

### 05:30–06:20 — Low Balance and Suspend
Controlled next cycle:
- threshold crossed
- one warning
- Wallet becomes zero/negative
- service immediately Suspended

Open Customer B:
- Wallet/service unaffected.

### 06:20–06:50 — Recharge and Resume
Customer A:
- recharge
- Wallet positive
- Unhold/Resume
- service Active again.

### 06:50–07:30 — Reseller Admin
Show:
- Dashboard
- Customers
- Services
- Finance
- Ledger
- Payments
- Settlement/Reconciliation

### 07:30–08:10 — Mobile
Show on mobile/mobile recording:
- CDN page
- Wallet/Account
- Service status
- no broken layout

### 08:10–08:40 — Security/Architecture
Briefly explain:
- Custom Tables
- WordPress only runtime/auth/UI
- no WooCommerce/plugins
- API secrets encrypted
- customer ownership isolation
- idempotent billing

### 08:40–end — Summary
State what is real vs simulated:
- Access Token list is test data per hackathon instruction
- Payment is Mock by challenge allowance
- Settlement is Mock if no official endpoint
- Provider mode clearly named
- CDN is the single complete product.

## 4. Recording Checklist

Before recording:
- [ ] reset predictable data
- [ ] verify no real secret visible
- [ ] verify demo token works
- [ ] verify two customers exist
- [ ] verify deterministic traffic
- [ ] verify exact charge math
- [ ] verify Suspend/Resume
- [ ] verify duplicate billing no-op
- [ ] verify mobile
- [ ] close unrelated browser tabs/notifications

## 5. Demo Failure Strategy

If Real Arvan API is unstable:
- do not improvise fake claims,
- switch to explicitly labeled Demo Provider,
- show architecture supports real adapter,
- use saved sanitized fixtures/mock contract.

If email delivery is unavailable locally:
- show notification event/status in plugin and explain local SMTP is not configured.

Never edit database manually during recorded primary scenario.
