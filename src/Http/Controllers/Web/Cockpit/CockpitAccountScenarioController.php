<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Lifecycle\RunAccountManagementScenario;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\RunCockpitAccountScenarioRequest;

final class CockpitAccountScenarioController extends Controller
{
    /**
     * @throws AuthenticationException
     */
    public function __invoke(
        RunCockpitAccountScenarioRequest $request,
        RunAccountManagementScenario $run,
    ): JsonResponse {
        $operator = $request->user();

        if (! $operator instanceof Model) {
            throw new AuthenticationException;
        }

        $result = $run->handle($operator);

        return response()->json(
            $result->payload,
            $result->exitCode === 0 ? 200 : 422,
        );
    }
}
