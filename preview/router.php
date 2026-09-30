<?php
// Preview Router: Proxies /api to Backend (127.0.0.1:8000) and serves static files for web preview

$uri = $_SERVER['REQUEST_URI'];
$path = parse_url($uri, PHP_URL_PATH);

if (str_starts_with($path, '/api')) {
    // Proxy API requests to PHP backend on port 8000
    $backendUrl = 'http://127.0.0.1:8000' . $uri;
    
    $ch = curl_init($backendUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
    
    // Forward relevant headers
    $headers = [];
    foreach (getallheaders() as $name => $value) {
        if (!in_array(strtolower($name), ['host', 'content-length'])) {
            $headers[] = "$name: $value";
        }
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    // Forward body if present
    if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH', 'DELETE'])) {
        $body = file_get_contents('php://input');
        if (!empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
    }
    
    // Capture headers
    curl_setopt($ch, CURLOPT_HEADER, true);
    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $headerStr = substr($response, 0, $headerSize);
    $bodyStr = substr($response, $headerSize);
    
    http_response_code($httpCode);
    foreach (explode("\r\n", $headerStr) as $h) {
        if (!empty($h) && !str_starts_with(strtolower($h), 'transfer-encoding:')) {
            header($h);
        }
    }
    echo $bodyStr;
    exit;
}

// Review & Spec aliases for AI / Technical Reviewers (Claude, Evaluators)
if ($path === '/review' || $path === '/claude' || $path === '/dossier') {
    include __DIR__ . '/review.html';
    exit;
}
if ($path === '/spec') {
    header('Content-Type: application/json; charset=UTF-8');
    readfile(__DIR__ . '/review.json');
    exit;
}

// Static files handling
$filePath = __DIR__ . $path;
if ($path !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false; // let built-in web server serve the file
}

// Fallback to index.html
include __DIR__ . '/index.html';
