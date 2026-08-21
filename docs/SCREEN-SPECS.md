# SCREEN-SPECS — Screen Requirements

## 1. Reseller Setup Wizard

### Purpose
Turn fresh plugin installation into “Ready to Sell”.

### Steps
1. Access Token
2. API Key
3. Business Profile
4. Pricing & Lifecycle
5. Finish — read-only summary of steps 1–4; no layout picker (T-2.4 decision:
   the public CDN sales page a layout choice would affect is T-7.3, not built
   yet, so exposing the choice here would have no visible effect)

### Critical behaviors
- invalid Access Token blocks later setup,
- valid test token unlocks Markup setup,
- API Key is never re-shown in plaintext,
- connection test has loading/success/failure,
- Markup >20% rejected server-side (verified against a live submission that
  bypassed the browser's own HTML `max` constraint, not just client-side),
- completion redirects to the WordPress Dashboard (`index.php`) — the
  plugin's own Dashboard (T-8.1) and CDN page (T-7.3) do not exist yet.

### Mobile
Single-column; sticky/visible primary action without obscuring content.

---

## 2. Admin Dashboard

### Purpose
Reseller operational overview.

### Content
- total customers
- active CDN services
- suspended services
- total virtual wallet balance
- current period charges
- reseller Markup revenue
- low-balance warnings
- provider/system status
- recent activity

### Actions
- Customers
- Services
- Run Billing Cycle Now
- Settings

### States
- empty/new
- healthy
- warnings
- provider error

---

## 3. Admin Customers

### List
Columns/cards:
- customer
- wallet balance
- service count
- status
- recent usage/charge

### Filters
Minimum viable:
- status/search if cheap

### Action
Open Customer Detail.

### Security
Admin capability required.

---

## 4. Admin Customer Detail

### Content
- identity summary
- Wallet
- thresholds
- Services
- Payments
- Ledger
- Usage

### Admin action
Manual Wallet adjustment only if implemented:
- amount
- direction
- mandatory reason
- confirmation
- audit

Never edit Ledger history.

---

## 5. Admin Services

### Content
- domain
- Resource ID
- owner
- status
- API credential label/last4 reference, not secret
- metered through
- recent charge
- provider sync state

### Actions
- retry failed lifecycle/provisioning
- view detail
- manual lifecycle only if authorized and required

---

## 6. Admin Finance

Single page with tabs.

### Payments
- amount
- customer
- method
- status
- time
- reference

### Ledger
- customer
- type
- signed amount
- balance after
- reference
- time

### Settlements
- period
- Base
- Markup
- Customer total
- status

Financial values must use consistent Rial/Toman presentation.

---

## 7. Admin Settings

Tabs:

### Business
- name
- logo
- website
- email
- phone
- about

### API Keys
- label
- purpose
- masked key
- default
- active
- last validation
- Add/Test/Disable

### Pricing
- Unit price per gigabyte of CDN outbound traffic (Toman) — what the reseller pays ArvanCloud; base for the markup calculation (added T-5.3, since Billing needs it to convert raw usage into a Rial cost)
- Markup 0–20%
- example calculation

Example preview:

```text
Base 100
Markup 20
Customer 120
```

### Lifecycle
- low balance threshold
- resume threshold
- termination grace period

### Layout
Maximum two simple CDN variants:
- Cards
- Compact

---

## 8. Customer Login/Register

### Purpose
Keep customer inside reseller-branded plugin experience while using WordPress auth.

### Register
Minimum fields necessary for WordPress account.

Success:
- WP user
- `arvan_customer`
- zero Wallet

### Security
Rate limiting where custom endpoint is exposed; server-side validation.

---

## 9. CDN Product Page `/arvan/cdn`

### Purpose
Explain and sell CDN.

### Content
- reseller branding
- CDN features
- domain field
- required minimal configuration
- pricing/estimated or unit price presentation
- Markup-inclusive price
- Wallet balance if logged in

### CTA states
Logged out:
- Register/Login

Logged in, insufficient/no credit:
- Add Credit

Logged in and eligible:
- Activate CDN

### Error states
- invalid domain
- setup/API unavailable
- provisioning failure

Only CDN appears.

---

## 10. Wallet / Recharge

### Content
- current balance
- status
- recharge amount
- payment method (Mock/manual receipt if enabled)
- recent payments

### Mock flow
- pending
- succeeded
- failed

Successful:
- Wallet refresh
- Ledger credit
- eligible suspended services may Resume

Duplicate success must not credit again.

---

## 11. Provisioning Result

### Loading
- “Creating CDN service…”
- avoid fake instant success if provider call pending.

### Success
- domain
- status
- Resource ID
- next steps/instructions returned or known safely

### Failure
- safe reason
- retry path if appropriate
- no false claim that resource was not created if provider result is uncertain.

---

## 12. Customer Account Dashboard

### Primary
- Wallet balance
- Add Credit
- Wallet status
- low balance/suspended alert

### Services
Each card:
- domain
- status
- recent Outbound Traffic
- recent charge

### Tabs/sections
- Services
- Transactions
- Payments
- Usage

All data belongs to current customer.

---

## 13. Customer Service Detail

### Content
- domain
- status
- Resource ID
- created date
- Outbound Traffic
- billing period
- Base/Markup/Total where transparency is useful
- recent usage/ledger

### Suspended
Prominent:
- reason: wallet exhausted
- current negative/zero balance
- Recharge CTA
- explanation that successful recharge can resume service

### Failed
Safe error + retry/support path.

### Terminated
Read-only historical state.

---

## 14. System Status / Demo Tools

Can be a section of Admin Dashboard/Settings rather than standalone page.

Show:
- provider mode: Real/Mock
- API health
- last Metering
- last Settlement
- last Sync
- recent safe errors
- Run Billing Cycle Now

Demo-only controls must be capability/nonce protected.
