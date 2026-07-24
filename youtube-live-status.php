<?php
declare(strict_types=1);

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$channelId = preg_replace(
    "/[^A-Za-z0-9_-]/",
    "",
    (string)filter_input(INPUT_GET, "channel", FILTER_SANITIZE_SPECIAL_CHARS),
);
if ($channelId === "") {
    $channelId = (string)getenv("YOUTUBE_CHANNEL_ID");
}
if ($channelId === "") {
    $channelId = "UCmRXtIEIHqHUkGFxixH35qQ";
}

$apiKey = (string)getenv("YOUTUBE_API_KEY");

$cacheDir = __DIR__;
$cacheFile = $cacheDir . DIRECTORY_SEPARATOR . ".youtube-live-cache.json";
$staleTtlSeconds = 900;

$now = time();
$emptyState = [
    "live" => false,
    "videoId" => "",
    "checkedAt" => $now,
    "cached" => false,
    "source" => "unknown",
];

$cachedState = null;
if (is_readable($cacheFile)) {
    $cachedContents = @file_get_contents($cacheFile);
    if (is_string($cachedContents) && $cachedContents !== "") {
        $decodedCache = json_decode($cachedContents, true);
        if (is_array($decodedCache) && isset($decodedCache["checkedAt"]) && is_numeric($decodedCache["checkedAt"])) {
            $age = max(0, $now - (int)$decodedCache["checkedAt"]);
            if ($age <= $staleTtlSeconds) {
                $cachedState = $decodedCache;
            }
        }
    }
}

$buildPayloadFromApi = function (bool $live, string $videoId = "") {
    return [
        "live" => $live,
        "videoId" => $live ? $videoId : "",
        "checkedAt" => time(),
        "cached" => false,
        "source" => "youtube-api",
    ];
};

$buildPayloadFromCache = function (array $cached) {
    $cached["cached"] = true;
    $cached["source"] = "cache";
    if (!isset($cached["checkedAt"])) {
        $cached["checkedAt"] = time();
    }
    return $cached;
};

if (!function_exists("jsonErrorResponse")) {
    function jsonErrorResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($apiKey === "") {
    if ($cachedState && isset($cachedState["live"])) {
        jsonErrorResponse($buildPayloadFromCache($cachedState), 200);
    }
    $emptyState["cached"] = false;
    $emptyState["source"] = "missing-key";
    jsonErrorResponse($emptyState, 200);
}

$searchUrl = "https://www.googleapis.com/youtube/v3/search?part=snippet&channelId="
    . urlencode($channelId)
    . "&eventType=live&type=video&order=date&maxResults=1&key="
    . urlencode($apiKey);

if (!function_exists("requestYouTubeLivePayload")) {
    function requestYouTubeLivePayload(string $url): ?array
    {
        if (function_exists("curl_init")) {
            $handle = curl_init($url);
            if (!$handle) {
                return null;
            }

            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_HTTPHEADER => ["Accept: application/json"],
                CURLOPT_USERAGENT => "PraiseHimMoreLiveChecker/1.0",
            ]);

            $body = curl_exec($handle);
            $statusCode = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $curlErrNo = curl_errno($handle);
            curl_close($handle);

            if ($body === false || $curlErrNo !== 0 || $statusCode < 200 || $statusCode >= 300) {
                return null;
            }
            $payload = json_decode((string)$body, true);
            if (!is_array($payload) || !isset($payload["items"]) || !is_array($payload["items"])) {
                return null;
            }

            $item = $payload["items"][0] ?? null;
            if (!is_array($item) || empty($item["id"]["videoId"])) {
                return ["live" => false, "videoId" => ""];
            }

            return [
                "live" => true,
                "videoId" => (string)$item["id"]["videoId"],
            ];
        }

        $context = stream_context_create([
            "http" => [
                "method" => "GET",
                "header" => "Accept: application/json\r\nUser-Agent: PraiseHimMoreLiveChecker/1.0\r\n",
                "timeout" => 8,
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            return null;
        }

        $payload = json_decode((string)$body, true);
        if (!is_array($payload) || !isset($payload["items"]) || !is_array($payload["items"])) {
            return null;
        }

        $item = $payload["items"][0] ?? null;
        if (!is_array($item) || empty($item["id"]["videoId"])) {
            return ["live" => false, "videoId" => ""];
        }

        return [
            "live" => true,
            "videoId" => (string)$item["id"]["videoId"],
        ];
    }
}

$apiPayload = requestYouTubeLivePayload($searchUrl);

if ($apiPayload === null) {
    if ($cachedState && isset($cachedState["checkedAt"])) {
        $cachedAge = $now - (int)$cachedState["checkedAt"];
        if ($cachedAge <= $staleTtlSeconds) {
            jsonErrorResponse($buildPayloadFromCache($cachedState), 200);
        }
    }

    $emptyState["source"] = "youtube-request-failed";
    jsonErrorResponse($emptyState, 200);
}

$livePayload = $buildPayloadFromApi((bool)$apiPayload["live"], (string)($apiPayload["videoId"] ?? ""));
@file_put_contents($cacheFile, json_encode($livePayload));
jsonErrorResponse($livePayload, 200);
