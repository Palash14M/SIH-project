<?php

require_once __DIR__ . '/../config/config.php';

interface SmsProvider {
    public function sendOtp(string $phone, string $otp): array;
}

class DemoSmsProvider implements SmsProvider {
    public function sendOtp(string $phone, string $otp): array {
        $env = Config::get('APP_ENV', 'production');
        $logMessage = "[" . date('Y-m-d H:i:s') . "] [DEMO SMS] Sent OTP '{$otp}' to {$phone}\n";
        
        $logFile = dirname(__DIR__) . '/storage/sms.log';
        @file_put_contents($logFile, $logMessage, FILE_APPEND);

        $response = [
            'sent' => true,
            'message' => 'OTP dispatched successfully.',
            'phone' => $phone,
        ];

        if ($env === 'demo' || Config::get('APP_DEBUG', 'false') === 'true') {
            $response['demo_otp'] = $otp;
        }

        return $response;
    }
}
