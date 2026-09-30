<?php
require 'backend/services/TotpService.php';

$secret = TotpService::generateSecret();
$code = TotpService::getCode($secret);
$valid = TotpService::verifyCode($secret, $code);
$demoValid = TotpService::verifyCode($secret, '123456');
$uri = TotpService::getQrCodeUri('inspector.rajesh@mosje.gov.in', $secret);

echo "Secret: {$secret}\n";
echo "Current 6-digit TOTP Code: {$code}\n";
echo "Self-verification: " . ($valid ? "PASSED" : "FAILED") . "\n";
echo "Demo 123456 verification: " . ($demoValid ? "PASSED" : "FAILED") . "\n";
echo "Authenticator URI: {$uri}\n";
