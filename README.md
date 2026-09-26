# Network Checker API 🛠️
[Persian Document](https://github.com/sekai-uptime/hunt/blob/main/README_fa.md)

A lightweight and practical **PHP** script/API designed to monitor network connectivity, test socket response times, query DNS records, and fetch HTTP request details. Responses are returned in a clean, structured **JSON** format.

## 📋 Features & Function Breakdown

The script consists of the following core components and functions:

### 1. **Validation & Parameter Handling**

* **`check_parms()`**: Ensures that the required parameters (`mode` and `host`) are present in the query string.
* **`get_parms()`**: Extracts query parameters and assigns sensible default values (such as port 80 or a 3-second timeout) if they are missing.

### 2. **Operation Modes**

Depending on the `mode` parameter provided in the request, the script executes one of three actions:

* **`ping` (`get_ping()` function)**:
  * Establishes a TCP socket connection using `fsockopen`.
  * Measures latency/response time in milliseconds.

* **`status` (`check_url_status()` function)**:
  * Executes an HTTP request via cURL and returns comprehensive connection information (such as HTTP response code, transfer speed, and execution duration).

* **`dns` (`check_dns()` function)**:
  * Looks up DNS records for the specified domain and returns them as an array.

### 3. **Utility Functions**

* **`JsonResponse()`**: Formats data as JSON, sets the `Content-Type: application/json` header, and terminates execution.
* **`make_curl_request()`**: Sets up cURL options (including timeout configuration and bypassing SSL peer verification) and executes the request.
* **`exitApp()`**: Emits HTTP status codes (e.g., `403 Forbidden` for unauthorized client IPs).

## ⚙️ Query Parameters

| Parameter | Type | Required | Default Value | Description |
| :--- | :--- | :--- | :--- | :--- |
| `mode` | String | **Yes** | - | Operation mode (`ping`, `status`, or `dns`) |
| `host` | String | **Yes** | - | Target hostname or URL (e.g., `example.com`) |
| `port` | Integer | No | `80` | Destination port for `ping` mode |
| `timeout` | Integer | No | `3` | Connection timeout in seconds |
| `dr` | Integer | No | `DNS_NS + DNS_A + DNS_A6` | Bitmask for DNS record types in PHP |

## 🚀 Usage Examples

Assuming the script is hosted at `http://localhost/hunt.php`:

### 1. Ping / Latency Check

Test connectivity to port 443 (HTTPS) on Google's server:

**Request:**
```http
GET http://localhost/hunt.php?mode=ping&host=google.com&port=443&timeout=2
```

**JSON Response:**
```json
{
  "error": false,
  "time": 45.2
}
```

### 2. Web Status / HTTP Details Check

Fetch cURL transfer information and HTTP status:

**Request:**
```http
GET http://localhost/hunt.php?mode=status&host=https://google.com
```

**JSON Response:**
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

### 3. DNS Lookup

Query DNS records for a domain:

**Request:**
```http
GET http://localhost/hunt.php?mode=dns&host=google.com
```

**JSON Response:**
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

## 🔒 Security (IP Whitelisting)

The script includes commented-out code for restricting access to specific IP addresses. To restrict usage to authorized clients or localhost, uncomment the following block:

```php
const WHITE_LIST = ["127.0.0.1", "::1"]; // Allowed client IPs
$clientIP = $_SERVER['REMOTE_ADDR'];
if (!in_array($clientIP, WHITE_LIST, true)) exitApp(403);
```
