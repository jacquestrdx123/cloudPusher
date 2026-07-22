<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ProvisionCompany;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ProvisionCompanyRequest;
use App\Http\Resources\Api\ProvisionedCompanyResource;
use Illuminate\Http\JsonResponse;

class CompanyProvisioningController extends Controller
{
    /**
     * Create (or return, on slug conflict) a company for an upstream system.
     */
    public function store(ProvisionCompanyRequest $request, ProvisionCompany $provisionCompany): JsonResponse
    {
        $result = $provisionCompany->handle($request->companyData());

        return (new ProvisionedCompanyResource($result['company']))
            ->additional(['created' => $result['created']])
            ->response()
            ->setStatusCode($result['created'] ? 201 : 200);
    }
}
