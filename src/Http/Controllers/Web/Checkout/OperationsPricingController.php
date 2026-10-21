<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Checkout;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use LBHurtado\XChange\Contracts\CommercialLegalTraceResolverContract;
use LBHurtado\XChange\Contracts\CommercialOfferingResolverContract;
use LBHurtado\XChange\Models\CommercialOffering;
use LBHurtado\XChange\Services\Checkout\CheckoutConsoleAccess;
use LBHurtado\XChange\Services\Commercial\ActivateCommercialOfferingRevision;
use LBHurtado\XChange\Services\Commercial\CommercialOfferingManifestCompiler;
use LBHurtado\XChange\Services\Commercial\CommercialPricingWorkspace;
use LBHurtado\XChange\Services\Commercial\ConfiguredPublicIssuanceEscrowPrincipalResolver;
use LBHurtado\XChange\Support\Time\UtcInstant;
use LBHurtado\XCommerce\Data\CommercialCatalogData;
use LBHurtado\XCommerce\Data\CommercialOfferingData;
use LBHurtado\XCommerce\Data\CommercialWaterfallPolicyData;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OperationsPricingController extends Controller
{
    public function show(
        Request $request,
        ConfiguredPublicIssuanceEscrowPrincipalResolver $resolver,
        CheckoutConsoleAccess $access,
        CommercialPricingWorkspace $workspace,
    ): Response {
        $principal = $resolver->resolve();
        abort_unless($access->owner($request, $principal), 404);

        $active = app(CommercialOfferingResolverContract::class)->resolve('pay_code');

        return Inertia::render('x-change/checkout/OperationsPricing', [
            'pricing' => [
                'currency' => $active->catalog->currency,
                'items' => $active->catalog->toArray()['items'],
                'active_version' => $active->version,
                'workspace' => $workspace->build(null, 'pay_code'),
            ],
        ]);
    }

    public function template(
        Request $request,
        ConfiguredPublicIssuanceEscrowPrincipalResolver $resolver,
        CheckoutConsoleAccess $access,
    ): StreamedResponse {
        $principal = $resolver->resolve();
        abort_unless($access->owner($request, $principal), 404);

        $active = app(CommercialOfferingResolverContract::class)->resolve('pay_code');

        return response()->streamDownload(function () use ($active): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'reference',
                'label',
                'category',
                'currency',
                'unit_price',
                'expected_provider_cost',
                'expected_provider_cost_basis',
            ]);

            foreach ($active->catalog->toArray()['items'] as $item) {
                fputcsv($handle, [
                    $item['reference'],
                    $item['label'],
                    $item['category'],
                    $active->catalog->currency,
                    number_format(((int) $item['unit_price_minor']) / 100, 2, '.', ''),
                    number_format(((int) ($item['expected_provider_cost_minor'] ?? 0)) / 100, 2, '.', ''),
                    (string) ($item['expected_provider_cost_basis'] ?? 'unconfigured'),
                ]);
            }

            fclose($handle);
        }, 'x-change-pricing-template.csv', ['Cache-Control' => 'no-store, private']);
    }

    public function update(
        Request $request,
        ConfiguredPublicIssuanceEscrowPrincipalResolver $resolver,
        CheckoutConsoleAccess $access,
        CommercialOfferingResolverContract $offerings,
        CommercialLegalTraceResolverContract $legalTrace,
        CommercialOfferingManifestCompiler $manifests,
        ActivateCommercialOfferingRevision $activate,
    ): RedirectResponse {
        $principal = $resolver->resolve();
        abort_unless($access->owner($request, $principal), 404);

        $active = $offerings->resolve('pay_code');
        $items = $request->hasFile('pricing_file')
            ? $this->itemsFromSpreadsheet($request, $active->catalog->toArray()['items'])
            : $request->validate([
                'items' => ['required', 'array', 'min:1'],
                'items.*.reference' => ['required', 'string', 'max:160', 'distinct'],
                'items.*.unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
                'items.*.expected_provider_cost' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
                'items.*.expected_provider_cost_basis' => ['required', 'string', 'max:160'],
            ])['items'];
        $catalog = $active->catalog->toArray();
        $catalogReferences = collect($catalog['items'])->pluck('reference')->sort()->values()->all();
        $submittedReferences = collect($items)->pluck('reference')->sort()->values()->all();

        if ($catalogReferences !== $submittedReferences) {
            abort(422, 'The pricing update must contain exactly the current pricing references.');
        }

        $prices = collect($items)->keyBy('reference');

        foreach ($catalog['items'] as $index => $item) {
            $reference = (string) $item['reference'];
            if ($prices->has($reference)) {
                $updated = $prices->get($reference);
                $catalog['items'][$index]['unit_price_minor'] = $this->minorUnits((string) $updated['unit_price']);
                $catalog['items'][$index]['expected_provider_cost_minor'] = $this->minorUnits((string) $updated['expected_provider_cost']);
                $catalog['items'][$index]['expected_provider_cost_basis'] = trim((string) $updated['expected_provider_cost_basis']);
            }
        }
        $catalog['version'] = (int) $catalog['version'] + 1;
        $version = (int) CommercialOffering::query()
            ->where('reference', $active->reference)
            ->max('version') + 1;
        $data = new CommercialOfferingData(
            reference: $active->reference,
            version: $version,
            catalog: CommercialCatalogData::fromArray($catalog),
            waterfallPolicy: CommercialWaterfallPolicyData::fromArray($active->waterfallPolicy->toArray()),
            attributionPolicy: $active->attributionPolicy,
            legalTrace: $active->legalTrace,
            effectiveAt: UtcInstant::canonical(now()->toIso8601String()),
        );
        $published = $legalTrace->forPublication($data);
        $manifest = $manifests->compile('pay_code', $published);
        $authorizationReference = 'operations-beta:'.hash('sha256', $request->session()->getId().':'.$version);

        $offering = DB::transaction(function () use ($principal, $published, $manifest, $authorizationReference): CommercialOffering {
            return CommercialOffering::query()->create([
                'reference' => $published->reference,
                'version' => $published->version,
                'profile' => 'pay_code',
                'status' => 'published',
                'origin' => 'maker_checker_revision',
                'currency' => $published->catalog->currency,
                'snapshot_hash' => $published->snapshotHash(),
                'snapshot' => $published->toArray(),
                'manifest_schema' => $manifest->schema,
                'manifest_hash' => $manifest->hash,
                'manifest_yaml' => $manifest->yaml,
                'created_by_type' => $principal->getMorphClass(),
                'created_by_id' => $principal->getKey(),
                'submitted_by_type' => $principal->getMorphClass(),
                'submitted_by_id' => $principal->getKey(),
                'approved_by_type' => $principal->getMorphClass(),
                'approved_by_id' => $principal->getKey(),
                'authorization_reference' => $authorizationReference,
                'submitted_at' => now(),
                'approved_at' => now(),
                'effective_at' => now(),
            ]);
        });

        $activate->execute($offering, $principal, $authorizationReference);

        return back()->with('success', 'Beta pricing published and activated immediately.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $catalogItems
     * @return array<int, array{reference: string, unit_price: string, expected_provider_cost: string, expected_provider_cost_basis: string}>
     */
    private function itemsFromSpreadsheet(Request $request, array $catalogItems): array
    {
        $request->validate([
            'pricing_file' => ['required', 'file', 'mimes:csv,txt', 'max:1024'],
        ]);

        $file = $request->file('pricing_file');
        $handle = fopen($file->getRealPath(), 'rb');
        $headers = fgetcsv($handle) ?: [];
        $headers = array_map(static fn (mixed $header): string => trim(ltrim((string) $header, "\xEF\xBB\xBF")), $headers);
        $headerIndexes = array_flip($headers);

        if (! isset($headerIndexes['reference'], $headerIndexes['unit_price'], $headerIndexes['expected_provider_cost'], $headerIndexes['expected_provider_cost_basis'])) {
            abort(422, 'The pricing spreadsheet must include reference, unit_price, expected_provider_cost, and expected_provider_cost_basis columns.');
        }

        $items = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count(array_filter($row, static fn (mixed $value): bool => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $items[] = [
                'reference' => trim((string) ($row[$headerIndexes['reference']] ?? '')),
                'unit_price' => trim((string) ($row[$headerIndexes['unit_price']] ?? '')),
                'expected_provider_cost' => trim((string) ($row[$headerIndexes['expected_provider_cost']] ?? '')),
                'expected_provider_cost_basis' => trim((string) ($row[$headerIndexes['expected_provider_cost_basis']] ?? '')),
            ];
        }
        fclose($handle);

        $validated = Validator::make(['items' => $items], [
            'items' => ['required', 'array', 'min:1'],
            'items.*.reference' => ['required', 'string', 'max:160', 'distinct'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'items.*.expected_provider_cost' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'items.*.expected_provider_cost_basis' => ['required', 'string', 'max:160'],
        ])->validate();

        $catalogReferences = collect($catalogItems)->pluck('reference')->sort()->values()->all();
        $uploadedReferences = collect($validated['items'])->pluck('reference')->sort()->values()->all();

        if ($catalogReferences !== $uploadedReferences) {
            abort(422, 'The pricing spreadsheet must contain exactly the current pricing references. Download the current template and try again.');
        }

        return $validated['items'];
    }

    private function minorUnits(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }
}
