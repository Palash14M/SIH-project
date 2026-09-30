<?php

require_once __DIR__ . '/../config/config.php';

interface PaymentProvider {
    public function createOrder(float $amount, string $currency, array $metadata): array;
    public function verifyPayment(string $orderId, array $paymentData): array;
    public function processRefund(string $paymentId, float $amount, string $reason): array;
}

class DemoPaymentProvider implements PaymentProvider {
    public function createOrder(float $amount, string $currency = 'INR', array $metadata = []): array {
        $orderId = 'DEMO_ORD_' . strtoupper(bin2hex(random_bytes(6)));
        $receipt = 'RCPT_' . strtoupper(bin2hex(random_bytes(4)));

        return [
            'success' => true,
            'order_id' => $orderId,
            'receipt' => $receipt,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'CREATED',
            'metadata' => $metadata,
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function verifyPayment(string $orderId, array $paymentData): array {
        $transactionId = 'DEMO_TXN_' . strtoupper(bin2hex(random_bytes(8)));
        $simulateFailure = ($paymentData['simulate_failure'] ?? false) === true;

        if ($simulateFailure) {
            return [
                'success' => false,
                'status' => 'FAILED',
                'order_id' => $orderId,
                'error_message' => 'Simulated payment failure: card declined or cancelled by user.',
            ];
        }

        return [
            'success' => true,
            'status' => 'SUCCESS',
            'order_id' => $orderId,
            'transaction_id' => $transactionId,
            'paid_amount' => (float)($paymentData['amount'] ?? 472.00),
            'method' => $paymentData['method'] ?? 'UPI',
            'verified_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function processRefund(string $paymentId, float $amount, string $reason): array {
        $refundId = 'DEMO_RFND_' . strtoupper(bin2hex(random_bytes(6)));

        return [
            'success' => true,
            'refund_id' => $refundId,
            'payment_id' => $paymentId,
            'amount' => $amount,
            'status' => 'REFUNDED',
            'reason' => $reason,
            'processed_at' => date('Y-m-d H:i:s'),
        ];
    }
}
