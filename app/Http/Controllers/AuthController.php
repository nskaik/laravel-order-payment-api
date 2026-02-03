<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\AuthResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    /**
     * Register a new user.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->authService->register($request->validated());

        return (new AuthResource($result->user, $result->token))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Log in a user and return a JWT token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated());

        if ($result === null) {
            return response()->json([
                'error' => 'Invalid credentials',
            ], 401);
        }

        return (new AuthResource($result->user, $result->token))
            ->response()
            ->setStatusCode(200);
    }
}

