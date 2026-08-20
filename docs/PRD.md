# PRD — پلاگین ریسلری CDN آروان‌کلاد برای وردپرس

| | |
|---|---|
| **نام محصول** | Arvan Reseller |
| **نسخه سند** | 1.1 — Scope Frozen |
| **تاریخ** | ۱۴۰۵/۰۵/۲۹ — 2026-08-20 |
| **وضعیت** | تأییدشده برای اجرا |
| **بودجه زمانی** | ۳۶ ساعت |
| **محصول منتخب MVP** | CDN |
| **مرجع الزامات** | بریف نهایی چالش + ویدئوی معرفی |

---

## ۱. خلاصه

**Arvan Reseller** یک افزونه مستقل وردپرس است که به یک ریسلر اجازه می‌دهد سرویس **CDN آروان‌کلاد** را از طریق وب‌سایت خودش بفروشد، بدون نیاز به WooCommerce، ACF، Elementor یا هر افزونه جانبی دیگر.

ریسلر افزونه را نصب می‌کند، Access Token مجاز و API Key اکانت آروان خودش را ثبت می‌کند، اطلاعات مجموعه و درصد Markup خود را مشخص می‌کند و یک صفحه فروش CDN در سایتش فعال می‌شود.

مشتری در سایت ریسلر ثبت‌نام می‌کند، کیف پول مجازی خود را شارژ می‌کند، CDN سفارش می‌دهد، افزونه Resource را از طریق API آروان Provision می‌کند، مصرف را دوره‌ای محاسبه می‌کند و هزینه را از کیف پول همان مشتری کسر می‌کند. در صورت عبور از آستانه هشدار داده می‌شود و در صورت صفر یا منفی شدن موجودی، همان سرویس بلافاصله Suspend می‌شود.

### مسئله‌ای که حل می‌شود

آروان فقط حساب مادر ریسلر را می‌شناسد. اگر چند مشتری به ریسلر پول پرداخت کنند، آروان مسئول نگهداری تفکیک مالی آن‌ها نیست.

افزونه باید مشخص کند:

- هر مشتری چقدر پرداخت کرده است.
- موجودی فعلی کیف پول هر مشتری چقدر است.
- هر Resource متعلق به کدام مشتری است.
- هر سرویس چقدر مصرف کرده است.
- چه مبلغی متعلق به آروان و چه مبلغی سود ریسلر است.
- کدام مشتری باید هشدار دریافت کند.
- کدام سرویس باید Suspend، Resume یا Terminate شود.

---

## ۲. تصمیم‌های Freeze شده MVP

این تصمیم‌ها در طول هکاتون بدون ثبت ADR جدید تغییر نمی‌کنند.

1. **فقط CDN پیاده‌سازی می‌شود.**
2. Cloud Server و Object Storage کاملاً خارج از Scope هستند.
3. فقط **یک صفحه فروش محصول CDN** ساخته می‌شود.
4. مدل درآمد فقط **Markup روی قیمت پایه آروان** است؛ Commission Mode وجود ندارد.
5. Markup از `0%` تا حداکثر `20%` قابل تنظیم است.
6. VAT/Tax Engine جزو P0 نیست و در محاسبه MVP وارد نمی‌شود.
7. Payment واقعی ساخته نمی‌شود؛ Mock Payment یا ثبت فیش کافی است.
8. Settlement واقعی با آروان الزام نیست؛ Mock/Reconciliation مجاز است.
9. WordPress Core فقط Runtime/Container، احراز هویت کاربران و بستر UI است. استفاده از Hookها، `$wpdb` و APIهای پایه وردپرس مجاز است؛ Business Logic نباید به `posts/postmeta`، Theme یا Pluginهای دیگر گره بخورد.
10. داده‌های مالی در Custom Tables افزونه ذخیره می‌شوند، نه `posts/postmeta`.
11. پول همیشه با integer و واحد ریال ذخیره می‌شود؛ float ممنوع است.
12. Suspend بلافاصله پس از Debit منجر به موجودی `<= 0` اجرا می‌شود و منتظر Cron جداگانه نمی‌ماند.
13. اگر مشتری کیف پول را مجدداً شارژ کند، سرویس Suspendشده قابلیت Resume دارد.
14. برای Demo، چند Access Token تستی توسط خود تیم تعریف می‌شود؛ فقط hash آن‌ها داخل Plugin قرار می‌گیرد و مقدار ساده فقط برای اجرای دمو نگه داشته می‌شود.

