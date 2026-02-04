<?php

namespace App\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\DataTransferObjects\PaymentResult;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Support\Str;

readonly class PayPalGateway implements PaymentGatewayInterface
{
    public function __construct(
        private array $config = []
    ) {}

    public function process(Order $order, array $paymentData): PaymentResult
    {
        usleep(150000);

        // If paypal_email contains 'success', always succeed
        // If paypal_email contains 'fail', always fail
        // Otherwise, random 85% success rate
        $paypalEmail = $paymentData['paypal_email'] ?? '';

        if (str_contains(strtolower($paypalEmail), 'success')) {
            return new PaymentResult(
                status: PaymentStatus::Successful,
                transactionId: $this->generateTransactionId()
            );
        }

        if (str_contains(strtolower($paypalEmail), 'fail')) {
            return new PaymentResult(
                status: PaymentStatus::Failed,
                errorMessage: 'PayPal payment declined: account verification required'
            );
        }

        if (random_int(1, 100) <= 85) {
            return new PaymentResult(
                status: PaymentStatus::Successful,
                transactionId: $this->generateTransactionId()
            );
        }

        return new PaymentResult(
            status: PaymentStatus::Failed,
            errorMessage: 'PayPal payment could not be processed. Please try again.'
        );
    }

    private function generateTransactionId(): string
    {
        return 'PP_' . strtoupper(Str::random()) . '_' . time();
    }
}

