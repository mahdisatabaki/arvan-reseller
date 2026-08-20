# USER-FLOWS — Reseller and Customer Journeys

## 1. Reseller First-Time Setup

```text
Install Plugin
→ Activate
→ Setup Wizard
→ Enter Demo Access Token
   ├ invalid → error + retry
   └ valid   → unlock setup
→ Add Arvan API Key
→ Test Connection
→ Business Profile
→ Markup (0–20%)
→ Low Balance / Lifecycle Policy
→ CDN Layout
→ Ready to Sell
```

Success condition:
Reseller reaches a usable CDN sales page without editing code/database or installing another plugin.

## 2. Access Token Failure

```text
Enter invalid token
→ validation fails
→ do not store raw token
→ selling/Markup setup remains locked
→ show concise error
→ rate limit repeated attempts
```

Demo must show invalid and valid behavior.

## 3. Multi API Key

```text
Settings → API Keys
→ Add key
→ label/purpose
→ Test Connection
→ save encrypted
→ show last4 only
→ optionally mark default
```

Existing Service remains associated with its original credential.

## 4. Customer Registration

```text
CDN page / Account
→ Register
→ WordPress user created
→ arvan_customer mapping created
→ zero-balance Wallet created
→ authenticated customer enters account
```

## 5. Wallet Recharge

```text
Account → Add Credit
→ enter amount
→ Mock Payment
   ├ failed    → Payment failed, no Wallet credit
   ├ pending   → no Wallet credit yet
   └ succeeded → one Ledger CREDIT + Wallet update
```

Duplicate success does not double-credit.

## 6. CDN Purchase

```text
Customer opens /arvan/cdn
→ sees features and customer price
→ enters domain/configuration
→ submit
→ validate input/auth
→ create Order
→ create local Service(provisioning)
→ call Arvan/Mock CdnClient
   ├ success → map Resource ID → Active
   └ failure → failed state + retry path
→ show delivery/result
```

No cart required.

## 7. Normal Billing

```text
Active CDN
→ Hourly Metering
→ Outbound Traffic
→ Base Cost
→ Markup
→ Customer Charge
→ Ledger DEBIT
→ Wallet update
→ Usage visible in account
```

## 8. Low Balance

```text
Debit
→ balance crosses threshold
→ one notification event/email
→ Service remains active
```

## 9. Wallet Exhaustion

```text
Debit
→ balance <= 0
→ preserve real negative balance
→ immediately Hold only this customer's service(s)
→ Service = Suspended(wallet)
→ customer sees reason and Recharge CTA
```

Customer B is untouched.

## 10. Recharge and Resume

```text
Suspended customer
→ Mock Recharge succeeds
→ Wallet > resume threshold
→ Unhold wallet-suspended service
   ├ success → Active
   └ failure → Resume Failed + admin retry
```

## 11. Termination

```text
Wallet suspension
→ grace period expires
→ Delete remote Resource
→ Terminated
```

Terminate is irreversible in MVP.

## 12. Customer Account

Main information:
- Wallet balance
- Low balance/suspended status
- Active/Suspended services
- Outbound Traffic usage
- charges
- payment history
- transaction history

Customer sees only own records.

## 13. Reseller Daily Management

```text
Admin Dashboard
→ check customers/services/warnings
→ Customers
→ Customer detail
→ Finance
→ Payments/Ledger/Settlement
→ Services
→ retry provider failures if needed
→ Settings
```

## 14. API Failure

Provisioning:

```text
customer order
→ provider unavailable
→ local state = provisioning_failed
→ no fake success
→ safe user message
→ admin retry
```

Lifecycle:

```text
wallet <= 0
→ Hold fails
→ service = suspend_failed
→ financial debit stays
→ audit
→ safe retry
```

## 15. Demo Mode

```text
Admin selects Demo provider
→ same customer journey
→ MockCdnClient
→ deterministic Outbound Traffic
→ Run Billing Cycle Now
→ threshold/negative balance
→ Suspend
→ Recharge
→ Resume
```

Business logic remains identical to Real provider mode.
