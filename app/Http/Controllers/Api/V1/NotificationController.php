<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\DispatchPushNotification;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreNotificationRequest;
use App\Http\Resources\Api\PushNotificationResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class NotificationController extends Controller
{
    /**
     * Queue a notification to a company user or user group.
     */
    public function store(
        StoreNotificationRequest $request,
        Company $company,
        DispatchPushNotification $dispatchPushNotification,
    ): JsonResponse {
        try {
            $notification = $dispatchPushNotification->handle($company, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return (new PushNotificationResource($notification))
            ->response()
            ->setStatusCode(202);
    }
}
