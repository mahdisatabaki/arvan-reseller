# PROGRESS — Task Completion Log

این فایل وضعیت واقعیِ اجرای `BACKLOG.md` را نگه می‌دارد. برخلاف BACKLOG (که برنامه‌ی از‌پیش‌تعیین‌شده است)، این فایل **تاریخچه‌ی واقعی** است: چه‌کاری، کِی، با چه تصمیمی انجام شد.

**قانون نگه‌داری:** طبق CLAUDE.md §Work Protocol بند ۹ — بعد از هر تسکی که تمام می‌شود، یک ردیف به جدول زیر و یک ورودی به Changelog اضافه می‌شود؛ اگر تسک باعث شود سند دیگری (API.md، DATA-MODEL.md، …) نادرست شود، همان سند هم در همان لحظه اصلاح می‌شود.

---

## الان کجاییم

**بلوک ۰ و بلوک ۱ کامل تمام شدند (DoD هر دو با تست واقعی تأیید شد). بلوک ۲ — Reseller Setup + Secrets — بعدی است، با T-2.1 (`SecretStore`).**

```
بلوک ۰  ██████████ 100%   تمام (۹/۹ تسک)
بلوک ۱  ██████████ 100%   تمام (۴/۴ تسک) — DoD تأیید شد: هر دو driver قابل‌تعویض‌اند
بلوک ۲  ░░░░░░░░░░   0%   بعدی: T-2.1 SecretStore
بلوک ۳  ░░░░░░░░░░   0%
بلوک ۴  ░░░░░░░░░░   0%
بلوک ۵  ░░░░░░░░░░   0%
بلوک ۶  ░░░░░░░░░░   0%
بلوک ۷  ░░░░░░░░░░   0%
بلوک ۸  ░░░░░░░░░░   0%
بلوک ۹  ░░░░░░░░░░   0%
بلوک ۱۰ ░░░░░░░░░░   0%
بلوک ۱۱ ░░░░░░░░░░   0%
```

**قدم بعدی: T-2.1 — `SecretStore`** (رمزنگاری AES-256-GCM برای کلید API آروان، طبق SECURITY.md §۴).

---

## جدول وضعیت بلوک‌ها

| بلوک | عنوان | وضعیت | تسک‌های تمام | فایل‌های اصلی |
|---|---|---|---|---|
| ۰ | Foundation | ✅ تمام | 9/9 | `arvan-reseller.php`, `Schema.php`, `Installer.php`, `Capabilities.php`, `Scheduler.php`, `Money.php`, `MarkupRate.php`, `ChargeBreakdown.php`, `ResellerPricing.php`, `src/Ports/*` (۸ فایل) |
| ۱ | CDN API + Mock | ✅ تمام | 4/4 | `src/Arvan/CdnClient.php`, `CdnResource.php`, `OutboundTrafficUsage.php`, `ArvanCdnClient.php`, `MockCdnClient.php`, `CdnProviderException.php`, `src/Ports/HttpClient.php`, `wp/Http/WordPressHttpClient.php` |
| ۲ | Reseller Setup + Secrets | ⏳ شروع‌نشده | 0/4 | — |
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
