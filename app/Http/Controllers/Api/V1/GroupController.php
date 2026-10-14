<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\UserGroupResource;
use App\Models\Company;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GroupController extends Controller
{
    /**
     * List the company's user groups with member counts.
     */
    public function index(Company $company): AnonymousResourceCollection
    {
        $groups = $company->groups()
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return UserGroupResource::collection($groups);
    }
}
