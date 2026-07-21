<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\LoginWithPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginWithPasswordRequest;
use App\Http\Resources\Api\AuthUserResource;
use App\Models\User;
use App\Models\UserApiToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    /**
     * Authenticate a mobile user with phone and password.
     */
    public function login(
        LoginWithPasswordRequest $request,
        LoginWithPassword $loginWithPassword,
    ): JsonResponse {
        $result = $loginWithPassword->handle(
            $request->string('phone')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json([
            'token' => $result['plain_text_token'],
            'token_type' => $result['token_type'],
            'user' => (new AuthUserResource($result['user']))->resolve(),
        ]);
    }

    /**
     * Return the authenticated mobile user.
     */
    public function me(Request $request): AuthUserResource
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing(['companies' => fn ($query) => $query->where('is_active', true)]);

        return new AuthUserResource($user);
    }

    /**
     * Revoke the current personal API token.
     */
    public function logout(Request $request): Response
    {
        $apiToken = $request->attributes->get('api_token');

        if ($apiToken instanceof UserApiToken) {
            $apiToken->delete();
        }

        return response()->noContent();
    }
}
