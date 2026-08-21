# PROGRESS — Task Completion Log

این فایل وضعیت واقعیِ اجرای `BACKLOG.md` را نگه می‌دارد. برخلاف BACKLOG (که برنامه‌ی از‌پیش‌تعیین‌شده است)، این فایل **تاریخچه‌ی واقعی** است: چه‌کاری، کِی، با چه تصمیمی انجام شد.

**قانون نگه‌داری:** طبق CLAUDE.md §Work Protocol بند ۹ — بعد از هر تسکی که تمام می‌شود، یک ردیف به جدول زیر و یک ورودی به Changelog اضافه می‌شود؛ اگر تسک باعث شود سند دیگری (API.md، DATA-MODEL.md، …) نادرست شود، همان سند هم در همان لحظه اصلاح می‌شود.

---

## الان کجاییم

**بلوک ۰، ۱ و ۲ کامل تمام شدند. بلوک ۳ و ۴ هم‌زمان در حال پیشرفت‌اند (سه مسیر مستقل بدون فایل مشترک).**

```
بلوک ۰  ██████████ 100%   تمام (۹/۹ تسک)
بلوک ۱  ██████████ 100%   تمام (۴/۴ تسک) — DoD تأیید شد: هر دو driver قابل‌تعویض‌اند
بلوک ۲  ██████████ 100%   تمام (۴/۴ تسک) — DoD تأیید شد با کلیک واقعی روی وردپرس محلی
بلوک ۳  ██████████ 100%   تمام (۶/۶ تسک)
بلوک ۴  ██████████ 100%   تمام (۴/۴ تسک)
بلوک ۵  ███████░░░  75%   T-5.1, T-5.2, T-5.3 تمام — بعدی: T-5.4 Demo billing trigger
بلوک ۶  ░░░░░░░░░░   0%
بلوک ۷  ░░░░░░░░░░   0%
بلوک ۸  ░░░░░░░░░░   0%
بلوک ۹  ░░░░░░░░░░   0%
بلوک ۱۰ ░░░░░░░░░░   0%
بلوک ۱۱ ░░░░░░░░░░   0%
```

**قدم بعدی: T-5.4 — Demo billing trigger** («Run Billing Cycle Now»، اولین trigger واقعی برای `MeteringService`+`BillingService`).

**نکته‌ی محیطی:** سایت تست محلی (`arvan-test.test`) الان در وضعیت «ویزارد تمام‌شده» است. برای دموی از صفر، باید پلاگین را deactivate/activate کرد یا آپشن‌های `arvan_reseller_*` را پاک کرد.

### اصلاح محدوده — فارسی/RTL سراسری + متن گام توکن دسترسی

بعد از تکمیل بلوک ۲، یک الزام محصولی جدید رسید: تمام UI کاربر-محور پلاگین باید فارسی/RTL باشد (نه فقط بخشی)، و متن گام ۱ ویزارد باید به‌جای ارجاع به «توکن دمو تیم هکاتون» به رابطه‌ی واقعی محصول (توکن دسترسی واقعی reseller از آروان) اشاره کند — **بدون تغییر مکانیزم واقعی احراز هویت** (همان `password_verify()` روی هش‌های bundle‌شده در `data/access-token-hashes.php`؛ ADR-006 دست‌نخورده ماند).

- **فایل‌های تغییریافته:** `wp/Admin/templates/setup-wizard.php` (تمام متن‌ها فارسی + `dir="rtl"`/`lang="fa"` روی wrapper + CSS داخلی برای راست‌چین‌شدن `form-table`)، `wp/Admin/SetupWizard.php` (پیام‌های خطا/اعتبارسنجی)، `src/Arvan/ApiKeyConnectionTester.php` (۳ پیام تست اتصال — بدون `__()` چون این کلاس در لایه‌ی `src/` است)، `wp/Support/Capabilities.php` (نام نمایشی نقش «مشتری آروان»)، `arvan-reseller.php` (پیام خطای نسخه‌ی PHP)، `wp/Cron/Scheduler.php` (برچسب دو interval سفارشی).
- **اسناد:** `CLAUDE.md` بخش جدید «UI Language & Direction»، `docs/DESIGN.md` §۴ یک جمله‌ی صریح الزام‌آور اضافه شد، `docs/USER-FLOWS.md` «Enter Demo Access Token» به «Enter Access Token (issued to the reseller by ArvanCloud)» اصلاح شد. اسناد صرفاً معماری/امنیتی (`SECURITY.md`, `DECISIONS.md` ADR-006, `DATA-MODEL.md`, `API.md`) که مکانیزم واقعی هش-محور را توصیف می‌کنند عمداً دست‌نخورده ماندند — طبق دستور صریح کاربر، تغییر آن‌ها یعنی تغییر توصیف معماری، نه فقط متن UI.
- **تست:** روی `arvan-test.test` واقعی — گام ۱ و ۲ با کلیک واقعی و توکن دمو معتبر (`arvan_test_123`) بازبینی بصری شدند؛ راست‌چین بودن، ترتیب صحیح نشانگر مراحل، و رندر صحیح پیام‌ها تأیید شد. `php -l` روی هر ۷ فایل PHP تغییریافته سبز.

---

## جدول وضعیت بلوک‌ها

