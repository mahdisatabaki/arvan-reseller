# PROGRESS — Task Completion Log

این فایل وضعیت واقعیِ اجرای `BACKLOG.md` را نگه می‌دارد. برخلاف BACKLOG (که برنامه‌ی از‌پیش‌تعیین‌شده است)، این فایل **تاریخچه‌ی واقعی** است: چه‌کاری، کِی، با چه تصمیمی انجام شد.

**قانون نگه‌داری:** طبق CLAUDE.md §Work Protocol بند ۹ — بعد از هر تسکی که تمام می‌شود، یک ردیف به جدول زیر و یک ورودی به Changelog اضافه می‌شود؛ اگر تسک باعث شود سند دیگری (API.md، DATA-MODEL.md، …) نادرست شود، همان سند هم در همان لحظه اصلاح می‌شود.

---

## الان کجاییم

**بلوک ۰ تمام شده. بلوک ۱ در حال اجراست — T-1.1 و T-1.2 تمام، T-1.3 بعدی است.**

```
بلوک ۰  ██████████ 100%   تمام (۹/۹ تسک)
بلوک ۱  █████░░░░░  50%   T-1.1 ✓  T-1.2 ✓  T-1.3 …  T-1.4 …
بلوک ۲  ░░░░░░░░░░   0%
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

**قدم بعدی: T-1.3 — `ArvanCdnClient`** (پیاده‌سازی واقعی `CdnClient` روی `napi.arvancloud.ir`، طبق یافته‌های اسپایک T-1.1).

---

## جدول وضعیت بلوک‌ها

| بلوک | عنوان | وضعیت | تسک‌های تمام | فایل‌های اصلی |
|---|---|---|---|---|
| ۰ | Foundation | ✅ تمام | 9/9 | `arvan-reseller.php`, `Schema.php`, `Installer.php`, `Capabilities.php`, `Scheduler.php`, `Money.php`, `MarkupRate.php`, `ChargeBreakdown.php`, `ResellerPricing.php`, `src/Ports/*` (۸ فایل) |
| ۱ | CDN API + Mock | 🔄 در حال اجرا | 2/4 | `src/Arvan/CdnClient.php`, `CdnResource.php`, `OutboundTrafficUsage.php` |
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

### بلوک ۱ — CDN API + Mock (در حال اجرا)

- **T-1.1 — API Spike** — چون مستندات زنده‌ی آروان (`docs.arvancloud.ir`) قابل fetch نبود (redirect loop)، قرارداد واقعی از سورس‌کد دو ریپوی گیت‌هاب زیر سازمان ArvanCloud/جامعه استخراج شد: `arvancloud/ar-prometheus-exporter` و `hamidfzm/arvancloud-go`. نتیجه: base URL، هدر Authorization، endpoint ساخت/دریافت/حذف دامنه، و endpoint ترافیک خروجی (`/domains/{domain}/reports/traffics`, بازه‌ای نه cumulative) با اطمینان بالا تأیید شد. مکانیزم واقعی hold/unhold **پیدا نشد** — به‌صورت صریح باز گذاشته شد، نه حدس زده شد.
- **T-1.2 — `CdnClient` interface** — طبق یافته‌های T-1.1 محدود شد: فقط ۴ متد (`createResource`, `getResource`, `getOutboundTrafficUsage`, `deleteResource`)؛ `ping` و `holdResource`/`unholdResource` عمداً حذف شدند. دو DTO نرمال‌شده (`CdnResource`, `OutboundTrafficUsage`) بدون هیچ فیلد خام Arvan. پارامتر `Credential` هم حذف شد — هر نمونه‌ی `CdnClient` از قبل به یک کلید متصل ساخته می‌شود، نه per-call.
  - **اسناد هم‌ترازشده:** `docs/API.md` §۳ (امضای متدها) و §۱۲ (قابلیت‌های `MockCdnClient`) با اینترفیس نهایی یکی شدند.
  - **بدهی مستندسازی شناخته‌شده:** `docs/BACKLOG.md` بلوک ۶ (`T-6.3`) هنوز `holdResource` را در سناریوی Suspend ذکر می‌کند — عمداً دست‌نخورده ماند چون حلش وابسته به همان مکانیزم نامعلوم است؛ وقتی به بلوک ۶ رسیدیم باید بازبینی شود.
- **T-1.3 — `ArvanCdnClient`** — شروع نشده.
- **T-1.4 — `MockCdnClient`** — شروع نشده.

---

## تصمیم‌های باز (باید در بلوک‌های بعدی حل شوند)

| موضوع | کجا اثر می‌گذارد | باز از |
|---|---|---|
| مکانیزم واقعی hold/unhold روی API آروان پیدا نشد | T-1.3 (`ArvanCdnClient`)، T-6.3/T-6.4 (Suspend/Resume) | T-1.1 |
| مقادیر واقعی enum فیلد `status` روی Domain resource | T-1.3، نگاشت به `ServiceStatus` محلی | T-1.1 |
| واحد دقیق ترافیک خروجی («byte» قوی حدس زده شده، تأیید ۱۰۰٪ نشده) | T-1.3، `UsagePricingAdapter` | T-1.1 |
| `docs/BACKLOG.md` بلوک ۶ هنوز به `holdResource` ارجاع می‌دهد | مستندسازی بلوک ۶ | T-1.2 |
