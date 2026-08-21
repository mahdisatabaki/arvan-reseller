# BACKLOG — Arvan Reseller CDN

بک‌لاگ اجرایی ۳۶ ساعته. مرجع اصلی: `PRD.md` نسخه 1.1.

---

## ۰. قوانین اجرا

### Scope Freeze

```text
IN:
CDN

OUT:
Cloud Server
Object Storage
VAT engine
Multi-metric CDN billing
WooCommerce
Real payment gateway
Extra product pages
```

### Critical Rules

- مدل مالی فقط Markup است.
- `Base 100 + 20% = Customer 120`.
- پول integer Rial است.
- Ledger append-only است.
- هر Billing period idempotent است.
- Suspend بلافاصله پس از Debit منجر به `balance <= 0` اجرا می‌شود.
- Customer isolation قابل قربانی کردن نیست.
- فقط یک metric برای Billing پیاده می‌شود: CDN Outbound Traffic.
- schema همین metric قبل از اتکا باید با response واقعی validate شود.
- هیچ feature جدیدی بدون Decision Log وارد P0 نمی‌شود.

---

## ۱. بودجه

| بلوک | عنوان | بودجه |
|---|---|---:|
| ۰ | Foundation تکمیل/اصلاح | 1.0h باقی‌مانده |
| ۱ | CDN API + Mock | 2.0h |
| ۲ | Reseller Setup + Secrets | 2.5h |
| ۳ | Wallet + Ledger + Payment | 4.0h |
| ۴ | CDN Provisioning + Mapping | 1.75h |
| ۵ | Metering + Billing | 2.5h |
| ۶ | Limits + Suspend/Resume/Terminate | 2.25h |
| ۷ | Customer Frontend | 4.0h |
| ۸ | Reseller Admin | 2.0h |
| ۹ | Settlement + System Status | 1.0h |
| ۱۰ | Security + Responsive + QA | 2.0h |
| ۱۱ | Demo + README + Delivery | 3.0h |
| | **Remaining planned** | **28h** |

کار انجام‌شده قبلی حدود ۳٫۲۵ ساعت است. کل برنامه هدف حدود **31.25h** است و حدود **4.75h buffer** از ۳۶ ساعت باقی می‌گذارد.

Buffer برای bug، API uncertainty و recording است؛ برای Feature جدید مصرف نمی‌شود.

---

# بلوک ۰ — Foundation Correction · 0h remaining

- [x] **T-0.0** ⛔ محیط توسعه کامل
  - PHP 8.1+
  - MySQL/MariaDB
  - WordPress clean install
  - Plugin activation
  - **0.5h**
  - پذیرش: Plugin بدون fatal error فعال شود.

- [x] **T-0.1** Plugin skeleton + autoloader

- [x] **T-0.2** Custom Table schema

- [x] **T-0.3** Versioned installer

- [x] **T-0.4** Custom capabilities + `arvan_customer`

- [x] **T-0.5** Scheduler foundation

- [x] **T-0.6** Money/Pricing foundation

- [x] **T-0.7** ⛔ اصلاح Pricing implementation
  - حذف `Commission`
  - حذف `MarginMode`
  - فقط `MarkupRate`
  - سقف 20%
  - حذف VAT از P0
  - `ChargeBreakdown = base + markup + total`
  - **0.25h**
  - پذیرش:
    - Base 100 → Markup 20% → Total 120
    - 25% throws validation error

- [x] **T-0.75** مرز WordPress/Business Logic
  - استفاده از Hooks و `$wpdb` مجاز است.
  - Auth با WordPress users.
  - Persistence با Custom Tables.
  - Domain logic مستقل از `posts/postmeta`، Theme و Pluginهای جانبی.
  - هیچ تلاش برای حذف کامل WordPress APIs انجام نشود.
  - **0.0h — rule only**

- [x] **T-0.8** Port interfaces نهایی
  - `WalletRepository`
  - `LedgerRepository`
  - `ServiceRepository`
  - `PaymentRepository`
  - `Clock`
  - `SecretStore`
  - `Mailer`
  - `AuditLogger`
  - **0.25h**

### خارج از P0

- Zero-WordPress grep proof
- TaxRate
- multi-language polish

**DoD:** Plugin فعال، schema موجود، pricing تست‌پذیر و فقط Markup.

---

