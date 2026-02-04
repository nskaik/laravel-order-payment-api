<?php

namespace App\Gateways;

use App\Contracts\PaymentGatewayInterface;
use App\DataTransferObjects\PaymentResult;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Support\Str;

class CreditCardGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly array $config = []
    ) {}

    public function process(Order $order, array $paymentData): PaymentResult
    {
        usleep(100000);

        // If card_number ends with '0000', always succeed
        // If card_number ends with '9999', always fail
        // Otherwise, random 80% success rate
        $cardNumber = $paymentData['card_number'] ?? '';
        $cardLastFourDigits = substr($cardNumber, -4);

        if ($cardLastFourDigits === '0000') {
            return new PaymentResult(
                status: PaymentStatus::Successful,
                transactionId: $this->generateTransactionId()
            );
        }

        if ($cardLastFourDigits === '9999') {
            return new PaymentResult(
                status: PaymentStatus::Failed,
                errorMessage: 'Card declined: insufficient funds'
            );
        }

        if (random_int(1, 100) <= 80) {
            return new PaymentResult(
                status: PaymentStatus::Successful,
                transactionId: $this->generateTransactionId()
            );
        }

        return new PaymentResult(
            status: PaymentStatus::Failed,
            errorMessage: 'Payment processing failed. Please try again.'
        );
    }

    private function generateTransactionId(): string
    {
        return 'CC_' . strtoupper(Str::random()) . '_' . time();
    }
}

