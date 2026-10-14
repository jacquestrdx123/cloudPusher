<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SendMemberMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreMemberMessageRequest;
use App\Models\Company;
use App\Models\PushNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class MessageController extends Controller
{
    /**
     * Queue a push message from the authenticated member to people, a group, or the whole company.
     */
    public function store(
        StoreMemberMessageRequest $request,
        Company $company,
        SendMemberMessage $sendMemberMessage,
    ): JsonResponse {
        /** @var User $sender */
        $sender = $request->user();

        $notifications = $sendMemberMessage->handle($company, $sender, $request->payload());

        return response()->json([
            'data' => [
                'sent' => count($notifications),
                'notification_ids' => array_map(
                    fn (PushNotification $notification): int => $notification->id,
                    $notifications,
                ),
            ],
        ], 202);
    }
}