---

## ۲.۱ Access Token تستی هکاتون

برای Demo منتظر Token واقعی آروان نمی‌مانیم.

Tokenهای تستی پیشنهادی:

```text
arvan_test_123
arvan_test_456
```

در سورس Plugin فقط hash آن‌ها قرار می‌گیرد:

```text
password_hash(token, PASSWORD_DEFAULT)
```

و اعتبارسنجی با:

```text
password_verify(input, stored_hash)
```

انجام می‌شود.

قواعد:

- Raw token داخل دیتابیس Plugin ذخیره نمی‌شود.
- Raw token داخل فایل allowlist قرار نمی‌گیرد.
- Hashها به‌عنوان seed اولیه Plugin bundle می‌شوند.
- پس از validation موفق، امکان تنظیم Markup تا سقف ۲۰٪ و شروع فروش فعال می‌شود.
- این Token صرفاً Access Gate دمو است و جای API Key آروان را نمی‌گیرد.

---

## ۳. اهداف و غیرهدف‌ها

### اهداف

| # | هدف | سنجه موفقیت |
|---|---|---|
| G-1 | ریسلر بدون کار فنی پیچیده افزونه را راه‌اندازی کند | Setup Wizard کامل و تست اتصال API |
| G-2 | هر ریال به مشتری صحیح نسبت داده شود | Ledger append-only + Wallet قابل reconciliation |
| G-3 | CDN مشتری از سایت ریسلر Provision شود | Resource ID آروان به Customer/Service متصل شود |
| G-4 | مصرف دوره‌ای صحیح محاسبه شود | Usage Record idempotent + Debit دقیق |
| G-5 | سرویس مشتری بی‌اعتبار بدون اثر روی دیگران Suspend شود | Customer isolation + lifecycle test |
| G-6 | افزونه مستقل از Theme و Plugin جانبی باشد | Zero third-party runtime dependency |
| G-7 | Secrets و عملیات مالی امن باشند | Encryption، authorization، nonce، ownership checks |

### غیرهدف‌ها

- Cloud Server
- Object Storage
- WooCommerce integration
- درگاه پرداخت واقعی
- Tax/VAT engine کامل
- سبد خرید
- Coupon/Discount engine
- PDF Invoice
- چندارزی
- Multi-site
- اپ موبایل
- Terraform
- مدیریت Resourceهای قبلی مشتری
- API واقعی Settlement در صورت نبود endpoint رسمی

---

## ۴. پرسوناها

### ریسلر

آژانس، شرکت میزبانی یا مجموعه‌ای که اکانت آروان دارد و می‌خواهد CDN را با برند و قیمت خودش بفروشد.

نیاز اصلی:

> API Key و درصد سود را تنظیم کنم و سیستم فروش، کیف پول، مصرف و محدودیت‌ها را برایم مدیریت کند.

### مشتری

کاربر سایت ریسلر که CDN می‌خرد و نیاز دارد:

- کیف پولش را شارژ کند.
- CDN سفارش دهد.
- وضعیت Resource را ببیند.
- مصرف و هزینه را ببیند.
- تاریخچه پرداخت و تراکنش را بررسی کند.
- در صورت Suspend شدن دلیل آن را بفهمد و با Recharge سرویس را Resume کند.

### داور هکاتون

