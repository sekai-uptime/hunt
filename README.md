# HUNT - Host Uptime and Network Tracker

[Persian version](README_fa.md)

HUNT is a lightweight PHP API for checking host reachability, HTTP availability, and DNS records. It returns structured JSON responses and can be used from a browser, shell, or monitoring system.

## Features

- TCP connectivity check via `fsockopen`
- HTTP status and timing check via cURL
- DNS record lookup via `dns_get_record`
- Strict validation for query parameters
- Consistent JSON success/error responses

## Supported modes

The script accepts a `mode` query parameter and switches behavior accordingly:

- `ping` → checks whether a host and port are reachable
- `status` → checks an HTTP URL and returns status and timing info
- `nslookup` → retrieves DNS records for a host

## Required parameters

| Parameter | Required | Default | Description |
| --- | --- | --- | --- |
| `mode` | Yes | — | One of `ping`, `status`, or `nslookup` |
| `host` | Yes | — | Hostname, IPv4, IPv6, or local host name |
| `port` | No | `80` | TCP port used by `ping` |
| `timeout` | No | `3` | Timeout in seconds, from 1 to 30 |
| `dns` | No | `DNS_NS + DNS_A + DNS_A6` | DNS record bitmask used by `nslookup` |

Notes:
- `host` may be a plain hostname like `example.com`, an IP address, or a URL such as `https://example.com`.
- For `status`, the script normalizes the URL before making the request.
- `ping` requires a valid host and port, while `status` does not use `port`.

## Response format

All requests return JSON in this shape:

Success:
```json
{
  "ok": true,
  "data": {
    "...": "..."
  }
}
```

Error:
```json
{
  "ok": false,
  "error": {
    "code": "missing_mode",
    "message": "Mode parameter is required."
  }
}
```

## Examples

Assume the script is served at `http://localhost/hunt.php`.

### 1) Ping a host

Request:
```http
GET /hunt.php?mode=ping&host=example.com&port=443&timeout=2
```

Example response:
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

If the port is closed or the host is unreachable, the API still responds with `ok: true` and includes an `error` object inside `data`:

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

### 2) Check an HTTP URL status

Request:
```http
GET /hunt.php?mode=status&host=https://example.com&timeout=5
```

Example response:
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

### 3) Query DNS records

Request:
```http
GET /hunt.php?mode=nslookup&host=example.com&dns=3
```

Example response:
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

## Validation rules

The script validates all input before running the requested action:

- `mode` must be present and not empty
- `host` must be present and not empty
- `port` must be an integer between 1 and 65535
- `timeout` must be between 1 and 30 seconds
- `dns` must be a positive integer
- `host` must be a valid hostname, IPv4, IPv6, or local name

## Error examples

Some common errors:

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

## Security notes

The current implementation does not include an IP whitelist. If you want to restrict access by source IP, add your own checks before the request logic runs.

Example pattern:
```php
$allowedIps = ['127.0.0.1', '::1'];
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($clientIp, $allowedIps, true)) {
    http_response_code(403);
    exit;
}
```

## Quick start

1. Place `hunt.php` on a PHP-enabled web server.
2. Open the file in a browser or call it with query parameters.
3. Use `mode=ping`, `mode=status`, or `mode=nslookup` based on what you need to check.

Example command:
```bash
curl "http://localhost/hunt.php?mode=status&host=https://example.com"
```