| بلوک | عنوان | وضعیت | تسک‌های تمام | فایل‌های اصلی |
|---|---|---|---|---|
| ۰ | Foundation | ✅ تمام | 9/9 | `arvan-reseller.php`, `Schema.php`, `Installer.php`, `Capabilities.php`, `Scheduler.php`, `Money.php`, `MarkupRate.php`, `ChargeBreakdown.php`, `ResellerPricing.php`, `src/Ports/*` (۸ فایل) |
| ۱ | CDN API + Mock | ✅ تمام | 4/4 | `src/Arvan/CdnClient.php`, `CdnResource.php`, `OutboundTrafficUsage.php`, `ArvanCdnClient.php`, `MockCdnClient.php`, `CdnProviderException.php`, `src/Ports/HttpClient.php`, `wp/Http/WordPressHttpClient.php` |
| ۲ | Reseller Setup + Secrets | ✅ تمام | 4/4 | `wp/Security/WordPressSecretStore.php`, `AccessTokenGate.php`, `data/access-token-hashes.php`, `src/Ports/ApiKeyRepository.php`, `wp/Persistence/WpApiKeyRepository.php`, `src/Arvan/ApiKeyConnectionTester.php`, `wp/Admin/ResellerSettings.php`, `SetupWizard.php`, `templates/setup-wizard.php` |
| ۳ | Wallet + Ledger + Payment | ✅ تمام | 6/6 | `wp/Persistence/WpLedgerRepository.php`, `src/Ports/CustomerRepository.php`, `wp/Persistence/WpCustomerRepository.php`, `wp/Persistence/WpWalletRepository.php`, `wp/Persistence/WpPaymentRepository.php`, `src/Wallet/PaymentService.php`, `wp/Customer/CustomerRegistration.php`, `src/Wallet/ManualAdjustmentService.php`, `wp/Persistence/WpAuditLogger.php` |
| ۴ | CDN Provisioning + Mapping | ✅ تمام | 4/4 | `src/Lifecycle/ServiceStatus.php`, `src/Ports/OrderRepository.php`, `wp/Persistence/WpOrderRepository.php`, `wp/Persistence/WpServiceRepository.php`, `src/Provisioning/ProvisioningService.php`, `src/Provisioning/ResourceSyncService.php`, `src/Provisioning/DeliveryData.php` |
| ۵ | Metering + Billing | 🔶 در حال انجام | 3/4 | `src/Metering/MeteringService.php`, `src/Metering/UsagePeriod.php`, `src/Metering/UsagePricingAdapter.php`, `src/Ports/UsageLogRepository.php`, `wp/Persistence/WpUsageLogRepository.php`, `src/Billing/BillingService.php` |
| ۶ | Limits + Lifecycle | ⏳ شروع‌نشده | 0/5 | — |
| ۷ | Customer Frontend | ⏳ شروع‌نشده | 0/6 | — |
| ۸ | Reseller Admin | ⏳ شروع‌نشده | 0/5 | — |
| ۹ | Settlement + System Status | ⏳ شروع‌نشده | 0/2 | — |
| ۱۰ | Security + Responsive + QA | ⏳ شروع‌نشده | 0/6 | — |
| ۱۱ | Demo + Delivery | ⏳ شروع‌نشده | 0/6 | — |

---

## Changelog

### بلوک ۰ — Foundation

- **T-0.1 تا T-0.6** — اسکلت پلاگین، اتولودر PSR-4 بدون Composer، شمای ۱۰ جدول اختصاصی (بعداً ۱۱ تا با اضافه‌شدن `arvan_orders`)، Installer نسخه‌دار، Capabilityهای اختصاصی + نقش `arvan_customer`، Scheduler با ۵ کرون، لایه‌ی پایه‌ی Money/Pricing.
- **T-0.7** — اصلاح Pricing: حذف کامل `MarginMode`/Commission، `MarkupRate` با سقف سخت ۲۰٪، `ChargeBreakdown = base + markup + total` بدون فیلد مالیات. تست: `Base 100 → Markup 20% → Total 120`؛ تلاش برای ۲۵٪ خطا می‌دهد.
- **T-0.75** — مرز WordPress/Business Logic تأیید شد: `src/` صفر فراخوانی `wp_*` دارد (grep تأیید شد)، `wp/` لایه‌ی آداپتور مجاز است.
- **T-0.8** — ۸ اینترفیس `src/Ports/`: `Clock`, `SecretStore`, `Mailer`, `AuditLogger`, `WalletRepository`, `LedgerRepository`, `ServiceRepository`, `PaymentRepository`. فقط قرارداد؛ بدون پیاده‌سازی.
- **T-0.0 (خارج از این گفتگو)** — محیط توسعه (PHP/MySQL/WordPress) راه‌اندازی و پلاگین روی وردپرس واقعی فعال شد. حین این تست، یک باگ واقعی پیدا شد: `Scheduler::schedule()` هنگام فراخوانی از activation hook، فیلتر `cron_schedules` را هنوز ثبت‌نشده می‌دید (چون `Plugin::boot()` که آن را ثبت می‌کند، به `plugins_loaded` گره خورده و ترتیب اجرا نسبت به فعال‌سازی تضمین‌شده نیست). رفع شد با ثبت دوباره‌ی idempotent همان فیلتر داخل خودِ `schedule()`. کامیت: `584b6bd`.
- **دلیل تکمیل بلوک:** هر ۹ تسک تیک خورده؛ DoD («Plugin فعال، schema موجود، pricing تست‌پذیر و فقط Markup») با تست واقعی روی وردپرس تأیید شد، نه فقط بازبینی کد.

### بلوک ۱ — CDN API + Mock (تمام شد)

