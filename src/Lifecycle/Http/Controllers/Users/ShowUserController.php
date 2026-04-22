<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Users;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\UserLifecycleServiceContract;
use LBHurtado\XChange\Lifecycle\Http\Resources\Users\UserResource;

class ShowUserController extends Controller
{
    public function __invoke(
        string $user,
        UserLifecycleServiceContract $users,
    ): JsonResponse {
        $result = $users->show($user);

        return UserResource::make($result)
            ->response()
            ->setStatusCode(200);
    }
}