# بلوک ۱ — CDN API + Mock · 2h

- [ ] **T-1.1** ⛔ API Spike واقعی CDN
  - Test authentication
  - Create resource/domain
  - Fetch resource
  - پیدا کردن فقط **Outbound Traffic** موردنیاز Billing
  - تشخیص unit و cumulative/bucketed بودن response
  - Hold
  - Unhold
  - Delete
  - Save sanitized fixture responses
  - **0.75h**

  پذیرش:
  - schema واقعی Resource ID مشخص باشد.
  - endpoint/field/unit مربوط به Outbound Traffic مشخص باشد.
  - هیچ metric دیگر برای MVP وارد scope نشود.
  - هیچ endpoint یا field فرضی در Production code باقی نماند.

  **Stop condition:**
  اگر API مبلغ مستقیم نمی‌دهد، `UsagePricingAdapter` فقط Outbound Traffic را با قیمت واحد configured به Base Cost تبدیل کند. این قرارداد در `API.md` ثبت شود.

- [x] **T-1.2** `CdnClient` interface
  - `createResource`
  - `getResource`
  - `getOutboundTrafficUsage`
  - `deleteResource`
  - **0.25h**
  - پذیرش:
    - Provider-agnostic؛ هیچ DTO خام Arvan در لایه‌ی دامنه نشت نمی‌کند
    - `ping`, `holdResource`, `unholdResource` عمداً حذف شدند — اسپایک T-1.1 مکانیزم واقعی‌شان را در API آروان پیدا نکرد؛ حدس زدن endpoint طبق CLAUDE.md §Work Protocol مجاز نیست (باز مانده تا رفع ابهام، API.md §14)

- [x] **T-1.3** `ArvanCdnClient`
  - HTTPS only
  - TLS verify
  - timeout
  - normalized errors
  - secret redaction
  - bounded retry only for safe/retryable requests
  - **0.5h**
  - پذیرش:
    - فقط به `HttpClient` (`src/Ports/HttpClient.php`) وابسته است، نه `wp_remote_request` و نه `curl_*` مستقیم — تصمیم معماری این پیام (نه TECH.md اولیه)، تأییدشده با ۳۰ چک خودکار بدون بوت‌استرپ وردپرس
    - `CdnProviderException` تنها abstraction اضافه؛ ۸ دسته‌ی API.md §۱۰، `retryable` از روی دسته مشتق می‌شود
    - retry فقط روی `getResource`/`getOutboundTrafficUsage` (حداکثر ۳ تلاش)؛ `createResource`/`deleteResource` هرگز خودکار retry نمی‌شوند
    - پیام‌های exception هرگز کلید API یا بدنه‌ی خام provider را افشا نمی‌کنند

- [x] **T-1.4** `MockCdnClient`
  - contract دقیقاً مشابه Production client
  - deterministic resource IDs
  - configurable outbound-traffic fixture
  - create/get/traffic/delete states (منطبق با ۴ متد نهایی؛ hold/unhold از خودِ اینترفیس در T-1.2 حذف شدند)
  - **0.5h**
  - پذیرش:
    - بدون constructor dependency (نه HttpClient، نه API key، نه وردپرس) — state فقط در حافظه
    - `forceFailure`/`clearFailure`/`seedResource`/`setOutboundTraffic` — ۴ ابزار کمکی تست، خارج از قرارداد `CdnClient`
    - همان `CdnProviderException` واقعی را پرتاب می‌کند (نه یک کلاس جعلی)؛ ۳۳ چک خودکار سبز

**DoD:** ✅ یک integration scenario با هر دو driver خروجی domain-level مشابه تولید کند — تأیید شد با ۲۰ چک مشترک (create → get → traffic → delete → not-found) روی هر دو driver، بدون تفاوت در سناریوی تست.

---

# بلوک ۲ — Reseller Setup + Secrets · 2.5h

