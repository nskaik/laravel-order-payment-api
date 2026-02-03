<?php

namespace App\Services;

use App\DataTransferObjects\AuthResult;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Tymon\JWTAuth\Facades\JWTAuth;

readonly class AuthService
{
    public function __construct(
        private UserRepositoryInterface $userRepository
    ) {}

    public function register(array $data): AuthResult
    {
        $user = $this->userRepository->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $token = JWTAuth::fromUser($user);

        return new AuthResult($user, $token);
    }

    public function login(array $credentials): ?AuthResult
    {
        $token = auth('api')->attempt($credentials);

        if (!$token) {
            return null;
        }

        /** @var User $user */
        $user = auth('api')->user();

        return new AuthResult($user, $token);
    }
}

