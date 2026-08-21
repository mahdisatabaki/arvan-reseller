# DESIGN — UI/UX Direction

## 1. Goal

The plugin should feel like a small standalone cloud reseller product embedded in WordPress, not like a collection of default WordPress forms.

UI quality is judged on:
- clarity,
- setup simplicity,
- trust,
- responsive behavior,
- financial transparency,
- lifecycle feedback.

## 2. Design Source

Use the ArvanCloud **Sorkhab** design system as visual/component reference where practical.

The plugin should implement its own CSS/components and must not depend on a runtime Sorkhab plugin/package unless explicitly approved.

## 3. Product Principles

### A. One clear primary action
Each state should make the next action obvious:
- Connect API
- Save Markup
- Add Credit
- Activate CDN
- Recharge
- Retry

### B. Financial transparency
Never show only a final unexplained charge.

Where relevant, expose:
- Base cost
- Reseller Markup
- Customer total
- Wallet balance

### C. Status is explicit
Use text + semantic treatment, not color alone:
- Active
- Provisioning
- Low balance
- Suspended
- Failed
- Terminated

### D. Failure is recoverable
Errors state:
- what happened,
- whether money/resource was affected,
- what user/admin can do next.

### E. Plugin-owned experience
Theme styles must not break critical layouts.

## 4. Direction

- RTL-first Persian UI
- clean cloud/SaaS admin aesthetic
- restrained visual density
- cards for summaries
- tables for admin data on desktop
- card/list transformation on mobile
- accessible focus states
- no decorative complexity that threatens 36-hour delivery

## 5. Responsive Breakpoints

Do not hard-bind behavior to exact values if existing CSS has standards.

Expected:
- mobile: single column, touch-friendly controls
- tablet: adaptive cards/tables
- desktop/laptop: side navigation/admin tables where useful

Critical rule:
no horizontal page overflow on mobile.

Wide financial tables:
- collapse to key-value cards, or
- intentional inner scroll only when information cannot be reduced.

## 6. Navigation

### Reseller Admin
- Dashboard
- Customers
- Services
- Finance
- Settings

Finance tabs:
- Payments
- Ledger
- Settlements

Settings tabs:
- Business
- API Keys
- Pricing
- Lifecycle
- Layout

### Customer
- CDN
- Account
- Service detail
- Recharge/Auth as contextual views

## 7. Components

Minimum reusable components:
- Button
- Input/Field
- Select if needed
- Radio/segmented control
- Badge/Status
- Alert
- Toast
- Modal/Confirmation
- Card
- Metric/Summary card
- Table
- Empty state
- Loading/Progress
- Tabs
- Pagination only if required
- Copyable Resource ID

## 8. Setup Wizard

Five steps:

1. Access Token
2. Arvan API Key
3. Business Information
4. Markup + Lifecycle
5. Finish — a read-only summary of steps 1–4, then Save. No layout picker
   here: the public CDN sales page that would use a layout choice is T-7.3,
   not this wizard, so T-2.4 does not expose a selector with no visible
   effect. A default layout value is still stored (silently) so T-7.3 has
   something to read from day one.

Requirements:
- progress indicator,
- back/next,
- persist completed steps,
- inline validation,
- API connection result,
- Markup limit visible (`max 20%`),
- no technical jargon without explanation.

## 9. CDN Sales Page

Required information:
- reseller branding
- CDN value/features
- domain input
- minimal required configuration
- customer pricing
- wallet context if logged in
- primary CTA

Do not build Cloud Server/Object Storage cards.

Pricing must make clear that shown price is reseller customer price.

## 10. Customer Account

Top priority:
- Wallet balance
- wallet status
- Add Credit
- active/suspended service summary

Then:
- Services
- Usage
- Transactions
- Payments

When suspended:
show a prominent reason and Recharge action.

## 11. Service Detail

Show:
- domain
- status
- Resource ID/identifier
- created date
- Outbound Traffic usage
- billed amount
- recent transactions
- lifecycle message

Do not expose API Key or provider secrets.

## 12. Admin Dashboard

Prioritize:
- customer count
- active services
- suspended services
- total virtual balances
- current usage/charges
- reseller Markup revenue
- low-balance warnings
- system/API status summary

## 13. Customers

Desktop table:
- Customer
- Wallet
- Services
- Current status
- Recent consumption

Customer detail:
- wallet
- services
- payments
- ledger
- usage

## 14. Finance

### Payments
Status filters:
- Pending
- Successful
- Failed

### Ledger
Immutable transaction view.

### Settlements
Show:
- period
- Base total
- Markup total
- Customer total
- status

## 15. Settings — API Keys

Each key card/row:
- label
- purpose
- `••••last4`
- Active/Disabled
- Default
- Last tested
- actions: Test, Make Default, Disable, Replace/Delete if supported

Never render plaintext.

## 16. State Library

Every async action should have:
- idle
- loading
- success
- failure

Required product states:
- setup incomplete
- no customers
- no services
- no transactions
- provisioning
- provider error
- low balance
- suspended
- resume failed
- terminated

## 17. Accessibility

- semantic form labels
- visible keyboard focus
- sufficient contrast
- no color-only status
- button labels describe action
- errors linked to relevant field
- minimum reasonable touch target
- dialogs trap/restore focus if custom modal used

## 18. Copy Tone

- direct
- operational
- reassuring without marketing hype
- explain financial consequences before destructive actions

Examples:
- «اعتبار کیف پول شما رو به پایان است.»
- «سرویس به‌دلیل اتمام اعتبار متوقف شده است.»
- «پس از افزایش اعتبار، سرویس دوباره فعال می‌شود.»

Final Persian copy can be centralized later; behavior is authoritative in Screen Specs.
