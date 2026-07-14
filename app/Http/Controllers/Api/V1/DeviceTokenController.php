<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegisterDeviceToken;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterDeviceTokenRequest;
use App\Http\Resources\Api\DeviceTokenResource;
use App\Models\Company;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use InvalidArgumentException;

class DeviceTokenController extends Controller
{
    /**
     * Register or update a device token for a company user.
     */
    public function store(
        RegisterDeviceTokenRequest $request,
        Company $company,
        RegisterDeviceToken $registerDeviceToken,
    ): JsonResponse {
        try {
            $deviceToken = $registerDeviceToken->handle($company, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return (new DeviceTokenResource($deviceToken))
            ->response()
            ->setStatusCode($deviceToken->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Remove a device token that belongs to a user in this company.
     */
    public function destroy(Company $company, DeviceToken $deviceToken): Response|JsonResponse
    {
        $belongsToCompany = $company->users()
            ->whereKey($deviceToken->user_id)
            ->exists();

        if (! $belongsToCompany) {
            return response()->json([
                'message' => 'The device token does not belong to this company.',
            ], 404);
        }

        $deviceToken->delete();

        return response()->noContent();
    }
}
