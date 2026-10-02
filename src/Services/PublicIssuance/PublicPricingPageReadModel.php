<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Services\PublicIssuance;

use LBHurtado\XChange\Actions\PayCode\EstimatePayCodeCost;
use LBHurtado\XChange\Contracts\PricelistServiceContract;
use LBHurtado\XChange\Services\Commercial\CommercialBillingPolicy;
use Throwable;

final class PublicPricingPageReadModel
{
    /** @var array<string, array{label:string,description:string,order:int}> */
    private const Groups = [
        'base' => [
            'label' => 'Base Pay Code',
            'description' => 'Core issuance and payment instructions.',
            'order' => 10,
        ],
        'onboarding' => [
            'label' => 'Account onboarding',
            'description' => 'Optional account setup instructions.',
            'order' => 20,
        ],
        'input_fields' => [
            'label' => 'Claim details and evidence',
            'description' => 'Information or evidence requested from the claimant.',
            'order' => 30,
        ],
        'feedback' => [
            'label' => 'Status updates',
            'description' => 'Optional delivery and claim notifications.',
            'order' => 40,
        ],
        'validation' => [
            'label' => 'Safeguards and restrictions',
            'description' => 'Optional controls applied to the claim.',
            'order' => 50,
        ],
        'rider' => [
            'label' => 'After-claim experiences',
            'description' => 'Messages and destinations shown after a successful claim.',
            'order' => 60,
        ],
    ];

    public function __construct(
        private readonly PricelistServiceContract $pricelist,
        private readonly CommercialBillingPolicy $billing,
        private readonly EstimatePayCodeCost $estimate,
    ) {}

    /** @return array<string, mixed> */
    public function present(): array
    {
        try {
            $pricelist = $this->pricelist->showPricelist();

            return [
                'available' => true,
                'currency' => (string) ($pricelist['currency'] ?? 'PHP'),
                'groups' => $this->groups((array) ($pricelist['items'] ?? [])),
                'billing' => $this->billingPresentation(),
                'examples' => $this->examples(),
                'provenance' => [
                    'catalog_reference' => data_get($pricelist, 'catalog.reference'),
                    'catalog_version' => data_get($pricelist, 'catalog.version'),
                    'offering_reference' => data_get($pricelist, 'commercial_offering.reference'),
                    'offering_version' => data_get($pricelist, 'commercial_offering.version'),
                ],
            ];
        } catch (Throwable $exception) {
            report($exception);

            return [
                'available' => false,
                'currency' => 'PHP',
                'groups' => [],
                'billing' => $this->billingPresentation(),
                'examples' => [],
                'provenance' => null,
                'unavailable_message' => 'Pricing is temporarily unavailable. Review the exact total in the Pay Code composer before paying.',
            ];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function groups(array $items): array
    {
        return collect($items)
            ->filter(static fn (array $item): bool => ($item['active'] ?? false) === true)
            ->groupBy(static fn (array $item): string => (string) ($item['category'] ?? 'other'))
            ->map(function ($groupItems, string $key): array {
                $definition = self::Groups[$key] ?? [
                    'label' => str($key)->replace('_', ' ')->title()->toString(),
                    'description' => 'Additional services available for this Pay Code.',
                    'order' => 90,
                ];

                return [
                    'key' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'order' => $definition['order'],
                    'items' => $groupItems
                        ->map(static fn (array $item): array => [
                            'code' => (string) ($item['code'] ?? ''),
                            'name' => (string) ($item['name'] ?? ''),
                            'amount_minor' => (int) ($item['amount_minor'] ?? 0),
                        ])
                        ->sortBy('name')
                        ->values()
                        ->all(),
                ];
            })
            ->sortBy('order')
            ->map(static function (array $group): array {
                unset($group['order']);

                return $group;
            })
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function billingPresentation(): array
    {
        $billable = $this->billing->isBillable();

        return [
            'mode' => $this->billing->mode()->value,
            'customer_charging_active' => $billable,
            'heading' => $billable
                ? 'Live service charging is active'
                : 'Service prices are shown for transparency',
            'message' => $billable
                ? 'The composer includes applicable service and instruction fees in the exact amount due.'
                : 'The composer shows the host’s authoritative amount due. Listed service prices are not added unless live charging is active.',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function examples(): array
    {
        return [
            $this->example(
                'send-50',
                'Send ₱50',
                'A straightforward disburseable Pay Code.',
                $this->instructions(50.0),
            ),
            $this->example(
                'selfie-message',
                '₱50 with selfie and message',
                'Ask for a selfie and show a short message after the claim.',
                $this->instructions(50.0, ['selfie'], message: 'Thank you.'),
            ),
            $this->example(
                'redirect',
                '₱50 with a destination',
                'Continue the claimant to a website after a successful claim.',
                $this->instructions(50.0, url: 'https://example.com/thank-you'),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $instructions
     * @return array<string, mixed>
     */
    private function example(string $key, string $label, string $description, array $instructions): array
    {
        $estimate = $this->estimate->handle($instructions);

        return [
            'key' => $key,
            'label' => $label,
            'description' => $description,
            'principal_minor' => $this->minor($estimate->pay_code_value),
            'service_fees_minor' => $this->minor($estimate->total),
            'amount_due_minor' => $this->minor($estimate->account_debit),
        ];
    }

    /**
     * @param  array<int, string>  $fields
     * @return array<string, mixed>
     */
    private function instructions(
        float $amount,
        array $fields = [],
        ?string $message = null,
        ?string $url = null,
    ): array {
        return [
            'cash' => [
                'amount' => $amount,
                'currency' => 'PHP',
                'validation' => [
                    'secret' => null,
                    'mobile' => null,
                    'payable' => null,
                    'country' => 'PH',
                    'location' => null,
                    'radius' => null,
                ],
            ],
            'inputs' => ['fields' => $fields],
            'feedback' => ['email' => null, 'mobile' => null, 'webhook' => null],
            'rider' => [
                'message' => $message,
                'url' => $url,
                'redirect_timeout' => null,
                'splash' => null,
                'splash_timeout' => null,
                'og_source' => null,
            ],
            'count' => 1,
            'prefix' => 'PRICE',
            'mask' => '****',
            'ttl' => null,
            'voucher_type' => 'redeemable',
            'metadata' => ['source' => 'public_pricing_example'],
        ];
    }

    private function minor(?float $amount): int
    {
        return (int) round(($amount ?? 0.0) * 100);
    }
}
