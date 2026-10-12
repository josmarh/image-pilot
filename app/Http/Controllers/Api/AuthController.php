<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\Api\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where(
            'email',
            $request->string('email')
        )->first();

        if (
            !$user ||
            !Hash::check(
                $request->string('password'),
                $user->password
            )
        ) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $token = $user->createToken(
            'imagepilot-api'
        )->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',

            'data' => [
                'token' => $token,

                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
        ]);
    }
}