باید بتواند سناریوی اصلی را حتی در صورت نبود API Key واقعی از طریق Demo Mode مشاهده کند. Demo Mode هرگز جای مسیر Production را نمی‌گیرد؛ فقط همان Contract را با `MockCdnClient` اجرا می‌کند.

---

## ۵. مدل مالی

### ۵.۱ مدل درآمد

مدل فقط Markup است.

```text
Arvan Base Cost = 100
Reseller Markup = 20%
Customer Charge = 120

Arvan Share = 100
Reseller Profit = 20
```

فرمول:

```text
markup_amount = arvan_base_cost × markup_rate
customer_charge = arvan_base_cost + markup_amount
reseller_profit = markup_amount
arvan_share = arvan_base_cost
```

شرط:

```text
0 <= markup_rate <= 0.20
```

### ۵.۲ واحد پول

- تمام مبالغ در دیتابیس: integer Rial
- نمایش UI: Toman
- استفاده از `float` برای پول ممنوع
- تمام Roundها در یک Money abstraction مرکزی انجام می‌شوند.

### ۵.۳ کیف پول پیش‌پرداخت

```text
Customer Payment
      ↓
Payment = succeeded
      ↓
Ledger CREDIT
      ↓
Wallet increases
```

مصرف:

```text
Usage Period
      ↓
Arvan Base Cost
      ↓
Apply Markup
      ↓
Ledger DEBIT
      ↓
Wallet decreases
```

### ۵.۴ موجودی منفی

مثال:

```text
Wallet before = 5,000
Charge = 8,000
Wallet after = -3,000
```

موجودی به صفر Clamp نمی‌شود. مقدار واقعی منفی در Ledger و Wallet ثبت می‌شود تا reconciliation دقیق بماند.

بلافاصله:

```text
balance <= 0
→ SuspensionEngine
→ hold CDN resource
```

### ۵.۵ Resume

```text
Suspended service
      ↓
Successful recharge
      ↓
balance > resume_threshold
      ↓
unhold resource
      ↓
Service = active
```

`resume_threshold` در MVP برابر صفر است، مگر Reseller مقدار دیگری تنظیم کند.

---

## ۶. جریان اصلی محصول

### ریسلر

```text
Install Plugin
→ Access Token Validation
→ Add Arvan API Key
→ Test Connection
→ Business Profile
→ Markup
→ Wallet/Lifecycle Policy
→ Select CDN sales layout
→ Ready to Sell
```

### مشتری

```text
Register/Login
→ View CDN page
→ Recharge Wallet
→ Enter domain / CDN configuration
→ Submit Order
→ Provision through Arvan API
→ Receive Resource ID/status
→ View Service
→ Usage Billing
→ Low Balance Warning
→ Suspend if balance <= 0
→ Recharge
→ Resume
```

---

## ۷. دامنه محصول

### تنها محصول MVP: CDN

Route اصلی:

```text
/arvan/cdn
```

چرخه کامل:

```text
Display & Pricing
→ Order
→ Provision
→ Resource Mapping
→ Fetch CDN Outbound Traffic
→ Calculate Base Cost
→ Apply Markup
→ Wallet Debit
→ Warning
→ Suspend
→ Recharge
→ Resume
→ Terminate when policy requires
```

### پارامتر مصرف منتخب MVP

برای نسخه هکاتون فقط **Outbound Traffic CDN** پیاده‌سازی می‌شود.

نیازی به محاسبه هم‌زمان مواردی مانند:

- ترافیک داخلی/خارجی
- Pop-site breakdown
- Request count
- Cache hit/miss
- چند pricing dimension

نیست.

مدل:

```text
outbound_traffic_delta
×
configured Arvan unit price
=
base_cost

base_cost
+
reseller markup
=
customer_charge
```

اگر API مقدار cumulative برگرداند، Plugin delta بازه را از آخرین sample محاسبه می‌کند. اگر API bucket زمانی بدهد، فقط bucketهای بازه موردنظر جمع می‌شوند.

