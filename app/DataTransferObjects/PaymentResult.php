<?php

namespace App\DataTransferObjects;

use App\Enums\PaymentStatus;

readonly class PaymentResult
{
    public function __construct(
        public PaymentStatus $status,
        public ?string $transactionId = null,
        public ?string $errorMessage = null
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === PaymentStatus::Successful;
    }

    public function isFailed(): bool
    {
        return $this->status === PaymentStatus::Failed;
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }
}

