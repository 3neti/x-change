<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Lifecycle;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleScenarioEngine;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\LifecycleScenarioRunOptions;
use LBHurtado\XChange\Console\Commands\Lifecycle\ScenarioRunners\Support\NullLifecycleOutput;

final class RunLifecycleScenarioController
{
    public function __invoke(
        Request $request,
        LifecycleScenarioEngine $engine,
    ): JsonResponse {
        $validated = $request->validate([
            'scenario' => ['required', 'string'],
            'only_attempt' => ['nullable', 'string'],
            'issuer' => ['nullable', 'string'],
            'wallet' => ['nullable', 'string'],
            'amount' => ['nullable', 'numeric'],
            'timeout' => ['nullable', 'integer'],
            'poll' => ['nullable', 'integer'],
            'max_polls' => ['nullable', 'integer'],
            'no_claim' => ['nullable', 'boolean'],
            'accept_pending' => ['nullable', 'boolean'],
        ]);

        $options = LifecycleScenarioRunOptions::fromApiPayload($validated);

        $result = $engine->run(
            command: app(\Illuminate\Console\Command::class), // still required by engine signature
            scenarioKey: $validated['scenario'],
            options: $options,
            output: new NullLifecycleOutput(),
        );

        return response()->json(
            $result->payload,
            $result->exitCode === 0 ? 200 : 422
        );
    }
}