### خارج از Scope

```text
/arvan/cloud-server
/arvan/object-storage
```

هیچ UI، سفارش، API integration یا placeholder برای این دو محصول ساخته نمی‌شود.

---

## ۸. الزامات کارکردی

### گروه A — نصب، Access Token و API Key

| # | الزام |
|---|---|
| A1 | دریافت API Key ریسلر برای اکانت آروان |
| A2 | دریافت Access Token و تطبیق با allowlist هش‌شده |
| A3 | فروش فقط پس از Access Token معتبر فعال شود |
| A4 | ذخیره امن API Key در دیتابیس به‌صورت encrypted-at-rest |
| A5 | پشتیبانی از چند API Key با label، usage و default key |
| A6 | دکمه Test Connection برای هر API Key |
| A7 | ساخت و migration نسخه‌دار Custom Tables هنگام activation |

### گروه B — پنل ریسلر

| # | الزام |
|---|---|
| B1 | نام مجموعه، لوگو، وب‌سایت، ایمیل، تلفن و درباره ما |
| B2 | Dashboard خلاصه مشتری، سرویس، مصرف، Wallet و هشدار |
| B3 | تنظیم Markup بین ۰ تا ۲۰٪ |
| B4 | تعیین Low Balance Threshold |
| B5 | تعیین policy صفر موجودی: Suspend و grace period برای Terminate |
| B6 | مدیریت API Keyهای متعدد |
| B7 | کنترل پرداخت‌ها با pending/succeeded/failed |
| B8 | مشاهده مشتری، Wallet، سرویس، مصرف و تاریخچه مالی |
| B9 | انتخاب یکی از حداکثر دو layout ساده برای صفحه CDN |

### گروه C — Customer Frontend

| # | الزام |
|---|---|
| C1 | صفحه فروش CDN |
| C2 | نمایش ویژگی‌ها، configuration و pricing CDN |
| C3 | ثبت‌نام و ورود مشتری با UI خود افزونه |
| C4 | شارژ Wallet با Mock Payment یا ثبت فیش |
| C5 | ثبت Order و نمایش وضعیت Provisioning |
| C6 | Dashboard مشتری |
| C7 | نمایش سرویس‌های همان مشتری |
| C8 | تاریخچه تراکنش، پرداخت و Usage |
| C9 | نمایش Low Balance/Suspended/Failed states |
| C10 | Responsive در موبایل و لپ‌تاپ |

### گروه D — CDN Provisioning

| # | الزام |
|---|---|
| D1 | ساخت Order قبل از API call |
| D2 | ایجاد CDN Resource از طریق API آروان بلافاصله پس از سفارش |
| D3 | دریافت Resource ID/identifier و ذخیره آن |
| D4 | Mapping Resource به `customer_id`, `service_id`, `api_key_id` |
| D5 | نمایش اطلاعات تحویل Resource به مشتری |
| D6 | دریافت وضعیت Resource |
| D7 | Hold/Suspend Resource |
| D8 | Unhold/Resume Resource |
| D9 | Delete/Terminate Resource |
| D10 | Mock driver با contract یکسان برای Demo/Test |

### گروه E — سیستم مالی

| # | الزام |
|---|---|
| E1 | Custom Tables برای Wallet، Ledger، Payment، Usage و Service |
| E2 | عدم استفاده از `posts/postmeta` برای داده مالی |
| E3 | Wallet مجازی مستقل برای هر مشتری |
| E4 | Metering دوره‌ای حداقل هر یک ساعت برای یک metric منتخب: CDN Outbound Traffic |
| E5 | محاسبه `customer_charge = base + markup` |
| E6 | Ledger append-only |
| E7 | Debit اتمیک Wallet + Ledger |
| E8 | Idempotency برای جلوگیری از Billing تکراری |
| E9 | Mock Payment |
| E10 | Settlement/Reconciliation دوره‌ای |
| E11 | Customer-level financial isolation |

