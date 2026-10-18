<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Checkout;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
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

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.reference' => ['required', 'string', 'max:160', 'distinct'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
        ]);
        $active = $offerings->resolve('pay_code');
        $catalog = $active->catalog->toArray();
        $prices = collect($validated['items'])->mapWithKeys(
            fn (array $item): array => [$item['reference'] => (int) round(((float) $item['unit_price']) * 100)],
        );

        foreach ($catalog['items'] as $index => $item) {
            $reference = (string) $item['reference'];
            if ($prices->has($reference)) {
                $catalog['items'][$index]['unit_price_minor'] = (int) $prices->get($reference);
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
            legalTrace: $legalTrace->forPublication($active),
            effectiveAt: UtcInstant::canonical(now()->toIso8601String()),
        );
        $manifest = $manifests->compile('pay_code', $data);
        $authorizationReference = 'operations-beta:'.hash('sha256', $request->session()->getId().':'.$version);

        $offering = DB::transaction(function () use ($principal, $data, $manifest, $authorizationReference): CommercialOffering {
            return CommercialOffering::query()->create([
                'reference' => $data->reference,
                'version' => $data->version,
                'profile' => 'pay_code',
                'status' => 'published',
                'origin' => 'maker_checker_revision',
                'currency' => $data->catalog->currency,
                'snapshot_hash' => $data->snapshotHash(),
                'snapshot' => $data->toArray(),
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
}
