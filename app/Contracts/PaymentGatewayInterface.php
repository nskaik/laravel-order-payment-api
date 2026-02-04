<?php

namespace App\Contracts;

use App\DataTransferObjects\PaymentResult;
use App\Models\Order;

interface PaymentGatewayInterface
{
    /**
     * Process a payment for the given order.
     */
    public function process(Order $order, array $paymentData): PaymentResult;
}

