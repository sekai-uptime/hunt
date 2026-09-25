# HUNT - Host Uptime and Network Tracker 🛠️

این اسکریپت یک ابزار کاربردی و سبک به زبان **PHP** برای بررسی وضعیت شبکه‌ای، سرورها، رکورد‌های DNS و درخواست‌های HTTP است. با استفاده از این API می‌توانید وضعیت در دسترس بودن یک هاست یا وب‌سایت را سنجیده و خروجی را به صورت ساختاریافته **JSON** دریافت کنید.

---

## 📋 ویژگی‌ها و بخش‌های اصلی اسکریپت

اسکریپت از توابع و بخش‌های زیر تشکیل شده است:

### ۱. **بخش ورودی و اعتبارسنجی (Validation & Parameters)**
* **`check_parms()`**: بررسی می‌کند که حتماً دو پارامتر اجباری `mode` و `host` ارسال شده باشند.
* **`get_parms()`**: پارامترهای دریافتی از `GET` را پردازش کرده و در صورت عدم ارسال، مقادیر پیش‌فرض تعیین می‌کند (مانند پورت ۸۰ یا تایم‌اوت ۳ ثانیه).

### ۲. **حالت‌های کاری (Operation Modes)**
اسکریپت بر اساس پارامتر `mode` یکی از سه عملکرد زیر را اجرا می‌کند:

* **`ping` (تابع `get_ping`)**: 
  * با استفاده از `fsockopen` اتصال TCP به هاست و پورت مشخص‌شده ایجاد می‌کند.
  * زمان پاسخ‌گویی (Latency/Ping) را بر حسب میلی‌ثانیه محاسبه می‌کند.
* **`status` (تابع `check_url_status`)**:
  * با استفاده از cURL یک درخواست HTTP به آدرس مقصد فرستاده و اطلاعات جامع مربوط به اتصال (مانند HTTP Status Code، زمان بارگذاری و...) را بازمی‌گرداند.
* **`dns` (تابع `check_dns`)**:
  * رکوردهای DNS دامنه مورد نظر را استعلام (Lookup) کرده و به صورت آرایه بازمی‌گرداند.

### ۳. **توابع کمکی (Utility Functions)**
* **`JsonResponse()`**: خروجی را با فرمت JSON و هدر `application/json` ارسال کرده و برنامه را پایان می‌دهد.
* **`make_curl_request()`**: تنظیمات پایه cURL (شامل تایم‌اوت و لغو بررسی گواهی SSL) را اعمال و اجرا می‌کند.
* **`exitApp()`**: برای ارسال کدهای وضعیت HTTP (مانند کد ۴۰۳ برای آی‌پی‌های غیرمجاز) استفاده می‌شود.

---

## ⚙️ پارامترهای ورودی (Query Parameters)

| پارامتر | نوع | اجباری؟ | مقدار پیش‌فرض | توضیحات |
| :--- | :--- | :--- | :--- | :--- |
| `mode` | String | **بله** | - | نوع تست (`ping` / `status` / `dns`) |
| `host` | String | **بله** | - | دامنه یا IP مورد نظر (مثال: `example.com`) |
| `port` | Integer | خیر | `80` | پورت مورد نظر برای حالت `ping` |
| `timeout` | Integer | خیر | `3` | حداکثر زمان انتظار بر حسب ثانیه |
| `dr` | Integer | خیر | `DNS_NS + DNS_A + DNS_A6` | کد عددی نوع رکورد DNS در PHP |

---

## 🚀 مثال‌های استفاده (Usage Examples)

فرض کنید اسکریپت روی `http://localhost/hunt.php` میزبانی شده است:

### ۱. تست Ping و بررسی زمان پاسخ‌گویی
برای تست باز بودن پورت 443 (HTTPS) روی سرور گوگل:

**درخواست:**
```http
GET http://localhost/hunt.php?mode=ping&host=google.com&port=443&timeout=2
```

**نمونه خروجی JSON:**
```json
{
  "error": false,
  "time": 45.2
}
```

---

### ۲. بررسی وضعیت وب‌سایت (HTTP Status & cURL Info)
برای دریافت جزئیات اتصال HTTP و کد وضعیت سایت:

**درخواست:**
```http
GET http://localhost/hunt.php?mode=status&host=https://google.com
```

**نمونه خروجی JSON:**
```json
{
  "url": "https://google.com/",
  "content_type": "text/html; charset=ISO-8859-1",
  "http_code": 200,
  "header_size": 320,
  "request_size": 54,
  "filetime": -1,
  "ssl_verify_result": 0,
  "redirect_count": 0,
  "total_time": 0.125,
  "namelookup_time": 0.012,
  "connect_time": 0.045,
  "pretransfer_time": 0.089,
  "size_upload": 0,
  "size_download": 14210,
  "speed_download": 113680,
  "speed_upload": 0,
  "download_content_length": -1,
  "upload_content_length": -1,
  "starttransfer_time": 0.124,
  "redirect_time": 0
}
```

---

### ۳. استعلام رکوردهای DNS
برای دریافت رکوردهای DNS یک دامنه:

**درخواست:**
```http
GET http://localhost/hunt.php?mode=dns&host=google.com
```

**نمونه خروجی JSON:**
```json
[
  {
    "host": "google.com",
    "class": "IN",
    "ttl": 300,
    "type": "A",
    "ip": "142.250.180.206"
  },
  {
    "host": "google.com",
    "class": "IN",
    "ttl": 21600,
    "type": "NS",
    "target": "ns1.google.com"
  }
]
```

---

## 🔒 امنیت (IP Whitelist)

در انتهای فایل، بخش کنترل دسترسی (IP Whitelist) به صورت کامنت قرار گرفته است. در صورت نیاز به محدودسازی دسترسی به افراد/سرورهای خاص، می‌توانید آن را فعال کنید:

```php
const WHITE_LIST = ["127.0.0.1", "::1"]; // آی‌پی‌های مجاز
$clientIP = $_SERVER['REMOTE_ADDR'];
if (!in_array($clientIP, WHITE_LIST, true)) exitApp(403);
```
