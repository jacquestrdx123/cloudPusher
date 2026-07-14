<?php

namespace App\Http\Controllers\Api;

use App\Actions\DispatchPushNotification;
use App\Http\Controllers\Controller;
use App\Http\Requests\WebhookPushRequest;
use App\Http\Resources\Api\PushNotificationResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class WebhookController extends Controller
{
    /**
     * Accept a signed webhook and queue a push to a user or group.
     */
    public function push(
        WebhookPushRequest $request,
        Company $company,
        DispatchPushNotification $dispatchPushNotification,
    ): JsonResponse {
        try {
            $notification = $dispatchPushNotification->handle($company, $request->payload());
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
