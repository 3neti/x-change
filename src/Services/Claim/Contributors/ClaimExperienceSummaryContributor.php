<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\Claim\Contributors;

use Illuminate\Support\Facades\Route;
use LBHurtado\XChange\Actions\Claim\ResolveClaimExperience;
use LBHurtado\XChange\Contracts\Claim\ClaimSurfaceContributor;
use LBHurtado\XChange\Contracts\ClaimShareCardUrlResolverContract;
use LBHurtado\XChange\Contracts\ClaimShareMetadataResolverContract;
use LBHurtado\XChange\Data\Claim\ClaimSurfaceContextData;
use LBHurtado\XChange\Services\Claim\ClaimShareCardAmountFormatter;
use LBHurtado\XChange\Services\Claim\ClaimSurfaceBuilder;

/**
 * Converts live Rider/claim-experience steps into a static audit preview for
 * terminal or issuer-review claim surfaces. The live countdown/redirect
 * runtime remains owned by ClaimWidget for active claim journeys.
 */
final class ClaimExperienceSummaryContributor implements ClaimSurfaceContributor
{
    private const ISSUER_ROLES = ['issuer', 'admin'];

    public function __construct(
        private readonly ResolveClaimExperience $claimExperience,
        private readonly ClaimShareMetadataResolverContract $shareMetadata,
        private readonly ClaimShareCardUrlResolverContract $shareCardUrls,
        private readonly ClaimShareCardAmountFormatter $amounts,
    ) {}

    public function contribute(ClaimSurfaceBuilder $surface, ClaimSurfaceContextData $context): void
    {
        $isIssuerViewer = in_array($context->viewer->role, self::ISSUER_ROLES, true);

        if (! $context->state->terminal && ! ($isIssuerViewer && $context->hasClaimActivity())) {
            return;
        }

        $experience = $this->claimExperience->handle($context->voucher)->toArray();
        $summary = $this->summary($context, $experience);

        if ($summary === []) {
            return;
        }

        $surface->addComponent('claim_experience_summary', $summary);
    }

    /**
     * @param  array<string, mixed>  $experience
     * @return array<string, mixed>
     */
    private function summary(ClaimSurfaceContextData $context, array $experience): array
    {
        $riderIntro = $this->firstPhase($experience, 'rider_intro');
        $successRider = $this->firstPhase($experience, 'success_rider');
        $redirect = $this->firstPhase($experience, 'redirect');

        $splashStage = $this->firstStage($riderIntro, 'splash');
        $messageStage = $this->firstStage($successRider, 'message');
        $redirectUrl = data_get($redirect, 'url');

        $hasExperience = filled(data_get($messageStage, 'payload.content'))
            || filled(data_get($splashStage, 'payload.content'))
            || filled($redirectUrl);

        if (! $hasExperience) {
            return [];
        }

        return [
            'message' => $this->stageContent($messageStage),
            'splash' => $this->stageContent($splashStage),
            'redirect' => filled($redirectUrl) ? [
                'url' => (string) $redirectUrl,
                'delay_seconds' => data_get($redirect, 'delay_seconds'),
                'show_countdown' => (bool) data_get($redirect, 'show_countdown', false),
            ] : null,
            'og_meta' => $this->ogMeta($context, $messageStage),
            'options' => [
                'static_preview' => true,
                'disable_countdown' => true,
                'disable_auto_redirect' => true,
                'skip_consumed_splash' => (bool) data_get($experience, 'options.skip_consumed_splash', false),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $phase
     * @return array<string, mixed>|null
     */
    private function firstStage(?array $phase, string $type): ?array
    {
        $stage = collect(data_get($phase, 'stages', []))
            ->first(fn (mixed $stage): bool => is_array($stage) && data_get($stage, 'type') === $type);

        return is_array($stage) ? $stage : null;
    }

    /**
     * @param  array<string, mixed>|null  $stage
     * @return array<string, mixed>|null
     */
    private function stageContent(?array $stage): ?array
    {
        $content = data_get($stage, 'payload.content') ?? data_get($stage, 'content');

        if (! filled($content)) {
            return null;
        }

        return [
            'content' => (string) $content,
            'content_type' => (string) (data_get($stage, 'payload.content_type') ?? data_get($stage, 'content_type', 'html')),
            'presentation' => data_get($stage, 'payload.presentation') ?? data_get($stage, 'presentation'),
            'timeout' => data_get($stage, 'payload.timeout'),
            'meta' => (array) (data_get($stage, 'payload.meta') ?? data_get($stage, 'meta', [])),
        ];
    }

    /**
     * @param  array<string, mixed>  $experience
     * @return array<string, mixed>|null
     */
    private function firstPhase(array $experience, string $key): ?array
    {
        $phase = collect(data_get($experience, 'phases', []))
            ->first(fn (mixed $phase): bool => is_array($phase) && data_get($phase, 'key') === $key);

        return is_array($phase) ? $phase : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function ogMeta(
        ClaimSurfaceContextData $context,
        ?array $messageStage,
    ): array
    {
        $claimUrl = Route::has('x-change.claim.show')
            ? route('x-change.claim.show', ['code' => $context->code])
            : url('/x/claim/'.$context->code);

        $metadata = $this->shareMetadata
            ->resolve($context->voucher, $claimUrl, $this->shareCardUrls->resolve($context->voucher))
            ->toArray();

        return [
            ...$metadata,
            'amount_label' => $this->amounts->format($context->voucher),
            'message_preview' => $this->plainPreview(data_get(
                $messageStage,
                'payload.content',
            ) ?? data_get($messageStage, 'content')),
        ];
    }

    private function plainPreview(mixed $content): ?string
    {
        if (! is_string($content) || trim($content) === '') {
            return null;
        }

        $preview = trim((string) preg_replace(
            '/\s+/u',
            ' ',
            strip_tags($content),
        ));

        return $preview === ''
            ? null
            : mb_substr($preview, 0, 180);
    }
}