- [x] **T-2.1** 🔒 `SecretStore`
  - AES-256-GCM یا implementation امن معادل
  - encryption key از config/salts
  - no plaintext DB
  - **0.5h**
  - پذیرش:
    - `wp/Security/WordPressSecretStore.php` — پیاده‌سازی اینترفیس T-0.8، بدون تغییر در قرارداد
    - کلید از `ARVAN_ENCRYPTION_KEY` (اگر تعریف شده) وگرنه از `AUTH_KEY`+`SECURE_AUTH_KEY`؛ اگر هیچ‌کدام نبود، constructor throw می‌کند (بدون fallback به کلید ثابت)
    - nonce تصادفی جدا برای هر `encrypt()`؛ دو رمزنگاری از یک plaintext ciphertext متفاوت می‌دهند
    - `decrypt()` روی ciphertext دستکاری‌شده یا رمزشده با کلید غلط `RuntimeException` می‌دهد (تست شد با کلید واقعاً متفاوت در یک پروسه‌ی جدا)
    - ۱۷ چک خودکار در ۴ پروسه‌ی PHP جدا (برای تست دقیق سناریوهای مختلف ثابت‌های wp-config) سبز

- [x] **T-2.2** 🔒 Access Token Gate
  - تعریف حداقل دو Demo Token ساده، مثلاً:
    - `arvan_test_123`
    - `arvan_test_456`
  - فقط hash آن‌ها داخل Plugin seed شود.
  - `password_hash(..., PASSWORD_DEFAULT)` برای تولید hash
  - `password_verify()` برای validation
  - هیچ Raw Token در DB یا allowlist ذخیره نشود.
  - rate limit failed attempts
  - فروش و تنظیم Markup تا قبل از validation موفق disabled
  - **0.5h**
  - پذیرش:
    - `wp/Security/AccessTokenGate.php` + `data/access-token-hashes.php` (فقط هش، بدون هیچ توکن خام حتی در کامنت)
    - هیچ ارتباطی با `SecretStore`/`WordPressSecretStore` ندارد؛ `SecretStore` دست‌نخورده ماند
    - rate limit: بعد از ۵ تلاش ناموفق، حتی توکن درست هم رد می‌شود تا وقتی transient منقضی شود (۱۵ دقیقه)؛ موفقیت شمارنده را ریست می‌کند
    - فلگ فعال‌سازی (`arvan_reseller_access_token_verified`) یک option بولین است، نه یک secret — `isActivated()` نقطه‌ی اتصال آینده برای قفل کردن ویزارد/Markup است؛ سیم‌کشی واقعی UI در T-2.4 انجام می‌شود
    - `uninstall.php` اصلاح شد: نام قدیمی و اشتباه `arvan_reseller_access_token` (که هیچ‌وقت مقداری در آن نمی‌رفت) حذف شد؛ نام واقعی به لیست purge-only اضافه شد (نه لیست «همیشه پاک شو»، چون این یک secret نیست)
    - ۲۰ چک خودکار سبز، شامل اجرای واقعی `uninstall.php` با `$wpdb`/`get_option` شبیه‌سازی‌شده

- [x] **T-2.3** Multi API Key Management
  - add/edit label
  - product/use label
  - default key
  - active/disabled
  - test connection
  - show last4 only
  - **0.75h**
  - پذیرش:
    - `src/Ports/ApiKeyRepository.php` (پورت جدید، هم‌الگو با ۴ Repository موجود از T-0.8)، `wp/Persistence/WpApiKeyRepository.php`، `src/Arvan/ApiKeyConnectionTester.php` — بدون تغییر در schema (ستون‌های لازم از T-0.2 موجود بودند)
    - Repository هیچ‌جا پارامتر plaintext ندارد — رمزنگاری بیرون از آن انجام می‌شود؛ `SecretStore`/`AccessTokenGate` دست‌نخورده ماندند
    - تشخیص تکراری از روی `fingerprint` (SHA-256 کلید): افزودن همان کلید دوباره سطر جدید نمی‌سازد، همان id قبلی را برمی‌گرداند
    - «تست اتصال» بدون `ping`: از `getResource()` موجود روی یک دامنه‌ی probe ثابت استفاده می‌کند؛ `AUTHENTICATION_FAILED`/`FORBIDDEN` → رد شد، بقیه‌ی دسته‌ها → نامشخص (نه لزوماً خراب)؛ پیام‌ها همیشه رشته‌ی ثابت دست‌نویس‌اند، هرگز از خروجی provider گرفته نمی‌شوند
    - ۲۹ چک خودکار سبز — شامل چرخه‌ی کامل رمزنگاری واقعی (`WordPressSecretStore`) از میان Repository و تست سناریوهای موفق/ناموفق اتصال با `MockCdnClient`