- **T-1.1 — API Spike** — چون مستندات زنده‌ی آروان (`docs.arvancloud.ir`) قابل fetch نبود (redirect loop)، قرارداد واقعی از سورس‌کد دو ریپوی گیت‌هاب زیر سازمان ArvanCloud/جامعه استخراج شد: `arvancloud/ar-prometheus-exporter` و `hamidfzm/arvancloud-go`. نتیجه: base URL، هدر Authorization، endpoint ساخت/دریافت/حذف دامنه، و endpoint ترافیک خروجی (`/domains/{domain}/reports/traffics`, بازه‌ای نه cumulative) با اطمینان بالا تأیید شد. مکانیزم واقعی hold/unhold **پیدا نشد** — به‌صورت صریح باز گذاشته شد، نه حدس زده شد.
- **T-1.2 — `CdnClient` interface** — طبق یافته‌های T-1.1 محدود شد: فقط ۴ متد (`createResource`, `getResource`, `getOutboundTrafficUsage`, `deleteResource`)؛ `ping` و `holdResource`/`unholdResource` عمداً حذف شدند. دو DTO نرمال‌شده (`CdnResource`, `OutboundTrafficUsage`) بدون هیچ فیلد خام Arvan. پارامتر `Credential` هم حذف شد — هر نمونه‌ی `CdnClient` از قبل به یک کلید متصل ساخته می‌شود، نه per-call.
  - **اسناد هم‌ترازشده:** `docs/API.md` §۳ (امضای متدها) و §۱۲ (قابلیت‌های `MockCdnClient`) با اینترفیس نهایی یکی شدند.
  - **بدهی مستندسازی شناخته‌شده:** `docs/BACKLOG.md` بلوک ۶ (`T-6.3`) هنوز `holdResource` را در سناریوی Suspend ذکر می‌کند — عمداً دست‌نخورده ماند چون حلش وابسته به همان مکانیزم نامعلوم است؛ وقتی به بلوک ۶ رسیدیم باید بازبینی شود.
- **T-1.3 — `ArvanCdnClient`** — پیاده‌سازی کامل شد، با یک تعدیل معماری آگاهانه نسبت به پلن اولیه: به‌جای `wp_remote_request()` مستقیم (که TECH.md §۴ اولیه پیشنهاد داده بود)، یک پورت جدید `src/Ports/HttpClient.php` ساخته شد و `ArvanCdnClient` **فقط** به همین پورت وابسته است — نه به وردپرس، نه به `curl_*` مستقیم. پیاده‌سازی واقعی روی وردپرس در `wp/Http/WordPressHttpClient.php` است.
  - **abstraction اضافه:** `src/Arvan/CdnProviderException.php` — یک کلاس واحد برای هر ۸ دسته‌ی خطای API.md §۱۰؛ `retryable` از روی `category` مشتق می‌شود (نه پارامتر جدا) تا هیچ call site نتواند با آن اشتباه کند.
  - **bounded retry:** حداکثر ۳ تلاش، فقط روی `getResource`/`getOutboundTrafficUsage` (GET، بی‌اثر جانبی). `createResource`/`deleteResource` هرگز خودکار retry نمی‌شوند — طبق API.md §۹، تکرار یک POST/DELETE غیر-idempotent ریسک ساخت منبع تکراری دارد.
  - **تست:** ۳۰ چک خودکار (اسکریپت یک‌بارمصرف، خارج از ریپو) بدون بوت‌استرپ وردپرس اجرا شد — مسیر موفق، ۴۰۱/۴۰۴/۴۰۹/۴۲۹/۵xx، شکست transport، بازیابی بعد از retry، عدم retry روی POST، و عدم نشت کلید API/بدنه‌ی خام provider در پیام خطا. همه سبز.
  - **یافته‌ی جانبی:** `wp/Support/Autoloader.php` گارد `ABSPATH` دارد، پس حتی برای لود کلاس‌های `src/` هم باید مسیر جایگزین باز کرد (تست از این فایل عبور نکرد، مستقیم `require` کرد). این با «Zero-WordPress grep proof: خارج از P0» در BACKLOG بلوک ۰ سازگار است؛ اگر آن تسک دوباره باز شد، این نکته پیش‌نیازش است.
  - **بدهی مستندسازی شناخته‌شده‌ی جدید:** فیلدهای دقیق JSON پاسخ (`id`, `domain`, `created_at` روی resource؛ نام فیلد مقدار ترافیک در هر bucket) هنوز با کلید واقعی تأیید نشده‌اند — سطح اطمینان MEDIUM، نه HIGH. هرکدام در یک متد نگاشت جدا ایزوله شده تا اصلاح بعدی فقط همان‌جا اثر بگذارد.
- **T-1.4 — `MockCdnClient`** — تمام شد. `src/Arvan/MockCdnClient.php`، بدون هیچ constructor dependency (نه HttpClient، نه کلید، نه وردپرس) — state فقط در یک آرایه‌ی خصوصی در حافظه. resourceId از هش دامنه مشتق می‌شود (قطعی، نه تصادفی). چهار ابزار کمکی تست خارج از قرارداد `CdnClient`: `setOutboundTraffic()`, `forceFailure()`/`clearFailure()`, `seedResource()`. برای هر خطای شبیه‌سازی‌شده همان `CdnProviderException` واقعی پرتاب می‌شود (نه یک کلاس جعلی)، تا لایه‌ی application هیچ‌وقت نتواند بین mock و real تفاوت رفتاری ببیند.
  - **تست:** ۳۳ چک روی خودِ `MockCdnClient` (ساخت، تکراری، lookup، fixture ترافیک، حذف، بازساخت بعد از حذف، `seedResource`، `forceFailure`) + ۱ باگ در خودِ اسکریپت تست پیدا و اصلاح شد (اولویت عملگر `===`/`??` در PHP) — نه در کد پلاگین.
  - **اسناد هم‌ترازشده:** `docs/API.md` §۱۲ («Status: implemented») و توضیح ابزارهای کمکی.

