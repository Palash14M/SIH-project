<?php

require_once __DIR__ . '/../config/config.php';

class Jwt {
    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function encode(array $payload, ?int $expirySeconds = null): string {
        $secret = Config::get('JWT_SECRET', 'DefaultSecretKeyReplaceMeInProd2026!');
        $expiry = $expirySeconds ?? (int)Config::get('JWT_EXPIRY', 86400);

        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $issuedAt = time();
        $payload['iat'] = $issuedAt;
        $payload['exp'] = $issuedAt + $expiry;

        $encodedHeader = self::base64UrlEncode(json_encode($header));
        $encodedPayload = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", $secret, true);
        $encodedSignature = self::base64UrlEncode($signature);

        return "{$encodedHeader}.{$encodedPayload}.{$encodedSignature}";
    }

    public static function decode(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;

        $secret = Config::get('JWT_SECRET', 'DefaultSecretKeyReplaceMeInProd2026!');
        $validSignature = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", $secret, true);
        $expectedEncodedSignature = self::base64UrlEncode($validSignature);

        if (!hash_equals($expectedEncodedSignature, $encodedSignature)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($encodedPayload), true);
        if (!$payload || !is_array($payload)) {
            return null;
        }

        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null; // Expired
        }

        return $payload;
    }
}