- [x] **T-2.4** Setup Wizard
  1. Access Token
  2. API Key
  3. Business Profile
  4. Markup + lifecycle policy
  5. Finish (بدون انتخاب چیدمان — تصمیم حین اجرا، پایین را ببین)
  - **0.75h**
  - پذیرش:
    - `wp/Admin/ResellerSettings.php`, `wp/Admin/SetupWizard.php`, `wp/Admin/templates/setup-wizard.php` — بدون فایل `src/` جدید
    - تنظیمات به‌صورت ۴ option گروه‌بندی‌شده (`arvan_reseller_branding/pricing/limits/settings`)، نه اسکترد
    - قدم ۲: ترتیب «تست سپس ذخیره» — کلید نامعتبر هرگز رمزنگاری/ذخیره نمی‌شود؛ فیلد کلید هرگز pre-fill نمی‌شود
    - قدم ۴: اعتبارسنجی روی همان `MarkupRate::fromPercent()` از T-0.7؛ رد ۲۵٪ حتی با دور زدن اعتبارسنجی سمت مرورگر تأیید شد
    - **تصمیم حین اجرا:** انتخاب چیدمان Cards/Compact از UI قدم ۵ حذف شد چون صفحه‌ی فروش عمومی (T-7.3) که ازش استفاده می‌کرد هنوز نیست؛ یک مقدار پیش‌فرض بی‌صدا ذخیره می‌شود، قدم ۵ به‌جایش خلاصه‌ی نهایی تنظیمات را نشان می‌دهد
    - **تست:** روی وردپرس واقعی محلی (نه شبیه‌سازی) — کاربر خودش login کرد (رمز عبور توسط Claude هرگز وارد نشد، طبق قانون ایمنی)، من هر ۵ قدم را در مرورگر واقعی کلیک کردم؛ شامل تست XSS واقعی (`<script>` در فیلد «درباره‌ما» توسط `sanitize_textarea_field` حذف شد) و رد سرور-ساید Markup>20٪ با دور زدن عمدی attribute `max` مرورگر
    - **۳ باگ واقعی پیدا و رفع شد** که هیچ تست شبیه‌سازی‌شده‌ای پیدایشان نمی‌کرد — جزئیات در PROGRESS.md

**DoD:** ✅ نصب تا Ready to Sell بدون ویرایش فایل یا دیتابیس — تأیید شد با کلیک واقعی هر ۵ قدم روی وردپرس محلی، از فعال‌سازی پلاگین تا ریدایرکت نهایی به Dashboard.

---

# بلوک ۳ — Wallet + Ledger + Payment · 4h

- [x] **T-3.1** ⛔ LedgerService اتمیک
  - append-only
  - unique idempotency key
  - DB transaction around:
    - ledger insert
    - wallet update
  - **1.25h**
  - پذیرش:
    - `wp/Persistence/WpLedgerRepository.php` — پیاده‌سازی پورت `LedgerRepository` (T-0.8)، بدون تغییر در امضای پورت
    - `SELECT ... FOR UPDATE` روی سطر wallet داخل `START TRANSACTION`/`COMMIT` قفل می‌کند تا دو `append()` هم‌زمان روی یک customer یکدیگر را نبینند
    - idempotency دو لایه‌ای: اول یک `SELECT` روی `idempotency_key` (مسیر سریع تکرار عادی)، و اگر race واقعی رخ دهد و INSERT با نقض unique key مواجه شود، `ROLLBACK` و خواندن دوباره‌ی سطر برنده — هیچ‌وقت دو بار credit/debit نمی‌شود
    - `direction`/`amount_rial` (بدون علامت، unsigned) از روی علامت `Money` مشتق می‌شوند؛ `balance_after_rial` و `wallet.balance_rial` علامت‌دار می‌مانند و منفی می‌شوند، clamp به صفر نمی‌شوند
    - ۲۴ چک خودکار (fake `$wpdb`، خارج از ریپو) سبز: credit، duplicate idempotency_key (بدون credit دوم)، debit به موجودی منفی، و شبیه‌سازی race روی INSERT