**بستن بلوک ۱ — DoD واقعاً تست شد، نه فقط بازبینی کد.** یک سناریوی واحد (`create → get → traffic → delete → not-found`) بدون هیچ تغییری، یک‌بار روی `MockCdnClient` و یک‌بار روی `ArvanCdnClient` (با `HttpClient` اسکریپت‌شده که یک توالی موفق واقعی را شبیه‌سازی می‌کرد) اجرا شد. هر ۲۰ چک (۱۰ در هر driver) سبز شد — یعنی جای‌گزین‌پذیری دو driver ادعا نیست، اثبات‌شده است.

### بلوک ۲ — Reseller Setup + Secrets (تمام شد)

- **T-2.1 — `SecretStore`** — `wp/Security/WordPressSecretStore.php`: AES-256-GCM با OpenSSL. کلید رمزنگاری از ثابت `ARVAN_ENCRYPTION_KEY` (اگر تعریف شده باشد) وگرنه از salt‌های وردپرس (`AUTH_KEY` + `SECURE_AUTH_KEY`) با SHA-256 مشتق می‌شود؛ اگر هیچ‌کدام موجود نبود exception پرتاب می‌شود — هیچ کلید هاردکدشده‌ی fallback در کد نیست. فرمت ciphertext: `base64(nonce . tag . ciphertext)`، خودکفا (نیازی به ذخیره‌ی جدای IV/tag نیست).
- **T-2.2 — `AccessTokenGate`** — `wp/Security/AccessTokenGate.php` + `data/access-token-hashes.php`. تصمیم معماری: این کلاس عمداً یک Port نیست (فقط یک پیاده‌سازی واقعی دارد، جایگزینی/mock معنی‌داری ندارد)، برخلاف repositoryهای جدول‌محور. اعتبارسنجی یک‌طرفه با `password_verify()` روی هش‌های bundle‌شده (بدون هیچ توکن خام در کد). Rate limit: ۵ تلاش در بازه‌ی ۱۵ دقیقه‌ای با WordPress transient. موفقیت یک آپشن boolean (`arvan_reseller_access_token_verified`) ست می‌کند. تعدیل کاربر: توکن‌های دمو دیگر به‌صورت plaintext کنار هش‌ها در کامنت نوشته نمی‌شوند.
  - **اسناد هم‌ترازشده:** `uninstall.php` — `arvan_reseller_access_token` (که دیگر وجود نداشت) از آرایه‌ی همیشه-پاک‌شونده حذف و `arvan_reseller_access_token_verified` + transient به purge اضافه شد.
- **T-2.3 — `ApiKeyRepository`** — `src/Ports/ApiKeyRepository.php` (پورت) + `wp/Persistence/WpApiKeyRepository.php` (پیاده‌سازی `$wpdb`) + `src/Arvan/ApiKeyConnectionTester.php`. اصل طراحی: repository فقط «persistence خنگ» است، هرگز کلید plaintext نمی‌بیند — فقط ciphertext + fingerprint (`sha256` روی plaintext، برای جلوگیری از تکرار) + last-four رقم. تست اتصال چون `ping()` روی API آروان وجود ندارد (طبق T-1.1) از `getResource()` روی یک دامنه‌ی probe ثابت و بی‌معنی (`arvan-reseller-connection-probe.invalid`) استفاده می‌کند؛ موفقیت یعنی «هر پاسخ واقعی، حتی not-found»، شکست فقط `AUTHENTICATION_FAILED`/`FORBIDDEN` است. ترتیب test-then-persist: هرگز کلیدی که تست اتصالش شکست خورده ذخیره نمی‌شود.
  - **باگ تست پیدا و رفع شد:** فراموش شد `$GLOBALS['wpdb']` قبل از ساخت repository ست شود (چون `Schema::table()` داخلی `global $wpdb;` می‌کند، نه پارامتر گرفته‌شده)؛ باعث warning «read property prefix on null» شد — نه یک باگ کد پلاگین، باگ اسکریپت تست.
