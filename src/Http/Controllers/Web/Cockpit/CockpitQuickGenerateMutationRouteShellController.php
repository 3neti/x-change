<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CockpitQuickGenerateMutationRouteShellController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'schema' => 'x-change.cockpit.quick-generate-mutation-route-shell.v1',
            'status' => 'route_shell_registered',
            'authorized' => $request->user() !== null,
            'mutation_enabled' => false,
            'runtime_enabled' => false,
            'route' => 'x-change.cockpit.quick-generate.store',
            'request_adapter' => 'GeneratePayCodeRequest-compatible-adapter',
            'issuance_owner' => 'GeneratePayCode',
            'validation' => [
                'status' => 'deferred',
                'request' => 'GeneratePayCodeRequest',
                'executed' => false,
            ],
            'handoff' => [
                'status' => 'not_executed',
                'target' => 'GeneratePayCode',
                'controller' => 'GeneratePayCodeController',
                'executed' => false,
            ],
            'idempotency' => [
                'status' => 'required-before-submit-enabled',
                'persisted' => false,
                'fingerprinted' => false,
                'replay_checked' => false,
            ],
            'redactions' => [
                'payloads' => 'route-shell-only',
                'request_payload' => 'excluded',
                'validated_payload' => 'excluded',
                'provider_payload' => 'excluded',
                'wallet' => 'excluded',
                'raw_payload' => 'excluded',
            ],
            'next_step' => 'Cockpit Mutation Wave 1C — Existing Issuance Handoff',
        ], 409);
    }
}
