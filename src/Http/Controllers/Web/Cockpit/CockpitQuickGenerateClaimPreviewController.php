<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\ClaimWalkthrough\ClaimExperiencePreviewOptions;
use LBHurtado\XChange\ClaimWalkthrough\ClaimExperiencePreviewService;
use LBHurtado\XChange\Contracts\CockpitIssuanceDraftCompilerContract;
use LBHurtado\XChange\Contracts\CockpitIssuanceDraftValidatorContract;
use LBHurtado\XChange\Contracts\CockpitQuickGenerateDraftFactoryContract;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftValidationResultData;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;
use LBHurtado\XChange\Services\Cockpit\CompileCockpitQuickGenerateClaimPolicy;
use LBHurtado\XChange\Services\IdempotencyService;

final class CockpitQuickGenerateClaimPreviewController extends Controller
{
    public function __invoke(
        GeneratePayCodeRequest $request,
        IdempotencyService $idempotency,
        CockpitQuickGenerateDraftFactoryContract $quickGenerateDraftFactory,
        CockpitIssuanceDraftValidatorContract $draftValidator,
        CockpitIssuanceDraftCompilerContract $draftCompiler,
        CompileCockpitQuickGenerateClaimPolicy $claimPolicy,
        ClaimExperiencePreviewService $previews,
    ): JsonResponse {
        $payload = $this->normalizePayloadForIssuance($request->validated());
        $key = $idempotency->extractKey($request);
        $correlationId = $request->header((string) config('x-change.api.correlation.header', 'X-Correlation-ID'));

        $draft = $quickGenerateDraftFactory->fromPayload(
            $payload,
            idempotencyKey: $key,
            correlationId: is_string($correlationId) ? $correlationId : null,
        );
        $this->ensureDraftIsValid($draftValidator->validate($draft));

        $payload = array_replace_recursive($payload, $draftCompiler->compile($draft));
        $payload = $claimPolicy->handle($payload);

        if ($request->user()?->getAuthIdentifier() !== null) {
            data_set($payload, 'metadata.issuer_id', (string) $request->user()->getAuthIdentifier());
        }

        $result = $previews->renderFromInstructions(
            VoucherInstructionsData::createFromAttribs($payload),
            new ClaimExperiencePreviewOptions(
                issuer: $request->user(),
                baseUrl: rtrim((string) config('app.url', 'http://localhost'), '/'),
                profile: (string) $request->input('preview_profile', 'issuer'),
                dryRun: $request->boolean('dry_run'),
                refresh: $request->boolean('refresh_preview'),
                mobile: (string) $request->input('preview_mobile', '09173011987'),
                bankCode: (string) $request->input('preview_bank_code', 'GXCHPHM2XXX'),
                accountNumber: (string) $request->input('preview_account_number', '09173011987'),
            ),
        );

        return response()->json([
            ...$result,
            'source' => 'cockpit.quick-generate',
            'money_movement' => false,
            'provider_calls' => false,
        ])->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayloadForIssuance(array $payload): array
    {
        if (data_get($payload, 'cash.validation') === null) {
            data_set($payload, 'cash.validation', []);
        }

        if (data_get($payload, 'count') === null) {
            data_set($payload, 'count', 1);
        }

        return $payload;
    }

    private function ensureDraftIsValid(CockpitIssuanceDraftValidationResultData $validation): void
    {
        if ($validation->valid) {
            return;
        }

        throw ValidationException::withMessages([
            'draft' => array_values($validation->errors),
        ]);
    }
}
