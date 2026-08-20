# ACCEPTANCE — Hackathon Definition of Done

## 1. Scope Gate

- [ ] CDN is the only implemented product.
- [ ] Only one CDN product page exists.
- [ ] Cloud Server is absent.
- [ ] Object Storage is absent.
- [ ] No WooCommerce/ACF/Elementor dependency.
- [ ] No VAT engine in P0.
- [ ] Only Outbound Traffic is used for CDN billing.

## 2. Setup / Reseller

- [ ] Plugin installs and activates on clean WordPress.
- [ ] Test Access Token allowlist contains hashes only.
- [ ] Invalid token is rejected.
- [ ] Valid seeded token is accepted.
- [ ] Markup configuration is unlocked after valid token.
- [ ] Markup max 20% enforced server-side.
- [ ] Business profile can be saved.
- [ ] Multiple API Keys can be added.
- [ ] API Keys are encrypted at rest.
- [ ] API Key UI shows only masked/last4.
- [ ] Test Connection works safely.

## 3. Standalone Architecture

- [ ] WordPress is used as runtime/auth/UI/database adapter.
- [ ] Financial/business records are not stored in `posts/postmeta`.
- [ ] Core Pricing/Wallet/Ledger/Metering/Lifecycle logic is isolated from Theme/Plugin dependencies.
- [ ] Plugin UI works without theme-specific code.

## 4. Customer

- [ ] Customer can Register/Login.
- [ ] Customer mapping and zero Wallet are created.
- [ ] Customer can recharge using Mock/manual payment.
- [ ] Successful payment credits exactly once.
- [ ] Customer can see Wallet, payments, transactions and services.
- [ ] Customer sees only own data.

## 5. CDN Sales / Provisioning

- [ ] CDN page shows product information and customer pricing.
- [ ] Customer can submit required CDN/domain configuration.
- [ ] Local Order/Service record exists before remote create.
- [ ] Provisioning call is sent through `CdnClient`.
- [ ] Remote Resource ID/identifier is saved.
- [ ] Resource is mapped to Customer and API credential.
- [ ] Result is returned to customer.
- [ ] Provider failure has a safe/retryable state.

## 6. Metering / Billing

- [ ] Outbound Traffic API/Mock value is consumed.
- [ ] Exact provider field/unit is verified before Real implementation is considered complete.
- [ ] Base Cost is derived consistently.
- [ ] Formula is `Base + Markup = Customer Charge`.
- [ ] Example 100 + 20% = 120 passes.
- [ ] Ledger is append-only.
- [ ] Wallet + Ledger mutation is atomic.
- [ ] Same usage period cannot charge twice.
- [ ] delayed WP-Cron catches up correctly.
- [ ] Wallet can become negative.

## 7. Limits / Lifecycle

- [ ] Low Balance threshold is configurable.
- [ ] Threshold creates a deduplicated notification.
- [ ] `balance <= 0` triggers Suspend immediately after debit.
- [ ] Only affected customer's services are touched.
- [ ] lifecycle call uses Service's associated API credential.
- [ ] successful recharge can Resume wallet-suspended service.
- [ ] Termination after configured grace period works or is demonstrated through Mock if provider constraints require.
- [ ] lifecycle failures are auditable/retryable.

## 8. Settlement

- [ ] Settlement/Reconciliation aggregates Base total.
- [ ] aggregates Markup total.
- [ ] aggregates Customer total.
- [ ] invariant `Base + Markup = Customer` holds.
- [ ] duplicate settlement period is protected.
- [ ] Mock settlement is explicitly labeled as simulation.

## 9. Security

- [ ] Nonces on state-changing browser actions.
- [ ] Admin capabilities checked.
- [ ] Customer ownership checked server-side.
- [ ] IDOR tests pass for Wallet/Order/Service/Payment/Ledger.
- [ ] dynamic SQL is prepared.
- [ ] input validation/sanitization present.
- [ ] output escaped.
- [ ] TLS verification enabled for provider API.
- [ ] secrets absent from HTML/JS/log/error responses.
- [ ] Access Token failures rate-limited.
- [ ] payment and billing idempotency tested.

## 10. UI/UX

- [ ] Setup flow understandable without editing files/database.
- [ ] Admin Dashboard works.
- [ ] Customer flow works.
- [ ] required loading/error/empty/suspended states exist.
- [ ] Desktop/laptop responsive.
- [ ] Mobile responsive.
- [ ] no critical horizontal overflow.
- [ ] financial values/statuses understandable.

## 11. Demo / Submission

- [ ] GitHub repository available.
- [ ] README installation instructions exist.
- [ ] Demo video >= 5 minutes.
- [ ] participant explains product.
- [ ] install/setup shown.
- [ ] Access Token validation shown.
- [ ] API key management shown.
- [ ] CDN purchase/provision shown.
- [ ] Outbound Traffic → Billing shown.
- [ ] Markup calculation shown.
- [ ] Low Balance/Suspend shown.
- [ ] Customer isolation shown.
- [ ] Recharge/Resume shown.
- [ ] Admin finance/settlement shown.
- [ ] Mobile shown.
- [ ] Desktop shown.

## 12. Final Gate

Do not submit until the P0 items above are green or explicitly recorded as a known limitation with a clear demo-safe fallback.

A visually polished plugin with broken Wallet isolation/billing is not accepted as complete.
