<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Actions\Commercial\ManageCommercialOffering;
use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Http\Requests\Cockpit\StoreCommercialOfferingRequest;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Support\Time\UtcInstant;
use LBHurtado\XCommerce\Data\CommercialCatalogData;
use LBHurtado\XCommerce\Data\CommercialOfferingData;
use LBHurtado\XCommerce\Data\CommercialWaterfallPolicyData;

final class CockpitCommercialOfferingController extends Controller
{
    public function __construct(
        private readonly CommercialOfferingResolverContract $offerings,
        private readonly ManageCommercialOffering $manage,
    ) {}

    public function store(StoreCommercialOfferingRequest $request): RedirectResponse
    {
        $operator = $request->user();
        abort_unless($operator instanceof Model, 403);

        $validated = $request->validated();
        $profile = (string) $validated['profile'];
        $active = $this->offerings->resolve($profile);
        $catalog = $active->catalog->toArray();
        $catalogChanged = false;
        $prices = collect($validated['items'])->mapWithKeys(
            fn (array $item): array => [
                $item['reference'] => (int) round(((float) $item['unit_price']) * 100),
            ],
        );

        foreach ($catalog['items'] as $index => $item) {
            $reference = (string) $item['reference'];

            if ($prices->has($reference)) {
                $price = (int) $prices->get($reference);
                $catalogChanged = $catalogChanged
                    || (int) $item['unit_price_minor'] !== $price;
                $catalog['items'][$index]['unit_price_minor'] = $price;
            }
        }

        if ($catalogChanged) {
            $catalog['version'] = (int) $catalog['version'] + 1;
        }

        $policy = $active->waterfallPolicy->toArray();
        $rules = collect($validated['rules'])->keyBy('reference');

        foreach ($policy['rules'] as $index => $rule) {
            $input = $rules->get($rule['reference']);

            if (! is_array($input)) {
                continue;
            }

            $method = (string) $input['method'];
            $value = isset($input['value']) ? (float) $input['value'] : null;
            $policy['rules'][$index] = array_replace($rule, [
                'recipient_reference' => (string) $input['recipient_reference'],
                'fixed_amount_minor' => $method === 'fixed' && $value !== null
                    ? (int) round($value * 100)
                    : null,
                'basis_points' => $method === 'basis_points' && $value !== null
                    ? (int) round($value)
                    : null,
                'minimum_amount_minor' => $this->optionalMinor($input['minimum_amount'] ?? null),
                'maximum_amount_minor' => $this->optionalMinor($input['maximum_amount'] ?? null),
                'participant_role' => filled($input['participant_role'] ?? null)
                    ? (string) $input['participant_role']
                    : null,
            ]);
        }

        $version = (int) CommercialOffering::query()
            ->where('reference', $active->reference)
            ->max('version') + 1;
        $offering = new CommercialOfferingData(
            reference: $active->reference,
            version: $version,
            catalog: CommercialCatalogData::fromArray($catalog),
            waterfallPolicy: CommercialWaterfallPolicyData::fromArray($policy),
            attributionPolicy: $active->attributionPolicy,
            legalTrace: $active->legalTrace,
            effectiveAt: UtcInstant::canonical((string) $validated['effective_at']),
        );

        $draft = $this->manage->createDraft($operator, $profile, $offering);
        $this->manage->submit($operator, $draft);

        return back()->with('success', 'Commercial Offering submitted for independent approval.');
    }

    private function optionalMinor(mixed $value): ?int
    {
        return filled($value) ? (int) round(((float) $value) * 100) : null;
    }
}
