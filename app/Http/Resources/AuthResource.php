<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthResource extends JsonResource
{
    private string $token;

    /**
     * Create a new resource instance.
     */
    public function __construct(User $user, string $token)
    {
        parent::__construct($user);
        $this->token = $token;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'token' => $this->token,
            'user' => new UserResource($this->resource),
        ];
    }
}