- **T-2.4 — Setup Wizard** — `wp/Admin/SetupWizard.php` + `wp/Admin/ResellerSettings.php` + `wp/Admin/templates/setup-wizard.php`. تنظیمات reseller در ۴ آپشن وردپرس گروه‌بندی‌شده (`arvan_reseller_branding`, `arvan_reseller_pricing`, `arvan_reseller_limits`, `arvan_reseller_settings`) ذخیره می‌شوند، نه یک آپشن به‌ازای هر فیلد. ویزارد ۵ مرحله‌ای با الگوی POST-redirect-GET.
  - **تعدیل حین‌کار کاربر:** مرحله‌ی ۵ در طراحی اولیه یک انتخاب‌گر Cards/Compact داشت؛ کاربر صریحاً دستور داد حذف شود چون صفحه‌ی فروش عمومی CDN که این انتخاب رویش اثر می‌گذاشت (T-7.3) هنوز ساخته نشده — نمایش یک گزینه‌ی بی‌اثر گمراه‌کننده است. جایگزین شد با یک خلاصه‌ی فقط-خواندنی از مراحل ۱ تا ۴ و دکمه‌ی Finish. `DESIGN.md` §۸ و `SCREEN-SPECS.md` §۱ هم‌زمان به‌روزرسانی شدند.
  - **سه باگ واقعی وردپرس، فقط با تست زنده روی `arvan-test.test` پیدا شدند** (هیچ‌کدام با تست خارج از وردپرس قابل‌کشف نبودند):
    1. «headers already sent»: `wp_safe_redirect()` داخل callback رندر `add_submenu_page()` صدا زده می‌شد، که بعد از خروجی هدر صفحه اجرا می‌شود. رفع: پردازش POST به یک متد `handleRequest()` منتقل شد که به اکشن `load-{$hook}` هوک شده (قبل از هر خروجی اجرا می‌شود).
    2. `add_submenu_page()` با parent slug اشتباه (`null` به‌جای `''` مستندشده برای صفحه‌ی مخفی) صدا زده شده بود؛ این به‌آرامی `$hook`/`load-{$hook}` را می‌شکست — فرم مرحله‌ی ۱ را دوباره نشان می‌داد بدون خطا و بدون پیشرفت. رفع: `''`.
    3. آدرس ریدایرکت اشتباه: `admin_url('admin.php')` (بدون پارامتر `page` صفحه‌ی سفید خالی است) به‌جای `admin_url('index.php')`. با اسکرین‌شات واقعی صفحه‌ی سفید پیدا شد.
  - **دو رویداد امنیتی حین تست:** کاربر مستقیماً ایمیل/رمز ورود وردپرس را در چت پیشنهاد داد؛ طبق قانون «هرگز رمز/API key/توکن مالی در هیچ فیلدی وارد نکن، حتی با اجازه‌ی صریح کاربر»، از تایپ رمز خودداری شد و از کاربر خواسته شد خودش وارد شود (کاربر پذیرفت و انجام داد). برای تست مسیر موفق واقعیِ اعتبارسنجی API Key (که نیاز به کلید واقعی ArvanCloud دارد و Claude اجازه‌ی واردکردن اعتبارنامه ندارد)، یک `MockCdnClient` موقت و به‌وضوح کامنت‌گذاری‌شده جایگزین `ArvanCdnClient` شد فقط برای تأیید، سپس بلافاصله برگردانده شد — با `grep` تأیید شد هیچ اثری از آن باقی نمانده.
  - **اسناد هم‌ترازشده:** `docs/API.md`، `docs/TECH.md` (اضافه‌شدن `HttpClient` به Ports، اصلاح مسیر واقعی `ArvanCdnClient`/`MockCdnClient` در `src/Arvan/`).

**بستن بلوک ۲ — DoD با کلیک واقعی روی وردپرس محلی تأیید شد** (نه فقط بازبینی کد)، شامل تکمیل کامل ویزارد ۵ مرحله تا ریدایرکت نهایی به Dashboard. تنها بخش تأییدنشده: مسیر موفق واقعی (غیر-Mock) `ArvanCdnClient` داخل ویزارد با یک اعتبارنامه‌ی واقعی ArvanCloud هرگز end-to-end تست نشده — چون Claude اجازه‌ی واردکردن API key واقعی ندارد (به «تصمیم‌های باز» زیر اضافه شد).

---

### بلوک ۳ و ۴ — شروع هم‌زمان (سه مسیر مستقل)

بعد از بلوک ۲، سه زیرتسک بدون فایل مشترک و بدون وابستگی متقابل شناسایی شدند: T-3.1 (`WpLedgerRepository` — پیاده‌سازی پورت موجود)، T-3.2 (`CustomerRepository` جدید + `WpWalletRepository`)، T-4.1 (`ServiceStatus`، دامنه‌ی خالص). هر سه روی Schema.php/پورت‌های بلوک ۰ متکی‌اند، نه روی هم — پس هم‌زمان برده شدند جلو.

- **T-3.1 — `WpLedgerRepository`** — قفل `SELECT ... FOR UPDATE` روی سطر wallet داخل تراکنش SQL؛ idempotency دو لایه (چک اول سریع + fallback بعد از تشخیص نقض unique key در INSERT، برای race واقعی). جزئیات کامل در بخش «پذیرش» بلوک ۳ در `BACKLOG.md`.
- **T-3.2 — `CustomerRepository` + `WpWalletRepository`** — یک ایجنت پس‌زمینه شروع کرد اما میان‌کار قطع شد (فرآیند Claude Code قبل از اتمام بسته شد)؛ از خروجی‌اش فقط `CustomerRepository.php` (پورت) و `WpCustomerRepository.php` باقی ماندند — هر دو بازبینی و تأیید شدند (کیفیت خوب، دقیقاً مطابق بریف). `WpWalletRepository.php` که ناتمام مانده بود مستقیماً تکمیل شد.
- **T-4.1 — `ServiceStatus`** — یک ایجنت پس‌زمینه‌ی دوم هم با همان قطعی از بین رفت، بدون هیچ فایلی از خودش به‌جا بماند؛ مستقیماً از نو نوشته شد.
- **درس گرفته‌شده:** ایجنت‌های پس‌زمینه در صورت بسته‌شدن پردازش میزبان، state خود را کامل از دست می‌دهند — کار نیمه‌کاره‌شان گاهی روی دیسک می‌ماند (T-3.2) و گاهی هیچ (T-4.1). بعد از هر بازیابی از این حالت باید `git status` چک شود قبل از فرض بر تکمیل یا عدم تکمیل هر تسک.
- **تست:** یک fake `$wpdb` واحد (اسکریپت یک‌بارمصرف، خارج از ریپو) هر سه‌ی `WpCustomerRepository`/`WpWalletRepository`/`WpLedgerRepository` را با هم پوشش داد — ۲۴ چک سبز؛ `ServiceStatus` جدا با ۲۶ چک سبز. `php -l` روی هر ۴ فایل سبز.