### گروه F — مصرف و lifecycle

| # | الزام |
|---|---|
| F1 | Email/notification هنگام عبور از Low Balance Threshold |
| F2 | Deduplication نوتیفیکیشن |
| F3 | Suspend بلافاصله پس از Billing منجر به balance <= 0 |
| F4 | Suspend فقط روی Resourceهای همان مشتری |
| F5 | ذخیره موجودی منفی واقعی |
| F6 | Resume پس از Recharge موفق |
| F7 | Terminate پس از grace period تعریف‌شده |
| F8 | Retry و audit برای lifecycle API failure |
| F9 | گزارش Usage و Ledger برای مشتری |

### گروه G — استقلال و امنیت

| # | الزام |
|---|---|
| G1 | بدون WooCommerce، ACF، Elementor یا Plugin جانبی |
| G2 | UI مستقل از Theme با CSS namespace و templateهای افزونه |
| G3 | WordPress Core بستر مجاز اجراست؛ Domain logic تا حد عملی از WordPress APIs جدا نگه داشته شود |
| G4 | Nonce روی تمام عملیات تغییردهنده |
| G5 | Capability check برای Admin |
| G6 | Ownership check برای Customer |
| G7 | جلوگیری از IDOR |
| G8 | `$wpdb->prepare()` برای queryهای دارای ورودی |
| G9 | Validation/Sanitization ورودی و escaping خروجی |
| G10 | AES-256-GCM یا معادل امن برای API Secret |
| G11 | Access Token با `password_verify()` و hashهای تولیدشده توسط `PASSWORD_DEFAULT` |
| G12 | Secretها در HTML، JS و Logs نمایش داده نشوند |
| G13 | TLS verification و timeout برای API calls |

### گروه H — خروجی نهایی

| # | الزام |
|---|---|
| H1 | ویدئو حداقل ۵ دقیقه |
| H2 | توضیح توسط شرکت‌کننده |
| H3 | نمایش Desktop |
| H4 | نمایش Mobile |
| H5 | سناریوی کامل Install → Sell → Provision → Bill → Suspend/Resume |
| H6 | لینک GitHub |

---

## ۹. مدل داده

Custom Tables:

| جدول | نقش |
|---|---|
| `arvan_customers` | پروفایل مالی مشتری و mapping به WordPress user |
| `arvan_wallets` | موجودی فعلی و threshold |
| `arvan_ledger` | دفتر کل append-only |
| `arvan_payments` | پرداخت‌ها و status |
| `arvan_orders` | سفارش‌ها و provisioning state |
| `arvan_services` | CDN resources و mapping |
| `arvan_usage_log` | Usage period + cost breakdown |
| `arvan_api_keys` | API Keys رمزنگاری‌شده |
| `arvan_settlements` | settlement/reconciliation periods |
| `arvan_notifications` | dedupe و notification history |
| `arvan_audit_log` | عملیات حساس |

### روابط اصلی

```text
wp_users
  ↓
arvan_customers
  ↓
arvan_wallets
  ↓
arvan_orders
  ↓
arvan_services
  ↓
arvan_usage_log

wallet
  ↓
arvan_ledger
```

### Constraintهای حیاتی

- `arvan_ledger.idempotency_key` = UNIQUE
- Usage period برای یک service فقط یک بار bill شود.
- هر service دقیقاً یک `customer_id` دارد.
- هر service دقیقاً credential سازنده خود را نگه می‌دارد.
- عملیات lifecycle همیشه از همان credential مربوط به service استفاده می‌کند.

---

## ۱۰. معماری

