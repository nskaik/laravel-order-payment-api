<?php

namespace App\Repositories\Contracts;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
    /**
     * Create a new order with items.
     */
    public function createWithItems(array $orderData, array $items): Order;

    /**
     * Find an order by ID.
     */
    public function findById(int $id): ?Order;

    /**
     * Find an order by ID with relationships.
     */
    public function findByIdWithRelations(int $id, array $relations = []): ?Order;

    /**
     * Get paginated orders for a user.
     */
    public function getPaginatedForUser(int $userId, ?OrderStatus $status = null, int $perPage = 15): LengthAwarePaginator;

    /**
     * Update an order.
     */
    public function update(Order $order, array $data): Order;

    /**
     * Update an order with items.
     */
    public function updateWithItems(Order $order, array $orderData, ?array $items = null): Order;

    /**
     * Delete an order.
     */
    public function delete(Order $order): bool;

    /**
     * Check if an order has associated payments.
     */
    public function hasPayments(Order $order): bool;
}

