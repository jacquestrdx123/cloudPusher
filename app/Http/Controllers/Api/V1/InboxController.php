<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\InboxItemResource;
use App\Models\Company;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InboxController extends Controller
{
    /**
     * List notification deliveries for a company user (inbox feed).
     */
    public function index(Request $request, Company $company): AnonymousResourceCollection
    {
        $user = $this->resolveUser($request, $company);

        $deliveries = NotificationDelivery::query()
            ->with(['pushNotification'])
            ->where('user_id', $user->id)
            ->whereHas('pushNotification', fn ($query) => $query->where('company_id', $company->id))
            ->latest()
            ->paginate(min((int) $request->integer('per_page', 25), 100));

        return InboxItemResource::collection($deliveries);
    }

    private function resolveUser(Request $request, Company $company): User
    {
        $userRef = $request->validate([
            'user' => ['required', 'array'],
            'user.id' => ['nullable', 'integer'],
            'user.email' => ['nullable', 'email'],
        ])['user'];

        if (! isset($userRef['id']) && ! isset($userRef['email'])) {
            abort(422, 'A user reference requires "id" or "email".');
        }

        $user = $company->users()
            ->when(isset($userRef['id']), fn ($query) => $query->whereKey($userRef['id']))
            ->when(isset($userRef['email']), fn ($query) => $query->where('email', $userRef['email']))
            ->first();

        if ($user === null) {
            abort(404, 'The requested user does not exist for this company.');
        }

        return $user;
    }
}
