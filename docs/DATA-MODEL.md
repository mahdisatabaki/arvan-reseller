# DATA-MODEL — Custom Tables and Relationships

## 1. Rules

- Use `$wpdb->prefix`; never hardcode `wp_`.
- Financial/business data lives in Custom Tables.
- WordPress `wp_users` is used only for identity/authentication.
- Money is stored as signed integer Rial (`BIGINT`).
- UTC timestamps are recommended internally; local formatting happens in UI.
- Ledger rows are append-only.
- Foreign-key semantics are enforced by application code even if physical FK constraints are omitted for WordPress portability.
- All owner/resource/status lookup paths require indexes.

Logical table names below omit the dynamic WordPress prefix.

## 2. Relationship Map

```text
wp_users
   │
   └── arvan_customers
          │
          ├── arvan_wallets
          │      └── arvan_ledger
          │
          ├── arvan_payments
          │
          └── arvan_orders
                 └── arvan_services
                        ├── arvan_usage_log
                        └── arvan_notifications

arvan_api_keys ───────────────┘
arvan_settlements ← usage/ledger aggregation
arvan_audit_log ← sensitive operations
```

## 3. `arvan_customers`

Purpose: financial/customer profile mapped to a WordPress user.

Recommended columns:
- `id BIGINT UNSIGNED` PK
- `wp_user_id BIGINT UNSIGNED` NOT NULL UNIQUE
- `status VARCHAR(32)` NOT NULL default `active`
- `created_at DATETIME`
- `updated_at DATETIME`

Indexes:
- unique `wp_user_id`
- `status`

Rule: every customer-facing financial query resolves current WordPress user → customer ID server-side.

## 4. `arvan_wallets`

Purpose: cached current wallet balance and customer-specific thresholds.

Columns:
- `id BIGINT UNSIGNED` PK
- `customer_id BIGINT UNSIGNED` NOT NULL UNIQUE
- `balance_rial BIGINT` NOT NULL default `0`
- `low_balance_threshold_rial BIGINT` NOT NULL default `0`
- `resume_threshold_rial BIGINT` NOT NULL default `0`
- `updated_at DATETIME`
- `created_at DATETIME`

Indexes:
- unique `customer_id`

Invariant:

```text
wallet.balance_rial == latest ledger.balance_after_rial
```

Wallet may be negative.

## 5. `arvan_ledger`

Purpose: immutable source of truth for wallet changes.

Columns:
- `id BIGINT UNSIGNED` PK
- `customer_id BIGINT UNSIGNED` NOT NULL
- `wallet_id BIGINT UNSIGNED` NOT NULL
- `type VARCHAR(32)` NOT NULL
- `amount_rial BIGINT` NOT NULL
- `balance_after_rial BIGINT` NOT NULL
- `reference_type VARCHAR(32)` NULL
- `reference_id BIGINT UNSIGNED` NULL
- `idempotency_key VARCHAR(191)` NOT NULL
- `description TEXT` NULL
- `metadata_json LONGTEXT` NULL
- `created_at DATETIME` NOT NULL

Recommended `type` values:
- `wallet_credit`
- `usage_debit`
- `manual_adjustment`
- `refund`

Convention:
- credit amount positive
- debit amount negative

Indexes:
- unique `idempotency_key`
- `(customer_id, created_at)`
- `(reference_type, reference_id)`

Forbidden after creation:
- UPDATE financial amount
- DELETE row

Corrections use compensating entries.

## 6. `arvan_payments`

Purpose: payment attempts and final status.

Columns:
- `id BIGINT UNSIGNED` PK
- `customer_id BIGINT UNSIGNED` NOT NULL
- `amount_rial BIGINT UNSIGNED` NOT NULL
- `method VARCHAR(32)` NOT NULL (`mock`, `manual_receipt`)
- `status VARCHAR(32)` NOT NULL (`pending`, `succeeded`, `failed`)
- `external_reference VARCHAR(191)` NULL
- `idempotency_key VARCHAR(191)` NOT NULL
- `receipt_data_json LONGTEXT` NULL
- `created_at DATETIME`
- `updated_at DATETIME`
- `succeeded_at DATETIME` NULL

Indexes:
- unique `idempotency_key`
- `(customer_id, created_at)`
- `status`

Rule: one succeeded payment creates exactly one Ledger credit.

## 7. `arvan_orders`

Purpose: commercial request before remote provisioning.

Columns:
- `id BIGINT UNSIGNED` PK
- `customer_id BIGINT UNSIGNED` NOT NULL
- `product VARCHAR(32)` NOT NULL default `cdn`
- `domain VARCHAR(255)` NOT NULL
- `status VARCHAR(32)` NOT NULL
- `request_json LONGTEXT` NULL
- `idempotency_key VARCHAR(191)` NULL
- `created_at DATETIME`
- `updated_at DATETIME`

Statuses:
- `draft`
- `pending`
- `provisioning`
- `completed`
- `failed`

Indexes:
- `(customer_id, created_at)`
- `status`
- optional unique `idempotency_key`

## 8. `arvan_services`

Purpose: local representation and ownership mapping for an Arvan CDN resource.

Columns:
- `id BIGINT UNSIGNED` PK
- `customer_id BIGINT UNSIGNED` NOT NULL
- `order_id BIGINT UNSIGNED` NOT NULL
- `api_key_id BIGINT UNSIGNED` NOT NULL
- `product VARCHAR(32)` NOT NULL default `cdn`
- `domain VARCHAR(255)` NOT NULL
- `remote_resource_id VARCHAR(191)` NULL
- `status VARCHAR(32)` NOT NULL
- `suspension_reason VARCHAR(32)` NULL
- `metered_through DATETIME` NULL
- `terminate_after DATETIME` NULL
- `last_provider_sync_at DATETIME` NULL
- `created_at DATETIME`
- `updated_at DATETIME`