### T-3.4 — Mock Payment (موازی با تلاش دوم پس‌زمینه برای T-3.3)

`wp/Persistence/WpPaymentRepository.php` (پیاده‌سازی پورت `PaymentRepository`) + `src/Wallet/PaymentService.php` (سرویس دامنه‌ی خالص که `PaymentRepository`+`LedgerRepository` را وصل می‌کند). محافظت دو لایه‌ای در برابر double-credit: گارد status روی خودِ payment (`markSucceeded()` روی پرداخت already-succeeded می‌شود `false` و سرویس متوقف می‌شود) + idempotency_key مجزای ledger (`payment-{id}`) به‌عنوان لایه‌ی دوم مستقل. `findOwnedByCustomer()` قبل از هر عملیات — تلاش برای تأیید پرداخت مشتری دیگر `RuntimeException` می‌دهد. هنوز به هیچ UI/trigger‌ای وصل نشده (T-5.4/T-7.4 که این را صدا می‌زنند هنوز نیستند) — عمداً، طبق «بدون scope اضافه». تست: ۱۴ چک خودکار سبز روی همان الگوی fake `$wpdb`.

### T-3.3 — Customer creation (ایجنت پس‌زمینه، این‌بار موفق)

برخلاف تلاش‌های قبلی برای T-3.2/T-4.1، این ایجنت پس‌زمینه بدون قطعی تا انتها اجرا و گزارش داد. `wp/Customer/CustomerRegistration.php` هوک `user_register` را می‌گیرد، ادمین (`manage_options`) را نادیده می‌گیرد، نقش `arvan_customer` ست می‌کند، و `CustomerRepository::create()` + `WalletRepository::ensureExists()` را صدا می‌زند — بدون گارد تکراری خودش، چون هر دو پورت از قبل idempotent‌اند. سیم‌کشی در `wp/Plugin.php::boot()` خارج از `is_admin()` (ثبت‌نام روی سایت عمومی هم رخ می‌دهد).

**تأیید مستقل (trust but verify):** خروجی ایجنت مستقیماً commit نشد؛ ابتدا فایل خوانده و با بریف مقایسه شد (منطبق)، بعد یک اسکریپت تست کاملاً جدا و مستقل (نه اسکریپت خودِ ایجنت) با fake `CustomerRepository`/`WalletRepository` و stub توابع وردپرس نوشته شد — ۸ چک سبز، شامل رد ادمین، idempotency تماس دوم، و مدیریت صحیح `get_userdata()===false`.

**اصلاح یافته حین طراحی T-6.1:** `WalletRepository::ensureExists()` قبلاً هیچ آستانه‌ای نمی‌گرفت — کیف‌پول جدید با `notify_threshold_rial=NULL`/`resume_threshold_rial=0` از DB default ساخته می‌شد، یعنی تنظیمات واقعی ریسلر (ویزارد، T-2.4) برای هیچ مشتری جدیدی اعمال نمی‌شد. با تأیید کاربر، پورت و `WpWalletRepository`/`CustomerRegistration`/`Plugin.php` همین حالا اصلاح شدند: `ensureExists()` حالا دو پارامتر `Money` اجباری می‌گیرد، `CustomerRegistration` آن‌ها را از `ResellerSettings::getLifecyclePolicy()` می‌خواند. تست: ۳ چک خودکار سبز (seed صحیح، عدم بازنویسی روی کیف‌پول موجود).

**بستن بلوک ۳ — DoD:** Wallet/Ledger قابل reconciliation و duplicate-safe — ledger idempotent (دو لایه)، wallet منفی مجاز و clamp نمی‌شود، پرداخت موفق دقیقاً یک credit، customer isolation در همه‌ی repositoryها با `findOwnedByCustomer`/`findByWpUserId` رعایت شده.

### T-3.5 — Manual receipt/admin adjustment (ایجنت پس‌زمینه)

«رسید دستی» نیاز به کد جدید نداشت — `PaymentService` (T-3.4) از قبل method-agnostic بود. چیز واقعاً جدید: `src/Wallet/ManualAdjustmentService.php` (تعدیل مستقیم کیف‌پول بدون پرداخت، با دلیل الزامی) + `wp/Persistence/WpAuditLogger.php` (اولین پیاده‌سازی پورت `AuditLogger` از T-0.8). نگاشت جالب: چون `arvan_audit_log` ستون `customer_id` اختصاصی ندارد، customer_id همیشه در JSON فیلد `meta` هم نوشته می‌شود، حتی وقتی `entity_type` جای ستون‌های subject را گرفته — تا هیچ‌وقت گم نشود.

**تأیید مستقل:** ۴۰ چک ایجنت پس‌زمینه + بازخوانی مستقیم هر دو فایل و مقایسه با بریف — منطبق، بدون انحراف.

### T-4.2 — ProvisioningService

