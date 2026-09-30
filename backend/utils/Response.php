<?php

class Response {
    public static function json(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'Success', int $statusCode = 200, array $meta = []): void {
        $payload = [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }
        self::json($payload, $statusCode);
    }

    public static function error(string $message = 'An error occurred', int $statusCode = 400, ?array $errors = null): void {
        $payload = [
            'success' => false,
            'message' => $message,
        ];
        if ($errors !== null) {
            $payload['errors'] = $errors;
        }
        self::json($payload, $statusCode);
    }

    public static function unauthorized(string $message = 'Unauthorized access'): void {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'Forbidden: insufficient permissions'): void {
        self::error($message, 403);
    }

    public static function notFound(string $message = 'Resource not found'): void {
        self::error($message, 404);
    }
}
