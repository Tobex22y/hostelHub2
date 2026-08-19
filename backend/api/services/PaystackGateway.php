<?php

declare(strict_types=1);

class PaystackGateway
{
    private string $secretKey;
    private string $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = $_ENV['PAYSTACK_SECRET_KEY']
            ?? getenv('PAYSTACK_SECRET_KEY')
            ?: '';

        if (empty($this->secretKey)) {
            throw new RuntimeException(
                'PAYSTACK_SECRET_KEY is not configured in your .env file.', 500
            );
        }
    }

    
    public function initialize(array $params): array
    {
        $payload = [
            'email'        => $params['email'],
            'amount'       => (int) round((float)$params['amount'] * 100), // naira → kobo
            'reference'    => $params['reference'],
            'callback_url' => $params['callback_url'],
            'currency'     => $params['currency'] ?? 'NGN',
            'metadata'     => array_merge([
                'student_id'    => $params['student_id']    ?? null,
                'allocation_id' => $params['allocation_id'] ?? null,
                'bed_id'        => $params['bed_id']        ?? null,
                'bed_label'     => $params['bed_label']     ?? null,
                'cancel_action' => $params['cancel_url']    ?? '',
            ], $params['metadata'] ?? []),
        ];

        // Optional: restrict payment channels
        if (!empty($params['channels'])) {
            $payload['channels'] = $params['channels'];
        }

        $response = $this->post('/transaction/initialize', $payload);

        if (($response['status'] ?? false) === true && !empty($response['data']['authorization_url'])) {
            return [
                'success'           => true,
                'authorization_url' => $response['data']['authorization_url'],
                'access_code'       => $response['data']['access_code'] ?? '',
                'reference'         => $response['data']['reference']   ?? $params['reference'],
                'message'           => 'Payment session created. Redirect student to authorization_url.',
            ];
        }

        return [
            'success'           => false,
            'authorization_url' => '',
            'access_code'       => '',
            'reference'         => $params['reference'],
            'message'           => $response['message'] ?? 'Failed to create Paystack payment session.',
        ];
    }

    
    public function verify(string $reference): array
    {
        $response = $this->get('/transaction/verify/' . rawurlencode($reference));

        if (($response['status'] ?? false) !== true) {
            return [
                'success'     => false,
                'status'      => 'failed',
                'amount'      => 0.0,
                'reference'   => $reference,
                'channel'     => '',
                'gateway_ref' => '',
                'paid_at'     => '',
                'message'     => $response['message'] ?? 'Paystack verification request failed.',
            ];
        }

        $d      = $response['data'];
        $status = strtolower($d['status'] ?? 'failed');

        return [
            'success'     => $status === 'success',
            'status'      => $status,
            'amount'      => (float)($d['amount'] ?? 0) / 100, // kobo → naira
            'reference'   => $d['reference']              ?? $reference,
            'channel'     => $d['channel']                ?? '',
            'gateway_ref' => $d['id']                     ?? '',
            'paid_at'     => $d['paid_at']                ?? '',
            'message'     => $status === 'success'
                ? 'Transaction verified successfully.'
                : 'Transaction not successful. Status: ' . $status,
        ];
    }

    
    public function verifyWebhookSignature(string $rawBody, string $signatureHeader): bool
    {
        $computed = hash_hmac('sha512', $rawBody, $this->secretKey);
        return hash_equals($computed, strtolower($signatureHeader));
    }

  
    private function post(string $path, array $payload): array
    {
        return $this->request('POST', $path, $payload);
    }

    private function get(string $path): array
    {
        return $this->request('GET', $path, []);
    }

    private function request(string $method, string $path, array $payload): array
    {
        $ch = curl_init($this->baseUrl . $path);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->secretKey,
                'Cache-Control: no-cache',
            ],
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $raw    = curl_exec($ch);
        $error  = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $error) {
            throw new RuntimeException(
                'Paystack cURL error: ' . $error, 502
            );
        }

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            throw new RuntimeException(
                "Paystack returned invalid JSON (HTTP {$httpCode}). Body: " . substr($raw, 0, 200),
                502
            );
        }

        return $decoded;
    }
}
