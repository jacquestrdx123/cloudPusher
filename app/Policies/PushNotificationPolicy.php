<?php

namespace App\Policies;

use App\Models\PushNotification;
use App\Models\User;

class PushNotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isGlobalAdmin() || $user->isCompanyAdmin();
    }

    public function view(User $user, PushNotification $notification): bool
    {
        return $user->canAdministerCompany($notification->company);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PushNotification $notification): bool
    {
        return false;
    }

    public function delete(User $user, PushNotification $notification): bool
    {
        return $user->isGlobalAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isGlobalAdmin();
    }
}
