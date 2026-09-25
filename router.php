<?php
$root = __DIR__;
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

if ($uri === '/' || $uri === '') {
    $indexFile = $root . '/index.html';
    if (file_exists($indexFile)) {
        include $indexFile;
        exit;
    }
}

if (strpos($uri, '/api') === 0) {
    $route = substr($uri, 4);
    $_GET['__route'] = ltrim($route, '/');

    $apiFile = $root . '/api/index.php';
    if (file_exists($apiFile)) {
        include $apiFile;
        exit;
    }
}

$requestedPath = $root . $uri;
if (file_exists($requestedPath) && is_file($requestedPath)) {
    return false;
}

http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => false,
    'message' => 'Route not found: ' . $uri
], JSON_UNESCAPED_UNICODE);
exit;
