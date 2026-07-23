<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Funding\CreateFundingIntent;
use LBHurtado\XChange\Actions\Funding\IssueFundingInstructions;
use LBHurtado\XChange\Contracts\FundingDestinationResolverContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\Funding\CreateFundingIntentData;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\CreateCockpitFundingIntentRequest;
use LBHurtado\XChange\Services\Cockpit\FundingInstructionPresenter;
use RuntimeException;

class CockpitFundingIntentController extends Controller
{
    public function __invoke(
        CreateCockpitFundingIntentRequest $request,
        WalletAccessContract $wallets,
        FundingDestinationResolverContract $destinations,
        CreateFundingIntent $createFundingIntent,
        IssueFundingInstructions $issueFundingInstructions,
        FundingInstructionPresenter $instructions,
    ): RedirectResponse {
        $actor = $request->user();
        $wallet = $wallets->resolveForUser($actor);
        $accountReference = $this->accountReference($wallet);
        $validated = $request->validated();
        $intent = $createFundingIntent->handle(new CreateFundingIntentData(
            accountReference: $accountReference,
            provider: $validated['provider'],
            expectedAmountMinor: $validated['amount_minor'],
            currency: $validated['currency'],
            idempotencyKey: $validated['idempotency_key'],
            actorType: $actor::class,
            actorId: (string) $actor->getAuthIdentifier(),
            expiresAt: new DateTimeImmutable(
                now()->addSeconds((int) config('x-change.funding.intent_ttl_seconds', 1800))->toIso8601String(),
            ),
            metadata: [
                'source' => 'x-change.cockpit',
            ],
            destination: $destinations->resolve(
                owner: $actor,
                provider: $validated['provider'],
                accountReference: $accountReference,
            ),
        ));
        $intent = $issueFundingInstructions->handle(
            intent: $intent,
            actorType: $actor::class,
            actorId: (string) $actor->getAuthIdentifier(),
        );

        return redirect()
            ->route('x-change.cockpit.funding.index')
            ->with('funding_instruction', $instructions->forIntent($intent));
    }

    private function accountReference(mixed $wallet): string
    {
        $uuid = data_get($wallet, 'uuid');

        if (is_string($uuid) && trim($uuid) !== '') {
            return 'wallet:'.trim($uuid);
        }

        if (is_object($wallet) && method_exists($wallet, 'getKey')) {
            return 'wallet:'.$wallet->getKey();
        }

        throw new RuntimeException('Funding Account reference could not be resolved.');
    }
}
