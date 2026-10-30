<?php

declare(strict_types=1);

namespace LBHurtado\XChange\ClaimWalkthrough;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\XChange\Contracts\CockpitIssuanceDraftCompilerContract;
use LBHurtado\XChange\Contracts\CockpitIssuanceDraftValidatorContract;
use LBHurtado\XChange\Contracts\CockpitQuickGenerateDraftFactoryContract;
use LBHurtado\XChange\Data\Cockpit\CockpitIssuanceDraftValidationResultData;
use LBHurtado\XChange\Enums\ClaimPreviewProgressState;
use LBHurtado\XChange\Http\Requests\GeneratePayCodeRequest;
use LBHurtado\XChange\Models\ClaimPreviewArtifact;
use LBHurtado\XChange\Services\Cockpit\CompileCockpitQuickGenerateClaimPolicy;
use LBHurtado\XChange\Services\Cockpit\SettlementAttestationDraftProjection;
use LBHurtado\XChange\Services\IdempotencyService;
use LBHurtado\XChange\Services\PublicIssuance\PublicIssuanceInstructionPolicy;

final class GenerateClaimPreview
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly CockpitQuickGenerateDraftFactoryContract $quickGenerateDraftFactory,
        private readonly CockpitIssuanceDraftValidatorContract $draftValidator,
        private readonly CockpitIssuanceDraftCompilerContract $draftCompiler,
        private readonly CompileCockpitQuickGenerateClaimPolicy $claimPolicy,
        private readonly ClaimExperiencePreviewService $previews,
        private readonly ClaimPreviewWebManifestPresenter $presenter,
        private readonly ClaimPreviewRequestContext $context,
        private readonly PublicIssuanceInstructionPolicy $publicPolicy,
    ) {}

    public function handle(GeneratePayCodeRequest $request): JsonResponse
    {
        $progressState = ClaimPreviewProgressState::tryFrom(
            (string) $request->input('preview_state', ClaimPreviewProgressState::Current->value),
        );

        if ($progressState === null) {
            throw ValidationException::withMessages([
                'preview_state' => 'The selected claim preview state is invalid.',
            ]);
        }

        $payload = $this->normalizePayloadForIssuance($request->validated());
        if ($this->context->isPublic($request)) {
            $this->publicPolicy->assertAllowed($payload, allowCommissionedStoredValue: true);
        }

        $key = $this->idempotency->extractKey($request);
        $correlationId = $request->header((string) config('x-change.api.correlation.header', 'X-Correlation-ID'));

        $projection = app(SettlementAttestationDraftProjection::class);
        $draft = $this->quickGenerateDraftFactory->fromPayload(
            $projection->forDraft($payload),
            idempotencyKey: $key,
            correlationId: is_string($correlationId) ? $correlationId : null,
        );
        $this->ensureDraftIsValid($this->draftValidator->validate($draft));

        $payload = $projection->restore(array_replace_recursive($payload, $this->draftCompiler->compile($draft)), $payload);
        $payload = $this->claimPolicy->handle($payload);

        if ($this->context->isPublic($request)) {
            $this->publicPolicy->assertAllowed($payload, allowCommissionedStoredValue: true);
        }

        if ($request->user()?->getAuthIdentifier() !== null) {
            data_set($payload, 'metadata.issuer_id', (string) $request->user()->getAuthIdentifier());
        }

        $result = $this->previews->renderFromInstructions(
            VoucherInstructionsData::createFromAttribs($payload),
            new ClaimExperiencePreviewOptions(
                issuer: $request->user(),
                storageScope: $this->context->storageScope($request),
                guestScope: $this->context->isPublic($request) ? $this->context->guestScope($request) : null,
                expiresAt: $this->context->isPublic($request) ? now()->addMinutes(30)->toImmutable() : null,
                baseUrl: rtrim((string) config('app.url', 'http://localhost'), '/'),
                profile: (string) $request->input('preview_profile', 'issuer'),
                dryRun: $request->boolean('dry_run'),
                refresh: $request->boolean('refresh_preview'),
                mobile: (string) $request->input('preview_mobile', '09173011987'),
                bankCode: (string) $request->input('preview_bank_code', 'GXCHPHM2XXX'),
                accountNumber: (string) $request->input('preview_account_number', '09173011987'),
                progressState: $progressState,
            ),
        );
        $artifact = ClaimPreviewArtifact::query()
            ->where('reference', $result['reference'])
            ->firstOrFail();

        return response()->json(
            $this->presenter->present(
                $artifact,
                (bool) ($result['cache_hit'] ?? false),
                $this->context->routeFamily($request),
            ),
        )->withHeaders([
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
