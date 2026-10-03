<?php

// Check if running via PHP built-in web server and requested an existing static file
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$rootDir = dirname(__DIR__);
if (php_sapi_name() === 'cli-server') {
    if ($uri !== '/' && $uri !== '' && is_file($rootDir . $uri)) {
        return false;
    }
    if ($uri !== '/' && $uri !== '' && is_file(__DIR__ . $uri)) {
        return false;
    }
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/utils/Jwt.php';
require_once __DIR__ . '/utils/Validator.php';
require_once __DIR__ . '/utils/Audit.php';
require_once __DIR__ . '/middleware/CorsMiddleware.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';

// Handle CORS
CorsMiddleware::handle();

// Set error and exception handlers for uniform JSON output
set_exception_handler(function (Throwable $e) {
    error_log("Unhandled Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    Response::error($e->getMessage(), 500, [
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
    ]);
});

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Normalize request path and method
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Helper to read JSON body
function getJsonInput(): array {
    $raw = file_get_contents('php://input');
    if (empty($raw)) {
        return $_POST;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? array_merge($_POST, $decoded) : $_POST;
}

// Router matcher
class Router {
    private static array $routes = [];

    public static function add(string $method, string $pattern, callable|array $handler): void {
        self::$routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public static function dispatch(string $method, string $uri): void {
        $cleanUri = rtrim($uri, '/');
        if ($cleanUri === '') {
            $cleanUri = '/';
        }

        foreach (self::$routes as $route) {
            if ($route['method'] !== $method && $route['method'] !== 'ANY') {
                continue;
            }

            // Convert route pattern with parameters e.g. /api/tenders/{id} to regex
            $regex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $route['pattern']);
            $trimmed = rtrim($regex, '/');
            $regex = "#^" . ($trimmed === '' ? '/' : $trimmed) . "$#";

            if (preg_match($regex, $cleanUri, $matches)) {
                $params = [];
                foreach ($matches as $key => $val) {
                    if (is_string($key)) {
                        $params[$key] = $val;
                    }
                }

                $handler = $route['handler'];
                if (is_callable($handler)) {
                    call_user_func($handler, $params);
                    return;
                } elseif (is_array($handler) && count($handler) === 2) {
                    [$class, $methodName] = $handler;
                    $controller = new $class();
                    $controller->$methodName($params);
                    return;
                }
            }
        }

        Response::notFound("Route '{$method} {$uri}' not found.");
    }
}

// Register Health Check route (GATE 1 requirement)
Router::add('GET', '/api/health', function () {
    $dbStatus = 'UNKNOWN';
    try {
        $pdo = Database::getConnection();
        $pdo->query("SELECT 1");
        $dbStatus = 'CONNECTED (' . Config::get('DB_CONNECTION', 'sqlite') . ')';
    } catch (Throwable $e) {
        $dbStatus = 'ERROR: ' . $e->getMessage();
    }

    Response::success([
        'status' => 'HEALTHY',
        'service' => Config::get('APP_NAME', 'Smart Inspection MoSJE API'),
        'environment' => Config::get('APP_ENV', 'demo'),
        'database' => $dbStatus,
        'php_version' => PHP_VERSION,
        'timestamp' => date('Y-m-d H:i:s'),
        'team' => 'CHAKRAVYUH',
        'problem_statement' => 'SIH26095',
        'ministry' => 'Ministry of Social Justice and Empowerment (MoSJE)',
    ], 'API is online and operational.');
});

// Register Direct Download & Portal routes
Router::add('GET', '/', function () {
    $indexPath = dirname(__DIR__) . '/dist/index.html';
    if (!file_exists($indexPath)) {
        $indexPath = dirname(__DIR__) . '/preview/index.html';
    }
    if (file_exists($indexPath)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($indexPath);
        exit;
    }
    Response::notFound('Portal page not found.');
});

Router::add('GET', '/portal', function () {
    $indexPath = dirname(__DIR__) . '/dist/index.html';
    if (!file_exists($indexPath)) {
        $indexPath = dirname(__DIR__) . '/preview/index.html';
    }
    if (file_exists($indexPath)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($indexPath);
        exit;
    }
    Response::notFound('Portal page not found.');
});

function getApkFilePath(): ?string {
    $candidates = [
        dirname(__DIR__) . '/backend/SmartInspection-MoSJE-release.apk',
        dirname(__DIR__) . '/backend/app-release.apk',
        dirname(__DIR__) . '/dist/SmartInspection-MoSJE-release.bin',
        dirname(__DIR__) . '/preview/SmartInspection-MoSJE-release.apk',
        dirname(__DIR__) . '/android/app/build/outputs/apk/release/app-release.apk',
    ];
    foreach ($candidates as $candidate) {
        if (file_exists($candidate) && filesize($candidate) > 1000000) {
            return $candidate;
        }
    }
    return null;
}

Router::add('ANY', '/SmartInspection-MoSJE-release.apk', function () {
    $apkPath = getApkFilePath();
    if ($apkPath) {
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="SmartInspection-MoSJE-release.apk"');
        header('Content-Length: ' . filesize($apkPath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
            readfile($apkPath);
        }
        exit;
    }
    Response::notFound('APK file not found.');
});

Router::add('ANY', '/download-apk', function () {
    $apkPath = getApkFilePath();
    if ($apkPath) {
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="SmartInspection-MoSJE-release.apk"');
        header('Content-Length: ' . filesize($apkPath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
            readfile($apkPath);
        }
        exit;
    }
    Response::notFound('APK file not found.');
});

Router::add('ANY', '/app-release.apk', function () {
    $apkPath = getApkFilePath();
    if ($apkPath) {
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="SmartInspection-MoSJE-release.apk"');
        header('Content-Length: ' . filesize($apkPath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') {
            readfile($apkPath);
        }
        exit;
    }
    Response::notFound('APK file not found.');
});

Router::add('GET', '/preview', function () {
    $previewPath = dirname(__DIR__) . '/preview/index.html';
    if (file_exists($previewPath)) {
        header('Content-Type: text/html; charset=utf-8');
        readfile($previewPath);
        exit;
    }
    Response::notFound('Preview page not found.');
});

Router::add('GET', '/watermark_logo.png', function () {
    $imgPath = dirname(__DIR__) . '/dist/watermark_logo.png';
    if (file_exists($imgPath)) {
        header('Content-Type: image/png');
        header('Content-Length: ' . filesize($imgPath));
        readfile($imgPath);
        exit;
    }
    Response::notFound('Logo not found.');
});

Router::add('GET', '/manifest.json', function () {
    $path = dirname(__DIR__) . '/preview/manifest.json';
    if (file_exists($path)) {
        header('Content-Type: application/manifest+json');
        readfile($path);
        exit;
    }
    Response::notFound('Manifest not found.');
});

Router::add('GET', '/sw.js', function () {
    $path = dirname(__DIR__) . '/preview/sw.js';
    if (file_exists($path)) {
        header('Content-Type: application/javascript');
        readfile($path);
        exit;
    }
    Response::notFound('Service Worker not found.');
});

// Load controller routes if controllers exist
$controllersDir = __DIR__ . '/controllers';
if (is_dir($controllersDir)) {
    foreach (glob($controllersDir . '/*.php') as $controllerFile) {
        require_once $controllerFile;
    }
}

// Dispatch the incoming request
Router::dispatch($requestMethod, $requestUri);