- [x] **T-3.2** WalletRepository + CustomerRepository
  - تمام customer queries customer-scoped
  - no cross-customer access
  - **0.5h**
  - پذیرش:
    - `src/Ports/CustomerRepository.php` (پورت جدید) + `wp/Persistence/WpCustomerRepository.php` + `wp/Persistence/WpWalletRepository.php` (پیاده‌سازی پورت موجود از T-0.8)
    - `CustomerRepository::create()` همان الگوی «برگرداندن id موجود روی تکرار» را دارد که `ApiKeyRepository::create()` دارد (T-2.3) — دو بار ثبت یک `wp_user_id` دو سطر نمی‌سازد
    - `WalletRepository` فقط خواندن + `ensureExists()` دارد (بدون متد نوشتن مستقیم روی balance) — طبق قرارداد پورت، تنها مسیر نوشتن `LedgerRepository::append()` است
    - ۲۴ چک مشترک با T-3.1 (بالا) سبز، شامل `ensureExists()` idempotent و خواندن صحیح `notify_threshold_rial` NULL به‌صورت صفر

- [ ] **T-3.3** Customer creation
  - WP registration
  - `arvan_customer`
  - financial customer row
  - zero-balance wallet
  - **0.5h**

- [ ] **T-3.4** Mock Payment
  - pending
  - succeeded
  - failed
  - successful payment → exactly one CREDIT
  - duplicate callback → no duplicate credit
  - **0.75h**

- [ ] **T-3.5** Manual receipt/admin adjustment
  - reason required
  - audit log
  - **0.25h**

- [ ] **T-3.6** Financial unit tests
  - Base + Markup
  - duplicate payment
  - duplicate ledger idempotency
  - negative balance
  - 1000 sequential billing operations without reconciliation drift
  - **0.75h**

**DoD:** Wallet و Ledger قابل reconciliation و duplicate-safe باشند.

---

# بلوک ۴ — CDN Provisioning + Mapping · 1.75h

- [x] **T-4.1** Service state machine
  - provisioning
  - active
  - suspended
  - terminated
  - failed states
  - **0.25h**
  - پذیرش:
    - `src/Lifecycle/ServiceStatus.php` — value object خالص دامنه (بدون `wp_*`)؛ ۸ وضعیت دقیقاً مطابق `arvan_services.status` در Schema.php
    - `canTransitionTo()` جدول انتقال مجاز را یک‌جا نگه می‌دارد تا `ProvisioningService`/`SuspensionEngine` بعدی مجبور به تکرار منطق نباشند؛ `terminated` هیچ انتقال خروجی ندارد (BILLING.md §۱۶: غیرقابل‌بازگشت)
    - ۲۶ چک خودکار سبز: تمام انتقال‌های مجاز، چند انتقال غیرمجاز نمونه، رد وضعیت ناشناخته

- [ ] **T-4.2** ⛔ ProvisioningService
  - create local order/service BEFORE remote API call
  - call `CdnClient.createResource`
  - store Resource ID
  - map:
    - customer_id
    - order_id
    - service_id
    - api_key_id
  - **0.75h**

- [ ] **T-4.3** Delivery data
  - resource identifier
  - domain
  - status
  - configuration/instructions returned by API when applicable
  - no server credential assumptions
  - **0.25h**

- [ ] **T-4.4** Resource sync/retry
  - retry provisioning failure
  - reconcile remote/local status
  - audit mismatch
  - **0.5h**

**DoD:** Order → CDN Resource → Resource mapping به همان customer.

---

# بلوک ۵ — Metering + Billing · 2.5h

- [ ] **T-5.1** ⛔ MeteringService
  - use `metered_through`
  - calculate elapsed periods
  - catch-up after delayed WP-Cron
  - fetch real/mock CDN outbound traffic only
  - **1.0h**

- [ ] **T-5.2** Billing idempotency + lock
  - unique usage period key
  - cron/process lock
  - duplicate execution safe
  - **0.5h**

- [ ] **T-5.3** Pricing + Debit
  - `outbound_traffic_value`
  - `outbound_traffic_unit`
  - `unit_price`
  - `base`
  - `markup_rate`
  - `markup_amount`
  - `total`
  - atomic wallet debit
  - negative balance preserved
  - **0.5h**

- [ ] **T-5.4** Demo billing trigger
  - `Run Billing Cycle Now`
  - optional controlled usage fixture/time advance
  - invokes same application service
  - **0.5h**

**DoD:** اجرای دوباره یک period هیچ Debit جدید ایجاد نکند.