```text
arvan-reseller/
├─ arvan-reseller.php
├─ uninstall.php
│
├─ src/
│  ├─ Domain/
│  ├─ Pricing/
│  ├─ Ledger/
│  ├─ Metering/
│  ├─ Lifecycle/
│  ├─ Arvan/
│  └─ Ports/
│
├─ wp/
│  ├─ Plugin.php
│  ├─ Installation/
│  ├─ Persistence/
│  ├─ Arvan/
│  │  ├─ ArvanCdnClient.php
│  │  └─ MockCdnClient.php
│  ├─ Security/
│  ├─ Cron/
│  ├─ Admin/
│  ├─ Frontend/
│  └─ Rest/
│
├─ assets/
├─ templates/
├─ languages/
├─ docs/
└─ tests/
```

### مرز WordPress و Business Logic

```text
WordPress Runtime
├─ Hooks
├─ Auth / wp_users
├─ $wpdb adapters
├─ REST/Admin/UI
│
└─ Application Adapters
      ↓
Pure Business Logic
├─ Pricing
├─ Wallet
├─ Ledger
├─ Metering
├─ Lifecycle
└─ Settlement
```

قانون: Domain/Application code نباید برای تصمیم مالی یا lifecycle به `WP_Post`، `postmeta`، Theme state یا API یک Plugin دیگر وابسته باشد.

---

### Client Contract

`CdnClient` حداقل قابلیت‌های زیر را abstraction می‌کند:

```text
ping
createResource
getResource
getOutboundTrafficUsage
holdResource
unholdResource
deleteResource
```

برای MVP فقط یک Metric مالی پشتیبانی می‌شود:

```text
CDN Outbound Traffic
```

**نکته P0:** endpoint و schema واقعی این metric باید در اولین API Spike تأیید شود. تا قبل از مشاهده response واقعی، field name یا واحد مصرف فرض نمی‌شود.

`UsagePricingAdapter` خروجی API را به این ساختار Domain تبدیل می‌کند:

```text
usage_value
usage_unit
unit_price_rial
base_cost_rial
period_start
period_end
```

سپس Billing Engine فقط Base Cost را دریافت و Markup را اعمال می‌کند.

---

## ۱۱. Background Jobs

### 11.1 Metering Cron — Hourly

```text
Get active services
→ Fetch CDN outbound traffic
→ Calculate base cost from outbound traffic only
→ Apply markup
→ Write usage record
→ Atomic ledger debit + wallet update
→ Check threshold
→ Send deduplicated warning
→ if balance <= 0:
      Suspend immediately
```

### 11.2 Settlement Cron — Daily

```text
Aggregate:
base cost
markup
customer charges
→ create settlement/reconciliation period
→ mock settlement if real API unavailable
```

### 11.3 Resource Sync — Every 6 hours

- sync Resource states
- retry failed lifecycle operations
- identify inconsistent mappings

### WP-Cron rule

Metering بر اساس `metered_through` و زمان واقعی سپری‌شده عمل می‌کند؛ فرض نمی‌کند که هر execution دقیقاً یک ساعت بعد از قبلی اجرا شده است.

برای Demo:

```text
Run Billing Cycle Now
```

همان Application Service واقعی را trigger می‌کند؛ منطق جداگانه Fake برای Billing ساخته نمی‌شود.

---

## ۱۲. Security Model

### Access Token

- برای Demo، Tokenهای ساده توسط تیم تعریف می‌شوند.
- نمونه: `arvan_test_123`.
- فایل allowlist داخل Plugin فقط شامل hash است.
- verification با `password_verify()`.
- hash generation با `PASSWORD_DEFAULT` انجام می‌شود؛ در PHP معمولاً bcrypt/الگوریتم امن پیش‌فرض استفاده می‌شود.
- Raw Token در دیتابیس ذخیره نمی‌شود.
- failed attemptها rate-limited می‌شوند.

### API Key

- encrypted-at-rest
- فقط آخرین ۴ کاراکتر در UI
- هیچ Secret در Log
- Secret در JS یا hidden field بازچاپ نمی‌شود.
- هر Service `api_key_id` سازنده خودش را نگه می‌دارد.

