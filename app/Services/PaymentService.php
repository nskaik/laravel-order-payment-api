<?php

namespace App\Services;

use App\Contracts\PaymentGatewayInterface;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

readonly class PaymentService
{
    private array $gatewayMap;

    public function __construct(
        private PaymentRepositoryInterface $paymentRepository
    )
    {
        $this->gatewayMap = config('payment.gateways', []);
    }

    /**
     * Process a payment for an order.
     */
    public function processPayment(Order $order, string $paymentMethod, array $paymentData = []): Payment
    {
        if ($order->status !== OrderStatus::Confirmed) {
            throw new InvalidArgumentException('Payments can only be processed for confirmed orders.');
        }

        if ($this->paymentRepository->orderHasPayment($order)) {
            throw new InvalidArgumentException('This order already has a payment.');
        }

        // Get the appropriate gateway
        $gateway = $this->resolveGateway($paymentMethod);

        // Process the payment through the gateway
        $result = $gateway->process($order, $paymentData);

        return $this->paymentRepository->create([
            'order_id' => $order->id,
            'status' => $result->status,
            'payment_method' => $paymentMethod,
            'amount' => $order->total_amount,
            'transaction_id' => $result->transactionId,
        ]);
    }

    /**
     * Resolve the payment gateway based on the payment method.
     */
    private function resolveGateway(string $paymentMethod): PaymentGatewayInterface
    {
        if (!isset($this->gatewayMap[$paymentMethod])) {
            throw new InvalidArgumentException("Unsupported payment method: {$paymentMethod}");
        }

        $gatewayClass = $this->gatewayMap[$paymentMethod];

        return app($gatewayClass);
    }

    /**
     * Get paginated payments for a user.
     */
    public function getPaymentsForUser(int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->paymentRepository->getPaginatedForUser($userId, $perPage);
    }

    /**
     * Get a payment by ID.
     */
    public function getPaymentById(int $id): ?Payment
    {
        return $this->paymentRepository->findByIdWithRelations($id, ['order']);
    }

    /**
     * Get a payment by order ID.
     */
    public function getPaymentByOrderId(int $orderId): ?Payment
    {
        return $this->paymentRepository->findByOrderId($orderId);
    }
}

