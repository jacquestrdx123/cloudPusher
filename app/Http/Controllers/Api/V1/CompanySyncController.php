<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SyncCompanyGroups;
use App\Actions\SyncCompanyUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SyncCompanyDirectoryRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;

class CompanySyncController extends Controller
{
    /**
     * Declaratively sync a company's users and user groups from an upstream
     * system. Users are reconciled before groups so freshly synced users can
     * be referenced as group members in the same request.
     */
    public function sync(
        SyncCompanyDirectoryRequest $request,
        Company $company,
        SyncCompanyUsers $syncCompanyUsers,
        SyncCompanyGroups $syncCompanyGroups,
    ): JsonResponse {
        $response = [
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
            ],
        ];

        if ($request->has('users')) {
            $response['users'] = $syncCompanyUsers->handle(
                $company,
                $request->users(),
                $request->deleteMissingUsers(),
            );
        }

        if ($request->has('groups')) {
            $response['groups'] = $syncCompanyGroups->handle(
                $company,
                $request->groups(),
                $request->deleteMissingGroups(),
            );
        }

        return response()->json($response);
    }
}
