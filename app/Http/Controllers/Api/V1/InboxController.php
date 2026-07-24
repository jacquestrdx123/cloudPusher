<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\MarkNotificationDelivered;
use App\Http\Concerns\ResolvesCompanyApiUser;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\InboxItemResource;
use App\Models\Company;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class InboxController extends Controller
{
    use ResolvesCompanyApiUser;

    public function __construct(private MarkNotificationDelivered $markNotificationDelivered) {}

    /**
     * List inbox notifications across all companies for the authenticated user.
     */
    public function unifiedIndex(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $companySlug = $request->string('company')->toString();

        $notifications = UserNotification::query()
            ->with('company')
            ->where('user_id', $user->id)
            ->when(
                $companySlug !== '',
                fn ($query) => $query->whereHas(
                    'company',
                    fn ($companies) => $companies->where('slug', $companySlug),
                ),
            )
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->latest('delivered_at')
            ->latest('id')
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return InboxItemResource::collection($notifications);
    }

    /**
     * Mark a single inbox notification as read (any membership company).
     */
    public function unifiedMarkRead(Request $request, UserNotification $inbox): InboxItemResource|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ((int) $inbox->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Notification not found.'], 404);
        }

        if ($inbox->read_at === null) {
            $inbox->update(['read_at' => now()]);
        }

        $this->markNotificationDelivered->handle($inbox);

        return new InboxItemResource($inbox->fresh()->load('company'));
    }

    /**
     * Mark inbox notifications as read across all (or one filtered) companies.
     */
    public function unifiedMarkAllRead(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $companySlug = $request->string('company')->toString();

        UserNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->when(
                $companySlug !== '',
                fn ($query) => $query->whereHas(
                    'company',
                    fn ($companies) => $companies->where('slug', $companySlug),
                ),
            )
            ->update(['read_at' => now()]);

        return response()->noContent();
    }

    /**
     * List stored inbox notifications for a company user.
     */
    public function index(Request $request, Company $company): AnonymousResourceCollection
    {
        $user = $this->resolveUser($request, $company);

        $notifications = UserNotification::query()
            ->with('company')
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

        return new InboxItemResource($inbox->loadMissing('company'));
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

        $this->markNotificationDelivered->handle($inbox);

        return new InboxItemResource($inbox->fresh()->load('company'));
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