### Authorization

Admin:

```text
current_user_can('arvan_manage')
```

Customer:

```text
authenticated user
+
resource.customer_id == current customer id
```

هرگز `customer_id` دریافتی از Client مبنای authorization قرار نمی‌گیرد.

### SQL / XSS / CSRF

- `$wpdb->prepare()`
- sanitize/validate on input
- escape at output
- nonce on state-changing requests
- REST `permission_callback`
- no unsafe unserialize/eval

---

## ۱۳. UI Scope

### Reseller Admin

یک منوی اصلی با صفحات/تب‌های محدود:

1. Dashboard
2. Customers
3. Services
4. Finance
   - Payments
   - Ledger
   - Settlements
5. Settings
   - Business
   - API Keys
   - Pricing
   - Lifecycle

هدف: قابلیت کامل، نه ۱۲ صفحه مستقل.

### Customer

1. Login/Register
2. CDN Product
3. Wallet/Recharge
4. Account Dashboard
5. Service Detail

Finance/Usage history داخل Dashboard یا Service Detail با tab نمایش داده می‌شود.

### Sales Layout

حداکثر دو variant ساده روی همان صفحه CDN:

- Cards
- Compact

این feature نباید داده یا business logic جداگانه داشته باشد.

---

## ۱۴. State Machines

### Order

```text
draft
→ pending
→ provisioning
→ completed

provisioning
→ failed
```

### Service

```text
provisioning
→ active
→ suspended
→ active
→ terminated
```

Failure stateها:

```text
provisioning_failed
suspend_failed
resume_failed
terminate_failed
```

### Payment

```text
pending
→ succeeded

pending
→ failed
```

---

## ۱۵. تصمیمات معماری

### ADR-001 — فقط CDN

فقط CDN end-to-end و فقط صفحه CDN ساخته می‌شود. Cloud Server و Object Storage Out of Scope هستند.

### ADR-002 — Markup فقط روی Base

هیچ Commission Mode وجود ندارد.

```text
Base 100 + Markup 20 = Customer 120
```

### ADR-003 — VAT خارج از P0

Challenge نیاز به موتور مالی و سهم ریسلر دارد؛ VAT engine برای MVP ضروری نیست و زمان Core Loop را مصرف نمی‌کند.

### ADR-004 — WordPress فقط Runtime/Container است

استفاده از قابلیت‌های پایه WordPress مجاز و مورد انتظار است:

- Hooks
- `$wpdb`
- `wp_users`
- Login/Register
- REST/AJAX infrastructure
- Admin/UI runtime

اما Business Logic باید از ساختارهای پیش‌فرض محتوایی WordPress ایزوله باشد:

- Product catalog در `posts/postmeta` ذخیره نمی‌شود.
- Wallet/Ledger/Usage/Service در Custom Tables هستند.
- منطق Pricing/Billing/Lifecycle در Domain/Application layer قرار دارد.
- Theme و Plugin جانبی در اجرای محصول نقشی ندارند.

بنابراین Zero Dependency در این پروژه یعنی **Zero third-party plugin/theme dependency + isolated business logic**، نه حذف استفاده از APIهای پایه WordPress.

### ADR-005 — Access Token دمو توسط تیم تعریف می‌شود

طبق clarification مسئول هکاتون، برای Demo خود تیم چند Token ساده تعریف می‌کند؛ برای مثال:

```text
arvan_test_123
```

Plugin فقط hash این Tokenها را bundle می‌کند و validation با استاندارد امن PHP انجام می‌شود. فرمت خاصی از سمت آروان الزام نشده است.

### ADR-006 — Suspend inline

پس از Debit اگر balance `<= 0` شد، lifecycle همان Billing Cycle اجرا می‌شود و به Cron دیگری موکول نمی‌شود.