سه فایل جدید (`OrderRepository` پورت + `WpOrderRepository` + `WpServiceRepository`) و یک سرویس دامنه‌ی خالص (`ProvisioningService`). یک **گسترش پورت** لازم شد: `ServiceRepository` (T-0.8) هیچ متدی برای ذخیره‌ی Resource ID بعد از موفقیت remote call نداشت — چون هیچ پیاده‌سازی WP‌ای از این پورت قبلاً وجود نداشت، اضافه‌کردن `recordProvisioned()` بی‌خطر بود، نه یک breaking change.

ترتیب اجباری در `provision()`: `orders->create()` → `services->createProvisioning()` → `orders->markProvisioning()` → **سپس** `client->createResource()` — دقیقاً طبق قانون CLAUDE.md که یک provisioning ناموفق نباید بدون رکورد محلی، منبع remote یتیم بسازد. `$client`/`api_key_id` از بیرون تزریق می‌شوند چون ساخت `ArvanCdnClient` واقعی نیاز به `SecretStore`+`WordPressHttpClient` دارد (لایه‌ی WP)، نه چیزی که این سرویس دامنه‌ی خالص باید بسازد.

**تست:** ۱۸ چک با `MockCdnClient` واقعی (نه fake) — یک‌بار مسیر موفق کامل، یک‌بار مسیر شکست با `forceFailure()`؛ هر دو ترتیب دقیق فراخوان‌ها (order قبل از service، هر دو قبل از remote call) را تأیید کردند، نه فقط نتیجه‌ی نهایی.

**هنوز وصل نشده به هیچ UI‌ای** — صفحه‌ی فروش CDN که این را صدا می‌زند T-7.3 است (بلوک ۷، هنوز نیست).

### T-4.4 — Resource sync/retry (بستن بلوک ۴ تا T-4.3)

`src/Provisioning/ResourceSyncService.php` + یک متد `find()` جدید (بدون customer scoping، برای context ادمین) روی `ServiceRepository`. منطق کلیدی: قبل از هر retry، اول `getResource()` چک می‌شود — اگر remote از قبل منبع را دارد (سناریوی «create قبلی احتمالاً موفق شد ولی پاسخش گم شد»)، آن resource adopt می‌شود به‌جای create دوباره، و mismatch با AuditLogger ثبت می‌شود.

**باگ واقعی پیدا و رفع شد حین تست:** پیاده‌سازی اول فقط `createResource()` را try/catch می‌کرد. `getResource()` هم می‌تواند برای هر دلیلی جز «پیدا نشد» (rate limit مثلاً) پرتاب کند — طبق داک‌بلاک خودِ `CdnClient::getResource()`، فقط not-found به‌صورت `null` مدل‌سازی شده، نه همه‌ی خطاها. بدون این رفع، یک rate-limit موقت حین reconcile کل عملیات retry را با یک exception دستکاری‌نشده می‌ترکاند. رفع شد: اگر خودِ چک reconcile شکست بخورد، state محلی دست‌نخورده می‌ماند و audit جداگانه‌ای (`service.reconcile_check_failed`) ثبت می‌شود، به‌جای create کورکورانه یا crash.

**تست:** ۲۲ چک خودکار — retry موفق (not-found → create)، reconcile (mismatch → adopt)، شکست create بعد از reconcile موفق، شکست خودِ reconcile check، رد retry روی سرویس غیر-failed یا ناموجود.

### T-5.1 — MeteringService (ایجنت پس‌زمینه، اولین تسک بلوک ۵)

`src/Metering/MeteringService.php` + `src/Metering/UsagePeriod.php`. فقط fetch/normalize مصرف — صریحاً هیچ نوشتنی در DB یا `LedgerRepository` ندارد و عمداً `ServiceRepository::markMeteredThrough()` را صدا نمی‌زند (طبق داک‌بلاک آن متد، watermark فقط بعد از billed شدن باید جلو برود — جلوبردنش زودتر یعنی اگر T-5.3 بعداً شکست بخورد، آن بازه‌ی مصرف برای همیشه گم می‌شود). اولویت نقطه‌ی شروع بازه: `metered_through` → `provisioned_at` → `created_at`.

**تأیید مستقل:** ۱۲ چک ایجنت + ۴ چک بازبینی مستقل جدا (fallback اولویت با سه ترکیب مختلف، pass-through مقادیر) — بدون انحراف از بریف.

### T-4.3 — Delivery data (ایجنت پس‌زمینه، بستن بلوک ۴)

`src/Provisioning/DeliveryData.php` — شکل customer-facing («چی گرفتم») از یک سطر `arvan_services`، جدا از آرایه‌ی نتیجه‌ی داخلی `ProvisioningService`/`ResourceSyncService` (که `order_id`/`ok` دارند، customer-facing نیستند). فیلد `configuration` همیشه `null` — چون هیچ شکل تأییدشده‌ای برای «config/instructions برگشتی از API» در پروژه وجود ندارد (باز از T-1.1)، حدس زده نشد. ۱۱ چک خودکار سبز، بازبینی مستقل شد.

**بستن بلوک ۴ — DoD:** ✅ Order → CDN Resource → mapping کامل — با `MockCdnClient` واقعی روی هر دو مسیر موفق (T-4.2) و retry/reconcile (T-4.4) تأیید شد.

### T-3.6 — Financial unit tests (ایجنت پس‌زمینه، بستن بلوک ۳)

