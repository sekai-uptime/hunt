# HUNT - Host Uptime and Network Tracker

[English version](README.md)

HUNT یک API سبک PHP برای بررسی دسترسی‌پذیری هاست، وضعیت HTTP و رکوردهای DNS است. خروجی‌ها به‌صورت JSON ساختاریافته برگردانده می‌شوند و می‌توان از آن‌ها در مرورگر، خط فرمان یا سیستم‌های مانیتورینگ استفاده کرد.

## ویژگی‌ها

- بررسی اتصال TCP با `fsockopen`
- بررسی وضعیت و زمان پاسخ HTTP با cURL
- استعلام رکوردهای DNS با `dns_get_record`
- اعتبارسنجی دقیق ورودی‌های GET
- پاسخ‌های سازگار JSON برای حالت‌های موفق و خطا

## حالت‌های پشتیبانی‌شده

اسکریپت براساس پارامتر `mode` رفتار خود را تغییر می‌دهد:

- `ping` → بررسی در دسترس بودن هاست و پورت
- `status` → بررسی URLهای HTTP و بازگشت کد وضعیت و زمان‌ها
- `nslookup` → دریافت رکوردهای DNS برای یک هاست

## پارامترهای اجباری

| پارامتر | اجباری | پیش‌فرض | توضیح |
| --- | --- | --- | --- |
| `mode` | بله | — | یکی از `ping`، `status` یا `nslookup` |
| `host` | بله | — | نام دامنه، IPv4، IPv6 یا نام محلی |
| `port` | خیر | `80` | پورت TCP مورد استفاده در حالت `ping` |
| `timeout` | خیر | `3` | زمان انتظار بر حسب ثانیه، از ۱ تا ۳۰ |
| `dns` | خیر | `DNS_NS + DNS_A + DNS_A6` | بیت‌ماسک نوع رکورد DNS برای `nslookup` |

نکته‌ها:
- `host` می‌تواند نام دامنه مانند `example.com`، آدرس IP یا URL مانند `https://example.com` باشد.
- برای حالت `status`، اسکریپت URL را قبل از درخواست نرمال‌سازی می‌کند.
- حالت `ping` از `port` استفاده می‌کند، اما `status` از آن استفاده نمی‌کند.

## قالب پاسخ

همه درخواست‌ها با JSON برگردانده می‌شوند:

موفق:
```json
{
  "ok": true,
  "data": {
    "...": "..."
  }
}
```

خطا:
```json
{
  "ok": false,
  "error": {
    "code": "missing_mode",
    "message": "Mode parameter is required."
  }
}
```

## مثال‌ها

فرض کنید اسکریپت در `http://localhost/hunt.php` اجرا می‌شود.

### ۱) بررسی اتصال به یک هاست

درخواست:
```http
GET /hunt.php?mode=ping&host=example.com&port=443&timeout=2
```

نمونه پاسخ:
```json
{
  "ok": true,
  "data": {
    "reachable": true,
    "host": "example.com",
    "port": 443,
    "time_ms": 26.431
  }
}
```

اگر پورت بسته باشد یا هاست در دسترس نباشد، API همچنان با `ok: true` پاسخ می‌دهد و یک `error` داخل `data` می‌فرستد:

```json
{
  "ok": true,
  "data": {
    "reachable": false,
    "host": "example.com",
    "port": 443,
    "time_ms": 2011.924,
    "error": {
      "code": 0,
      "message": "Connection failed."
    }
  }
}
```

### ۲) بررسی وضعیت یک URL HTTP

درخواست:
```http
GET /hunt.php?mode=status&host=https://example.com&timeout=5
```

نمونه پاسخ:
```json
{
  "ok": true,
  "data": {
    "url": "https://example.com/",
    "http_code": 200,
    "content_type": "text/html; charset=UTF-8",
    "total_time_ms": 142.871,
    "connect_time_ms": 44.719
  }
}
```

### ۳) استعلام رکوردهای DNS

درخواست:
```http
GET /hunt.php?mode=nslookup&host=example.com&dns=3
```

نمونه پاسخ:
```json
{
  "ok": true,
  "data": {
    "host": "example.com",
    "record_type": 3,
    "records": [
      {
        "host": "example.com",
        "class": "IN",
        "ttl": 86400,
        "type": "A",
        "ip": "93.184.216.34"
      }
    ]
  }
}
```

## قوانین اعتبارسنجی

قبل از اجرای درخواست، ورودی‌ها بررسی می‌شوند:

- `mode` باید وجود داشته و خالی نباشد
- `host` باید وجود داشته و خالی نباشد
- `port` باید عددی بین ۱ تا ۶۵۵۳۵ باشد
- `timeout` باید بین ۱ تا ۳۰ ثانیه باشد
- `dns` باید عددی مثبت باشد
- `host` باید نام دامنه، IPv4، IPv6 یا نام محلی معتبر باشد

## نمونه خطاها

برخی خطاهای رایج:

```json
{
  "ok": false,
  "error": {
    "code": "invalid_mode",
    "message": "Invalid mode parameter. Supported modes: ping, status, nslookup."
  }
}
```

```json
{
  "ok": false,
  "error": {
    "code": "missing_host",
    "message": "Host parameter is required."
  }
}
```

## نکات امنیتی

در نسخه فعلی، لیست سفید IP وجود ندارد. اگر بخواهید دسترسی را فقط برای آدرس‌های مشخصی محدود کنید، قبل از اجرای درخواست، بررسیِ IP را اضافه کنید.

نمونه:
```php
$allowedIps = ['127.0.0.1', '::1'];
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($clientIp, $allowedIps, true)) {
    http_response_code(403);
    exit;
}
```

## شروع سریع

1. فایل `hunt.php` را روی سرور PHP قرار دهید.
2. آن را در مرورگر باز کنید یا با پارامترهای GET فراخوانی کنید.
3. بر اساس نیاز، یکی از `mode=ping`، `mode=status` یا `mode=nslookup` را انتخاب کنید.

نمونه دستور:
```bash
curl "http://localhost/hunt.php?mode=status&host=https://example.com"
```
