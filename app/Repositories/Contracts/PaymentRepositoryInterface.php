<?php

namespace App\Repositories\Contracts;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PaymentRepositoryInterface
{
    public function create(array $data): Payment;

    public function findByIdWithRelations(int $id, array $relations = []): ?Payment;

    public function findByOrderId(int $orderId): ?Payment;

    public function getPaginatedForUser(int $userId, int $perPage = 15): LengthAwarePaginator;

    public function orderHasPayment(Order $order): bool;
}