فقط تست — هیچ فایل `src/`/`wp/` تغییر نکرد. ۳۳ چک خودکار سبز، **هیچ باگ واقعی پیدا نشد**. برجسته‌ترین بخش: آزمون ۱۰۰۰ عملیات پیاپی واقعی (نه شبیه‌سازی‌شده، هر کدام idempotency_key منحصربه‌فرد) روی `WpLedgerRepository::append()` — موجودی محاسبه‌شده‌ی مستقل، موجودی `WalletRepository`، و `balance_after_rial` آخرین سطر ledger هر سه دقیقاً ۲۸۵۳۷ شدند؛ به‌علاوه تست isolation با interleave واقعی بین دو مشتری (نه پشت‌سرهم).

**بستن بلوک ۳ — DoD:** ✅ Wallet/Ledger قابل reconciliation و duplicate-safe.

### T-5.2 + T-5.3 — Billing idempotency + Pricing/Debit (یک `BillingService` واحد)

قبل از شروع، یک gap واقعی پیدا شد: تبدیل مصرف خام (بایت) به هزینه‌ی ریالی طبق BILLING.md §۶ نیاز به «قیمت واحد ترافیک» پیکربندی‌شده دارد، ولی هیچ‌جای پروژه این را نمی‌گرفت. با تأیید کاربر، یک فیلد «قیمت هر گیگابایت ترافیک (تومان)» به قدم ۴ Setup Wizard اضافه شد — `ResellerSettings::setPricing()` جایگزین `setMarkupRate()` شد (چون هر دو فیلد در یک آپشن هستند و `update_option` مقدار قبلی را کامل جایگزین می‌کند، نه merge). **زنده روی `arvan-test.test` تست شد:** submit واقعی با ۱۵۰۰ تومان، مقدار در دیتابیس به‌درستی ۱۵۰۰۰ ریال ذخیره شد، markup_bps کنارش دست‌نخورده ماند، خلاصه‌ی قدم ۵ هم درست نمایش داد.

سپس: `src/Metering/UsagePricingAdapter.php` (bytes→GB→Rial، رد صریح واحد غیر از `byte`) + `src/Ports/UsageLogRepository.php`/`WpUsageLogRepository.php` (پورت جدید، الگوی «برگردان سطر موجود») + `src/Billing/BillingService.php`.

**مهم‌ترین تصمیم طراحی:** کلید idempotency برای debit فقط از `service_id + period_start` ساخته می‌شود، نه `period_end`. چون `period_end` = `Clock::now()` لحظه‌ی هر فراخوانی است، دو اجرای هم‌زمان (دقیقاً همان race که T-5.2 باید جلویش را بگیرد) `period_end` متفاوت می‌گیرند — کلیدسازی روی آن idempotency را در دقیقاً همان سناریو می‌شکست. این مطابق unique key خودِ جدول `arvan_usage_log` است: `(service_id, period_start)` تنها، نه period_end.

**تست:** ۱۷ چک خودکار، شامل یک سناریوی اختصاصی برای همین race (همان period_start، period_end/مقدار مصرف متفاوت — شبیه‌سازی دو اجرای هم‌زمان قبل از پیشرفت watermark) که تأیید کرد فقط یک debit اتفاق می‌افتد، نه صرفاً استدلال نظری.

## تصمیم‌های باز (باید در بلوک‌های بعدی حل شوند)

| موضوع | کجا اثر می‌گذارد | باز از |
|---|---|---|
| مکانیزم واقعی hold/unhold روی API آروان پیدا نشد | T-6.3/T-6.4 (Suspend/Resume) | T-1.1 |
| مقادیر واقعی enum فیلد `status` روی Domain resource | نگاشت به `ServiceStatus` محلی (بلوک ۴) | T-1.1 |
| واحد دقیق ترافیک خروجی («byte» قوی حدس زده شده، تأیید ۱۰۰٪ نشده) | `UsagePricingAdapter` (بلوک ۵) | T-1.1 |
| فیلدهای دقیق JSON پاسخ (`id`, `domain`, `created_at`، فیلد مقدار bucket ترافیک) هنوز با کلید واقعی تست نشده‌اند | `ArvanCdnClient::mapResource()`/`mapTrafficUsage()` — نگاشت‌ها ایزوله‌اند، اصلاح ارزان است | T-1.3 |
| `wp/Support/Autoloader.php` گارد `ABSPATH` دارد؛ راهی برای لود `src/` بدون وردپرس وجود ندارد | «Zero-WordPress grep/load proof» اگر دوباره باز شود | T-1.3 |
| `docs/BACKLOG.md` بلوک ۶ هنوز به `holdResource` ارجاع می‌دهد | مستندسازی بلوک ۶ | T-1.2 |
| مسیر موفق واقعی (غیر-Mock) `ArvanCdnClient` داخل Setup Wizard هرگز با اعتبارنامه‌ی واقعی ArvanCloud end-to-end تست نشده (Claude اجازه‌ی واردکردن API key واقعی ندارد؛ فقط با `MockCdnClient` موقت تأیید شد) | T-2.4 handleApiKey(); اگر یک اعتبارنامه‌ی تست واقعی در دسترس قرار گرفت باید توسط کاربر تأیید شود | T-2.4 |
| سایت تست محلی (`arvan-test.test`) در وضعیت «ویزارد تمام‌شده» رها شده | نیاز به reset (deactivate/activate یا پاک‌کردن آپشن‌های `arvan_reseller_*`) قبل از دموی از صفر | T-2.4 |
