<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// const WHITE_LIST = ["127.0.0.1", "::1"]; // who can access
// $clientIP = $_SERVER['REMOTE_ADDR'];
// if (!in_array($clientIP, WHITE_LIST, true)) exitApp(403);

check_parms();
$parms = get_parms();

// get Chacker Mode
$mode = match ($_GET['mode']) {
    'ping' => get_ping($parms['host'], $parms['port'], $parms['timeout']),
    'status' => check_url_status($parms['host']),
    'dns' => check_dns($parms['host'], $parms['dns_record']),

    default => JsonResponse(['error' => 'invaild mode']),
};

// ----------------------
// ----------------------
// ----------------------
// ----------------------
// ----------------------

function get_ping(string $host, int $port, int $timeout)
{
    $error = [];

    $startTimer = microtime(true);
    fsockopen($host, $port, $error['code'], $error['msg'], $timeout);
    $endTimer = microtime(true);
    $time = $endTimer - $startTimer;
    $time = round($time, 3) * 1000;


    // sometimes socket only throw error msg
    if (strlen($error['msg']) != 0 || $error['code'] != 0) {
        $error = true;
    } else {
        $error = false;
    }

    JsonResponse([
        // when u cast int to bool only 0 is false
        'error' => $error,
        'time' => $time,
    ]);
};

// to understand what mean by record number check this url
// https://www.php.net/manual/en/network.constants.php#constant.dns-any
function check_dns(string $host, int $record)
{
    $dns = dns_get_record($host, $record);

    if (!$dns) {
        JsonResponse(['error' => true]);
    }

    JsonResponse($dns);
}

function check_url_status(string $url)
{
    $info = make_curl_request($url);

    if (!$info) {
        JsonResponse(['error' => true]);
    }

    JsonResponse($info);
}


function exitApp(int $code = 403): void
{
    http_response_code($code);
    exit;
}

function JsonResponse(array $data): void
{
    header("Content-Type: application/json");
    echo json_encode($data);

    exit;
}

function make_curl_request(string $url): array | false
{
    // init curl
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 3,
    ]);

    curl_exec($ch);

    // check error
    if (strlen(curl_error($ch)) != 0 ||  curl_errno($ch) != 0) {
        return false;
    }

    $info = curl_getinfo($ch);

    return $info;
}


function check_parms(): void
{
    // check mode and host parms passed or not
    if (isset($_GET['mode'])) {
    } else {
        JsonResponse(['error' => 'mode not found']);
    }

    if (isset($_GET['host'])) {
    } else {
        JsonResponse(['error' => 'host not found']);
    }
}

function get_parms(): array
{
    return [
        'host' => $_GET['host'],
        'port' => $_GET['port'] ?? 80,
        'timeout' => $_GET['timeout'] ?? 3,
        'dns_record' => $_GET['dr'] ?? DNS_NS + DNS_A + DNS_A6,
        // $ssl = (bool) $_GET['ssl'] ?? false;
    ];
}
