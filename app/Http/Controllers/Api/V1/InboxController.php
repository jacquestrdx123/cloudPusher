<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Concerns\ResolvesCompanyApiUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\InboxItemResource;
use App\Models\Company;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class InboxController extends Controller
{
    use ResolvesCompanyApiUser;

    /**
     * List stored inbox notifications for a company user.
     */
    public function index(Request $request, Company $company): AnonymousResourceCollection
    {
        $user = $this->resolveUser($request, $company);

        $notifications = UserNotification::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->latest('delivered_at')
            ->latest('id')
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return InboxItemResource::collection($notifications);
    }

    /**
     * Show a single inbox notification.
     */
    public function show(Request $request, Company $company, UserNotification $inbox): InboxItemResource|JsonResponse
    {
        $user = $this->resolveUser($request, $company);

        if ($inbox->company_id !== $company->id || $inbox->user_id !== $user->id) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        return new InboxItemResource($inbox);
    }

    /**
     * Mark a single inbox notification as read.
     */
    public function markRead(Request $request, Company $company, UserNotification $inbox): InboxItemResource|JsonResponse
    {
        $user = $this->resolveUser($request, $company);

        if ($inbox->company_id !== $company->id || $inbox->user_id !== $user->id) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        if ($inbox->read_at === null) {
            $inbox->update(['read_at' => now()]);
        }

        return new InboxItemResource($inbox->fresh());
    }

    /**
     * Mark every inbox notification as read for the user.
     */
    public function markAllRead(Request $request, Company $company): Response
    {
        $user = $this->resolveUser($request, $company);

        UserNotification::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->noContent();
    }
}
