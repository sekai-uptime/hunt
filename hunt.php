<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

try {
    if (!version_compare(PHP_VERSION, '8.5.0', ">=")) throw new RuntimeException("use php 8.5.0 or newer");

    check_parms();
    $parms = get_parms();
    $mode = strtolower((string) ($_GET['mode'] ?? ''));

    switch ($mode) {
        case 'ping':
            JsonResponseSuccess(get_ping($parms['host'], $parms['port'], $parms['timeout']));
            break;

        case 'status':
            JsonResponseSuccess(check_url_status($parms['host'], $parms['timeout']));
            break;

        case 'nslookup':
            JsonResponseSuccess(nslookup($parms['host'], $parms['dns']));
            break;

        default:
            JsonResponseError('invalid_mode', 'Invalid mode parameter. Supported modes: ping, status, nslookup.', 400);
    }
} catch (InvalidArgumentException $e) {
    JsonResponseError('invalid_parameter', $e->getMessage(), 400);
} catch (RuntimeException $e) {
    JsonResponseError('request_failed', $e->getMessage(), 503);
} catch (Throwable $e) {
    JsonResponseError('internal_error', 'An unexpected error occurred while processing the request.', 500);
}

function get_ping(string $host, int $port, int $timeout): array
{
    $host = normalize_host($host);
    $port = validate_port($port);
    $timeout = validate_timeout($timeout);


    $errno = 0;
    $errstr = '';
    $startTimer = microtime(true);
    $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
    $elapsedMs = round((microtime(true) - $startTimer) * 1000, 3);

    if ($connection === false) {
        return [
            'reachable' => false,
            'host' => $host,
            'port' => $port,
            'time_ms' => $elapsedMs,
            'error' => [
                'code' => $errno,
                'message' => $errstr !== '' ? $errstr : 'Connection failed.',
            ],
        ];
    }

    fclose($connection);

    return [
        'reachable' => true,
        'host' => $host,
        'port' => $port,
        'time_ms' => $elapsedMs,
    ];
}

// to understand what mean by record number check this url
// https://www.php.net/manual/en/network.constants.php#constant.dns-any
function nslookup(string $host, int $record): array
{
    $host = normalize_host($host);
    $record = validate_dns_record($record);

    $dns = @dns_get_record($host, $record);

    if ($dns === false || $dns === []) {
        throw new RuntimeException(sprintf('No DNS records found for host "%s".', $host));
    }

    return [
        'host' => $host,
        'record_type' => $record,
        'records' => $dns,
    ];
}

function check_url_status(string $url, int $timeout): array
{
    $url = normalize_url($url);
    $timeout = validate_timeout($timeout);

    $info = make_curl_request($url, $timeout);

    if ($info === false) {
        throw new RuntimeException(sprintf('Unable to fetch URL "%s".', $url));
    }

    return [
        'url' => $url,
        'http_code' => (int) ($info['http_code'] ?? 0),
        'content_type' => $info['content_type'] ?? null,
        'total_time_ms' => round(($info['total_time'] ?? 0) * 1000, 3),
        'connect_time_ms' => round(($info['connect_time'] ?? 0) * 1000, 3),
    ];
}

function JsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function JsonResponseSuccess(array $data, int $status = 200): void
{
    JsonResponse([
        'ok' => true,
        'data' => $data,
    ], $status);
}

function JsonResponseError(string $code, string $message, int $status = 400): void
{
    JsonResponse([
        'ok' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
        ],
    ], $status);
}

function make_curl_request(string $url, int $timeout): array|false
{
    $ch = curl_init($url);

    if ($ch === false) {
        return false;
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        // CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT => $timeout,
        // CURLOPT_CONNECTTIMEOUT => min(10, max(1, $timeout)),
        // CURLOPT_FOLLOWLOCATION => false,
        // CURLOPT_MAXREDIRS => 0,
        // CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        // CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $errno = curl_errno($ch);
    $info = curl_getinfo($ch);
    // curl_close($ch);

    if ($response === false || $curlError !== '' || $errno !== 0) {
        return false;
    }

    $info['body'] = $response;
    return $info;
}

function check_parms(): void
{
    if (!isset($_GET['mode']) || trim((string) $_GET['mode']) === '') {
        JsonResponseError('missing_mode', 'Mode parameter is required.', 400);
    }

    if (!isset($_GET['host']) || trim((string) $_GET['host']) === '') {
        JsonResponseError('missing_host', 'Host parameter is required.', 400);
    }
}

function get_parms(): array
{
    $host = trim((string) ($_GET['host'] ?? ''));
    $port = filter_var($_GET['port'] ?? 80, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    $timeout = filter_var($_GET['timeout'] ?? 3, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 30]]);
    $dns = filter_var($_GET['dns'] ?? (DNS_NS + DNS_A + DNS_A6), FILTER_VALIDATE_INT);

    if ($port === false) {
        throw new InvalidArgumentException('Port must be an integer between 1 and 65535.');
    }

    if ($timeout === false) {
        throw new InvalidArgumentException('Timeout must be an integer between 1 and 30 seconds.');
    }

    if ($dns === false) {
        throw new InvalidArgumentException('DNS record type is invalid.');
    }

    return [
        'host' => $host,
        'port' => (int) $port,
        'timeout' => (int) $timeout,
        'dns' => (int) $dns,
    ];
}

function validate_timeout(int $timeout): int
{
    if ($timeout < 1 || $timeout > 30) {
        throw new InvalidArgumentException('Timeout must be between 1 and 30 seconds.');
    }

    return $timeout;
}

function validate_port(int $port): int
{
    if ($port < 1 || $port > 65535) {
        throw new InvalidArgumentException('Port must be between 1 and 65535.');
    }

    return $port;
}

function validate_dns_record(int $record): int
{
    if ($record <= 0) {
        throw new InvalidArgumentException('DNS record type must be a positive integer.');
    }

    return $record;
}

function normalize_host(string $host): string
{
    $host = trim($host);

    if ($host === '') {
        throw new InvalidArgumentException('Host parameter cannot be empty.');
    }

    if (preg_match('#^https?://#i', $host) === 1) {
        $parsed = parse_url($host);
        if (!isset($parsed['host']) || $parsed['host'] === '') {
            throw new InvalidArgumentException('Host parameter must include a valid hostname or IP address.');
        }
        $host = $parsed['host'];
    }

    $isIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
    $isHostname = preg_match('/^(?=.{1,253}$)(?!-)(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$/', $host) === 1;
    $isLocalName = preg_match('/^(?=.{1,253}$)(?!-)[A-Za-z0-9.-]+(?<!-)$/', $host) === 1 && strpos($host, '.') === false;

    if (!$isIp && !$isHostname && !$isLocalName) {
        throw new InvalidArgumentException('Host parameter must be a valid hostname, IPv4, IPv6, or local name.');
    }

    return $host;
}

function normalize_url(string $url): string
{
    $url = trim($url);

    if ($url === '') {
        throw new InvalidArgumentException('URL parameter cannot be empty.');
    }

    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }

    $parsed = parse_url($url);

    if ($parsed === false || empty($parsed['host'])) {
        throw new InvalidArgumentException('URL parameter must contain a valid hostname.');
    }

    if (!in_array(strtolower((string) ($parsed['scheme'] ?? '')), ['http', 'https'], true)) {
        throw new InvalidArgumentException('URL scheme must be http or https.');
    }

    return $url;
}
