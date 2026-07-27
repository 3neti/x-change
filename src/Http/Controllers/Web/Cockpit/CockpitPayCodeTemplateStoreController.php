<?php

declare(strict_types=1);

namespace LBHurtado\XChange\Http\Controllers\Web\Cockpit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use LBHurtado\XChange\Contracts\CockpitIssuanceTemplateRegistryContract;
use LBHurtado\XChange\Http\Requests\Cockpit\StorePayCodeTemplateRequest;
use LBHurtado\XChange\Models\PayCodeTemplate;
use LBHurtado\XChange\Services\Cockpit\QuickGenerateTemplateBlueprintSanitizer;

class CockpitPayCodeTemplateStoreController extends Controller
{
    public function __construct(
        private readonly CockpitIssuanceTemplateRegistryContract $templates,
        private readonly QuickGenerateTemplateBlueprintSanitizer $sanitizer,
    ) {}

    public function __invoke(StorePayCodeTemplateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $owner = $request->user();
        $baseTemplateKey = (string) $validated['base_template_key'];

        abort_unless($owner instanceof Model, 403);
        abort_unless($this->templates->resolve($baseTemplateKey)?->enabled === true, 422);

        PayCodeTemplate::query()->create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => (string) $owner->getKey(),
            'name' => trim((string) $validated['name']),
            'description' => filled($validated['description'] ?? null)
                ? trim((string) $validated['description'])
                : null,
            'base_template_key' => $baseTemplateKey,
            'instructions_ciphertext' => $this->sanitizer->sanitize(
                $validated['instructions'],
                (bool) $validated['include_amount'],
                (bool) $validated['include_purpose'],
            ),
            'include_amount' => (bool) $validated['include_amount'],
            'include_purpose' => (bool) $validated['include_purpose'],
            'status' => 'active',
        ]);

        return back()->with('success', 'Pay Code template saved.');
    }
}
