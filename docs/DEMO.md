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
- Suspend is local-status only: no confirmed ArvanCloud hold/unhold endpoint exists (T-1.1), so a suspended service's wallet/UI state changes but the real CDN resource keeps serving traffic. State this plainly rather than letting the demo imply a remote block.

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

## 6. Current Environment Cheat-Sheet (as of 2026-08-22, for T-11.4/T-11.5 recording)

This section reflects the actual state of the local `arvan-test.test` site right now, so recording can start without re-discovering values. Re-run `php bin/seed-demo-data.php` any time to restore this exact state if the site changes.

**Reseller config already set:** markup 15%, unit price 1,500 Toman/GB, business name "آروان تست ریسلر". (§3's script assumes 20%/Base 100/Markup 20/Total 120 — either keep this site's real 15% and adjust the spoken numbers, or change markup to 20% in Settings → Pricing before recording to match the script's clean example exactly. Recommended: change it to 20% right before recording, it's a 10-second Settings save.)

**Seeded customers (from `bin/seed-demo-data.php`), both already active with history:**
- "مشتری اول (شرکت آلفا)" — 84,475 Toman wallet, service `shop-alpha.example.com` (active), 2 usage periods billed.
- "مشتری دوم (شرکت بتا)" — 8,100 Toman wallet, service `blog-beta.example.com` (active), 1 usage period billed.

Use these two to show the Admin Customers/Services/Finance screens already populated (§7 of the script) without building history live. For §3–§6 (registration → recharge → order → billing), register a **third, fresh** customer live — do not reuse the seeded two, so the "Wallet starts at zero" moment in §3 is real.

**One settlement already exists:** period 2026-08-20 11:35–13:35, base 19,500 / markup 2,925 / total 22,425 Toman, status "transmitted" — visible now in Finance → Settlements, useful for showing that screen is not empty (Admin section, §7).

**Known constraint for the live-order segment (§4):** the configured ArvanCloud API key in this environment has never successfully created a real resource (documented since the project's first API spike, T-1.1) — a live order will reliably show the graceful-failure path (`provisioning_failed`, safe error message, retry option), not a success. Before recording, decide one of:
- Record the failure path as-is and narrate it honestly (it demonstrates the invariant "a failed provisioning call leaves a recoverable local record, never an orphaned remote resource" — a real, defensible thing to show), **or**
- Swap in a real working ArvanCloud sandbox key if you have one, **or**
- Temporarily wire `MockCdnClient` in for a clean success + a controllable billing/suspend/resume cycle (§6's "crosses Low Balance → negative → Suspend" sequence needs an actually-active, actually-billable service to demonstrate live — this is the one segment that cannot be rehearsed against the real API in this environment).

**Pre-recording checklist specific to this environment:**
- [ ] Settings → کلیدهای API: confirm the default key's status is **فعال** (active) — it was found disabled once already during rehearsal (T-11.3) and silently changes the order-flow error message if left off.
- [ ] Decide markup 15% vs 20% (see above) before recording §5.
- [ ] Decide real-key vs Mock for §4/§6 (see above).
- [ ] Local admin login is `admin` / `ArvanDemo!2026` — change this before publishing the repository, since it's recorded in this project's chat history.
- [ ] If you want a fully from-scratch recording (wizard included), the Setup Wizard steps are already completed on this site — either walk through Settings instead of the Wizard, or deactivate/reactivate the plugin to re-trigger the wizard redirect (this does not drop the `arvan_*` tables, see `uninstall.php`).
