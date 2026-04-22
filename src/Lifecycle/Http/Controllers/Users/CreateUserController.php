<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Users;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\UserLifecycleServiceContract;
use LBHurtado\XChange\Lifecycle\Http\Requests\Users\CreateUserRequest;
use LBHurtado\XChange\Lifecycle\Http\Resources\Users\UserResource;

class CreateUserController extends Controller
{
    public function __invoke(
        CreateUserRequest $request,
        UserLifecycleServiceContract $users,
    ): JsonResponse {
        $result = $users->create($request->validated());

        return UserResource::make($result)
            ->response()
            ->setStatusCode(201);
    }
}