Statuses:
- `provisioning`
- `active`
- `suspended`
- `terminated`
- `provisioning_failed`
- `suspend_failed`
- `resume_failed`
- `terminate_failed`

Indexes:
- `(customer_id, status)`
- `remote_resource_id`
- `api_key_id`
- `metered_through`

Rule: lifecycle calls always use this row's `api_key_id`.

## 9. `arvan_usage_log`

Purpose: raw/normalized usage and financial calculation for one billed period.

Columns:
- `id BIGINT UNSIGNED` PK
- `service_id BIGINT UNSIGNED` NOT NULL
- `customer_id BIGINT UNSIGNED` NOT NULL
- `period_start DATETIME` NOT NULL
- `period_end DATETIME` NOT NULL
- `metric VARCHAR(64)` NOT NULL default `cdn_outbound_traffic`
- `usage_value DECIMAL(30,6)` NOT NULL
- `usage_unit VARCHAR(32)` NOT NULL
- `unit_price_rial BIGINT UNSIGNED` NOT NULL
- `base_cost_rial BIGINT UNSIGNED` NOT NULL
- `markup_bps SMALLINT UNSIGNED` NOT NULL
- `markup_amount_rial BIGINT UNSIGNED` NOT NULL
- `customer_charge_rial BIGINT UNSIGNED` NOT NULL
- `ledger_entry_id BIGINT UNSIGNED` NULL
- `source_fingerprint VARCHAR(191)` NULL
- `created_at DATETIME`

Indexes:
- unique `(service_id, period_start, period_end, metric)`
- `(customer_id, created_at)`
- `ledger_entry_id`

Only metric in MVP:
`cdn_outbound_traffic`.

## 10. `arvan_api_keys`

Purpose: encrypted reseller Machine User/API credentials.

Columns:
- `id BIGINT UNSIGNED` PK
- `label VARCHAR(100)` NOT NULL
- `purpose VARCHAR(64)` NOT NULL default `cdn`
- `encrypted_secret LONGTEXT` NOT NULL
- `iv_data VARCHAR(255)`/equivalent required by Crypto implementation
- `auth_tag VARCHAR(255)`/equivalent if separate
- `secret_last4 VARCHAR(4)` NOT NULL
- `is_default TINYINT(1)` NOT NULL default `0`
- `is_active TINYINT(1)` NOT NULL default `1`
- `last_validated_at DATETIME` NULL
- `created_at DATETIME`
- `updated_at DATETIME`

Indexes:
- `(purpose, is_active)`
- `is_default`

Never store plaintext secret.

## 11. `arvan_settlements`

Purpose: reconciliation periods.

Columns:
- `id BIGINT UNSIGNED` PK
- `period_start DATETIME` NOT NULL
- `period_end DATETIME` NOT NULL
- `base_total_rial BIGINT UNSIGNED` NOT NULL
- `markup_total_rial BIGINT UNSIGNED` NOT NULL
- `customer_total_rial BIGINT UNSIGNED` NOT NULL
- `status VARCHAR(32)` NOT NULL
- `gateway VARCHAR(32)` NOT NULL default `mock`
- `external_reference VARCHAR(191)` NULL
- `created_at DATETIME`
- `settled_at DATETIME` NULL

Indexes:
- unique `(period_start, period_end)`
- `status`

## 12. `arvan_notifications`

Purpose: notification history and deduplication.

Columns:
- `id BIGINT UNSIGNED` PK
- `customer_id BIGINT UNSIGNED` NOT NULL
- `service_id BIGINT UNSIGNED` NULL
- `type VARCHAR(64)` NOT NULL
- `dedupe_key VARCHAR(191)` NOT NULL
- `channel VARCHAR(32)` NOT NULL default `email`
- `status VARCHAR(32)` NOT NULL
- `created_at DATETIME`
- `sent_at DATETIME` NULL

Indexes:
- unique `dedupe_key`
- `(customer_id, created_at)`

## 13. `arvan_audit_log`

Purpose: sensitive/admin/provider lifecycle audit.

Columns:
- `id BIGINT UNSIGNED` PK
- `actor_wp_user_id BIGINT UNSIGNED` NULL
- `customer_id BIGINT UNSIGNED` NULL
- `action VARCHAR(100)` NOT NULL
- `entity_type VARCHAR(64)` NULL
- `entity_id BIGINT UNSIGNED` NULL
- `status VARCHAR(32)` NOT NULL
- `metadata_json LONGTEXT` NULL
- `ip_hash VARCHAR(191)` NULL if used
- `created_at DATETIME`

Indexes:
- `(customer_id, created_at)`
- `(entity_type, entity_id)`
- `action`

Never place raw secrets in metadata.

## 14. Demo Access Tokens

Demo Access Token hashes are **seed/config data**, not customer/business tables.

Recommended:
`data/access-token-hashes.php`

It returns only hashes. Raw tokens such as `arvan_test_123` are not stored there.

## 15. Migration Rules

- schema version stored in a dedicated plugin option is acceptable infrastructure metadata,
- migrations are versioned and idempotent,
- never drop financial tables during normal plugin update,
- uninstall behavior for financial data must be explicit and documented before production use,
- backup/migration failure must not silently continue.
