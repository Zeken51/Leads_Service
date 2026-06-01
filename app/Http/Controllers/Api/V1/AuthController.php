<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Enums\ApiAbility;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Revocar tokens anteriores del mismo nombre si existen
        $user->tokens()->where('name', 'api-access')->delete();

        $token = $user->createToken('api-access', ApiAbility::forAgent());

        return ApiResponse::success([
            'token'      => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => null,
        ]);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::noContent();
    }
}