---

# بلوک ۶ — Limits + Lifecycle · 2.25h

- [ ] **T-6.1** ThresholdPolicy
  - low balance threshold
  - resume threshold
  - terminate grace period
  - **0.25h**

- [ ] **T-6.2** Low Balance Notification
  - create notification event
  - email attempt
  - dedupe key
  - **0.5h**

- [ ] **T-6.3** ⛔ Suspend inline
  - BillingService بعد از Debit:
    ```text
    if balance <= 0
      → SuspensionEngine
      → holdResource
    ```
  - same `api_key_id` that created service
  - customer-scoped
  - **0.5h**

- [ ] **T-6.4** Resume after Recharge
  - if suspension reason = wallet
  - successful recharge
  - balance > resume threshold
  - unhold
  - service active
  - **0.5h**

- [ ] **T-6.5** Terminate + isolation test
  - grace period
  - delete resource
  - audit
  - Customer A zero → A suspended/terminated
  - Customer B unchanged
  - **0.5h**

**DoD:** Suspend و Resume end-to-end و isolation test سبز.

---

# بلوک ۷ — Customer Frontend · 4h

فقط CDN. هیچ صفحه Cloud Server/Object Storage ساخته نمی‌شود.

- [ ] **T-7.1** UI foundation
  - Sorkhab-inspired tokens
  - RTL
  - CSS namespace
  - mobile-first
  - accessibility basics
  - **0.75h**

- [ ] **T-7.2** Plugin-owned routes/templates
  - `/arvan/cdn`
  - `/arvan/account`
  - service detail
  - auth/recharge
  - **0.25h**

- [ ] **T-7.3** ⛔ CDN Product Page
  - business branding
  - features
  - configuration/domain input
  - pricing
  - markup-inclusive customer price
  - order CTA
  - max two simple layout variants
  - **1.0h**

- [ ] **T-7.4** Login/Register + Wallet Recharge
  - plugin UI
  - mock payment states
  - **0.75h**

- [ ] **T-7.5** Order/Provision Result
  - provisioning/loading
  - success
  - failure
  - Resource ID/status
  - **0.5h**

- [ ] **T-7.6** Customer Dashboard
  - wallet
  - services
  - outbound traffic usage
  - transactions
  - payments
  - low balance/suspended state
  - **0.75h**

**DoD:** Customer journey روی mobile و desktop بدون horizontal overflow کار کند.

---

# بلوک ۸ — Reseller Admin · 2h

از ۱۲ صفحه مستقل اجتناب شود.

- [ ] **T-8.1** Dashboard
  - customers
  - active/suspended services
  - virtual balances
  - today's usage
  - reseller markup revenue
  - warnings
  - **0.4h**

- [ ] **T-8.2** Customers
  - list
  - customer detail
  - wallet
  - services
  - payments
  - usage
  - **0.4h**

- [ ] **T-8.3** Services
  - owner
  - Resource ID
  - credential
  - status
  - usage/cost
  - retry action
  - **0.3h**

- [ ] **T-8.4** Finance
  - tabs:
    - Payments
    - Ledger
    - Settlements
  - filters minimum viable
  - **0.4h**

- [ ] **T-8.5** Settings
  - Business
  - API Keys
  - Markup
  - Threshold/Lifecycle
  - Layout
  - **0.5h**

**DoD:** Reseller تمام requirementهای مدیریتی را بدون database access انجام دهد.

---

# بلوک ۹ — Settlement + System Status · 1h

- [ ] **T-9.1** SettlementService
  - aggregate:
    - base
    - markup
    - customer total
  - MockSettlement
  - no VAT
  - **0.5h**

- [ ] **T-9.2** System Status
  - API connectivity
  - last metering run
  - last settlement
  - resource sync
  - Demo Mode
  - Run Billing Cycle Now
  - recent errors
  - **0.5h**

**DoD:** settlement totals با Ledger قابل reconciliation.

---

# بلوک ۱۰ — Security + Responsive + QA · 2h

- [ ] **T-10.1** 🔒 Nonce + capability audit
  - all state-changing actions
  - REST permission callbacks
  - **0.35h**

- [ ] **T-10.2** 🔒 IDOR audit
  - service
  - order
  - wallet
  - payment
  - ledger/history
  - **0.4h**

