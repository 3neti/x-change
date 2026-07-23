<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Lifecycle\Http\Controllers\Funding;

use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Funding\CreateFundingIntent;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\Funding\CreateFundingIntentData;
use LBHurtado\XChange\Lifecycle\Http\Requests\Funding\CreateFundingIntentRequest;
use LBHurtado\XChange\Lifecycle\Http\Resources\Funding\FundingIntentResource;

class CreateFundingIntentController extends Controller
{
    public function __invoke(
        CreateFundingIntentRequest $request,
        WalletAccessContract $wallets,
        CreateFundingIntent $createFundingIntent,
    ): JsonResponse {
        $actor = $request->user();
        $wallet = $wallets->resolveForUser($actor);
        $validated = $request->validated();
        $intent = $createFundingIntent->handle(new CreateFundingIntentData(
            accountReference: $this->accountReference($wallet),
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
                'source' => 'x-change.lifecycle-api',
            ],
        ));

        return FundingIntentResource::make($intent)
            ->response()
            ->setStatusCode(201);
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

        throw new \RuntimeException('Funding Account reference could not be resolved.');
    }
}
