<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Users;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\UserLifecycleServiceContract;
use LBHurtado\XChange\Lifecycle\Http\Requests\Users\SubmitUserKycRequest;
use LBHurtado\XChange\Lifecycle\Http\Resources\Users\UserKycResource;

class SubmitUserKycController extends Controller
{
    public function __invoke(
        string $user,
        SubmitUserKycRequest $request,
        UserLifecycleServiceContract $users,
    ): JsonResponse {
        $result = $users->submitKyc($user, $request->validated());

        return UserKycResource::make($result)
            ->response()
            ->setStatusCode(200);
    }
}
