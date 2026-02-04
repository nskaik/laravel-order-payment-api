<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

readonly class OrderService
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository
    ) {}

    /**
     * Create a new order with items.
     *
     * @param int $userId
     * @param array $items
     * @return Order
     */
    public function createOrder(int $userId, array $items): Order
    {
        $processedItems = $this->processItems($items);
        $totalAmount = $this->calculateTotalAmount($processedItems);

        $orderData = [
            'user_id' => $userId,
            'status' => OrderStatus::Pending,
            'total_amount' => $totalAmount,
        ];

        return $this->orderRepository->createWithItems($orderData, $processedItems);
    }

    /**
     * Get paginated orders for a user.
     *
     * @param int $userId
     * @param OrderStatus|null $status
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getOrdersForUser(int $userId, ?OrderStatus $status = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->orderRepository->getPaginatedForUser($userId, $status, $perPage);
    }

    /**
     * Get a single order by ID.
     *
     * @param int $id
     * @return Order|null
     */
    public function getOrderById(int $id): ?Order
    {
        return $this->orderRepository->findByIdWithRelations($id, ['items', 'payment']);
    }

    /**
     * Update an order's items and recalculate the total amount.
     *
     * @param Order $order
     * @param array $data
     * @return Order
     */
    public function updateOrder(Order $order, array $data): Order
    {
        $items = $this->processItems($data['items']);
        $orderData = [
            'total_amount' => $this->calculateTotalAmount($items),
        ];

        return $this->orderRepository->updateWithItems($order, $orderData, $items);
    }

    /**
     * Delete an order.
     *
     * @param Order $order
     * @return bool
     */
    public function deleteOrder(Order $order): bool
    {
        return $this->orderRepository->delete($order);
    }

    /**
     * Check if an order has associated payments.
     *
     * @param Order $order
     * @return bool
     */
    public function hasPayments(Order $order): bool
    {
        return $this->orderRepository->hasPayments($order);
    }

    /**
     * Confirm an order.
     *
     * @param Order $order
     * @return Order
     */
    public function confirmOrder(Order $order): Order
    {
        return $this->orderRepository->update($order, ['status' => OrderStatus::Confirmed]);
    }

    /**
     * Cancel an order.
     *
     * @param Order $order
     * @return Order
     */
    public function cancelOrder(Order $order): Order
    {
        return $this->orderRepository->update($order, ['status' => OrderStatus::Cancelled]);
    }

    /**
     * Process items and calculate subtotals.
     *
     * @param array $items
     * @return array
     */
    private function processItems(array $items): array
    {
        return array_map(function ($item) {
            return [
                'product_name' => $item['product_name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => bcmul($item['quantity'], $item['unit_price'], 2),
            ];
        }, $items);
    }

    /**
     * Calculate total amount from processed items.
     *
     * @param array $items
     * @return string
     */
    private function calculateTotalAmount(array $items): string
    {
        return array_reduce($items, function ($carry, $item) {
            return bcadd($carry, $item['subtotal'], 2);
        }, '0.00');
    }
}

