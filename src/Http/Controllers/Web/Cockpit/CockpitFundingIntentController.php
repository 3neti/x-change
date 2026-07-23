<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Number;
use LBHurtado\XChange\Actions\Funding\CreateFundingIntent;
use LBHurtado\XChange\Actions\Funding\IssueFundingInstructions;
use LBHurtado\XChange\Contracts\FundingDestinationResolverContract;
use LBHurtado\XChange\Contracts\WalletAccessContract;
use LBHurtado\XChange\Data\Funding\CreateFundingIntentData;
use LBHurtado\XChange\Http\Requests\Web\Cockpit\CreateCockpitFundingIntentRequest;
use LBHurtado\XChange\Models\FundingIntent;
use RuntimeException;

class CockpitFundingIntentController extends Controller
{
    public function __invoke(
        CreateCockpitFundingIntentRequest $request,
        WalletAccessContract $wallets,
        FundingDestinationResolverContract $destinations,
        CreateFundingIntent $createFundingIntent,
        IssueFundingInstructions $issueFundingInstructions,
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
            ->with('funding_instruction', $this->instruction($intent));
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

    /**
     * @return array<string, mixed>
     */
    private function instruction(FundingIntent $intent): array
    {
        $instructions = (array) $intent->instructions_ciphertext;
        $display = is_array($instructions['display_data'] ?? null)
            ? $instructions['display_data']
            : [];

        return [
            'reference' => $intent->reference,
            'provider' => $intent->provider_code,
            'amount' => Number::currency($intent->expected_amount_minor / 100, in: $intent->currency),
            'currency' => $intent->currency,
            'status' => $intent->status->value,
            'expires_at' => $intent->expires_at?->toIso8601String(),
            'funding_address' => $this->optionalString($instructions['funding_address'] ?? null),
            'action_url' => $this->safeActionUrl($instructions['action_url'] ?? null),
            'institution' => $this->optionalString($display['institution'] ?? null),
            'account_name' => $this->optionalString($display['account_name'] ?? null),
            'delivery' => $this->optionalString($display['delivery'] ?? null),
            'balance_changed' => false,
            'sensitive' => true,
        ];
    }

    private function safeActionUrl(mixed $value): ?string
    {
        $url = $this->optionalString($value);

        if ($url === null || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(parse_url($url, PHP_URL_SCHEME), ['https'], true) ? $url : null;
    }

    private function optionalString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