- [ ] **T-10.3** 🔒 Input/output/SQL audit
  - validate
  - sanitize
  - escape
  - `$wpdb->prepare()`
  - **0.35h**

- [ ] **T-10.4** 🔒 Secret audit
  - API key
  - Access Token
  - logs
  - HTML
  - JS
  - error responses
  - **0.25h**

- [ ] **T-10.5** Responsive + lifecycle regression
  - real/mobile viewport
  - CDN
  - wallet
  - account
  - service
  - admin critical screens
  - **0.4h**

- [ ] **T-10.6** Plugin/security check
  - critical findings only
  - **0.25h**

**DoD:** هیچ vulnerability شناخته‌شده P0 و هیچ customer data leak باقی نماند.

---

# بلوک ۱۱ — Demo + Delivery · 3h

این بلوک قربانی نمی‌شود.

- [ ] **T-11.1** One-click seed/demo data
  - 2 customers
  - different balances
  - CDN services
  - usage history
  - payment history
  - **0.4h**

- [ ] **T-11.2** README
  - install
  - architecture
  - security
  - demo mode
  - limitations
  - **0.3h**

- [ ] **T-11.3** Demo rehearsal
  - exact balances
  - exact markup
  - exact usage
  - predictable suspend/resume
  - **0.3h**

- [ ] **T-11.4** Desktop recording
  - **1.0h**

- [ ] **T-11.5** Mobile recording
  - **0.3h**

- [ ] **T-11.6** Final edit + GitHub delivery
  - **0.7h**

---

# فیلم‌نامه ویدئو

| بخش | نمایش |
|---|---|
| 1 | مسئله: آروان حساب مادر را می‌شناسد؛ افزونه accounting/customer layer را می‌سازد |
| 2 | نصب و activation |
| 3 | Access Token validation |
| 4 | دو API Key + mask + test connection |
| 5 | Business Profile + Markup 20%؛ رد 25% |
| 6 | فقط صفحه فروش CDN |
| 7 | Customer registration |
| 8 | Mock recharge |
| 9 | CDN order |
| 10 | Real/Mock provisioning + Resource ID |
| 11 | Billing cycle با Outbound Traffic CDN به‌عنوان تنها metric |
| 12 | نمایش Base 100 → Markup 20 → Total 120 |
| 13 | duplicate billing protection |
| 14 | low balance notification |
| 15 | negative balance + immediate Suspend |
| 16 | Customer دوم بدون تغییر |
| 17 | Recharge + Resume |
| 18 | Admin customer/payment/ledger/settlement |
| 19 | Mobile CDN + account + service |
| 20 | جمع‌بندی architecture/security |

---

# مسیر بحرانی

```text
Environment
  ↓
CDN API Spike
  ↓
Wallet/Ledger
  ↓
Provisioning
  ↓
Metering/Billing
  ↓
Suspend
  ↓
Resume
  ↓
Customer UI
  ↓
Security
  ↓
Demo
```

---

# Checkpoints

| ساعت کل پروژه | باید محقق شده باشد | اگر نشده |
|---|---|---|
| 6 | Plugin active + CDN Outbound Traffic API schema معلوم | Real API را موقتاً پشت Mock ببند؛ endpoint حدس نزن |
| 12 | Wallet/Ledger/Payment سبز | Admin polish را کم کن |
| 18 | Order → Provision → Mapping کامل | Layout variant دوم را حذف کن |
| 23 | Metering → Debit → Suspend → Resume کامل | Settlement UI را حداقلی کن |
| 28 | Customer UI + Admin critical flow | non-critical admin filters را حذف کن |
| 31 | Security pass + responsive | تمام feature work متوقف |
| 33 | recording شروع شده باشد | فقط bugهای demo-blocker |
| 36 | تحویل | — |

---

# فهرست قربانی

به همین ترتیب:

1. layout variant دوم CDN
2. advanced Finance filters
3. manual receipt UI polish
4. Resource Sync UI
5. Settlement detail polish
6. audit-log UI
7. en_US localization
8. charts

## هرگز قربانی نکن

- CDN provisioning + mapping
- Wallet/Ledger
- Markup correctness
- Billing idempotency
- Customer isolation
- Negative balance correctness
- Immediate Suspend
- Resume after Recharge
- Secret security
- IDOR audit
- Mobile demo
- Video
