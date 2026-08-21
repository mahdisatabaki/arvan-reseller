# PROGRESS — Task Completion Log

این فایل وضعیت واقعیِ اجرای `BACKLOG.md` را نگه می‌دارد. برخلاف BACKLOG (که برنامه‌ی از‌پیش‌تعیین‌شده است)، این فایل **تاریخچه‌ی واقعی** است: چه‌کاری، کِی، با چه تصمیمی انجام شد.

**قانون نگه‌داری:** طبق CLAUDE.md §Work Protocol بند ۹ — بعد از هر تسکی که تمام می‌شود، یک ردیف به جدول زیر و یک ورودی به Changelog اضافه می‌شود؛ اگر تسک باعث شود سند دیگری (API.md، DATA-MODEL.md، …) نادرست شود، همان سند هم در همان لحظه اصلاح می‌شود.

---

## الان کجاییم

**بلوک ۰، ۱ و ۲ کامل تمام شدند. بلوک ۳ — Wallet + Ledger + Payment — بعدی است، با T-3.1 (`LedgerService`).**

```
بلوک ۰  ██████████ 100%   تمام (۹/۹ تسک)
بلوک ۱  ██████████ 100%   تمام (۴/۴ تسک) — DoD تأیید شد: هر دو driver قابل‌تعویض‌اند
بلوک ۲  ██████████ 100%   تمام (۴/۴ تسک) — DoD تأیید شد با کلیک واقعی روی وردپرس محلی
بلوک ۳  ░░░░░░░░░░   0%   بعدی: T-3.1 LedgerService
بلوک ۴  ░░░░░░░░░░   0%
بلوک ۵  ░░░░░░░░░░   0%
بلوک ۶  ░░░░░░░░░░   0%
بلوک ۷  ░░░░░░░░░░   0%
بلوک ۸  ░░░░░░░░░░   0%
بلوک ۹  ░░░░░░░░░░   0%
بلوک ۱۰ ░░░░░░░░░░   0%
بلوک ۱۱ ░░░░░░░░░░   0%
```

**قدم بعدی: T-3.1 — `LedgerService`** (دفتر کل append-only، atomic، طبق CLAUDE.md).

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
| ۳ | Wallet + Ledger + Payment | ⏳ شروع‌نشده | 0/6 | — |
| ۴ | CDN Provisioning + Mapping | ⏳ شروع‌نشده | 0/4 | — |
| ۵ | Metering + Billing | ⏳ شروع‌نشده | 0/4 | — |
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
