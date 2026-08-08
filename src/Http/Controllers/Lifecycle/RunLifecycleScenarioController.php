<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Lifecycle;

use Illuminate\Console\Command;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use LBHurtado\XChange\Lifecycle\Output\NullLifecycleOutput;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioEngine;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRepository;
use LBHurtado\XChange\Lifecycle\Scenarios\LifecycleScenarioRunOptions;

final class RunLifecycleScenarioController
{
    public function __invoke(
        Request $request,
        LifecycleScenarioEngine $engine,
        LifecycleScenarioRepository $scenarios,
    ): JsonResponse {
        $validated = $request->validate([
            'scenario' => ['required', 'string'],
            'only_attempt' => ['nullable', 'string'],
            'issuer' => ['nullable', 'string'],
            'maker' => ['nullable', 'string'],
            'checker' => ['nullable', 'string'],
            'wallet' => ['nullable', 'string'],
            'amount' => ['nullable', 'numeric'],
            'timeout' => ['nullable', 'integer'],
            'poll' => ['nullable', 'integer'],
            'max_polls' => ['nullable', 'integer'],
            'no_claim' => ['nullable', 'boolean'],
            'accept_pending' => ['nullable', 'boolean'],
        ]);

        try {
            $scenario = $scenarios->findOrFail($validated['scenario']);
        } catch (InvalidArgumentException) {
            $scenario = null;
        }

        if ($scenario !== null && data_get($scenario, 'api_executable', true) !== true) {
            return response()->json([
                'success' => false,
                'message' => 'This lifecycle scenario is not executable through the generic lifecycle API.',
                'scenario' => $validated['scenario'],
            ], 403);
        }

        $options = LifecycleScenarioRunOptions::fromApiPayload($validated);

        $result = $engine->run(
            command: app(Command::class), // still required by engine signature
            scenarioKey: $validated['scenario'],
            options: $options,
            output: new NullLifecycleOutput,
        );

        return response()->json(
            $result->payload,
            $result->exitCode === 0 ? 200 : 422
        );
    }
}
