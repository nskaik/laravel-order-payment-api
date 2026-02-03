<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return response()->json([
                    'error' => 'User not found',
                ], 401);
            }
        } catch (TokenExpiredException $e) {
            return response()->json([
                'error' => 'Token has expired',
            ], 401);
        } catch (TokenInvalidException $e) {
            return response()->json([
                'error' => 'Token is invalid',
            ], 401);
        } catch (JWTException $e) {
            return response()->json([
                'error' => 'Token is missing',
            ], 401);
        }

        return $next($request);
    }
}