### ADR-007 — Negative balance حفظ می‌شود

برای auditability و reconciliation مقدار واقعی منفی ثبت می‌شود.

### ADR-008 — Resume پس از Recharge

Recharge موفق می‌تواند Service suspendشده به علت wallet را دوباره Active کند.

### ADR-009 — Demo Mode پشت همان Port

`ArvanCdnClient` و `MockCdnClient` یک contract دارند. Business logic نباید بداند کدام driver فعال است.

---

## ۱۶. Definition of Done

سناریوی اصلی باید کار کند:

1. Plugin روی WordPress تمیز نصب و activate شود.
2. Token تستی مثل `arvan_test_123` با hash bundleشده معتبر شود و Token اشتباه رد شود.
3. حداقل دو API Key قابل ثبت، تست، mask و انتخاب default باشند.
4. Reseller اطلاعات کسب‌وکار و Markup را تنظیم کند.
5. Markup `25%` رد شود و `20%` قبول شود.
6. فقط صفحه CDN در Frontend موجود باشد.
7. مشتری ثبت‌نام کند و Wallet صفر ایجاد شود.
8. Mock recharge موفق دقیقاً یک Ledger CREDIT بسازد.
9. مشتری CDN سفارش دهد.
10. CDN Resource توسط Real API یا Demo Driver Provision شود.
11. Resource identifier به Customer و Service متصل شود.
12. Billing Cycle فقط CDN Outbound Traffic را از API/Mock بخواند و Base/Markup/Total را ثبت کند.
13. اجرای دوباره همان period هیچ Debit جدید نسازد.
14. Wallet بتواند وارد مقدار منفی واقعی شود.
15. عبور از threshold فقط یک notification ایجاد کند.
16. Wallet `<= 0` همان لحظه Service همان مشتری را Suspend کند.
17. Customer دوم هیچ تغییری نکند.
18. Recharge مشتری suspended باعث Resume موفق شود.
19. Customer A نتواند Service/Wallet/Transactions مشتری B را ببیند.
20. Desktop و Mobile responsive باشند.
21. ویدئوی نهایی سناریوی Install تا Suspend/Resume را نشان دهد.

---

## ۱۷. نگاشت به معیارهای داوری

| معیار | امتیاز | تمرکز |
|---|---:|---|
| Financial Architecture | 45 | Wallet, Ledger, Metering, Markup, Mock Payment, Settlement |
| Usage & Limits | 30 | Isolation, Warning, Suspend, Terminate, Reporting |
| Independence & Data Security | 25 | Custom Tables, Secrets, Admin Setup, no third-party dependency |
| Sales & Provisioning | 20 | CDN page, order, provisioning, mapping |
| UI/UX | 70 | Setup, customer flow, admin flow, responsive, states |
| Security | 70 | Authorization, IDOR, CSRF, XSS, SQLi, secrets, API |
| Presentation | 40 | coherent end-to-end demo |
| **Total** | **300** | |

---

## ۱۸. ریسک‌ها

| ریسک | کاهش |
|---|---|
| schema ترافیک خروجی CDN نامشخص | API Spike فقط برای Outbound Traffic قبل از Metering |
| API Key واقعی موجود نباشد | MockCdnClient از ابتدا |
| WP-Cron دیر اجرا شود | `metered_through` catch-up + manual billing trigger |
| Billing duplicate شود | idempotency + unique index |
| API lifecycle شکست بخورد | failure state + audit + retry |
| Scope افزایش یابد | فقط CDN؛ Cloud Server/Object Storage ممنوع |
| UI زیاد شود | ۵ صفحه customer + admin consolidated |
| زمان تمام شود | Core plan حداکثر ~31h و حداقل ~5h buffer |

---

## ۱۹. مراجع

- ArvanCloud API Documentation
- ArvanCloud Pricing
- ArvanCloud Service Termination Policy
- Sorkhab Design System
- `BACKLOG.md`
