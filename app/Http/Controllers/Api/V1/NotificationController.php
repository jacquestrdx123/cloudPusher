<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\DispatchPushNotification;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreNotificationRequest;
use App\Http\Resources\Api\PushNotificationResource;
use App\Models\Company;
use App\Models\PushNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class NotificationController extends Controller
{
    /**
     * List recent notifications for the authenticated company.
     */
    public function index(Request $request, Company $company): AnonymousResourceCollection
    {
        $notifications = PushNotification::query()
            ->where('company_id', $company->id)
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return PushNotificationResource::collection($notifications);
    }

    /**
     * Show a notification and its delivery outcomes.
     */
    public function show(Company $company, PushNotification $notification): PushNotificationResource|JsonResponse
    {
        if ($notification->company_id !== $company->id) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        $notification->load('deliveries');

        return new PushNotificationResource($notification);
    }

    /**
     * Queue a notification to a company user, group, or broadcast audience.
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
