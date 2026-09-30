<?php

class Config {
    private static array $env = [];

    public static function load(string $path = __DIR__ . '/../.env'): void {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Strip surrounding quotes
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                self::$env[$key] = $value;
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed {
        return self::$env[$key] ?? getenv($key) ?: $default;
    }
}

// Automatically load environment on include
Config::load();
