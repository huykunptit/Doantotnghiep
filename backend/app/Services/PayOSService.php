<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Str;
use PayOS\Exceptions\APIException;
use PayOS\Models\V2\PaymentRequests\CreatePaymentLinkRequest;
use PayOS\PayOS;

class PayOSService
{
    public function isConfigured(): bool
    {
        return filled(config('services.payos.client_id'))
            && filled(config('services.payos.api_key'))
            && filled(config('services.payos.checksum_key'));
    }

    public function createPaymentLink(Order $order): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $request = new CreatePaymentLinkRequest(
            orderCode: (int) $order->id,
            amount: (int) $order->amount,
            description: $this->buildDescription($order),
            cancelUrl: (string) config('services.payos.cancel_url'),
            returnUrl: (string) config('services.payos.return_url'),
            buyerName: (string) optional($order->user)->name,
            buyerEmail: (string) optional($order->user)->email,
            items: [[
                'name' => Str::limit((string) optional($order->course)->title, 25, ''),
                'quantity' => 1,
                'price' => (int) $order->amount,
            ]],
            expiredAt: now()->addMinutes(15)->timestamp,
        );

        $result = $this->client()->paymentRequests->create($request);

        return [
            'checkout_url' => $result->checkoutUrl,
            'payment_link_id' => $result->paymentLinkId,
            'raw' => [
                'bin' => $result->bin,
                'accountNumber' => $result->accountNumber,
                'accountName' => $result->accountName,
                'amount' => $result->amount,
                'description' => $result->description,
                'orderCode' => $result->orderCode,
                'qrCode' => $result->qrCode,
            ],
        ];
    }

    public function getPaymentLink(int|string $orderCode): array
    {
        $payment = $this->client()->paymentRequests->get($orderCode, options: ['asArray' => true]);

        return is_array($payment) ? $payment : (array) $payment;
    }

    public function verifyWebhook(array $payload): array
    {
        $verified = $this->client()->webhooks->verify($payload, options: ['asArray' => true]);

        return is_array($verified) ? $verified : (array) $verified;
    }

    public function confirmWebhook(string $url): array|string
    {
        return $this->client()->webhooks->confirm($url, options: ['asArray' => true]);
    }

    public function handleApiError(APIException $e): array
    {
        return [
            'message' => $e->getMessage(),
            'status' => method_exists($e, 'getCode') ? (int) $e->getCode() : 422,
        ];
    }

    private function client(): PayOS
    {
        return new PayOS(
            clientId: (string) config('services.payos.client_id'),
            apiKey: (string) config('services.payos.api_key'),
            checksumKey: (string) config('services.payos.checksum_key'),
        );
    }

    private function buildDescription(Order $order): string
    {
        return Str::limit('Thanh toan khoa hoc #' . $order->id, 25, '');
    }
}

